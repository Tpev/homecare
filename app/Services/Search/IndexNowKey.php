<?php

namespace App\Services\Search;

class IndexNowKey
{
    public function value(): string
    {
        $configured = trim((string) config('services.indexnow.key'));
        if ($configured !== '') {
            return $configured;
        }
        if (! config('services.indexnow.derive_host_key')) {
            return '';
        }

        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === '' ? '' : hash('sha256', 'indexnow:'.$host);
    }
}
