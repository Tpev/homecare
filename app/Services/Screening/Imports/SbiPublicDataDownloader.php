<?php

namespace App\Services\Screening\Imports;

use App\Services\Screening\ScreeningPrivateFiles;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use ZipArchive;

class SbiPublicDataDownloader
{
    public const PAGE_URL = 'https://sexoffender.ncsbi.gov/stats.aspx';

    private const PAGE_LIMIT = 2097152;

    /** Fetch the observed ASP.NET form; never reuse saved view state or browser cookies. */
    public function download(string $destination): void
    {
        $cookies = new CookieJar;
        $page = $this->request($cookies, self::PAGE_LIMIT, 60)->accept('text/html')->get(self::PAGE_URL);
        $this->assertSuccess($page);
        $contentType = strtolower(trim(explode(';', $page->header('Content-Type'))[0]));
        if (! in_array($contentType, ['text/html', 'application/xhtml+xml'], true)) {
            $page->toPsrResponse()->getBody()->close();
            throw new RuntimeException('The official SBI download page did not return HTML.');
        }
        $fields = $this->postbackFields($this->readPage($page));
        $limit = (int) config('screening.max_download_bytes', 536870912);
        if ($limit < 1) {
            throw new RuntimeException('The screening download size limit must be positive.');
        }

        $response = $this->request($cookies, $limit, 900)->asForm()
            ->withHeaders(['Referer' => self::PAGE_URL, 'Accept' => 'application/zip, application/octet-stream'])
            ->post(self::PAGE_URL, $fields);
        $this->assertSuccess($response);
        $stream = $response->toPsrResponse()->getBody();
        $output = null;
        $created = false;
        try {
            // Exclusive creation also prevents replacing an existing file or following a file symlink.
            $output = app(ScreeningPrivateFiles::class)->open($destination);
            $created = true;
            $bytes = 0;
            while (! $stream->eof()) {
                $chunk = $stream->read(min(65536, $limit - $bytes + 1));
                if ($chunk === '' && ! $stream->eof()) {
                    throw new RuntimeException('The SBI download stream stalled.');
                }
                $bytes += strlen($chunk);
                if ($bytes > $limit) {
                    throw new RuntimeException('SBI download exceeds the configured size limit.');
                }
                if ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Cannot write the complete SBI download.');
                }
            }
            if (! fflush($output)) {
                throw new RuntimeException('Cannot finish the SBI download file.');
            }
            fclose($output);
            $output = null;
            if ($bytes === 0) {
                throw new RuntimeException('The SBI download was empty.');
            }
            $zip = new ZipArchive;
            if ($zip->open($destination, ZipArchive::CHECKCONS) !== true) {
                throw new RuntimeException('The SBI postback did not return a valid ZIP archive.');
            }
            try {
                if ($zip->numFiles === 0) {
                    throw new RuntimeException('The SBI ZIP archive contained no records.');
                }
            } finally {
                $zip->close();
            }
        } catch (Throwable $error) {
            if (is_resource($output)) {
                fclose($output);
            }
            if ($created && is_file($destination)) {
                unlink($destination);
            }
            throw $error;
        } finally {
            $stream->close();
        }
    }

    private function request(CookieJar $cookies, int $limit, int $timeout): PendingRequest
    {
        return Http::connectTimeout(20)->timeout($timeout)->withOptions([
            'cookies' => $cookies,
            'allow_redirects' => false,
            'verify' => true,
            'stream' => true,
            'read_timeout' => 60,
            'on_headers' => function (ResponseInterface $response) use ($limit): void {
                $length = $response->getHeaderLine('Content-Length');
                if ($length !== '' && (! ctype_digit($length) || (float) $length > $limit)) {
                    throw new RuntimeException('The SBI response exceeds the configured size limit.');
                }
            },
        ]);
    }

    private function assertSuccess(Response $response): void
    {
        if ($response->status() !== 200) {
            $response->toPsrResponse()->getBody()->close();
            throw new RuntimeException('The official SBI download returned an unexpected status; redirects are not permitted.');
        }
    }

    private function readPage(Response $response): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $page = '';
        try {
            while (! $stream->eof()) {
                $chunk = $stream->read(min(65536, self::PAGE_LIMIT - strlen($page) + 1));
                if ($chunk === '' && ! $stream->eof()) {
                    throw new RuntimeException('The SBI download page stream stalled.');
                }
                $page .= $chunk;
                if (strlen($page) > self::PAGE_LIMIT) {
                    throw new RuntimeException('The SBI download page exceeds the size limit.');
                }
            }
        } finally {
            $stream->close();
        }

        return $page;
    }

    private function postbackFields(string $html): array
    {
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        if (! $loaded) {
            throw new RuntimeException('The SBI download form could not be read.');
        }
        $xpath = new DOMXPath($document);
        $controls = $xpath->query('//a[@id="datadownloadbutton"]');
        $control = $controls->item(0);
        if ($controls->length !== 1 || ! $control instanceof DOMElement
            || preg_replace('/\s+/', ' ', trim($control->textContent)) !== 'Download Sex Offender Public data') {
            throw new RuntimeException('The expected SBI public-data download control is missing or changed.');
        }
        // Compare the observed link syntax as text. No JavaScript is evaluated.
        $href = rtrim(preg_replace('/\s+/', '', $control->getAttribute('href')), ';');
        if (! in_array($href, ["javascript:__doPostBack('datadownloadbutton','')", 'javascript:__doPostBack("datadownloadbutton","")'], true)) {
            throw new RuntimeException('The SBI download postback control has changed.');
        }
        $form = $control->parentNode;
        while ($form && (! $form instanceof DOMElement || strtolower($form->tagName) !== 'form')) {
            $form = $form->parentNode;
        }
        if (! $form instanceof DOMElement || $form->getAttribute('id') !== '_ctl0'
            || strtolower($form->getAttribute('method')) !== 'post'
            || $xpath->query('//form[@id="_ctl0"]')->length !== 1
            || ! in_array($form->getAttribute('action'), ['/stats.aspx', 'stats.aspx', self::PAGE_URL], true)) {
            throw new RuntimeException('The SBI download form must post to the official statistics page.');
        }
        $fields = [];
        foreach ($xpath->query('.//input', $form) as $input) {
            if (strtolower($input->getAttribute('type')) !== 'hidden') {
                continue;
            }
            $name = $input->getAttribute('name');
            if ($name === '' || strlen($name) > 128 || array_key_exists($name, $fields) || count($fields) >= 128) {
                throw new RuntimeException('The SBI download form contains invalid hidden fields.');
            }
            $fields[$name] = $input->getAttribute('value');
        }
        foreach (['__EVENTTARGET', '__EVENTARGUMENT', '__VIEWSTATE', '__VIEWSTATEGENERATOR'] as $name) {
            if (! array_key_exists($name, $fields)) {
                throw new RuntimeException('Required SBI postback fields are missing.');
            }
        }
        if ($fields['__VIEWSTATE'] === '' || $fields['__VIEWSTATEGENERATOR'] === '') {
            throw new RuntimeException('The SBI download form is missing its current view state.');
        }
        $fields['__EVENTTARGET'] = 'datadownloadbutton';
        $fields['__EVENTARGUMENT'] = '';

        return $fields;
    }
}
