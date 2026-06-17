<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;

class SapWebController extends Controller
{
    public function index(Project $project) { return view('sap.index', compact('project')); }
}
