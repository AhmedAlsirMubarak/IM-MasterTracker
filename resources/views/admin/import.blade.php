@extends('layouts.app', ['project' => $project])
@section('title', 'Import Excel — ' . $project->name)

@section('content')
<div class="flex flex-col h-full" x-data="importWizard({{ $project->id }})">

    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0">
        <h2 class="text-sm font-semibold text-gray-900">Import Excel</h2>
        <span class="badge badge-purple">Super Admin</span>
    </div>

    {{-- Step indicator --}}
    <div class="flex items-center gap-0 px-6 py-4 bg-white border-b border-gray-200 shrink-0">
        @foreach(['Upload file','Map columns','Preview','Importing'] as $i => $stepLabel)
        <div class="flex items-center" @if($i < 3) style="flex:1" @endif>
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold transition-colors"
                    :class="{{ $i + 1 }} <= step
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-400'">
                    {{ $i + 1 }}
                </div>
                <span class="text-xs font-medium transition-colors"
                    :class="{{ $i + 1 }} <= step ? 'text-gray-900' : 'text-gray-400'">
                    {{ $stepLabel }}
                </span>
            </div>
            @if($i < 3)
            <div class="flex-1 h-px mx-3 transition-colors"
                :class="{{ $i + 1 }} < step ? 'bg-blue-600' : 'bg-gray-200'"></div>
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

                    <label class="block border-2 border-dashed border-gray-300 rounded-xl p-8 text-center
                                  hover:border-blue-400 hover:bg-blue-50 transition-colors cursor-pointer">
                        <svg class="w-10 h-10 text-gray-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-sm font-medium text-gray-700">Click to upload Excel file</p>
                        <p class="text-xs text-gray-400 mt-1">.xlsx, .xls, .csv — max 50 MB</p>
                        <input type="file" accept=".xlsx,.xls,.csv" @change="uploadFile($event)"
                            class="hidden" :disabled="uploading" />
                    </label>

                    <div x-show="uploading" class="space-y-2">
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <svg class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-text="uploadProgress < 100 ? `Uploading… ${uploadProgress}%` : 'Reading headers…'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-200"
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
                    <h3 class="font-medium text-gray-900">Map Excel columns → system fields</h3>
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

                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Excel column</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">→ System field</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="col in headers" :key="col">
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2 font-mono text-xs text-gray-700" x-text="col"></td>
                                        <td class="px-3 py-2">
                                            <select x-model="mapping[col]" class="select text-xs py-1">
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

                    <div class="flex justify-end gap-2">
                        <button @click="step = 1" class="btn-secondary">Back</button>
                        <button @click="loadPreview()" class="btn-primary">Preview →</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 3: Preview --}}
        <div x-show="step === 3" class="space-y-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="font-medium text-gray-900">Preview — first 10 rows</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table" x-show="preview.length > 0">
                        <thead>
                            <tr>
                                <template x-for="key in Object.keys(preview[0] || {})" :key="key">
                                    <th x-text="key"></th>
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
                <div class="card-body border-t border-gray-100 flex items-center justify-between gap-2">
                    @if(auth()->user()->isSuperAdmin())
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                        <input type="checkbox" x-model="replaceExisting" class="rounded border-gray-300 text-red-600">
                        <span>Delete all existing wells first <span class="text-xs text-gray-400">(clean re-import)</span></span>
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
                        <svg class="animate-spin w-10 h-10 text-blue-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <p class="text-sm font-medium text-gray-900">Importing…</p>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-4">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                :style="`width:${progress}%`"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2"
                            x-text="processedRows > 0 ? `${processedRows} rows inserted…` : 'Processing…'"></p>
                    </div>

                    <div x-show="status === 'completed'" class="text-center">
                        <svg class="w-12 h-12 text-green-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-gray-900">Import complete!</p>
                        <p class="text-xs text-gray-500 mt-1"
                            x-text="`${processedRows} rows inserted, ${skippedRows} skipped`"></p>
                        <a href="/projects/{{ $project->id }}/master" class="btn-primary btn-sm mt-4 inline-flex">
                            View master track →
                        </a>
                    </div>

                    <div x-show="status === 'failed'" class="text-center">
                        <svg class="w-12 h-12 text-red-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-red-700">Import failed.</p>
                        <button @click="step = 1" class="btn-secondary btn-sm mt-4">Try again</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
