@if(auth()->user()->isSuperAdmin())
<div x-show="showShareModal" x-cloak class="modal-backdrop" @click.self="showShareModal = false">
    <div class="modal" style="max-width:480px" @click.stop>
        <div class="card-header">
            <h2 class="font-semibold text-slate-900">Share this project</h2>
            <button @click="showShareModal = false" class="ml-auto btn-ghost btn-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="card-body space-y-4">

            {{-- Loading --}}
            <div x-show="shareLoading" class="flex items-center gap-2 text-sm text-slate-500 py-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Loading…
            </div>

            {{-- No active link --}}
            <template x-if="!shareLoading && !shareLink">
                <div class="text-center py-4 space-y-3">
                    <div class="text-slate-400 text-sm">No active share link for this project.</div>
                    <button @click="generateShare()" class="btn-primary">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                        Generate share link
                    </button>
                </div>
            </template>

            {{-- Active link --}}
            <template x-if="!shareLoading && shareLink">
                <div class="space-y-3">
                    <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <input type="text" :value="shareLink.url" readonly
                                   class="input text-xs flex-1 bg-white font-mono" />
                            <button @click="copyShareLink(shareLink.url)"
                                    :class="shareCopied ? 'btn-primary' : 'btn-secondary'"
                                    class="btn-sm whitespace-nowrap flex items-center gap-1 transition-colors">
                                <template x-if="!shareCopied">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </template>
                                <template x-if="shareCopied">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                                <span x-text="shareCopied ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                        <p class="text-2xs text-slate-500">
                            Expires:
                            <span class="font-medium text-slate-700"
                                  x-text="shareLink.expires_at ? new Date(shareLink.expires_at).toLocaleDateString() : '—'"></span>
                            <span x-show="shareLink.expired" class="ml-1 text-red-500 font-medium">(expired)</span>
                        </p>
                    </div>

                    <div class="text-xs text-slate-500 space-y-1">
                        <p>
                            <svg class="w-3.5 h-3.5 inline mr-1 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Anyone with this link can view the master grid — no login required.
                        </p>
                        <p>
                            <svg class="w-3.5 h-3.5 inline mr-1 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Hidden columns and hidden cell values are automatically excluded.
                        </p>
                        <p>
                            <svg class="w-3.5 h-3.5 inline mr-1 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Read-only — no editing possible via shared link.
                        </p>
                    </div>

                    <div class="flex gap-2 pt-1 border-t border-slate-100">
                        <button @click="generateShare()" class="btn-secondary btn-sm flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Regenerate
                        </button>
                        <button @click="revokeShare()" class="btn-secondary btn-sm flex items-center gap-1 text-red-600 border-red-200 hover:bg-red-50 ml-auto">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            Revoke link
                        </button>
                    </div>
                </div>
            </template>

        </div>
    </div>
</div>
@endif
