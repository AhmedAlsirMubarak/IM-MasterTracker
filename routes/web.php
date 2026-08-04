<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\ContractWebController;
use App\Http\Controllers\Web\MasterWebController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\LetterController;
use App\Http\Controllers\Admin\CvLetterController;
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
use App\Http\Controllers\ShareController;
use App\Http\Controllers\Admin\ProjectShareController;
use App\Http\Controllers\Admin\HiddenCellController;
use Illuminate\Support\Facades\Route;

// ── Public share links (no auth required) ────────────────────────────────────

Route::get('/share/{token}',       [ShareController::class, 'view']);
Route::get('/share/{token}/wells', [ShareController::class, 'wells']);

// ── Auth ─────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthWebController::class, 'logout'])
    ->middleware('auth')->name('logout');

// ── Authenticated ─────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    Route::get('/', fn() => redirect()->route('contracts.index'));

    // ── Contracts ─────────────────────────────────────────────────────────────
    Route::get('/contracts',                               [ContractWebController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/list',                          [ContractController::class, 'index']);
    Route::get('/contracts/{contract}',                    [ContractWebController::class, 'show'])->name('contracts.show');
    Route::get('/contracts/{contract}/projects',           [ContractController::class, 'projects']);
    Route::get('/contracts/{contract}/letters',            [ContractWebController::class, 'letters'])->name('contracts.letters');
    Route::get('/contracts/{contract}/letters-data',       [LetterController::class, 'index']);
    Route::get('/contracts/{contract}/letters-alerts',     [LetterController::class, 'alerts']);
    Route::get('/contracts/{contract}/letters/export',     [LetterController::class, 'export']);

    Route::get('/contracts/{contract}/cvs',                [ContractWebController::class, 'cvs'])->name('contracts.cvs');
    Route::get('/contracts/{contract}/cvs-data',            [CvLetterController::class, 'index']);
    Route::get('/contracts/{contract}/cvs-alerts',          [CvLetterController::class, 'alerts']);
    Route::get('/contracts/{contract}/cvs/export',          [CvLetterController::class, 'export']);
    Route::post('/contracts/{contract}/cvs',                [CvLetterController::class, 'store']);
    Route::put('/contracts/{contract}/cvs/{cv}',            [CvLetterController::class, 'update']);
    Route::delete('/contracts/{contract}/cvs/{cv}',         [CvLetterController::class, 'destroy']);
    Route::post('/contracts/{contract}/letters',           [LetterController::class, 'store']);
    Route::put('/contracts/{contract}/letters/{letter}',   [LetterController::class, 'update']);
    Route::delete('/contracts/{contract}/letters/{letter}',[LetterController::class, 'destroy']);

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/contracts',                          [ContractController::class, 'store']);
        Route::put('/contracts/{contract}',                [ContractController::class, 'update']);
        Route::delete('/contracts/{contract}',             [ContractController::class, 'destroy']);
        Route::post('/contracts/{contract}/projects',      [ContractController::class, 'storeProject']);
        Route::put('/projects/{project}',    [ProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    });

    // Legacy redirect: keep /projects pointing to contracts
    Route::get('/projects', fn() => redirect()->route('contracts.index'));

    // ── Per-project views & data ──────────────────────────────────────────────
    Route::prefix('projects/{project}')->group(function () {

        // Views
        Route::get('/master',  [MasterWebController::class, 'index'])->name('projects.master');
        Route::get('/sap',     [SapWebController::class, 'index'])->middleware('role:sap_admin,super_admin')->name('projects.sap');
        Route::get('/dc',      [DcWebController::class, 'index'])->middleware('role:dc,super_admin')->name('projects.dc');
        Route::get('/columns', [AdminWebController::class, 'columns'])->middleware('role:super_admin')->name('projects.columns');
        Route::get('/import',         [AdminWebController::class, 'import'])->middleware('role:super_admin')->name('projects.import');
        Route::get('/import-history', [ImportController::class, 'history'])->middleware('role:super_admin')->name('projects.import.history');

        // Master grid data (all authenticated users can read)
        Route::get('/wells', [ProjectWellController::class, 'index']);
        Route::get('/wells/years',    [ProjectWellController::class, 'years']);
        Route::get('/wells/counts',   [ProjectWellController::class, 'counts']);
        Route::get('/wells/departed', [ProjectWellController::class, 'departed']);

        // Add well (super_admin only)
        Route::post('/wells', [ProjectWellController::class, 'store'])->middleware('role:super_admin');

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
            Route::post('/custom-columns', [ColumnConfigController::class, 'storeCustom']);
            Route::delete('/custom-columns/{config}', [ColumnConfigController::class, 'destroyCustom']);
            Route::patch('/column-config/{config}/hidden', [ColumnConfigController::class, 'toggleHidden']);
            // Share link management
            Route::get('/share',    [ProjectShareController::class, 'show']);
            Route::post('/share',   [ProjectShareController::class, 'generate']);
            Route::delete('/share', [ProjectShareController::class, 'revoke']);
            // Hidden cell toggle
            Route::post('/wells/{well}/hide-cell', [HiddenCellController::class, 'toggle']);
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
