{{-- Global Glassmorphism Delete Confirmation Modal --}}
<div id="global-delete-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md transition-all duration-300 opacity-0" aria-modal="true" role="dialog">
    <div id="global-delete-modal-card" class="relative w-full max-w-md bg-white/95 backdrop-blur-2xl border border-white/80 rounded-3xl p-6 sm:p-7 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.3)] transform scale-95 transition-all duration-300 space-y-5">
        
        <!-- Modal Header / Icon + Text -->
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-600 flex items-center justify-center text-xl shrink-0 shadow-xs">
                <i class="fa-solid fa-trash-can animate-pulse"></i>
            </div>
            <div class="space-y-1.5 flex-1 min-w-0">
                <h3 id="global-delete-modal-title" class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight leading-snug">
                    Konfirmasi Hapus Data
                </h3>
                <p id="global-delete-modal-message" class="text-xs text-slate-500 leading-relaxed font-medium">
                    Apakah Anda yakin ingin menghapus data yang dipilih? Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
        </div>

        <!-- Warning Callout -->
        <div class="bg-rose-50/70 border border-rose-100 rounded-2xl px-3.5 py-2.5 flex items-center gap-2.5 text-rose-800 text-[11px] font-semibold">
            <i class="fa-solid fa-triangle-exclamation text-rose-500 text-xs shrink-0"></i>
            <span>Data yang telah terhapus tidak dapat dikembalikan.</span>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
            <button
                type="button"
                id="global-delete-modal-cancel"
                class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all cursor-pointer active:scale-95"
                style="background-color: #f1f5f9; color: #334155;"
            >
                Batal
            </button>
            <button
                type="button"
                id="global-delete-modal-confirm"
                class="px-5 py-2.5 rounded-xl text-white font-extrabold text-xs shadow-md flex items-center gap-2 transition-all hover:scale-[1.02] active:scale-95 cursor-pointer"
                style="background-color: #dc2626 !important; color: #ffffff !important; box-shadow: 0 4px 14px 0 rgba(220, 38, 38, 0.35);"
                onmouseover="this.style.backgroundColor='#b91c1c'"
                onmouseout="this.style.backgroundColor='#dc2626'"
            >
                <i class="fa-solid fa-trash-can text-xs text-white" style="color: #ffffff !important;"></i>
                <span id="global-delete-modal-btn-text" class="text-white" style="color: #ffffff !important;">Ya, Hapus Sekarang</span>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    let currentConfirmCallback = null;

    window.showConfirmDeleteModal = function(options = {}) {
        const modal = document.getElementById('global-delete-modal');
        const card = document.getElementById('global-delete-modal-card');
        const titleEl = document.getElementById('global-delete-modal-title');
        const msgEl = document.getElementById('global-delete-modal-message');
        const btnTextEl = document.getElementById('global-delete-modal-btn-text');

        if (!modal) return;

        if (options.title) titleEl.textContent = options.title;
        if (options.message) msgEl.textContent = options.message;
        if (options.confirmText) btnTextEl.textContent = options.confirmText;
        else btnTextEl.textContent = 'Ya, Hapus Sekarang';

        currentConfirmCallback = options.onConfirm || null;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            if (card) {
                card.classList.remove('scale-95');
                card.classList.add('scale-100');
            }
        }, 10);
    };

    window.closeConfirmDeleteModal = function() {
        const modal = document.getElementById('global-delete-modal');
        const card = document.getElementById('global-delete-modal-card');

        if (!modal) return;

        modal.classList.add('opacity-0');
        if (card) {
            card.classList.remove('scale-100');
            card.classList.add('scale-95');
        }

        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            currentConfirmCallback = null;
        }, 200);
    };

    document.addEventListener('DOMContentLoaded', () => {
        const cancelBtn = document.getElementById('global-delete-modal-cancel');
        const confirmBtn = document.getElementById('global-delete-modal-confirm');
        const modal = document.getElementById('global-delete-modal');

        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeConfirmDeleteModal);
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                if (typeof currentConfirmCallback === 'function') {
                    currentConfirmCallback();
                }
                closeConfirmDeleteModal();
            });
        }

        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeConfirmDeleteModal();
                }
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('global-delete-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeConfirmDeleteModal();
                }
            }
        });
    });
})();
</script>
