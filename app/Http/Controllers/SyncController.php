<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index(){
        return view('backend.sync.index');
    }
}
