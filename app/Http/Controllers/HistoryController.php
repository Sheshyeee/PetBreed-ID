<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(){
        return inertia('normal_user/view-origin');
    }
}
