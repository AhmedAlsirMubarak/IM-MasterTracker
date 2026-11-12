{{--
    App-wide confirmation dialog, replacing the browser's confirm() box.
    Open it from JS with:  if (!(await confirmDialog({ title, subject, message, details, confirmText, requireText }))) return
--}}
<div x-data x-show="$store.confirm.open" x-cloak
     class="modal-backdrop"
     @keydown.escape.window="$store.confirm.close(false)"
     @click.self="$store.confirm.close(false)"
     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="modal max-w-md" role="alertdialog" aria-modal="true"
         aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message"
         x-trap.noscroll="$store.confirm.open"
         x-show="$store.confirm.open"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
        <form class="p-5" @submit.prevent="$store.confirm.canConfirm && $store.confirm.close(true)">
            <div class="flex items-start gap-4">
                {{-- Icon --}}
                <div class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center"
                     :class="$store.confirm.tone === 'danger' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path x-show="$store.confirm.tone === 'danger'" stroke-linecap="round" stroke-linejoin="round"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        <path x-show="$store.confirm.tone !== 'danger'" stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    <h2 id="confirm-dialog-title" class="text-base font-semibold text-slate-900" x-text="$store.confirm.title"></h2>

                    <p x-show="$store.confirm.subject"
                       class="mt-2 px-2.5 py-1.5 rounded-md bg-slate-100 text-sm font-medium text-slate-800 break-words"
                       x-text="$store.confirm.subject"></p>

                    <p id="confirm-dialog-message" class="mt-2 text-sm text-slate-600" x-text="$store.confirm.message"></p>

                    <ul x-show="$store.confirm.details.length" class="mt-2 space-y-1 text-sm text-slate-600">
                        <template x-for="(d, i) in $store.confirm.details" :key="i">
                            <li class="flex items-start gap-2">
                                <span class="mt-2 w-1 h-1 rounded-full bg-slate-400 shrink-0"></span>
                                <span x-text="d"></span>
                            </li>
                        </template>
                    </ul>

                    <p x-show="$store.confirm.tone === 'danger'" class="mt-3 text-xs font-medium text-red-600">This cannot be undone.</p>

                    {{-- Type-to-confirm for the biggest deletions --}}
                    <div x-show="$store.confirm.requireText" class="mt-3">
                        <label for="confirm-dialog-input" class="block text-xs text-slate-600 mb-1">
                            Type <span class="font-semibold text-slate-900" x-text="$store.confirm.requireText"></span> to confirm
                        </label>
                        <input id="confirm-dialog-input" type="text" class="input w-full" autocomplete="off"
                               x-model="$store.confirm.typed">
                    </div>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="$store.confirm.close(false)"
                        x-text="$store.confirm.cancelText"></button>
                <button type="submit"
                        :class="$store.confirm.tone === 'danger' ? 'btn-danger' : 'btn-primary'"
                        class="disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!$store.confirm.canConfirm"
                        x-text="$store.confirm.confirmText"></button>
            </div>
        </form>
    </div>
</div>
