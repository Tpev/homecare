<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LlmsController extends Controller
{
    public function index(): Response
    {
        return response(
            file_get_contents(public_path('llms.txt')),
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
