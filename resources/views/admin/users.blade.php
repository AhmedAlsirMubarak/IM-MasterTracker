@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div class="flex flex-col h-full" x-data="userManager()">

    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0">
        <h2 class="text-sm font-semibold text-gray-900">User management</h2>
        <div class="flex-1"></div>
        <button @click="openCreate()" class="btn-primary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add user
        </button>
    </div>

    <div class="flex-1 overflow-auto scrollbar-thin">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th><th>Email</th><th>Role</th>
                    <th>Department</th><th>Status</th><th>Last login</th>
                    <th class="w-24">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="u in users" :key="u.id">
                    <tr class="group">
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-2xs font-semibold text-blue-700 shrink-0"
                                    x-text="u.name.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase()"></div>
                                <span class="font-medium" x-text="u.name"></span>
                            </div>
                        </td>
                        <td class="text-gray-500" x-text="u.email"></td>
                        <td>
                            <span class="badge"
                                :class="{
                                    'badge-purple': u.role === 'super_admin',
                                    'badge-green':  u.role === 'manager',
                                    'badge-amber':  u.role === 'sap_admin',
                                    'badge-coral':  u.role === 'dc',
                                    'badge-blue':   u.role === 'im_lead',
                                    'badge-gray':   u.role === 'viewer',
                                }"
                                x-text="u.role.replace('_',' ')"></span>
                        </td>
                        <td x-text="u.department || '—'"></td>
                        <td>
                            <span class="badge" :class="u.is_active ? 'badge-green' : 'badge-red'"
                                x-text="u.is_active ? 'Active' : 'Inactive'"></span>
                        </td>
                        <td class="text-gray-500" x-text="u.last_login_at || 'Never'"></td>
                        <td>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="openEdit(u)" class="btn-ghost btn-sm">Edit</button>
                                <button @click="deleteUser(u.id)" class="btn-ghost btn-sm text-red-500 hover:text-red-700">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="users.length === 0 && !loading">
                    <td colspan="7" class="text-center py-10 text-gray-400">No users found.</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- User form modal --}}
    <div x-show="showForm" x-cloak class="modal-backdrop" @click.self="showForm = false">
        <div class="modal" @click.stop>
            <div class="card-header">
                <h2 class="font-semibold text-gray-900" x-text="form.id ? 'Edit user' : 'Add user'"></h2>
                <button @click="showForm = false" class="ml-auto btn-ghost btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="card-body space-y-3">
                <div x-show="error" class="alert-danger" x-text="error"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Name <span class="text-red-500">*</span></label>
                        <input x-model="form.name" class="input" />
                    </div>
                    <div>
                        <label class="label">Email <span class="text-red-500">*</span></label>
                        <input x-model="form.email" type="email" class="input" />
                    </div>
                    <div>
                        <label class="label">Password <span x-show="!form.id" class="text-red-500">*</span></label>
                        <input x-model="form.password" type="password" class="input"
                            :placeholder="form.id ? 'Leave blank to keep current' : ''" />
                    </div>
                    <div>
                        <label class="label">Role <span class="text-red-500">*</span></label>
                        <select x-model="form.role" class="select">
                            <option value="super_admin">Super Admin</option>
                            <option value="manager">Manager</option>
                            <option value="sap_admin">SAP Admin</option>
                            <option value="dc">Document Controller</option>
                            <option value="im_lead">IM Lead</option>
                            <option value="viewer">Viewer (read-only)</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Department</label>
                        <input x-model="form.department" class="input" placeholder="e.g. Hofincons" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <button @click="form.is_active = !form.is_active"
                                class="toggle shrink-0"
                                :class="form.is_active ? 'toggle-on' : 'toggle-off'">
                                <span class="toggle-knob" :class="form.is_active ? 'toggle-knob-on' : 'toggle-knob-off'"></span>
                            </button>
                            <span class="text-sm text-gray-700">Active account</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showForm = false" class="btn-secondary">Cancel</button>
                    <button @click="save()" :disabled="saving" class="btn-primary">
                        <span x-text="saving ? 'Saving…' : (form.id ? 'Save changes' : 'Create user')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
