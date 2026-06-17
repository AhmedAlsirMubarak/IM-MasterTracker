<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\ProjectWebController;
use App\Http\Controllers\Web\MasterWebController;
use App\Http\Controllers\Web\SapWebController;
use App\Http\Controllers\Web\DcWebController;
use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectWellController;
use App\Http\Controllers\Admin\ColumnConfigController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Staff\SapTrackingController;
use App\Http\Controllers\Staff\DcTrackingController;
use Illuminate\Support\Facades\Route;

// ── Auth ─────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthWebController::class, 'logout'])
    ->middleware('auth')->name('logout');

// ── Authenticated ─────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    Route::get('/', fn() => redirect()->route('projects.index'));

    // ── Projects ─────────────────────────────────────────────────────────────
    Route::get('/projects',      [ProjectWebController::class, 'index'])->name('projects.index');
    Route::get('/projects/list', [ProjectWebController::class, 'list']);

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/projects',             [ProjectController::class, 'store']);
        Route::put('/projects/{project}',    [ProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    });

    // ── Per-project views & data ──────────────────────────────────────────────
    Route::prefix('projects/{project}')->group(function () {

        // Views
        Route::get('/master',  [MasterWebController::class, 'index'])->name('projects.master');
        Route::get('/sap',     [SapWebController::class, 'index'])->middleware('role:sap_admin,super_admin')->name('projects.sap');
        Route::get('/dc',      [DcWebController::class, 'index'])->middleware('role:dc,super_admin')->name('projects.dc');
        Route::get('/columns', [AdminWebController::class, 'columns'])->middleware('role:super_admin')->name('projects.columns');
        Route::get('/import',  [AdminWebController::class, 'import'])->middleware('role:super_admin')->name('projects.import');

        // Master grid data (all authenticated users can read)
        Route::get('/wells', [ProjectWellController::class, 'index']);
        Route::get('/wells/years',    [ProjectWellController::class, 'years']);
        Route::get('/wells/counts',   [ProjectWellController::class, 'counts']);
        Route::get('/wells/departed', [ProjectWellController::class, 'departed']);

        // Add well (manager + super_admin)
        Route::post('/wells', [ProjectWellController::class, 'store'])->middleware('role:super_admin,manager');

        // Bulk delete (super_admin only)
        Route::delete('/wells/bulk', [ProjectWellController::class, 'bulkDestroy'])->middleware('role:super_admin');

        // SAP data
        Route::get('/sap-data', [SapTrackingController::class, 'index'])->middleware('role:sap_admin,super_admin');

        // DC data
        Route::get('/dc-data', [DcTrackingController::class, 'index'])->middleware('role:dc,super_admin');

        // Exports
        Route::get('/export/master', [ExportController::class, 'master'])->name('export.master');
        Route::get('/export/sap',    [ExportController::class, 'sap'])->middleware('role:sap_admin,super_admin')->name('export.sap');
        Route::get('/export/dc',     [ExportController::class, 'dc'])->middleware('role:dc,super_admin')->name('export.dc');

        // Audit log (per-project)
        Route::middleware('role:super_admin,manager')->group(function () {
            Route::get('/audit',      [AdminWebController::class, 'audit'])->name('projects.audit');
            Route::get('/audit-data', [AdminWebController::class, 'auditData']);
        });

        // Column config (super_admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/column-config', [ColumnConfigController::class, 'index']);
            Route::post('/column-config/toggle', [ColumnConfigController::class, 'toggle']);
            Route::post('/column-config/reorder', [ColumnConfigController::class, 'reorder']);
        });
    });

    // ── Well-level operations ─────────────────────────────────────────────────
    Route::patch('/wells/{well}/cell',  [ProjectWellController::class, 'updateCell']);
    Route::get('/wells/{well}/audit',   [ProjectWellController::class, 'auditLog']);

    Route::put('/wells/{well}/sap', [SapTrackingController::class, 'update'])
        ->middleware('role:sap_admin,super_admin');

    Route::put('/wells/{well}/dc', [DcTrackingController::class, 'update'])
        ->middleware('role:dc,super_admin');

    // ── Column status options ─────────────────────────────────────────────────
    Route::get('/columns/{sourceTable}/{sourceColumn}/options', [ColumnConfigController::class, 'statusOptions']);
    Route::post('/columns/{sourceTable}/{sourceColumn}/options', [ColumnConfigController::class, 'saveStatusOptions'])
        ->middleware('role:super_admin');

    // ── Cell comments ─────────────────────────────────────────────────────────
    Route::get('/wells/{well}/comments', [ProjectWellController::class, 'cellComments']);

    // ── Import ────────────────────────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/import/upload',              [ImportController::class, 'upload']);
        Route::post('/import/{session}/preview',   [ImportController::class, 'preview']);
        Route::post('/import/{session}/confirm',   [ImportController::class, 'confirm']);
        Route::get('/import/{session}/status',     [ImportController::class, 'status']);
    });

    // ── Admin pages ───────────────────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/users',           [AdminWebController::class, 'users'])->name('users.index');
        Route::get('/users/list',      [AdminWebController::class, 'userList']);
        Route::post('/users',          [UserController::class, 'store']);
        Route::put('/users/{user}',    [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

});
