@extends('layouts.app', ['project' => $project])
@section('title', 'Import Excel — ' . $project->name)

@section('content')
<div class="flex flex-col h-full" x-data="importWizard({{ $project->id }})">

    <div class="page-header">
        <h2 class="page-title">Import Excel</h2>
        <span class="badge badge-purple">Super Admin</span>
    </div>

    {{-- Step indicator --}}
    <div class="flex items-center gap-0 px-6 py-4 bg-white border-b border-slate-200 shrink-0">
        @foreach(['Upload file','Map columns','Preview','Importing'] as $i => $stepLabel)
        <div class="flex items-center" @if($i < 3) style="flex:1" @endif>
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold transition-colors"
                    :class="{{ $i + 1 }} <= step
                        ? 'bg-brand-600 text-white'
                        : 'bg-slate-100 text-slate-400'">
                    {{ $i + 1 }}
                </div>
                <span class="text-xs font-medium transition-colors"
                    :class="{{ $i + 1 }} <= step ? 'text-slate-900' : 'text-slate-400'">
                    {{ $stepLabel }}
                </span>
            </div>
            @if($i < 3)
            <div class="flex-1 h-px mx-3 transition-colors"
                :class="{{ $i + 1 }} < step ? 'bg-brand-600' : 'bg-slate-200'"></div>
            @endif
        </div>
        @endforeach
    </div>

    <div class="flex-1 overflow-y-auto p-6">

        {{-- Step 1: Upload --}}
        <div x-show="step === 1" class="max-w-lg mx-auto">
            <div class="card">
                <div class="card-body space-y-4">
                    <div x-show="error" class="alert-danger" x-text="error"></div>

                    <label class="block border-2 border-dashed border-slate-300 rounded-xl p-8 text-center
                                  hover:border-brand-400 hover:bg-brand-50 transition-colors cursor-pointer">
                        <svg class="w-10 h-10 text-slate-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-sm font-medium text-slate-700">Click to upload Excel file</p>
                        <p class="text-xs text-slate-400 mt-1">.xlsx, .xls, .csv — max 50 MB</p>
                        <input type="file" accept=".xlsx,.xls,.csv" @change="uploadFile($event)"
                            class="hidden" :disabled="uploading" />
                    </label>

                    <div x-show="uploading" class="space-y-2">
                        <div class="flex items-center gap-2 text-sm text-slate-500">
                            <svg class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-text="uploadProgress < 100 ? `Uploading… ${uploadProgress}%` : 'Reading headers…'"></span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-1.5">
                            <div class="bg-brand-600 h-1.5 rounded-full transition-all duration-200"
                                :style="`width:${uploadProgress}%`"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 2: Map columns --}}
        <div x-show="step === 2" class="max-w-3xl mx-auto space-y-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="font-medium text-slate-900">Map Excel columns → system fields</h3>
                    <span class="ml-auto text-xs text-slate-500"
                        x-text="`${mappedCount} of ${headers.length} columns mapped`"></span>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Sheet</label>
                            <select x-model="selectedSheet" @change="sheetSelected()" class="select">
                                <template x-for="(cols, name) in sheets" :key="name">
                                    <option :value="name" x-text="name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="label">Import into table</label>
                            <select x-model="targetTable" @change="sheetSelected()" class="select">
                                <option value="all">All Tables (auto-route)</option>
                                <option value="project_wells">Project Wells only</option>
                                <option value="sap_tracking">SAP Tracking only</option>
                                <option value="dc_tracking">DC Tracking only</option>
                            </select>
                        </div>
                    </div>

                    {{-- Identifier warning --}}
                    <div x-show="headers.length > 0 && !hasIdentifier"
                         class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <span><strong>No identifier mapped.</strong> Map at least one of <strong>Well ID</strong> or <strong>Well Number</strong> so rows can be matched to existing wells.</span>
                    </div>

                    {{-- Duplicate mapping warning --}}
                    <div x-show="duplicateMappings.length > 0"
                         class="flex items-start gap-2 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-xs">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>
                            <strong>Duplicate mapping:</strong>
                            <template x-for="dup in duplicateMappings" :key="dup">
                                <span class="inline-block bg-red-100 rounded px-1 mx-0.5 font-mono" x-text="fieldLabel(dup)"></span>
                            </template>
                            is assigned to multiple Excel columns. Each system field should be mapped once.
                        </span>
                    </div>

                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 w-6"></th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Excel column</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">→ System field</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="col in headers" :key="col">
                                    <tr class="border-t border-slate-100"
                                        :class="mapping[col] ? 'bg-green-50/40' : ''">
                                        {{-- Match status icon --}}
                                        <td class="px-3 py-2">
                                            <template x-if="mapping[col] && !duplicateMappings.includes(mapping[col])">
                                                <svg class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </template>
                                            <template x-if="mapping[col] && duplicateMappings.includes(mapping[col])">
                                                <svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01"/>
                                                </svg>
                                            </template>
                                            <template x-if="!mapping[col]">
                                                <span class="block w-3.5 h-3.5 rounded-full border border-slate-300"></span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs"
                                            :class="mapping[col] ? 'text-slate-900 font-medium' : 'text-slate-400'"
                                            x-text="col"></td>
                                        <td class="px-3 py-2">
                                            <select x-model="mapping[col]"
                                                    class="select text-xs py-1"
                                                    :class="duplicateMappings.includes(mapping[col]) ? 'border-red-300 bg-red-50' : ''">
                                                <option value="">— Skip —</option>
                                                <template x-for="field in fieldOptions" :key="field.value">
                                                    <option :value="field.value" x-text="field.label"></option>
                                                </template>
                                            </select>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <p x-show="!hasIdentifier && headers.length > 0"
                           class="text-xs text-amber-700">
                            Map <strong>Well ID</strong> or <strong>Well Number</strong> before continuing.
                        </p>
                        <div class="flex gap-2 ml-auto">
                            <button @click="step = 1" class="btn-secondary">Back</button>
                            <button @click="loadPreview()"
                                    :disabled="!hasIdentifier || duplicateMappings.length > 0 || loadingPreview"
                                    class="btn-primary disabled:opacity-40 disabled:cursor-not-allowed">
                                <span x-text="loadingPreview ? 'Checking all rows…' : 'Preview →'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 3: Preview --}}
        <div x-show="step === 3" class="space-y-4">
            <div class="card" x-show="analysis">
                <div class="card-header">
                    <h3 class="font-medium text-slate-900">Before you import</h3>
                    <span class="ml-auto text-xs text-slate-500" x-text="analysis ? `${plural(analysis.rows, 'row')} checked` : ''"></span>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <p class="text-slate-700" x-text="analysis ? `This will create ${plural(analysis.new_wells, 'new well')} and update ${plural(analysis.existing, 'existing well')}.` : ''"></p>
                    <p x-show="analysis && analysis.duplicate_rows" class="text-slate-600"
                       x-text="analysis ? `${plural(analysis.duplicate_rows, 'row')} ${analysis.duplicate_rows === 1 ? 'repeats' : 'repeat'} a Well ID from an earlier row and will be kept as ${analysis.duplicate_rows === 1 ? 'a separate well' : 'separate wells'}, marked &quot;Duplicate ID&quot;.` : ''"></p>

                    <div x-show="analysis && analysis.error_count" class="p-3 rounded-lg border border-red-200 bg-red-50 text-red-800 text-xs">
                        <p class="font-semibold mb-1" x-text="analysis ? `${plural(analysis.error_count, 'row')} will not be imported` : ''"></p>
                        <ul class="list-disc pl-4 space-y-0.5 max-h-40 overflow-y-auto">
                            <template x-for="(m, i) in (analysis?.errors || [])" :key="'e' + i"><li x-text="m"></li></template>
                        </ul>
                    </div>

                    <div x-show="analysis && analysis.warning_count" class="p-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 text-xs">
                        <p class="font-semibold mb-1" x-text="analysis ? `${plural(analysis.warning_count, 'warning')} — these rows will still be imported` : ''"></p>
                        <ul class="list-disc pl-4 space-y-0.5 max-h-56 overflow-y-auto">
                            <template x-for="(m, i) in (analysis?.warnings || [])" :key="'w' + i"><li x-text="m"></li></template>
                        </ul>
                        <p x-show="analysis && analysis.warning_count > (analysis.warnings || []).length" class="mt-1 italic"
                           x-text="analysis ? `…and ${analysis.warning_count - analysis.warnings.length} more.` : ''"></p>
                    </div>

                    <p x-show="analysis && !analysis.warning_count && !analysis.error_count" class="text-green-700 text-xs">No problems found.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="font-medium text-slate-900">Preview — first 10 rows</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table" x-show="preview.length > 0">
                        <thead>
                            <tr>
                                <template x-for="key in Object.keys(preview[0] || {})" :key="key">
                                    <th x-text="fieldLabel(key)"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in preview" :key="i">
                                <tr>
                                    <template x-for="key in Object.keys(row)" :key="key">
                                        <td x-text="row[key] ?? '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-t border-slate-100 flex items-center justify-between gap-2">
                    @if(auth()->user()->isSuperAdmin())
                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" x-model="replaceExisting" class="rounded border-slate-300 text-red-600">
                        <span>Delete all existing wells first <span class="text-xs text-slate-400">(clean re-import)</span></span>
                    </label>
                    @else
                    <div></div>
                    @endif
                    <div class="flex gap-2">
                        <button @click="step = 2" class="btn-secondary">Back</button>
                        <button @click="confirm()" class="btn-primary">Confirm &amp; import</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 4: Progress --}}
        <div x-show="step === 4" class="max-w-md mx-auto">
            <div class="card">
                <div class="card-body text-center space-y-4 py-8">
                    <div x-show="status !== 'completed' && status !== 'failed'">
                        <svg class="animate-spin w-10 h-10 text-brand-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <p class="text-sm font-medium text-slate-900">Importing…</p>
                        <div class="w-full bg-slate-200 rounded-full h-2 mt-4">
                            <div class="bg-brand-600 h-2 rounded-full transition-all duration-500"
                                :style="`width:${progress}%`"></div>
                        </div>
                        <p class="text-xs text-slate-500 mt-2"
                            x-text="processedRows > 0 ? `${plural(processedRows, 'row')} imported…` : 'Processing…'"></p>
                    </div>

                    <div x-show="status === 'completed'" class="text-center">
                        <svg class="w-12 h-12 text-green-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-slate-900">Import complete!</p>
                        <p class="text-xs text-slate-600 mt-1" x-text="resultSummary(result)"></p>
                        <div x-show="result?.error_log" class="mt-3 text-left p-3 rounded-lg border border-red-200 bg-red-50 text-red-800 text-xs">
                            <p class="font-semibold mb-1">Not imported</p>
                            <ul class="list-disc pl-4 space-y-0.5 max-h-40 overflow-y-auto">
                                <template x-for="(m, i) in messageLines(result?.error_log)" :key="'re' + i"><li x-text="m"></li></template>
                            </ul>
                        </div>
                        <div x-show="result?.warning_log" class="mt-3 text-left p-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 text-xs">
                            <p class="font-semibold mb-1">Imported with warnings</p>
                            <ul class="list-disc pl-4 space-y-0.5 max-h-56 overflow-y-auto">
                                <template x-for="(m, i) in messageLines(result?.warning_log)" :key="'rw' + i"><li x-text="m"></li></template>
                            </ul>
                        </div>
                        <a href="/projects/{{ $project->id }}/master" class="btn-primary btn-sm mt-4 inline-flex">
                            View master track →
                        </a>
                    </div>

                    <div x-show="status === 'failed'" class="text-center">
                        <svg class="w-12 h-12 text-red-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-red-700">Import failed.</p>
                        <p class="text-xs text-slate-600 mt-1" x-text="result?.error_log || ''"></p>
                        <button @click="step = 1" class="btn-secondary btn-sm mt-4">Try again</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Import History — nested Alpine component inside the scrollable area --}}
        <div class="mt-6" x-data="importHistory({{ $project->id }})">
    <div class="card mt-6">
        <div class="card-header">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="font-medium text-slate-900 text-sm">Import history</h3>
            <span class="ml-auto text-xs text-slate-400" x-show="!loading" x-text="`${sessions.length} import${sessions.length !== 1 ? 's' : ''}`"></span>
        </div>

        <div x-show="loading" class="flex items-center gap-2 px-5 py-6 text-xs text-slate-400">
            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Loading…
        </div>

        <div x-show="!loading && sessions.length === 0" class="px-5 py-6 text-xs text-slate-400 text-center">
            No imports yet for this project.
        </div>

        <div x-show="!loading && sessions.length > 0" class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>File</th>
                        <th>Target</th>
                        <th>By</th>
                        <th>Status</th>
                        <th>Wells created</th>
                        <th>Wells updated</th>
                        <th>Rows skipped</th>
                        <th>Issues</th>
                    </tr>
                </thead>
                <template x-for="s in sessions" :key="s.id">
                    <tbody>
                        <tr>
                            <td class="text-slate-500 whitespace-nowrap font-mono text-xs" :title="s.created_at_human" x-text="fmtDateTime(s.created_at)"></td>
                            <td class="max-w-xs truncate" :title="s.original_filename" x-text="s.original_filename"></td>
                            <td><span class="badge badge-blue" x-text="tableLabel(s.target_table)"></span></td>
                            <td x-text="s.uploaded_by?.name || '—'"></td>
                            <td>
                                <span class="badge" :class="statusBadge(s.status)" x-text="s.status"></span>
                            </td>
                            <td class="text-green-700 font-medium" x-text="s.status === 'completed' && !isLegacy(s) ? s.created_wells : '—'"></td>
                            <td class="text-slate-700" x-text="s.status === 'completed' && !isLegacy(s) ? s.updated_wells : '—'"></td>
                            <td class="text-amber-600" x-text="s.status === 'completed' ? s.skipped_rows : '—'"></td>
                            <td>
                                <template x-if="s.error_log || s.warning_log">
                                    <button type="button" class="text-xs underline"
                                            :class="s.error_log ? 'text-red-600' : 'text-amber-700'"
                                            @click="openId = openId === s.id ? null : s.id"
                                            x-text="[s.error_log ? plural(lines(s.error_log).length, 'error') : '', s.warning_log ? plural(lines(s.warning_log).length, 'warning') : ''].filter(Boolean).join(', ')"></button>
                                </template>
                                <template x-if="!s.error_log && !s.warning_log">
                                    <span class="text-slate-300">—</span>
                                </template>
                            </td>
                        </tr>
                        <tr x-show="openId === s.id">
                            <td colspan="8" class="bg-slate-50 text-xs">
                                <ul class="list-disc pl-5 space-y-0.5 max-h-64 overflow-y-auto">
                                    <template x-for="(m, i) in lines(s.error_log)" :key="'he' + i"><li class="text-red-700" x-text="m"></li></template>
                                    <template x-for="(m, i) in lines(s.warning_log)" :key="'hw' + i"><li class="text-amber-800" x-text="m"></li></template>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </template>
            </table>
        </div>
    </div>
        </div>{{-- /importHistory --}}

    </div>{{-- /flex-1 scrollable --}}
</div>{{-- /importWizard --}}
@endsection
