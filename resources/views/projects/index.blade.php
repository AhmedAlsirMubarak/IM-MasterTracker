@extends('layouts.app')
@section('title', 'Projects')

@section('content')
<div class="flex flex-col h-full" x-data="projectList()">

    {{-- Header --}}
    <div class="page-header">
        <h1 class="page-title">Projects</h1>
        <div class="flex-1"></div>
        @if(auth()->user()->isSuperAdmin())
        <button @click="showCreate = true" class="btn-primary btn-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New project
        </button>
        @endif
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="flex-1 flex items-center justify-center">
        <div class="flex items-center gap-2 text-slate-400 text-sm">
            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Loading projects…
        </div>
    </div>

    {{-- Project grid --}}
    <div x-show="!loading" class="flex-1 overflow-y-auto p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="p in projects" :key="p.id">
                <a :href="`/projects/${p.id}/master`"
                   class="card hover:border-brand-300 hover:shadow-md transition-all duration-150 block group">
                    <div class="card-body">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-slate-900 group-hover:text-brand-700 transition-colors"
                                    x-text="p.name"></h3>
                                <p class="text-xs text-slate-500 mt-0.5" x-text="p.contract_no || 'No contract no.'"></p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="badge"
                                    :class="{ 'badge-green': p.status === 'active', 'badge-gray': p.status === 'archived', 'badge-red': p.status === 'closed' }"
                                    x-text="p.status"></span>
                                @if(auth()->user()->isSuperAdmin())
                                <button @click.prevent.stop="deleteProject(p)"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50"
                                        title="Delete project">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 line-clamp-2 mb-4"
                           x-text="p.description || 'No description.'"></p>

                        <div class="flex items-center gap-4 text-xs text-slate-500 border-t border-slate-100 pt-3">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <span x-text="plural(p.wells_count || 0, 'well')"></span>
                            </span>
                            <span x-text="'By ' + (p.created_by?.name || '—')"></span>
                        </div>
                    </div>
                </a>
            </template>

            <div x-show="projects.length === 0 && !loading"
                 class="col-span-full text-center py-16 text-slate-400 text-sm">
                No projects yet. Create the first one.
            </div>
        </div>
    </div>

    {{-- Create project modal --}}
    <div x-show="showCreate" x-cloak class="modal-backdrop" @click.self="showCreate = false">
        <div class="modal" @click.stop>
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">New project</h2>
                <button @click="showCreate = false" class="ml-auto btn-ghost btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="card-body space-y-4">
                <div x-show="error" class="alert-danger" x-text="error"></div>

                <div>
                    <label class="label">Project name <span class="text-red-500">*</span></label>
                    <input x-model="form.name" class="input" placeholder="e.g. ODC-C3100000180" />
                </div>
                <div>
                    <label class="label">Contract NO</label>
                    <input x-model="form.contract_no" class="input" placeholder="C3100000180" />
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea x-model="form.description" class="input" rows="2"
                        placeholder="Brief description…"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showCreate = false" class="btn-secondary">Cancel</button>
                    <button @click="create" :disabled="saving" class="btn-primary">
                        <span x-text="saving ? 'Creating…' : 'Create project'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
