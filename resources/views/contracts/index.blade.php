@extends('layouts.app')
@section('title', 'Contracts')

@section('content')
<div class="flex flex-col h-full" x-data="contractList()">

    {{-- Header --}}
    <div class="page-header">
        <h1 class="page-title">Contracts</h1>
        <div class="flex-1"></div>
        @if(auth()->user()->isSuperAdmin())
        <button @click="error = ''; showCreate = true" class="btn-primary btn-sm flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New contract
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
            Loading contracts…
        </div>
    </div>

    {{-- Contracts grid --}}
    <div x-show="!loading" class="flex-1 overflow-y-auto p-6">
        {{-- Summary strip --}}
        <div class="stat-strip mb-5" x-show="contracts.length > 0">
            <div class="stat">
                <span class="stat-label">Contracts</span>
                <span class="stat-value" x-text="contracts.length"></span>
                <span class="stat-sub">in total</span>
            </div>
            <div class="stat">
                <span class="stat-label">Active</span>
                <span class="stat-value" x-text="contracts.filter(c => c.status === 'active').length"></span>
                <span class="stat-sub">currently running</span>
            </div>
            <div class="stat">
                <span class="stat-label">Projects</span>
                <span class="stat-value" x-text="contracts.reduce((n, c) => n + (c.projects_count || 0), 0)"></span>
                <span class="stat-sub">across all contracts</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="c in contracts" :key="c.id">
                <a :href="`/contracts/${c.id}`"
                   class="card hover:border-brand-300 hover:shadow-md transition-all duration-150 block group">
                    <div class="card-body">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-slate-900 group-hover:text-brand-700 transition-colors"
                                    x-text="c.name"></h3>
                                <p class="text-xs text-slate-500 mt-0.5" x-text="c.contract_no || 'No contract no.'"></p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="badge"
                                    :class="{ 'badge-green': c.status === 'active', 'badge-gray': c.status === 'archived', 'badge-red': c.status === 'closed' }"
                                    x-text="c.status"></span>
                                @if(auth()->user()->isSuperAdmin())
                                <button @click.prevent.stop="openEdit(c)"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded text-slate-400 hover:text-brand-600 hover:bg-brand-50"
                                        title="Edit contract">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button @click.prevent.stop="deleteContract(c)"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50"
                                        title="Delete contract">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 line-clamp-2 mb-4"
                           x-text="c.description || 'No description.'"></p>

                        <div class="flex items-center gap-4 text-xs text-slate-500 border-t border-slate-100 pt-3">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                </svg>
                                <span x-text="(c.projects_count || 0) + ' project' + (c.projects_count !== 1 ? 's' : '')"></span>
                            </span>
                            <span x-text="'By ' + (c.created_by?.name || '—')"></span>
                        </div>
                    </div>
                </a>
            </template>

            <div x-show="contracts.length === 0 && !loading"
                 class="col-span-full text-center py-16 text-slate-400 text-sm">
                No contracts yet.
                @if(auth()->user()->isSuperAdmin())
                Click <strong>New contract</strong> to get started.
                @endif
            </div>
        </div>
    </div>

    {{-- Edit contract modal --}}
    <div x-show="showEdit" x-cloak class="modal-backdrop" @click.self="showEdit = false">
        <div class="modal" @click.stop>
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">Edit contract</h2>
                <button @click="showEdit = false" class="ml-auto btn-ghost btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form class="card-body space-y-4" @submit.prevent="update()" @input="editError = ''">
                <div x-show="editError" class="alert-danger" x-text="editError"></div>
                <div>
                    <label class="label">Contract name <span class="text-red-500">*</span></label>
                    <input x-model="editForm.name" class="input" required maxlength="255" placeholder="e.g. ODC-C3100000180" />
                </div>
                <div>
                    <label class="label">Contract NO</label>
                    <input x-model="editForm.contract_no" class="input" maxlength="100" placeholder="C3100000180" />
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea x-model="editForm.description" class="input" rows="2"
                        placeholder="Brief description…"></textarea>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select x-model="editForm.status" class="input">
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showEdit = false" class="btn-secondary">Cancel</button>
                    <button type="submit" :disabled="saving" class="btn-primary">
                        <span x-text="saving ? 'Saving…' : 'Save changes'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Create contract modal --}}
    <div x-show="showCreate" x-cloak class="modal-backdrop" @click.self="showCreate = false">
        <div class="modal" @click.stop>
            <div class="card-header">
                <h2 class="font-semibold text-slate-900">New contract</h2>
                <button @click="showCreate = false" class="ml-auto btn-ghost btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form class="card-body space-y-4" @submit.prevent="create()" @input="error = ''">
                <div x-show="error" class="alert-danger" x-text="error"></div>
                <div>
                    <label class="label">Contract name <span class="text-red-500">*</span></label>
                    <input x-model="form.name" class="input" required maxlength="255" placeholder="e.g. ODC-C3100000180" />
                </div>
                <div>
                    <label class="label">Contract NO</label>
                    <input x-model="form.contract_no" class="input" maxlength="100" placeholder="C3100000180" />
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea x-model="form.description" class="input" rows="2"
                        placeholder="Brief description…"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showCreate = false" class="btn-secondary">Cancel</button>
                    <button type="submit" :disabled="saving" class="btn-primary">
                        <span x-text="saving ? 'Creating…' : 'Create contract'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
