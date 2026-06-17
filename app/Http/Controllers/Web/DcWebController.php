<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;

class DcWebController extends Controller
{
    public function index(Project $project) { return view('dc.index', compact('project')); }
}
