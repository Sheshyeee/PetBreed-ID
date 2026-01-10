<?php

namespace App\Http\Controllers\model;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ScanResultController extends Controller
{
    public function index()
    {
        return inertia('model/scan-results');
    }

    public function review()
    {
        return inertia('model/review-dog');
    }
}
