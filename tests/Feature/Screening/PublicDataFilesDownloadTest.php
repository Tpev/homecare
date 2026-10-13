<?php

namespace Tests\Feature\Screening;

use App\Services\Screening\Imports\PublicDataFiles;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;

class PublicDataFilesDownloadTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/dac-download-'.bin2hex(random_bytes(8)));
        config([
            'screening.private_directory' => $this->directory,
            'screening.max_download_bytes' => 1048576,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_response_keeps_transport_owned_sink_open_until_download_is_validated(): void
    {
        $bytes = 'synthetic public archive bytes';
        Http::globalOptions(['handler' => function (RequestInterface $request, array $options) use ($bytes) {
            $this->assertSame('https', $request->getUri()->getScheme());
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);
            $this->assertIsResource($options['sink']);
            $body = Utils::streamFor($options['sink']);
            $body->write($bytes);

            return Create::promiseFor(new Response(200, [], $body));
        }]);

        $path = $this->directory.'/download.zip';
        app(PublicDataFiles::class)->download('https://opus.doc.state.nc.us/offenders/test.zip', $path, ['opus.doc.state.nc.us']);

        $this->assertSame($bytes, file_get_contents($path));
    }

    public function test_transport_failure_keeps_original_error_when_transport_already_closed_its_sink(): void
    {
        Http::globalOptions(['handler' => function (RequestInterface $request, array $options) {
            // The transport owns a PSR stream around the caller's resource, as Guzzle handlers do.
            $body = Utils::streamFor($options['sink']);
            $body->write('partial archive');
            $body->close();

            return Create::rejectionFor(new ConnectException('Synthetic TLS transport failure.', $request));
        }]);

        $path = $this->directory.'/download.zip';
        try {
            app(PublicDataFiles::class)->download('https://opus.doc.state.nc.us/offenders/test.zip', $path, ['opus.doc.state.nc.us']);
            $this->fail('The failed transport must not leave a usable archive.');
        } catch (ConnectionException $error) {
            $this->assertStringContainsString('Synthetic TLS transport failure.', $error->getMessage());
        }
        $this->assertFileDoesNotExist($path);
    }

    public function test_size_failure_preserves_original_error_and_removes_partial_archive(): void
    {
        Http::globalOptions(['handler' => function (RequestInterface $request, array $options) {
            $body = Utils::streamFor($options['sink']);
            $body->write('partial archive');
            try {
                $options['progress'](1048577, 0);
            } finally {
                $body->close();
            }
        }]);

        $path = $this->directory.'/download.zip';
        try {
            app(PublicDataFiles::class)->download('https://opus.doc.state.nc.us/offenders/test.zip', $path, ['opus.doc.state.nc.us']);
            $this->fail('The oversized transfer must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertSame('Screening download exceeds the configured size limit.', $error->getMessage());
        }
        $this->assertFileDoesNotExist($path);
    }
}
