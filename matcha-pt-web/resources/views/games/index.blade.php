@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#A8E63A]/20 border border-[#063B00]/25 text-[#050608] text-xs font-semibold shadow-xs mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-[#063B00]"></span>
                Jadwal & Komunitas Mabar
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#050608] tracking-tight">
                Jadwal Mabar & Turnamen
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Temukan sesi mabar aktif, pantau sesi yang kamu ikuti, atau kelola jadwal turnamenmu.</p>
        </div>

        @auth
            @if(Auth::user()->is_host && !Auth::user()->isAdmin())
                <a href="{{ route('games.schedule') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01] shrink-0">
                    <i class="fa-solid fa-plus text-[#A8E63A] text-xs"></i> <span>Buat Sesi Mabar Baru</span>
                </a>
            @elseif(Auth::user()->role !== 'venue_owner' && !Auth::user()->isAdmin())
                <a href="{{ route('player.profile', ['notice' => 'host_required']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#EBF8D8] border border-[#063B00]/25 hover:bg-[#A8E63A]/30 text-[#063B00] font-bold text-xs shadow-2xs transition-all hover:scale-[1.01] shrink-0" title="Aktifkan Mode Host untuk Membuat Sesi Mabar">
                    <i class="fa-solid fa-bolt text-[11px]"></i> <span>Jadi Host untuk Buat Mabar</span>
                </a>
            @endif
        @endauth
    </div>

    <!-- 1. Primary Filter Tabs (Semua Sesi / Sesi di Venue Saya / Mabar Saya / Dikelola Saya) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-semibold py-1">
            <!-- Tab 1: Semua Sesi (Eksplorasi) -->
            <a href="{{ route('games.index', ['tab' => 'all', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}" 
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-earth-americas text-xs {{ ($activeTab ?? 'all') === 'all' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i>
                <span>Semua Sesi</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? 'all') === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $countAll ?? $games->total() }}
                </span>
            </a>

            @auth
                <!-- Tab Khusus Venue Owner: Sesi di Venue Saya -->
                @if(Auth::user()->role === 'venue_owner' || count($ownedVenueIds ?? []) > 0 || ($countVenue ?? 0) > 0)
                    <a href="{{ route('games.index', ['tab' => 'venue', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}" 
                       class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? '') === 'venue' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                        <i class="fa-solid fa-location-dot text-xs {{ ($activeTab ?? '') === 'venue' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i>
                        <span>Sesi di Venue Saya</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'venue' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                            {{ $countVenue ?? 0 }}
                        </span>
                    </a>
                @endif

                <!-- Tab 2: Mabar yang Saya Ikuti (Player Scope) -->
                @if(Auth::user()->role !== 'venue_owner')
                    <a href="{{ route('games.index', ['tab' => 'joined', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}" 
                       class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? '') === 'joined' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                        <i class="fa-solid fa-circle-check text-xs {{ ($activeTab ?? '') === 'joined' ? 'text-[#A8E63A]' : 'text-emerald-500' }}"></i>
                        <span>Mabar Saya / Diikuti</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'joined' ? 'bg-[#A8E63A] text-[#063B00]' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                            {{ $countJoined ?? 0 }}
                        </span>
                    </a>
                @endif

                <!-- Tab 3: Dikelola Saya (Host Scope) -->
                @if(Auth::user()->is_host || ($countHosted ?? 0) > 0)
                    <a href="{{ route('games.index', ['tab' => 'hosted', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}" 
                       class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? '') === 'hosted' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                        <i class="fa-solid fa-crown text-xs {{ ($activeTab ?? '') === 'hosted' ? 'text-[#A8E63A]' : 'text-amber-500' }}"></i>
                        <span>Dikelola Saya (Host)</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'hosted' ? 'bg-amber-400 text-amber-950' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                            {{ $countHosted ?? 0 }}
                        </span>
                    </a>
                @endif
            @endauth
        </div>

        <!-- Search Bar -->
        <form method="GET" action="{{ route('games.index') }}" class="relative w-full md:w-72 shrink-0">
            <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
            <input type="hidden" name="sport" value="{{ $selectedSport ?? 'all' }}">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari sesi, venue, host..." 
                       class="w-full pl-9 pr-8 py-2 text-xs rounded-xl bg-white border border-slate-200/90 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] shadow-2xs transition-all">
                @if(!empty($search))
                    <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all']) }}" 
                       class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                       title="Hapus pencarian">
                        <i class="fa-solid fa-circle-xmark text-xs"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- 2. Secondary Sub-Filters Bar (Sport Category & Counter) -->
    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 border border-white/90 shadow-2xs">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-0.5">
            <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => 'all', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                Semua Cabang
            </a>
            <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => 'tennis', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🎾 Tennis
            </a>
            <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => 'padel', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'padel' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🏓 Padel
            </a>
        </div>

        <div class="text-xs text-slate-500 flex items-center gap-2">
            @if(!empty($search))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold">
                    <i class="fa-solid fa-magnifying-glass text-[9px]"></i> "{{ $search }}"
                </span>
            @endif
            <span id="games-result-count">
                Menampilkan <strong class="text-[#050608]">{{ $games->total() }}</strong> sesi
                @if(($activeTab ?? 'all') === 'joined')
                    <span>yang kamu ikuti</span>
                @elseif(($activeTab ?? 'all') === 'hosted')
                    <span>yang kamu kelola</span>
                @elseif(($activeTab ?? 'all') === 'venue')
                    <span>di venue milikmu</span>
                @endif
            </span>
            @if(Auth::check() && Auth::user()->role === 'admin' && $games->count() > 0)
                {{-- Normal: "Pilih" button --}}
                <button type="button" id="btn-enter-select-mode"
                    class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition-colors border border-slate-200 shadow-xs cursor-pointer select-none">
                    Pilih
                </button>

                {{-- Selection mode: compact inline toolbar (hidden by default) --}}
                <div id="bulk-selection-container" class="hidden items-center gap-2">
                    <label class="flex items-center gap-1.5 cursor-pointer select-none whitespace-nowrap">
                        <input type="checkbox" id="bulk-select-all"
                            class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer">
                        <span id="bulk-select-label" class="text-xs font-semibold text-slate-700">
                            Pilih semua
                        </span>
                    </label>
                    <button type="button" id="btn-cancel-select"
                        class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg border border-slate-200 shadow-xs transition-all cursor-pointer whitespace-nowrap">
                        Batal
                    </button>
                    <button type="button" id="btn-submit-bulk" disabled
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg shadow-xs transition-all disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                        style="background-color:#dc2626;color:white;border:1px solid #b91c1c;">
                        Hapus <span id="bulk-count-display">0</span> sesi
                    </button>
                    <form id="bulk-delete-form" action="{{ route('games.bulkDestroy') }}" method="POST" class="hidden">
                        @csrf
                        @method('DELETE')
                        <div id="bulk-delete-inputs"></div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2.5">
            <i class="fa-solid fa-circle-check text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- 3. Grid of Games or Empty States -->
    @if($games->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($games as $game)
                <div class="bulk-item-wrapper relative h-full">
                    @if(Auth::check() && Auth::user()->role === 'admin')
                        <label class="bulk-item-checkbox-container absolute top-3 right-3 z-30 items-center justify-center bg-white/95 backdrop-blur-sm border border-slate-200 shadow-md rounded-lg p-1.5 cursor-pointer hover:bg-slate-50 transition-colors" style="display:none">
                            <input type="checkbox" value="{{ $game['id'] }}" class="bulk-item-checkbox w-4.5 h-4.5 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer shadow-xs">
                        </label>
                    @endif
                    <div class="bulk-item-card flex-1 h-full transition-all duration-200 rounded-2xl">
                        <x-game-card :game="$game" />
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination Component -->
        <x-pagination :paginator="$games" />
    @else
        <!-- Rich Clean Empty State -->
        <div class="glass-card rounded-3xl p-8 sm:p-12 text-center max-w-xl mx-auto border border-white/90 space-y-4 shadow-sm">
            <div class="w-16 h-16 rounded-3xl bg-[#EBF8D8] border border-[#063B00]/20 flex items-center justify-center text-[#063B00] text-2xl mx-auto shadow-xs">
                @if(!empty($search))
                    <i class="fa-solid fa-magnifying-glass text-slate-600"></i>
                @elseif(($activeTab ?? 'all') === 'joined')
                    <i class="fa-solid fa-calendar-xmark"></i>
                @elseif(($activeTab ?? 'all') === 'hosted')
                    <i class="fa-solid fa-crown text-amber-600"></i>
                @elseif(($activeTab ?? 'all') === 'venue')
                    <i class="fa-solid fa-location-dot text-sky-600"></i>
                @else
                    <i class="fa-solid fa-magnifying-glass"></i>
                @endif
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base sm:text-lg font-bold text-slate-900">
                    @if(!empty($search))
                        Tidak Ditemukan Hasil Pencarian
                    @elseif(($activeTab ?? 'all') === 'joined')
                        Belum Ada Sesi yang Kamu Ikuti
                    @elseif(($activeTab ?? 'all') === 'hosted')
                        Belum Ada Sesi yang Kamu Kelola
                    @elseif(($activeTab ?? 'all') === 'venue')
                        Belum Ada Sesi di Venue Milikmu
                    @else
                        Tidak Ada Sesi Mabar Ditemukan
                    @endif
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                    @if(!empty($search))
                        Tidak ada sesi mabar yang cocok dengan kata kunci "<strong>{{ $search }}</strong>". Coba gunakan kata kunci lain atau reset pencarian.
                    @elseif(($activeTab ?? 'all') === 'joined')
                        Kamu belum terdaftar di jadwal mabar manapun. Jelajahi sesi terbuka dan gabung slot sekarang!
                    @elseif(($activeTab ?? 'all') === 'hosted')
                        Kamu belum membuat sesi mabar. Buat sesi mabar barumu untuk mengundang teman dan komunitas!
                    @elseif(($activeTab ?? 'all') === 'venue')
                        Belum ada sesi mabar komunitas yang dijadwalkan di venue milikmu saat ini.
                    @else
                        Tidak ada sesi pertandingan yang sesuai dengan kategori atau filter yang dipilih.
                    @endif
                </p>
            </div>

            <div class="pt-2">
                @if(!empty($search))
                    <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-[#A8E63A]"></i> <span>Reset Pencarian</span>
                    </a>
                @elseif(($activeTab ?? 'all') === 'joined')
                    <a href="{{ route('games.index', ['tab' => 'all']) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-earth-americas text-[#A8E63A]"></i> <span>Jelajahi Semua Sesi</span>
                    </a>
                @elseif(($activeTab ?? 'all') === 'hosted')
                    <a href="{{ route('games.schedule') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-plus text-[#A8E63A]"></i> <span>Buat Sesi Mabar</span>
                    </a>
                @elseif(($activeTab ?? 'all') === 'venue')
                    <a href="{{ route('venues.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-building text-[#A8E63A]"></i> <span>Kelola Venue & Lapangan</span>
                    </a>
                @else
                    <a href="{{ route('games.index', ['tab' => 'all', 'sport' => 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-slate-400"></i> <span>Reset Filter</span>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>

<x-join-modal />
@endsection

@push('styles')
<style>
.bulk-item-checkbox-container { display: none !important; }
.bulk-item-wrapper.selection-mode-active .bulk-item-checkbox-container { display: flex !important; }
#bulk-selection-container.show-toolbar { display: flex !important; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnEnterSelect  = document.getElementById('btn-enter-select-mode');
    const btnCancelSelect = document.getElementById('btn-cancel-select');
    const btnSubmitBulk   = document.getElementById('btn-submit-bulk');
    const bulkContainer   = document.getElementById('bulk-selection-container');
    const bulkSelectAll   = document.getElementById('bulk-select-all');
    const bulkSelectLabel = document.getElementById('bulk-select-label');
    const bulkForm        = document.getElementById('bulk-delete-form');
    const bulkInputsCont  = document.getElementById('bulk-delete-inputs');
    const resultCount     = document.getElementById('games-result-count');

    if (!btnEnterSelect || !bulkContainer) return;

    let selectMode = false;

    function getCheckboxes() { return document.querySelectorAll('.bulk-item-checkbox'); }
    function getChecked()    { return document.querySelectorAll('.bulk-item-checkbox:checked'); }

    function updateUI() {
        const checkboxes   = getCheckboxes();
        const checkedBoxes = getChecked();
        const count        = checkedBoxes.length;
        const pageTotal    = checkboxes.length;

        // Show/hide checkbox overlays via direct inline style
        document.querySelectorAll('.bulk-item-checkbox-container').forEach(el => {
            el.style.display = selectMode ? 'flex' : 'none';
        });

        // Card highlight based on checked state
        checkboxes.forEach(cb => {
            const wrapper = cb.closest('.bulk-item-wrapper');
            const card    = wrapper ? wrapper.querySelector('.bulk-item-card') : null;
            if (!selectMode) { cb.checked = false; }
            if (card) {
                if (selectMode && cb.checked) {
                    card.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50/20');
                } else {
                    card.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50/20');
                }
            }
        });

        // Hide result count when toolbar is active
        if (resultCount) resultCount.style.display = selectMode ? 'none' : '';

        if (selectMode) {
            btnEnterSelect.style.display = 'none';
            bulkContainer.style.display  = 'flex';
        } else {
            btnEnterSelect.style.display = '';
            bulkContainer.style.display  = 'none';
            if (bulkSelectAll)   { bulkSelectAll.checked = false; bulkSelectAll.indeterminate = false; }
            if (bulkSelectLabel) bulkSelectLabel.textContent = 'Pilih semua';
            if (btnSubmitBulk)   { btnSubmitBulk.disabled = true; btnSubmitBulk.innerHTML = 'Hapus 0 sesi'; }
            return;
        }

        if (!bulkSelectAll || !bulkSelectLabel || !btnSubmitBulk) return;

        if (count === 0) {
            bulkSelectAll.checked = false; bulkSelectAll.indeterminate = false;
            bulkSelectLabel.textContent = 'Pilih semua';
            btnSubmitBulk.disabled = true; btnSubmitBulk.innerHTML = 'Hapus 0 sesi';
        } else if (count === pageTotal && pageTotal > 0) {
            bulkSelectAll.checked = true; bulkSelectAll.indeterminate = false;
            bulkSelectLabel.innerHTML = `Semua <strong>${count}</strong> terpilih`;
            btnSubmitBulk.disabled = false; btnSubmitBulk.innerHTML = `Hapus ${count} sesi`;
        } else {
            bulkSelectAll.checked = false; bulkSelectAll.indeterminate = true;
            bulkSelectLabel.innerHTML = `<strong>${count}</strong> dipilih`;
            btnSubmitBulk.disabled = false; btnSubmitBulk.innerHTML = `Hapus ${count} sesi`;
        }
    }

    btnEnterSelect.addEventListener('click',  () => { selectMode = true;  updateUI(); });
    btnCancelSelect.addEventListener('click', () => { selectMode = false; updateUI(); });

    if (bulkSelectAll) {
        bulkSelectAll.addEventListener('change', (e) => {
            getCheckboxes().forEach(cb => { cb.checked = e.target.checked; });
            updateUI();
        });
    }

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('bulk-item-checkbox')) updateUI();
    });

    if (btnSubmitBulk && bulkForm && bulkInputsCont) {
        btnSubmitBulk.addEventListener('click', () => {
            const checkedBoxes = getChecked();
            const count = checkedBoxes.length;
            if (count === 0) return;

            window.showConfirmDeleteModal({
                title: `Hapus ${count} Sesi Mabar?`,
                message: `Data ${count} sesi mabar yang dipilih akan dihapus secara permanen dari sistem. Tindakan ini tidak dapat dibatalkan.`,
                confirmText: `Ya, Hapus ${count} Sesi`,
                onConfirm: () => {
                    bulkInputsCont.innerHTML = '';
                    checkedBoxes.forEach(cb => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_ids[]';
                        input.value = cb.value;
                        bulkInputsCont.appendChild(input);
                    });
                    bulkForm.submit();
                }
            });
        });
    }
});
</script>
@endpush
