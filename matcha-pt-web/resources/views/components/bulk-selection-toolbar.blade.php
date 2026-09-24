@props(['itemName', 'totalItems', 'actionUrl'])

<div id="bulk-selection-container" class="hidden mb-4 glass-card p-3 rounded-xl border border-emerald-100 shadow-sm bg-emerald-50/50 transition-all duration-300">
    <form id="bulk-delete-form" action="{{ $actionUrl }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
        <div id="bulk-delete-inputs"></div>
    </form>
    
    <div class="flex items-center gap-1.5">
        <button
            type="button"
            id="btn-cancel-select"
            class="h-9 min-w-[76px] px-4 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg border border-slate-200 shadow-sm transition-all duration-200"
        >
            Batal
        </button>

        <button
            type="button"
            id="btn-submit-bulk"
            disabled
            class="h-9 min-w-[130px] px-4 font-semibold text-sm rounded-lg shadow-sm transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            style="background-color: #dc2626; color: white; border: 1px solid #b91c1c;"
        >
            Hapus <span id="bulk-count-display">0</span> {{ $itemName }}
        </button>
    </div>
</div>

<style>
.bulk-item-checkbox-container { display: none !important; }
.bulk-item-wrapper.selection-mode-active .bulk-item-checkbox-container { display: flex !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemName = '{{ $itemName }}';
    const totalItems = parseInt('{{ $totalItems }}') || 0;
    
    const btnEnterSelect = document.getElementById('btn-enter-select-mode');
    const btnCancelSelect = document.getElementById('btn-cancel-select');
    const btnSubmitBulk = document.getElementById('btn-submit-bulk');
    const bulkContainer = document.getElementById('bulk-selection-container');
    const bulkSelectAll = document.getElementById('bulk-select-all');
    const bulkSelectLabel = document.getElementById('bulk-select-label');
    const bulkForm = document.getElementById('bulk-delete-form');
    const bulkInputsContainer = document.getElementById('bulk-delete-inputs');
    
    if (!btnEnterSelect || !bulkContainer) return;
    
    let selectMode = false;
    
    function updateState() {
        const checkboxes = document.querySelectorAll('.bulk-item-checkbox');
        const checkedBoxes = document.querySelectorAll('.bulk-item-checkbox:checked');
        const count = checkedBoxes.length;
        const pageTotal = checkboxes.length;
        
        checkboxes.forEach(cb => {
            const wrapper = cb.closest('.bulk-item-wrapper');
            if (wrapper) {
                const card = wrapper.querySelector('.bulk-item-card') || wrapper.children[1];
                if (selectMode) {
                    wrapper.classList.add('selection-mode-active');
                    if (card) {
                        if (cb.checked) {
                            card.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50/20');
                            card.classList.remove('border-white/90');
                        } else {
                            card.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50/20');
                            card.classList.add('border-white/90');
                        }
                    }
                } else {
                    wrapper.classList.remove('selection-mode-active');
                    cb.checked = false;
                    if (card) {
                        card.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50/20');
                        card.classList.add('border-white/90');
                    }
                }
            }
        });
        
        if (selectMode) {
            bulkContainer.classList.remove('hidden');
            btnEnterSelect.classList.add('hidden');
            
            if (count === 0) {
                bulkSelectAll.checked = false;
                bulkSelectAll.indeterminate = false;
                bulkSelectLabel.innerText = 'Pilih semua di halaman ini';
                btnSubmitBulk.disabled = true;
                btnSubmitBulk.innerHTML = `Hapus 0 ${itemName}`;
            } else if (count === pageTotal && pageTotal > 0) {
                bulkSelectAll.checked = true;
                bulkSelectAll.indeterminate = false;
                bulkSelectLabel.innerHTML = `Semua <strong>${count}</strong> ${itemName} di halaman ini dipilih`;
                btnSubmitBulk.disabled = false;
                btnSubmitBulk.innerHTML = `Hapus ${count} ${itemName}`;
            } else {
                bulkSelectAll.checked = false;
                bulkSelectAll.indeterminate = true;
                bulkSelectLabel.innerHTML = `<strong>${count}</strong> item dipilih`;
                btnSubmitBulk.disabled = false;
                btnSubmitBulk.innerHTML = `Hapus ${count} ${itemName}`;
            }
        } else {
            bulkContainer.classList.add('hidden');
            btnEnterSelect.classList.remove('hidden');
        }
    }
    
    btnEnterSelect.addEventListener('click', () => {
        selectMode = true;
        updateState();
    });
    
    btnCancelSelect.addEventListener('click', () => {
        selectMode = false;
        updateState();
    });
    
    bulkSelectAll.addEventListener('change', (e) => {
        const checkboxes = document.querySelectorAll('.bulk-item-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = e.target.checked;
        });
        updateState();
    });
    
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('bulk-item-checkbox')) {
            updateState();
        }
    });
    
    btnSubmitBulk.addEventListener('click', () => {
        const checkedBoxes = document.querySelectorAll('.bulk-item-checkbox:checked');
        const count = checkedBoxes.length;
        if (count === 0) return;
        
        const confirmMsg = `Hapus ${count} ${itemName}?\n\nData ${itemName} yang dipilih akan dihapus. Tindakan ini tidak dapat dibatalkan.`;
        if (confirm(confirmMsg)) {
            bulkInputsContainer.innerHTML = '';
            checkedBoxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = cb.value;
                bulkInputsContainer.appendChild(input);
            });
            bulkForm.submit();
        }
    });
});
</script>
