<?php

namespace App\Http\Controllers;

use App\Services\Search\IndexNowKey;
use Illuminate\Http\Response;

class IndexNowKeyController extends Controller
{
    public function __invoke(IndexNowKey $keys): Response
    {
        $key = $keys->value();
        abort_if($key === '', 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
