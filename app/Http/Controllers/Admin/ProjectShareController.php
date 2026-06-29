<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectShare;
use Illuminate\Support\Str;

class ProjectShareController extends Controller
{
    public function show(Project $project)
    {
        $share = ProjectShare::where('project_id', $project->id)->latest()->first();
        return response()->json($share ? [
            'token'      => $share->token,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'url'        => url("/share/{$share->token}"),
            'expired'    => $share->isExpired(),
        ] : null);
    }

    public function generate(Project $project)
    {
        // Revoke any existing share for this project
        ProjectShare::where('project_id', $project->id)->delete();

        $share = ProjectShare::create([
            'project_id' => $project->id,
            'token'      => Str::random(48),
            'created_by' => auth()->id(),
            'expires_at' => now()->addDays(5),
        ]);

        return response()->json([
            'token'      => $share->token,
            'expires_at' => $share->expires_at->toIso8601String(),
            'url'        => url("/share/{$share->token}"),
            'expired'    => false,
        ], 201);
    }

    public function revoke(Project $project)
    {
        ProjectShare::where('project_id', $project->id)->delete();
        return response()->json(['message' => 'Share link revoked.']);
    }
}
