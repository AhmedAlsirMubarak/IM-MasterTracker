@extends('layouts.app')
@section('title', $contract->name . ' — CVs')

@section('content')
<div class="flex flex-col h-full" x-data="cvManager({{ $contract->id }}, {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }})"
     @cvs-imported.window="init()">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <p class="page-eyebrow">{{ $contract->name }}</p>
            <h1 class="page-title">CVs Letters & Correspondence</h1>
        </div>

        <div class="relative w-48">
            <input x-model="search" type="text" class="input-sm w-full pr-7"
                   placeholder="Search description, name or ref…" autocomplete="off" />
            <svg class="absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <div class="flex-1"></div>

        {{-- Tabs --}}
        <div class="flex items-center bg-slate-100 rounded-lg p-0.5 gap-0.5">
            <button @click="tab = 'outgoing'"
                    :class="tab === 'outgoing' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                    class="px-3 py-1.5 rounded-md text-xs font-medium transition-all">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Sent to PDO
                    <span class="bg-brand-100 text-brand-700 rounded-full px-1.5 py-0.5 text-2xs font-bold"
                          x-text="outgoing.length"></span>
                </span>
            </button>
            <button @click="tab = 'incoming'"
                    :class="tab === 'incoming' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                    class="px-3 py-1.5 rounded-md text-xs font-medium transition-all">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Correspond (PDO)
                    <span class="bg-purple-100 text-purple-700 rounded-full px-1.5 py-0.5 text-2xs font-bold"
                          x-text="incoming.length"></span>
                </span>
            </button>
        </div>

        <a href="/contracts/{{ $contract->id }}/cvs/export"
           class="btn-secondary btn-sm flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
            </svg>
            Export CSV
        </a>

        @unless(auth()->user()->isViewer())
        <button @click="$dispatch('open-import')"
                class="btn-secondary btn-sm flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 8l5-5 5 5M12 3v12"/>
            </svg>
            Import
        </button>
        @endunless

        @unless(auth()->user()->isViewer())
        <button @click="openCreate(tab)" class="btn-primary btn-sm flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span x-text="tab === 'outgoing' ? 'New letter' : 'New response'"></span>
        </button>
        @endunless
    </div>

    {{-- Alert banner --}}
    <div x-show="alertCvs.length > 0" x-cloak
         class="shrink-0 bg-amber-50 border-b border-amber-200 px-6 py-2 flex items-center gap-3">
        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <p class="text-xs text-amber-800">
            <strong x-text="alertCvs.length"></strong>
            <span x-text="alertCvs.length === 1 ? ' CV approval is' : ' CV approvals are'"></span>
            approaching or past expiry — a renewal letter to PDO may be needed.
        </p>
        <button @click="tab = 'incoming'" class="ml-auto text-xs text-amber-700 underline font-medium">View</button>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="flex-1 flex items-center justify-center">
        <div class="flex items-center gap-2 text-slate-400 text-sm">
            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Loading CVs…
        </div>
    </div>

    {{-- Table --}}
    <div x-show="!loading" class="flex-1 overflow-auto">
        <table class="w-full text-xs border-collapse">
            <thead class="sticky top-0 z-10 bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600 whitespace-nowrap w-24">CTP No.</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600 whitespace-nowrap">Reference No.</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600 whitespace-nowrap">Sent Date</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600 w-56">Description</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600 whitespace-nowrap">Name</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600 whitespace-nowrap">Position</th>
                    <th x-show="tab === 'outgoing'" class="px-3 py-2.5 text-left font-semibold text-slate-600 whitespace-nowrap">Reply Ref.</th>
                    <th x-show="tab === 'incoming'" class="px-3 py-2.5 text-left font-semibold text-slate-600 whitespace-nowrap">In Reply To</th>
                    <th class="px-3 py-2.5 text-center font-semibold text-slate-600 whitespace-nowrap w-8">Thread</th>
                    <th x-show="tab === 'incoming'" class="px-3 py-2.5 text-center font-semibold text-slate-600 whitespace-nowrap">Approval</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600">Remarks-PDO-HDE</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600">HDE</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-slate-600 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">

                {{-- Outgoing tab rows --}}
                <template x-if="tab === 'outgoing'">
                    <template x-for="c in outgoing" :key="c.id">
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="px-3 py-2.5 font-mono text-slate-700" x-text="c.ctp_number || '—'"></td>
                            <td class="px-3 py-2.5">
                                <span class="font-medium text-brand-700" x-text="c.reference_no"></span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-slate-600" x-text="fmtDate(c.submission_date)"></td>
                            <td class="px-3 py-2.5 text-slate-700 max-w-xs">
                                <p class="line-clamp-2" x-text="c.description || '—'"></p>
                            </td>
                            <td class="px-3 py-2.5 text-slate-800 whitespace-nowrap" x-text="c.person_name"></td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap" x-text="c.position || '—'"></td>
                            <td class="px-3 py-2.5">
                                <template x-if="linkedIncoming(c)">
                                    <span class="flex items-center gap-1 text-green-700">
                                        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span class="font-mono text-2xs" x-text="linkedIncoming(c).reference_no"></span>
                                    </span>
                                </template>
                                <template x-if="!linkedIncoming(c)">
                                    <span class="text-slate-400 italic">No reply yet</span>
                                </template>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button @click.stop="openThread(c)"
                                        class="relative inline-flex items-center justify-center w-6 h-6 rounded hover:bg-brand-50 text-slate-400 hover:text-brand-600 transition-colors"
                                        :class="threadCount(c) > 1 ? 'text-brand-600' : ''"
                                        title="View conversation thread">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3-3-3z"/>
                                    </svg>
                                    <span x-show="threadCount(c) > 1"
                                          class="absolute -top-1 -right-1 text-2xs font-bold bg-brand-600 text-white rounded-full w-3.5 h-3.5 flex items-center justify-center"
                                          x-text="threadCount(c)"></span>
                                </button>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600 max-w-xs">
                                <p class="line-clamp-2" x-text="c.remarks || '—'"></p>
                            </td>
                            <td class="px-3 py-2.5 text-slate-500 max-w-xs">
                                <p class="line-clamp-2" x-text="c.hde_remarks || '—'"></p>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @unless(auth()->user()->isViewer())
                                    <button @click="openEdit(c)" class="p-1 rounded text-slate-400 hover:text-brand-600 hover:bg-brand-50" title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    @endunless
                                    @if(auth()->user()->isSuperAdmin())
                                    <button @click="deleteCv(c)" class="p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </template>
                </template>

                {{-- Incoming tab rows --}}
                <template x-if="tab === 'incoming'">
                    <template x-for="c in incoming" :key="c.id">
                        <tr class="hover:bg-slate-50 transition-colors group"
                            :class="{ 'bg-red-50': c.approval_status === 'approved_temporary' && c.countdown_status === 'red',
                                      'bg-amber-50': c.approval_status === 'approved_temporary' && c.countdown_status === 'yellow' }">
                            <td class="px-3 py-2.5 font-mono text-slate-700" x-text="c.ctp_number || '—'"></td>
                            <td class="px-3 py-2.5">
                                <span class="font-medium text-purple-700" x-text="c.reference_no"></span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-slate-600" x-text="fmtDate(c.submission_date)"></td>
                            <td class="px-3 py-2.5 text-slate-700 max-w-xs">
                                <p class="line-clamp-2" x-text="c.description || '—'"></p>
                            </td>
                            <td class="px-3 py-2.5 text-slate-800 whitespace-nowrap" x-text="c.person_name"></td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap" x-text="c.position || '—'"></td>
                            <td class="px-3 py-2.5">
                                <template x-if="linkedOutgoing(c)">
                                    <span class="flex items-center gap-1 text-brand-700">
                                        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                        <span class="font-mono text-2xs" x-text="linkedOutgoing(c).reference_no"></span>
                                    </span>
                                </template>
                                <template x-if="!linkedOutgoing(c) && c.reply_ref_no">
                                    <span class="font-mono text-2xs text-slate-500" x-text="c.reply_ref_no"></span>
                                </template>
                                <template x-if="!linkedOutgoing(c) && !c.reply_ref_no">
                                    <span class="text-slate-400 italic">—</span>
                                </template>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button @click.stop="openThread(c)"
                                        class="relative inline-flex items-center justify-center w-6 h-6 rounded hover:bg-brand-50 text-slate-400 hover:text-brand-600 transition-colors"
                                        :class="threadCount(c) > 1 ? 'text-brand-600' : ''"
                                        title="View conversation thread">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3-3-3z"/>
                                    </svg>
                                    <span x-show="threadCount(c) > 1"
                                          class="absolute -top-1 -right-1 text-2xs font-bold bg-brand-600 text-white rounded-full w-3.5 h-3.5 flex items-center justify-center"
                                          x-text="threadCount(c)"></span>
                                </button>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <template x-if="c.approval_status === 'pending'">
                                    <span class="badge badge-gray">Pending</span>
                                </template>
                                <template x-if="c.approval_status === 'approved_final'">
                                    <span class="badge badge-green">Final approval</span>
                                </template>
                                <template x-if="c.approval_status === 'rejected'">
                                    <span class="badge badge-red">Rejected</span>
                                </template>
                                <template x-if="c.approval_status === 'approved_temporary'">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-2xs text-slate-500" x-text="c.validity_value + ' ' + c.validity_unit"></span>
                                        <span :class="{
                                                  'bg-green-100 text-green-700': c.countdown_status === 'green',
                                                  'bg-amber-100 text-amber-700': c.countdown_status === 'yellow',
                                                  'bg-red-100 text-red-700':    c.countdown_status === 'red'
                                              }"
                                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-semibold text-2xs whitespace-nowrap">
                                            <span x-text="c.countdown_status === 'red'
                                                ? (c.days_remaining <= 0 ? Math.abs(c.days_remaining) + 'd overdue' : c.days_remaining + 'd left')
                                                : c.days_remaining + 'd left'"></span>
                                        </span>
                                    </div>
                                </template>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600 max-w-xs">
                                <p class="line-clamp-2" x-text="c.remarks || '—'"></p>
                            </td>
                            <td class="px-3 py-2.5 text-slate-500 max-w-xs">
                                <p class="line-clamp-2" x-text="c.hde_remarks || '—'"></p>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @unless(auth()->user()->isViewer())
                                    <button @click="openEdit(c)" class="p-1 rounded text-slate-400 hover:text-brand-600 hover:bg-brand-50" title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    @endunless
                                    @if(auth()->user()->isSuperAdmin())
                                    <button @click="deleteCv(c)" class="p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </template>
                </template>

                {{-- Empty state --}}
                <tr x-show="(tab === 'outgoing' && outgoing.length === 0) || (tab === 'incoming' && incoming.length === 0)">
                    <td colspan="13" class="px-6 py-16 text-center text-slate-400 text-sm">
                        <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <p x-text="search.trim()
                            ? 'No CVs match your search.'
                            : (tab === 'outgoing' ? 'No CV letters sent to PDO yet.' : 'No CV correspondence from PDO yet.')"></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Conversation Thread panel --}}
    <div x-show="showThread" x-cloak class="modal-backdrop" @click.self="showThread = false">
        <div class="modal max-w-2xl" @click.stop>
            <div class="card-header">
                <div>
                    <h2 class="font-semibold text-slate-900">Conversation Thread</h2>
                    <p class="text-2xs text-slate-400 mt-0.5" x-text="threadCv ? threadCv.reference_no : ''"></p>
                </div>
                <span class="ml-3 badge badge-blue" x-text="threadCvs(threadCv).length + ' letters'"></span>
                <button @click="showThread = false" class="ml-auto btn-ghost btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="card-body p-0 max-h-[70vh] overflow-y-auto">
                <template x-if="threadCvs(threadCv).length === 0">
                    <p class="text-sm text-slate-400 text-center py-10">No linked letters found.</p>
                </template>
                <template x-for="(c, idx) in threadCvs(threadCv)" :key="c.id">
                    <div class="flex gap-3 px-5 py-4 border-b border-slate-100 last:border-0"
                         :class="threadCv && c.id === threadCv.id ? 'bg-brand-50' : 'hover:bg-slate-50'">
                        {{-- Timeline line --}}
                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-white text-xs font-bold"
                                 :class="c.direction === 'outgoing' ? 'bg-brand-500' : 'bg-purple-500'"
                                 x-text="idx + 1"></div>
                            <div class="w-px flex-1 bg-slate-200 mt-1" x-show="idx < threadCvs(threadCv).length - 1"></div>
                        </div>
                        {{-- Content --}}
                        <div class="flex-1 min-w-0 pb-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="text-2xs font-semibold uppercase tracking-wide"
                                      :class="c.direction === 'outgoing' ? 'text-brand-600' : 'text-purple-600'"
                                      x-text="c.direction === 'outgoing' ? '→ Sent to PDO' : '← From PDO'"></span>
                                <span class="text-2xs text-slate-400" x-text="fmtDate(c.submission_date)"></span>
                                <span x-show="c.ctp_number" class="font-mono text-2xs text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded" x-text="c.ctp_number"></span>
                                <template x-if="c.direction === 'incoming'">
                                    <span class="badge ml-auto"
                                          :class="{
                                              'badge-gray':  c.approval_status === 'pending',
                                              'badge-green': c.approval_status === 'approved_final',
                                              'badge-red':   c.approval_status === 'rejected',
                                              'badge-blue':  c.approval_status === 'approved_temporary',
                                          }"
                                          x-text="c.approval_status === 'approved_temporary' ? (c.validity_value + ' ' + c.validity_unit) : c.approval_status.replace('_', ' ')"></span>
                                </template>
                                <span x-show="c.approval_status === 'approved_temporary' && c.countdown_status === 'red'" class="badge badge-red">Overdue</span>
                                <span x-show="c.approval_status === 'approved_temporary' && c.countdown_status === 'yellow'" class="badge badge-amber">Due soon</span>
                            </div>
                            <p class="font-mono text-xs text-slate-800 font-semibold mb-1" x-text="c.reference_no"></p>
                            <p class="text-xs text-slate-600 mb-1" x-show="c.description" x-text="c.description"></p>
                            <div x-show="c.reply_ref_no" class="flex items-center gap-1 text-2xs text-slate-400">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                                <span>In reply to:</span>
                                <span class="font-mono" x-text="c.reply_ref_no"></span>
                            </div>
                            <p x-show="c.remarks" class="text-2xs text-slate-500 mt-1 italic" x-text="c.remarks"></p>
                            <div x-show="(c.related_refs || []).length > 0" class="flex flex-wrap gap-1 mt-1.5">
                                <span class="text-2xs text-slate-400">Linked:</span>
                                <template x-for="ref in (c.related_refs || [])" :key="ref">
                                    <span class="font-mono text-2xs bg-brand-50 text-brand-700 px-1.5 py-0.5 rounded" x-text="ref"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div x-show="showForm" x-cloak class="modal-backdrop" @click.self="showForm = false">
        <div class="modal max-w-lg" @click.stop>
            <div class="card-header">
                <h2 class="font-semibold text-slate-900"
                    x-text="editId ? 'Edit CV letter' : (form.direction === 'outgoing' ? 'New CV letter to PDO' : 'New CV correspondence from PDO')"></h2>
                <button @click="showForm = false" class="ml-auto btn-ghost btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="card-body space-y-4">
                <div x-show="error" class="alert-danger" x-text="error"></div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">CTP Number</label>
                        <input x-model="form.ctp_number" class="input" placeholder="CTP-0124" />
                    </div>
                    <div>
                        <label class="label">Direction</label>
                        <select x-model="form.direction" class="input">
                            <option value="outgoing">→ Sent to PDO</option>
                            <option value="incoming">← From PDO</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label">Reference No. <span class="text-red-500">*</span></label>
                    <input x-model="form.reference_no" class="input font-mono" placeholder="HDE/C3100000180/PDO-L-0124" />
                </div>

                <div>
                    <label class="label">In Reply To (original reference no.)</label>
                    <template x-if="cvs.length > 0">
                        <select x-model="form.reply_ref_no" class="input mb-1">
                            <option value="">— select existing reference —</option>
                            <template x-for="ref in cvs.filter(c => c.reference_no !== form.reference_no)" :key="ref.id">
                                <option :value="ref.reference_no"
                                        x-text="(ref.direction === 'outgoing' ? '→ ' : '← ') + ref.reference_no + ' (' + ref.person_name + ')'"></option>
                            </template>
                        </select>
                    </template>
                    <input x-model="form.reply_ref_no" class="input font-mono"
                           :placeholder="cvs.length ? 'Or type manually…' : 'e.g. HDE/C3100000180/PDO-L-0124'" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Sent Date <span class="text-red-500">*</span></label>
                        <input x-model="form.submission_date" type="date" class="input" />
                    </div>
                    <div></div>
                </div>

                <div>
                    <label class="label">Description</label>
                    <textarea x-model="form.description" class="input" rows="2"
                        placeholder="Request Approval for CV Submission of Core Personnel…"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Name <span class="text-red-500">*</span></label>
                        <input x-model="form.person_name" class="input" placeholder="Full name" />
                    </div>
                    <div>
                        <label class="label">Position</label>
                        <input x-model="form.position" class="input" placeholder="e.g. QCI - Mechanical" />
                    </div>
                </div>

                <div x-show="form.direction === 'incoming'" x-cloak>
                    <label class="label">Approval Status</label>
                    <select x-model="form.approval_status" class="input">
                        <option value="pending">Pending — no decision yet</option>
                        <option value="approved_temporary">Approved — for a limited period</option>
                        <option value="approved_final">Approved — final (no time limit)</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div x-show="form.direction === 'incoming' && form.approval_status === 'approved_temporary'" x-cloak class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Approved for</label>
                        <input x-model.number="form.validity_value" type="number" min="1" class="input" placeholder="6" />
                    </div>
                    <div>
                        <label class="label">Unit</label>
                        <select x-model="form.validity_unit" class="input">
                            <option value="months">Months</option>
                            <option value="days">Days</option>
                        </select>
                    </div>
                    <p class="col-span-2 text-2xs text-slate-400"
                       x-show="form.submission_date && form.validity_value">
                        Expires around <span x-text="fmtDate(computedExpiry)"></span> — a reminder will show 30 days before that date so a renewal letter can be sent to PDO.
                    </p>
                </div>

                {{-- Conversation thread links --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="label mb-0">Conversation Thread Links</label>
                        <span class="text-2xs text-slate-400">Link letters that are part of the same conversation</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5 p-2 border border-slate-200 rounded-lg min-h-[36px] bg-slate-50 mb-1.5">
                        <template x-for="ref in (form.related_refs || [])" :key="ref">
                            <span class="flex items-center gap-1 bg-brand-100 text-brand-800 rounded-full px-2.5 py-0.5 text-2xs font-medium font-mono">
                                <span x-text="ref"></span>
                                <button type="button" @click="removeRelatedRef(ref)"
                                        class="ml-0.5 text-brand-500 hover:text-red-600 font-bold leading-none">×</button>
                            </span>
                        </template>
                        <span x-show="!(form.related_refs || []).length" class="text-2xs text-slate-400 italic py-0.5">No linked references yet</span>
                    </div>
                    <div class="relative">
                        <input x-model="refSearch" type="text" class="input text-xs pr-8"
                               placeholder="Search reference no. to link…" autocomplete="off" />
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <div x-show="refSearch.length > 0 && filteredRefOptions.length > 0"
                             class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-36 overflow-y-auto">
                            <template x-for="c in filteredRefOptions" :key="c.id">
                                <button type="button" @click="addRelatedRef(c.reference_no)"
                                        class="w-full text-left px-3 py-2 hover:bg-brand-50 flex items-center gap-2 border-b border-slate-50 last:border-0">
                                    <span :class="c.direction === 'outgoing' ? 'text-brand-600' : 'text-purple-600'"
                                          class="text-2xs font-medium shrink-0"
                                          x-text="c.direction === 'outgoing' ? '→ PDO' : '← PDO'"></span>
                                    <span class="font-mono text-xs text-slate-800" x-text="c.reference_no"></span>
                                    <span class="text-2xs text-slate-400 ml-auto" x-text="fmtDate(c.submission_date)"></span>
                                </button>
                            </template>
                        </div>
                        <div x-show="refSearch.length > 0 && filteredRefOptions.length === 0"
                             class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow p-3 text-2xs text-slate-400 text-center">
                            No matching references found
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="label mb-0">Remarks (PDO / HDE)</label>
                        <span class="text-2xs text-slate-400">Click a preset to append</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <template x-for="preset in remarkPresets" :key="preset">
                            <button type="button" @click="appendRemark(preset)"
                                    class="px-2.5 py-1 rounded-full text-2xs font-medium bg-slate-100 hover:bg-brand-100 hover:text-brand-700 text-slate-600 border border-slate-200 hover:border-brand-300 transition-colors cursor-pointer"
                                    x-text="preset"></button>
                        </template>
                    </div>
                    <textarea x-model="form.remarks" class="input" rows="2"
                        placeholder="Notes visible to PDO / HDE…"></textarea>
                </div>

                <div>
                    <label class="label">HDE Remarks (internal)</label>
                    <textarea x-model="form.hde_remarks" class="input" rows="2"
                        placeholder="Internal notes, e.g. interview coordination…"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showForm = false" class="btn-secondary">Cancel</button>
                    <button @click="save()" :disabled="saving" class="btn-primary">
                        <span x-text="saving ? 'Saving…' : (editId ? 'Save changes' : 'Add CV letter')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Import modal --}}
    <div x-data="contractImport({{ $contract->id }}, 'cvs')" @open-import.window="show()">
        <div x-show="open" x-cloak class="modal-backdrop" @click.self="close()">
            <div class="modal max-w-2xl" @click.stop>
                <div class="card-header">
                    <h2 class="font-semibold text-slate-900">Import CVs</h2>
                    <button @click="close()" class="ml-auto btn-ghost btn-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="card-body space-y-4">
                    <div x-show="error" class="alert-danger" x-text="error"></div>

                    {{-- Step: upload --}}
                    <template x-if="stage === 'upload'">
                        <div>
                            <div class="flex justify-end mb-2">
                                <a href="/contracts/{{ $contract->id }}/import/template?type=cvs"
                                   class="text-xs text-brand-600 hover:underline flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                                    </svg>
                                    Download template
                                </a>
                            </div>
                            <label
                                @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                                @drop.prevent="handleDrop($event)"
                                :class="dragging ? 'border-brand-400 bg-brand-50' : 'border-slate-300'"
                                class="flex flex-col items-center justify-center gap-2 border-2 border-dashed rounded-lg py-12 cursor-pointer transition-colors">
                                <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 8l5-5 5 5M12 3v12"/>
                                </svg>
                                <p class="text-sm text-slate-600" x-show="!uploading">
                                    <span class="font-medium text-brand-600">Click to upload</span> or drag & drop
                                </p>
                                <p class="text-sm text-slate-600" x-show="uploading">Reading file…</p>
                                <p class="page-eyebrow">XLSX, XLS or CSV — up to 10MB</p>
                                <input type="file" class="hidden" accept=".xlsx,.xls,.csv" @change="handleFile($event)" />
                            </label>
                        </div>
                    </template>

                    {{-- Step: map columns + preview --}}
                    <template x-if="stage === 'map'">
                        <div class="space-y-4">
                            <p class="text-xs text-slate-500">
                                <span x-text="totalRows"></span> row(s) detected. Match each field to a column from your file.
                            </p>

                            <div class="grid grid-cols-2 gap-3 max-h-56 overflow-y-auto pr-1">
                                <template x-for="field in fields" :key="field.value">
                                    <div>
                                        <label class="label">
                                            <span x-text="field.label"></span>
                                            <span x-show="field.required" class="text-red-500">*</span>
                                        </label>
                                        <select x-model="mapping[field.value]" class="input-sm w-full">
                                            <option value="">— Not mapped —</option>
                                            <template x-for="(h, idx) in headers" :key="idx">
                                                <option :value="idx" x-text="h || ('Column ' + (idx + 1))"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <p class="label mb-1">Preview</p>
                                <div class="border border-slate-200 rounded-lg overflow-x-auto">
                                    <table class="w-full text-2xs border-collapse">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <template x-for="field in fields" :key="field.value">
                                                    <th class="px-2 py-1.5 text-left font-semibold text-slate-600 whitespace-nowrap" x-text="field.label"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <template x-for="(row, ridx) in sampleRows" :key="ridx">
                                                <tr>
                                                    <template x-for="field in fields" :key="field.value">
                                                        <td class="px-2 py-1.5 text-slate-600 whitespace-nowrap max-w-40 truncate"
                                                            x-text="previewValue(field.value, row) || '—'"></td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button @click="reset()" class="btn-secondary">Back</button>
                                <button @click="confirm()" :disabled="submitting" class="btn-primary">
                                    <span x-text="submitting ? 'Importing…' : 'Import'"></span>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Step: result --}}
                    <template x-if="stage === 'result'">
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-sm"><strong x-text="result.imported"></strong> CV(s) imported.</span>
                            </div>
                            <div x-show="result.skipped > 0" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                <strong x-text="result.skipped"></strong> row(s) skipped.
                                <ul class="mt-1.5 space-y-0.5 text-2xs text-amber-800 max-h-32 overflow-y-auto">
                                    <template x-for="err in result.errors" :key="err.row">
                                        <li>Row <span x-text="err.row"></span>: <span x-text="err.message"></span></li>
                                    </template>
                                </ul>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button @click="close()" class="btn-primary">Done</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
