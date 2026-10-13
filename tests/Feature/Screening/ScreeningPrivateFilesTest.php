<?php

namespace Tests\Feature\Screening;

use App\Services\Screening\Imports\PublicDataFiles;
use App\Services\Screening\ScreeningPrivateFiles;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ScreeningPrivateFilesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/screening-permissions-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        config(['screening.private_directory' => $this->directory.'/private']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_missing_nested_parents_and_files_are_private_and_group_writable_despite_restrictive_umask(): void
    {
        $private = app(ScreeningPrivateFiles::class);
        $root = $this->directory.'/private';
        $mask = umask(0077);
        try {
            $file = $private->open($root.'/imports/42/source.zip');
            fwrite($file, 'private bytes');
            fclose($file);
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->assertSame(0077, umask());
            }
        } finally {
            umask($mask);
        }
        foreach ([$root, $root.'/imports', $root.'/imports/42'] as $directory) {
            $this->assertDirectoryExists($directory);
            $this->assertMode($directory, 02770);
        }
        $this->assertMode($root.'/imports/42/source.zip', 0660);
        $file = $private->open($root.'/imports/42/source.zip', 'c+b');
        $this->assertSame('private bytes', stream_get_contents($file));
        fclose($file);
    }

    public function test_existing_private_subtree_permissions_are_corrected_without_losing_file_contents(): void
    {
        $root = $this->directory.'/private';
        mkdir($root.'/imports/42', 0700, true);
        file_put_contents($root.'/imports/42/existing.jsonl', 'preserved');
        chmod($root.'/imports/42/existing.jsonl', 0644);
        $file = app(ScreeningPrivateFiles::class)->open($root.'/imports/42/existing.jsonl', 'c+b');
        $this->assertSame('preserved', stream_get_contents($file));
        fclose($file);
        foreach ([$root, $root.'/imports', $root.'/imports/42'] as $directory) {
            $this->assertMode($directory, 02770);
        }
        $this->assertMode($root.'/imports/42/existing.jsonl', 0660);
    }

    public function test_exclusive_creation_cannot_overwrite_an_existing_file(): void
    {
        $path = $this->directory.'/existing.zip';
        file_put_contents($path, 'preserve original');
        try {
            app(ScreeningPrivateFiles::class)->open($path);
            $this->fail('Exclusive private-file creation must reject existing files.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Cannot create', $error->getMessage());
        }
        $this->assertSame('preserve original', file_get_contents($path));
    }

    public function test_copied_extracted_and_directly_downloaded_files_use_private_open_streams(): void
    {
        $source = $this->directory.'/fixture.zip';
        $zip = new ZipArchive;
        $zip->open($source, ZipArchive::CREATE);
        $zip->addFromString('data.txt', 'synthetic source bytes');
        $zip->close();
        $root = $this->directory.'/private/imports/42';
        app(ScreeningPrivateFiles::class)->copy($source, $root.'/source.zip');
        $paths = app(PublicDataFiles::class)->extract($root.'/source.zip', ['data' => 'data.txt'], $root.'/extracted');
        $this->assertSame('synthetic source bytes', file_get_contents($paths['data']));
        $this->assertMode($root.'/source.zip', 0660);
        $this->assertMode($paths['data'], 0660);
        $this->assertMode($root.'/extracted', 02770);
        $destination = $root.'/download.zip';
        Http::fake(function (Request $request, array $options) use ($destination, $source) {
            $this->assertIsResource($options['sink']);
            $this->assertMode($destination, 0660);
            fwrite($options['sink'], file_get_contents($source));

            return Http::response('', 200);
        });
        app(PublicDataFiles::class)->download('https://www.doc.state.nc.us/offenders/test.zip', $destination, ['www.doc.state.nc.us']);
        $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $destination));
        $this->assertMode($destination, 0660);
    }

    public function test_file_and_parent_symlinks_are_rejected_without_changing_the_target(): void
    {
        $target = $this->directory.'/target';
        mkdir($target, 0700);
        file_put_contents($target.'/source.zip', 'unchanged target');
        if (! @symlink($target, $this->directory.'/linked-parent')) {
            $this->markTestSkipped('Creating symbolic links is unavailable on this host.');
        }
        if (! @symlink($target.'/source.zip', $this->directory.'/linked-file')) {
            $this->markTestSkipped('Creating file symbolic links is unavailable on this host.');
        }
        foreach ([$this->directory.'/linked-parent/new.zip', $this->directory.'/linked-file'] as $path) {
            try {
                app(ScreeningPrivateFiles::class)->open($path, 'c+b');
                $this->fail('Private screening writes must reject symlinks.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Symlinks', $error->getMessage());
            }
        }
        $this->assertSame('unchanged target', file_get_contents($target.'/source.zip'));
        $this->assertFileDoesNotExist($target.'/new.zip');
        $this->assertMode($target, 0700);
    }

    public function test_deployment_storage_symlink_is_resolved_but_links_below_private_storage_are_rejected(): void
    {
        $physical = $this->directory.'/shared/storage';
        mkdir($physical, 0700, true);
        $logical = $this->directory.'/release-storage';
        if (! @symlink($physical, $logical)) {
            $this->markTestSkipped('Creating symbolic links is unavailable on this host.');
        }
        $originalStorage = storage_path();
        $this->app->useStoragePath($logical);
        config(['screening.private_directory' => $logical.'/app/private/screening']);
        try {
            $private = app(ScreeningPrivateFiles::class);
            $file = $private->open($logical.'/app/private/screening/imports/source.zip');
            fwrite($file, 'shared storage bytes');
            fclose($file);
            $this->assertSame('shared storage bytes', file_get_contents($physical.'/app/private/screening/imports/source.zip'));
            $this->assertMode($physical.'/app/private/screening', 02770);
            $this->assertMode($physical.'/app/private/screening/imports/source.zip', 0660);
            symlink($physical.'/app/private/screening/imports', $physical.'/app/private/screening/linked');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Symlinks');
            $private->open($logical.'/app/private/screening/linked/blocked.zip');
        } finally {
            $this->app->useStoragePath($originalStorage);
        }
    }

    private function assertMode(string $path, int $mode): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            clearstatcache(true, $path);
            $this->assertSame($mode, fileperms($path) & 07777);
        }
    }
}
