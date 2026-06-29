@extends('layouts.app')
@section('title', 'Audit Log – ' . $project->name)

@section('content')
<div class="flex flex-col h-full" x-data="auditLog('{{ route('projects.audit', $project) }}-data')">

    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0">
        <h2 class="text-sm font-semibold text-gray-900">Audit log</h2>
        <span class="text-xs text-gray-400">{{ $project->name }}</span>
        <div class="ml-auto">
            <a href="{{ route('projects.master', $project) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                </svg>
                Master Track
            </a>
        </div>
    </div>

    <div class="flex-1 overflow-auto scrollbar-thin relative">
        <div x-show="loading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
            <svg class="animate-spin w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>When</th><th>User</th><th>Role</th>
                    <th>Well</th><th>Field</th>
                    <th>Old value</th><th>New value</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="log in logs" :key="log.id">
                    <tr :class="log.model_type === 'import_session' ? 'bg-purple-50/50' : ''">
                        <td class="text-gray-500 whitespace-nowrap"
                            :title="log.created_at_relative"
                            x-text="log.created_at_human || log.created_at"></td>
                        <td class="font-medium" x-text="log.user?.name || '—'"></td>
                        <td>
                            <span class="badge"
                                :class="{
                                    'badge-purple': log.user?.role === 'super_admin',
                                    'badge-green':  log.user?.role === 'manager',
                                    'badge-amber':  log.user?.role === 'sap_admin',
                                    'badge-coral':  log.user?.role === 'dc',
                                    'badge-blue':   log.user?.role === 'im_lead',
                                }"
                                x-text="(log.user?.role || '').replace('_',' ')"></span>
                        </td>

                        {{-- Well / Event --}}
                        <td>
                            <template x-if="log.model_type === 'import_session'">
                                <span class="badge badge-purple">Import</span>
                            </template>
                            <template x-if="log.model_type !== 'import_session'">
                                <span class="text-gray-600" x-text="log.project_well?.well_number || '—'"></span>
                            </template>
                        </td>

                        {{-- Field / File --}}
                        <td>
                            <template x-if="log.model_type === 'import_session'">
                                <span class="text-xs text-gray-600 max-w-xs block truncate"
                                      :title="log.old_value"
                                      x-text="log.old_value || '—'"></span>
                            </template>
                            <template x-if="log.model_type !== 'import_session'">
                                <span class="font-mono text-xs text-gray-700" x-text="log.field_name"></span>
                            </template>
                        </td>

                        {{-- Old value (cell changes) / hidden for imports --}}
                        <td class="text-gray-400 line-through"
                            x-text="log.model_type === 'import_session' ? '' : (log.old_value || '—')"></td>

                        {{-- New value / import result --}}
                        <td>
                            <template x-if="log.model_type === 'import_session'">
                                <span class="text-xs font-medium"
                                    :class="(log.new_value || '').startsWith('Import failed') ? 'text-red-600' : 'text-green-700'"
                                    x-text="log.new_value || '—'"></span>
                            </template>
                            <template x-if="log.model_type !== 'import_session'">
                                <span class="font-medium text-gray-900" x-text="log.new_value || '—'"></span>
                            </template>
                        </td>
                    </tr>
                </template>
                <tr x-show="logs.length === 0 && !loading">
                    <td colspan="7" class="text-center py-10 text-gray-400">No audit entries yet.</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
@endsection
