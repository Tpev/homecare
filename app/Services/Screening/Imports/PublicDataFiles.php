<?php

namespace App\Services\Screening\Imports;

use App\Services\Screening\ScreeningPrivateFiles;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class PublicDataFiles
{
    public function download(string $url, string $destination, array $allowedHosts): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), $allowedHosts, true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new RuntimeException('The screening download URL must be HTTPS on a configured official source host.');
        }
        $limit = (int) config('screening.max_download_bytes');
        $output = app(ScreeningPrivateFiles::class)->open($destination);
        try {
            $response = Http::connectTimeout(20)->timeout(900)->withOptions([
                'allow_redirects' => false,
                'sink' => $output,
                'progress' => function ($total, $downloaded) use ($limit) {
                    if ($total > $limit || $downloaded > $limit) {
                        throw new RuntimeException('Screening download exceeds the configured size limit.');
                    }
                },
            ])->get($url)->throw();
            if (! fflush($output)) {
                throw new RuntimeException('Cannot finish the private screening download.');
            }
            $size = fstat($output)['size'];
            if ($size === 0 || $size > $limit) {
                throw new RuntimeException('The downloaded screening file is empty or exceeds the size limit.');
            }
        } catch (\Throwable $exception) {
            // The HTTP transport may already have closed its sink after a failed request.
            if (is_resource($output)) {
                fclose($output);
            }
            $output = null;
            if (is_file($destination)) {
                unlink($destination);
            }
            throw $exception;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    /** Extract only explicitly named files; never use extractTo on untrusted archives. */
    public function extract(string $path, array $members, string $directory): array
    {
        $private = app(ScreeningPrivateFiles::class);
        $private->directory($directory);
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Invalid or corrupt public-data ZIP archive.');
        }
        $result = [];
        $total = 0;
        try {
            if ($zip->numFiles > 100) {
                throw new RuntimeException('Public-data archive contains too many entries.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', $stat['name']);
                if (str_starts_with($name, '/') || preg_match('/(^|\/)\.\.(\/|$)|:|\x00/', $name)) {
                    throw new RuntimeException('Unsafe path in public-data archive.');
                }
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000 || ($stat['encryption_method'] ?? 0) !== 0) {
                    throw new RuntimeException('Links and encrypted entries are not supported in public-data archives.');
                }
                $total += $stat['size'];
                if ($total > (int) config('screening.max_uncompressed_bytes')) {
                    throw new RuntimeException('Uncompressed public-data archive exceeds the size limit.');
                }
                foreach ($members as $key => $expected) {
                    if (strcasecmp($name, $expected) !== 0) {
                        continue;
                    }
                    if (isset($result[$key])) {
                        throw new RuntimeException('Duplicate public-data ZIP member.');
                    }
                    $destination = $directory.'/'.hash('sha256', $expected).'.data';
                    $input = $zip->getStream($stat['name']);
                    if (! $input) {
                        throw new RuntimeException('Cannot extract public-data ZIP member.');
                    }
                    try {
                        $output = $private->open($destination);
                        try {
                            $copied = stream_copy_to_stream($input, $output, $stat['size'] + 1);
                        } finally {
                            fclose($output);
                        }
                    } finally {
                        fclose($input);
                    }
                    if ($copied !== $stat['size'] || strtolower(hash_file('crc32b', $destination)) !== sprintf('%08x', $stat['crc'])) {
                        throw new RuntimeException('Public-data ZIP member failed size or CRC validation.');
                    }
                    $result[$key] = $destination;
                }
            }
            if (count($result) !== count($members)) {
                throw new RuntimeException('Expected public-data ZIP member is missing; verify the published layout.');
            }
        } finally {
            $zip->close();
        }

        return $result;
    }
}
