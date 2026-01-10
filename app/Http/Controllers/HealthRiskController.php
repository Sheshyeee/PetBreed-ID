<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HealthRiskController extends Controller
{
    public function index()
    {
        return inertia('normal_user/view-health-risk');
    }
}
