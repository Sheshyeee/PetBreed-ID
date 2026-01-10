<?php

namespace App\Http\Controllers\model;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrainingQueueController extends Controller
{
      public function index(){
        return inertia('model/training-queue');
    }
}
