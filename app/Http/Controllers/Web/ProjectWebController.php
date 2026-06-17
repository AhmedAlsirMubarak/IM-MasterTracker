<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;

class ProjectWebController extends Controller
{
    public function index()
    {
        return view('projects.index');
    }

    public function list()
    {
        $projects = Project::withCount('wells')
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->get();
        return response()->json($projects);
    }
}
