<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function header()
    {
        return inertia('normal_user/header');
    }
}
