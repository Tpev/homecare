<?php

namespace Tests\Feature\Screening;

use App\Services\Screening\Imports\SbiPublicDataDownloader;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class SbiPublicDataDownloaderTest extends TestCase
{
    private string $directory;

    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['screening.max_download_bytes' => 1048576]);
        $this->directory = storage_path('framework/testing/sbi-download-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        $fixture = $this->directory.'/fixture.zip';
        $zip = new ZipArchive;
        $zip->open($fixture, ZipArchive::CREATE);
        $zip->addFromString('SOR Data Layout - PUBLIC.txt', 'Synthetic public-data layout fixture.');
        $zip->close();
        $this->archive = file_get_contents($fixture);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            foreach (glob($this->directory.'/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    #[DataProvider('officialActions')]
    public function test_fresh_official_form_is_posted_with_session_cookies_and_hidden_state_then_streamed_as_zip(string $action): void
    {
        $jar = null;
        Http::fake(function (Request $request, array $options) use (&$jar, $action) {
            $this->assertSame(SbiPublicDataDownloader::PAGE_URL, $request->url());
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);
            $this->assertTrue($options['stream']);
            $this->assertInstanceOf(CookieJar::class, $options['cookies']);
            $this->assertIsCallable($options['on_headers']);
            if ($request->method() === 'GET') {
                $jar = $options['cookies'];

                return Http::response(str_replace('action="/stats.aspx"', 'action="'.htmlspecialchars($action, ENT_QUOTES).'"', $this->form()), 200, [
                    'Content-Type' => 'text/html; charset=utf-8',
                    'Set-Cookie' => 'ASP.NET_SessionId=synthetic-session; Path=/; Secure; HttpOnly',
                ]);
            }
            $this->assertSame($jar, $options['cookies']);
            $this->assertSame('POST', $request->method());
            $this->assertStringContainsString('ASP.NET_SessionId=synthetic-session', $request->header('Cookie')[0] ?? '');
            $this->assertSame([
                '__EVENTTARGET' => 'datadownloadbutton', '__EVENTARGUMENT' => '',
                '__VIEWSTATE' => 'fresh+state&encoded=', '__VIEWSTATEGENERATOR' => 'A1B2C3D4',
                '__EVENTVALIDATION' => 'fresh-event-validation',
            ], $request->data());
            $this->assertSame([SbiPublicDataDownloader::PAGE_URL], $request->header('Referer'));

            return Http::response($this->archive, 200, ['Content-Type' => 'application/zip']);
        });

        app(SbiPublicDataDownloader::class)->download($this->directory.'/download.zip');

        if (PHP_OS_FAMILY !== 'Windows') {
            clearstatcache();
            $this->assertSame(02770, fileperms($this->directory) & 07777);
            $this->assertSame(0660, fileperms($this->directory.'/download.zip') & 07777);
        }

        $this->assertSame($this->archive, file_get_contents($this->directory.'/download.zip'));
        Http::assertSentCount(2);
    }

    public static function officialActions(): array
    {
        return [
            'root relative' => ['/stats.aspx'],
            'relative' => ['stats.aspx'],
            'observed official dot relative action' => ['./stats.aspx'],
            'absolute official URL' => [SbiPublicDataDownloader::PAGE_URL],
        ];
    }

    #[DataProvider('optionalEventFields')]
    public function test_postback_event_fields_are_synthesized_from_the_verified_download_control(string $eventFields): void
    {
        $form = <<<HTML
<!doctype html><html><body>
<form id="_ctl0" method="post" action="./stats.aspx">
{$eventFields}
<input type="hidden" name="__VIEWSTATE" value="fresh+state&amp;encoded=">
<input type="hidden" name="__VIEWSTATEGENERATOR" value="A1B2C3D4">
<a id="datadownloadbutton" href="javascript:__doPostBack('datadownloadbutton','')">Download Sex Offender Public data</a>
</form></body></html>
HTML;
        $jar = null;
        Http::fake(function (Request $request, array $options) use (&$jar, $form) {
            $this->assertSame(SbiPublicDataDownloader::PAGE_URL, $request->url());
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);
            $this->assertTrue($options['stream']);
            $this->assertInstanceOf(CookieJar::class, $options['cookies']);
            if ($request->method() === 'GET') {
                $jar = $options['cookies'];
                $this->assertEmpty($request->header('Cookie'));

                return Http::response($form, 200, [
                    'Content-Type' => 'text/html; charset=utf-8',
                    'Set-Cookie' => 'ASP.NET_SessionId=synthetic-session; Path=/; Secure; HttpOnly',
                ]);
            }
            $this->assertSame('POST', $request->method());
            $this->assertSame($jar, $options['cookies']);
            $this->assertStringContainsString('ASP.NET_SessionId=synthetic-session', $request->header('Cookie')[0] ?? '');
            $this->assertEquals([
                '__VIEWSTATE' => 'fresh+state&encoded=', '__VIEWSTATEGENERATOR' => 'A1B2C3D4',
                '__EVENTTARGET' => 'datadownloadbutton', '__EVENTARGUMENT' => '',
            ], $request->data());
            $this->assertSame([SbiPublicDataDownloader::PAGE_URL], $request->header('Referer'));

            return Http::response($this->archive, 200, ['Content-Type' => 'application/zip']);
        });

        app(SbiPublicDataDownloader::class)->download($this->directory.'/download.zip');

        $this->assertSame($this->archive, file_get_contents($this->directory.'/download.zip'));
        Http::assertSentCount(2);
    }

    public static function optionalEventFields(): array
    {
        return [
            'observed official form with only view state and generator' => [''],
            'missing event target' => ['<input type="hidden" name="__EVENTARGUMENT" value="unexpected">'],
            'missing event argument' => ['<input type="hidden" name="__EVENTTARGET" value="unexpected">'],
            'existing event values are overridden' => ['<input type="hidden" name="__EVENTTARGET" value="unexpected"><input type="hidden" name="__EVENTARGUMENT" value="unexpected">'],
        ];
    }

    public function test_each_download_fetches_new_view_state_and_uses_a_new_cookie_jar(): void
    {
        $requestNumber = 0;
        $jars = [];
        Http::fake(function (Request $request, array $options) use (&$requestNumber, &$jars) {
            if ($request->method() === 'GET') {
                $requestNumber++;
                $jars[] = $options['cookies'];

                return Http::response(str_replace('fresh+state&amp;encoded=', 'state-'.$requestNumber, $this->form()), 200, ['Content-Type' => 'text/html']);
            }
            $this->assertSame('state-'.$requestNumber, $request['__VIEWSTATE']);

            return Http::response($this->archive);
        });

        $downloader = app(SbiPublicDataDownloader::class);
        $downloader->download($this->directory.'/first.zip');
        $downloader->download($this->directory.'/second.zip');

        $this->assertCount(2, $jars);
        $this->assertNotSame($jars[0], $jars[1]);
        Http::assertSentCount(4);
    }

    #[DataProvider('unsafeActions')]
    public function test_unexpected_or_offsite_form_actions_are_rejected_without_posting(string $action): void
    {
        Http::fake([SbiPublicDataDownloader::PAGE_URL => Http::response(
            str_replace('action="/stats.aspx"', 'action="'.htmlspecialchars($action, ENT_QUOTES).'"', $this->form()),
            200, ['Content-Type' => 'text/html'],
        )]);

        $this->assertRejected('official statistics page');
        Http::assertSentCount(1);
    }

    public static function unsafeActions(): array
    {
        return [
            'offsite' => ['https://untrusted.example.test/stats.aspx'],
            'scheme relative' => ['//untrusted.example.test/stats.aspx'],
            'http downgrade' => ['http://sexoffender.ncsbi.gov/stats.aspx'],
            'javascript' => ['javascript:alert(1)'],
            'other path' => ['/admin.aspx'],
            'userinfo' => ['https://user@sexoffender.ncsbi.gov/stats.aspx'],
            'query' => ['/stats.aspx?forward=https://untrusted.example.test'],
            'dot relative query' => ['./stats.aspx?forward=https://untrusted.example.test'],
            'dot relative other path' => ['./other.aspx'],
            'dot relative fragment' => ['./stats.aspx#download'],
        ];
    }

    #[DataProvider('changedForms')]
    public function test_missing_or_ambiguous_postback_controls_and_state_are_rejected(string $search, string $replacement): void
    {
        Http::fake([SbiPublicDataDownloader::PAGE_URL => Http::response(
            str_replace($search, $replacement, $this->form()), 200, ['Content-Type' => 'text/html'],
        )]);

        $this->assertRejected();
        Http::assertSentCount(1);
    }

    public static function changedForms(): array
    {
        return [
            'changed control' => ['id="datadownloadbutton"', 'id="differentbutton"'],
            'unexpected script' => ["__doPostBack('datadownloadbutton','')", "__doPostBack('datadownloadbutton','');alert(1)"],
            'missing view state' => ['name="__VIEWSTATE"', 'name="other_state"'],
            'empty view state' => ['value="fresh+state&amp;encoded="', 'value=""'],
            'missing view state generator' => ['name="__VIEWSTATEGENERATOR"', 'name="other_generator"'],
            'empty view state generator' => ['value="A1B2C3D4"', 'value=""'],
            'duplicate state' => ['<input type="text" name="search">', '<input type="hidden" name="__VIEWSTATE" value="ambiguous">'],
            'duplicate generator' => ['<input type="text" name="search">', '<input type="hidden" name="__VIEWSTATEGENERATOR" value="ambiguous">'],
            'duplicate event target' => ['<input type="text" name="search">', '<input type="hidden" name="__EVENTTARGET" value="ambiguous">'],
            'wrong form' => ['id="_ctl0"', 'id="different_form"'],
            'wrong method' => ['method="post"', 'method="get"'],
            'control outside form' => ['<a id="datadownloadbutton"', '</form><a id="datadownloadbutton"'],
        ];
    }

    public function test_redirect_from_initial_page_is_not_followed(): void
    {
        Http::fake([SbiPublicDataDownloader::PAGE_URL => Http::response('', 302, ['Location' => 'https://untrusted.example.test'])]);

        $this->assertRejected('redirects are not permitted');
        Http::assertSentCount(1);
    }

    public function test_redirect_from_postback_is_not_followed(): void
    {
        Http::fakeSequence()->push($this->form(), 200, ['Content-Type' => 'text/html'])
            ->push('', 302, ['Location' => 'https://untrusted.example.test']);

        $this->assertRejected('redirects are not permitted');
        Http::assertSentCount(2);
    }

    public function test_oversized_page_is_stopped_before_postback(): void
    {
        Http::fake([SbiPublicDataDownloader::PAGE_URL => Http::response(str_repeat('x', 2097153), 200, ['Content-Type' => 'text/html'])]);

        $this->assertRejected('page exceeds the size limit');
        Http::assertSentCount(1);
    }

    public function test_oversized_download_without_content_length_is_stopped_and_partial_file_removed(): void
    {
        config(['screening.max_download_bytes' => 32]);
        Http::fakeSequence()->push($this->form(), 200, ['Content-Type' => 'text/html'])->push($this->archive);

        $this->assertRejected('exceeds the configured size limit');
        Http::assertSentCount(2);
    }

    public function test_html_postback_error_is_not_kept_as_a_zip(): void
    {
        Http::fakeSequence()->push($this->form(), 200, ['Content-Type' => 'text/html'])->push('<html>Invalid postback</html>');

        $this->assertRejected('did not return a valid ZIP');
        Http::assertSentCount(2);
    }

    public function test_existing_destination_is_neither_overwritten_nor_deleted(): void
    {
        file_put_contents($this->directory.'/download.zip', 'preserve-existing-file');
        Http::fakeSequence()->push($this->form(), 200, ['Content-Type' => 'text/html'])->push($this->archive);
        try {
            app(SbiPublicDataDownloader::class)->download($this->directory.'/download.zip');
            $this->fail('Existing files must not be replaced.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Cannot create', $error->getMessage());
        }
        $this->assertSame('preserve-existing-file', file_get_contents($this->directory.'/download.zip'));
    }

    private function assertRejected(string $message = ''): void
    {
        try {
            app(SbiPublicDataDownloader::class)->download($this->directory.'/download.zip');
            $this->fail('Unsafe or invalid SBI response should be rejected.');
        } catch (RuntimeException $error) {
            if ($message !== '') {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
        $this->assertFileDoesNotExist($this->directory.'/download.zip');
    }

    private function form(): string
    {
        return <<<'HTML'
<!doctype html><html><body>
<form id="_ctl0" method="post" action="/stats.aspx">
<input type="hidden" name="__EVENTTARGET" value="">
<input type="hidden" name="__EVENTARGUMENT" value="">
<input type="hidden" name="__VIEWSTATE" value="fresh+state&amp;encoded=">
<input type="hidden" name="__VIEWSTATEGENERATOR" value="A1B2C3D4">
<input type="hidden" name="__EVENTVALIDATION" value="fresh-event-validation">
<input type="text" name="search">
<a id="datadownloadbutton" href="javascript:__doPostBack('datadownloadbutton','')">Download Sex Offender Public data</a>
</form></body></html>
HTML;
    }
}
