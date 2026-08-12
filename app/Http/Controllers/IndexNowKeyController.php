<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class IndexNowKeyController extends Controller
{
    public function __invoke(): Response
    {
        $key = trim((string) config('services.indexnow.key'));
        abort_if($key === '', 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
