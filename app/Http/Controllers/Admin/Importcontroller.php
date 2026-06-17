<?php
 
namespace App\Http\Controllers\Admin;
 
use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\ImportSession;
use App\Models\Project;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
 
class ImportController extends Controller
{
    public function __construct(private ImportService $importService) {}
 
    /** Upload Excel file — returns detected sheet names and headers */
    public function upload(Request $request)
    {
        ini_set('memory_limit', '512M');

        $request->validate([
            'file'       => 'required|file|extensions:xlsx,xls,csv|max:51200',
            'project_id' => 'required|exists:projects,id',
        ]);

        $uploadedFile = $request->file('file');
        $ext      = $uploadedFile->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $ext;
        $destDir  = storage_path('app/private/imports');

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $uploadedFile->move($destDir, $filename);
        $path = 'imports/' . $filename;
 
        $headers = $this->importService->readHeaders($path);
 
        $session = ImportSession::create([
            'project_id'        => $request->project_id,
            'uploaded_by'       => auth()->id(),
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'stored_path'       => $path,
            'target_table'      => 'project_wells',
            'status'            => 'mapping',
        ]);
 
        return response()->json([
            'session_id' => $session->id,
            'sheets'     => $headers,
        ]);
    }
 
    /** Preview 10 rows with the proposed column mapping */
    public function preview(Request $request, ImportSession $session)
    {
        $data = $request->validate([
            'sheet'        => 'required|string',
            'mapping'      => 'present|array',
            'target_table' => 'required|in:project_wells,sap_tracking,dc_tracking,all',
        ]);
 
        $preview = $this->importService->preview(
            $session->stored_path,
            $data['sheet'],
            $data['mapping']
        );
 
        return response()->json(['preview' => $preview]);
    }
 
    /** Confirm mapping and dispatch async import job */
    public function confirm(Request $request, ImportSession $session)
    {
        if (in_array($session->status, ['pending', 'processing', 'completed'])) {
            return response()->json([
                'message'    => 'Import already ' . $session->status . '.',
                'session_id' => $session->id,
            ]);
        }

        $data = $request->validate([
            'mapping'         => 'present|array',
            'target_table'    => 'required|in:project_wells,sap_tracking,dc_tracking,all',
            'sheet'           => 'required|string',
            'replace_existing'=> 'boolean',
        ]);

        if (!empty($data['replace_existing'])) {
            \App\Models\ProjectWell::where('project_id', $session->project_id)->delete();
        }

        $session->update([
            'column_mapping' => $data['mapping'],
            'target_table'   => $data['target_table'],
            'sheet_name'     => $data['sheet'],
            'status'         => 'pending',
        ]);
 
        ProcessImportJob::dispatch($session);
 
        return response()->json([
            'message'    => 'Import queued.',
            'session_id' => $session->id,
        ]);
    }
 
    /** Poll import progress */
    public function status(ImportSession $session)
    {
        return response()->json([
            'status'         => $session->status,
            'progress'       => $session->progress,
            'total_rows'     => $session->total_rows,
            'processed_rows' => $session->processed_rows,
            'skipped_rows'   => $session->skipped_rows,
            'error_log'      => $session->error_log,
        ]);
    }
 
    /** List past import sessions for a project */
    public function history(Project $project)
    {
        $sessions = ImportSession::where('project_id', $project->id)
            ->with('uploadedBy:id,name')
            ->orderByDesc('created_at')
            ->get();
 
        return response()->json($sessions);
    }
}