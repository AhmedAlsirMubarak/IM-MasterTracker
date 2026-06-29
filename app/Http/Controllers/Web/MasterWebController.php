<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;

class MasterWebController extends Controller
{
    public function index(Project $project)
    {
        $project->load('contract');
        return view('master.index', compact('project'));
    }
}
