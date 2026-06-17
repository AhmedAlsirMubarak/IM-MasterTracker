<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;

class AdminWebController extends Controller
{
    public function columns(Project $project) { return view('admin.columns', compact('project')); }
    public function import(Project $project)  { return view('admin.import',  compact('project')); }
    public function users()
    {
        return view('admin.users');
    }

    public function userList()
    {
        $users = \App\Models\User::orderBy('name')
            ->get(['id','name','email','role','department','is_active','last_login_at']);
        return response()->json($users);
    }
    public function audit(Project $project) { return view('admin.audit', compact('project')); }

    public function auditData(Project $project)
    {
        $logs = \App\Models\AuditLog::with(['user:id,name,role', 'projectWell:id,well_number,well_id'])
            ->whereHas('projectWell', fn($q) => $q->where('project_id', $project->id))
            ->orderByDesc('created_at')
            ->paginate(200);
        return response()->json($logs);
    }
}
