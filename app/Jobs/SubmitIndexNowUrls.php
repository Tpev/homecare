<?php

namespace App\Jobs;

use App\Services\Search\IndexNowKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SubmitIndexNowUrls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @param list<string> $urls */
    public function __construct(public array $urls) {}

    public function handle(IndexNowKey $keys): void
    {
        $key = $keys->value();
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($key === '' || ! is_string($host) || $host === '') {
            return;
        }

        $urls = collect($this->urls)
            ->filter(fn ($url): bool => is_string($url) && parse_url($url, PHP_URL_HOST) === $host)
            ->unique()
            ->take(10000)
            ->values()
            ->all();
        if ($urls === []) {
            return;
        }

        Http::asJson()->timeout(8)->retry(2, 400)->post('https://api.indexnow.org/indexnow', [
            'host' => $host,
            'key' => $key,
            'keyLocation' => route('indexnow.key'),
            'urlList' => $urls,
        ])->throw();
    }
}
