<?php

namespace App\Http\Controllers;

class AdminLeadsController extends Controller
{
    public function index()
    {
        return view('admin.leads');
    }
}