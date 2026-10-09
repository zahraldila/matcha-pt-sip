@extends('layouts.app')

@section('content')
<style>
    /* Filter Chip Styling */
    .filter-chip {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 9px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        border: 1.5px solid #e2e8f0;
        background-color: #f8fafc;
        color: #334155;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        user-select: none;
        text-align: center;
    }
    .filter-chip:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
        color: #0f172a;
    }
    .filter-chip.active {
        background-color: #063B00 !important;
        color: #ffffff !important;
        border-color: #063B00 !important;
        box-shadow: 0 2px 8px rgba(6, 59, 0, 0.18);
    }
    .filter-chip.active i {
        color: #A8E63A !important;
    }


    /* iOS/Mobile Style Toggle Switch */
    .switch-track {
        width: 44px;
        height: 24px;
        background-color: #e2e8f0;
        border-radius: 9999px;
        position: relative;
        cursor: pointer;
        transition: background-color 0.2s ease;
        flex-shrink: 0;
    }
    .switch-track.active {
        background-color: #063B00;
    }
    .switch-thumb {
        width: 18px;
        height: 18px;
        background-color: #ffffff;
        border-radius: 9999px;
        position: absolute;
        top: 3px;
        left: 3px;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }
    .switch-track.active .switch-thumb {
        transform: translateX(20px);
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
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
            @if(Auth::user()->is_host)
                <a href="{{ route('games.schedule') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01] shrink-0">
                    <i class="fa-solid fa-plus text-[#A8E63A] text-xs"></i> <span>Buat Sesi Mabar Baru</span>
                </a>
            @elseif(Auth::user()->role !== 'venue_owner')
                <a href="{{ route('player.profile', ['notice' => 'host_required']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#EBF8D8] border border-[#063B00]/25 hover:bg-[#A8E63A]/30 text-[#063B00] font-bold text-xs shadow-2xs transition-all hover:scale-[1.01] shrink-0" title="Aktifkan Mode Host untuk Membuat Sesi Mabar">
                    <i class="fa-solid fa-bolt text-[11px]"></i> <span>Jadi Host untuk Buat Mabar</span>
                </a>
            @endif
        @endauth
    </div>

    <!-- 1. Primary Filter Tabs (Kiri) & Search / Filter Bar (Kanan Presisi) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Left Side: Scope Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-semibold py-1">
            <!-- Tab 1: Semua Sesi (Eksplorasi) -->
            <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'all'])) }}" 
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap shrink-0 {{ ($activeTab ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-earth-americas text-xs {{ ($activeTab ?? 'all') === 'all' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i>
                <span>Semua Sesi</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? 'all') === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $countAll ?? $games->total() }}
                </span>
            </a>

            @auth
                <!-- Tab Khusus Venue Owner: Sesi di Venue Saya -->
                @if(Auth::user()->role === 'venue_owner' || count($ownedVenueIds ?? []) > 0 || ($countVenue ?? 0) > 0)
                    <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'venue'])) }}" 
                       class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap shrink-0 {{ ($activeTab ?? '') === 'venue' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                        <i class="fa-solid fa-location-dot text-xs {{ ($activeTab ?? '') === 'venue' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i>
                        <span>Sesi di Venue Saya</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'venue' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                            {{ $countVenue ?? 0 }}
                        </span>
                    </a>
                @endif

                <!-- Tab 2: Mabar yang Saya Ikuti (Player Scope) -->
                @if(Auth::user()->role !== 'venue_owner')
                    <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'joined'])) }}" 
                       class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap shrink-0 {{ ($activeTab ?? '') === 'joined' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                        <i class="fa-solid fa-circle-check text-xs {{ ($activeTab ?? '') === 'joined' ? 'text-[#A8E63A]' : 'text-emerald-500' }}"></i>
                        <span>Mabar Saya / Diikuti</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'joined' ? 'bg-[#A8E63A] text-[#063B00]' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                            {{ $countJoined ?? 0 }}
                        </span>
                    </a>
                @endif

                <!-- Tab 3: Dikelola Saya (Host Scope) -->
                @if(Auth::user()->is_host || ($countHosted ?? 0) > 0)
                    <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'hosted'])) }}" 
                       class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap shrink-0 {{ ($activeTab ?? '') === 'hosted' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                        <i class="fa-solid fa-crown text-xs {{ ($activeTab ?? '') === 'hosted' ? 'text-[#A8E63A]' : 'text-amber-500' }}"></i>
                        <span>Dikelola Saya (Host)</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'hosted' ? 'bg-amber-400 text-amber-950' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                            {{ $countHosted ?? 0 }}
                        </span>
                    </a>
                @endif
            @endauth
        </div>

        <!-- Right Side: Search Bar & Filter Button (Rata Kanan Presisi Sejajar dengan Card) -->
        <div class="flex items-center gap-2 w-full md:w-80 shrink-0">
            <form method="GET" action="{{ route('games.index') }}" class="relative flex-1 min-w-0">
                <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
                <input type="hidden" name="sport" value="{{ $selectedSport ?? 'all' }}">
                <input type="hidden" name="status" value="{{ $selectedStatus ?? 'all' }}">
                <input type="hidden" name="slots" value="{{ $selectedSlots ?? 'all' }}">
                <input type="hidden" name="time" value="{{ $selectedTime ?? 'all' }}">
                <div class="relative w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari sesi, venue, host..." 
                           class="w-full pl-9 pr-8 py-2 text-xs rounded-xl bg-white border border-slate-200/90 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] shadow-2xs transition-all">
                    @if(!empty($search))
                        <a href="{{ route('games.index', array_merge(request()->except(['page', 'q', 'search']), [])) }}" 
                           class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                           title="Hapus pencarian">
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </a>
                    @endif
                </div>
            </form>

            <!-- Filter Trigger Button -->
            <button type="button" onclick="openFilterModal()" 
                    class="relative px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 border transition-all shrink-0 cursor-pointer {{ ($hasActiveFilter ?? false) ? 'bg-[#063B00] text-white border-[#063B00] shadow-xs' : 'glass-card text-slate-700 border-slate-200/90 hover:bg-white hover:text-[#050608]' }}"
                    title="Buka Filter Sesi Mabar">
                <i class="fa-solid fa-sliders text-xs {{ ($hasActiveFilter ?? false) ? 'text-[#A8E63A]' : 'text-slate-500' }}"></i>
                <span>Filter</span>
                @if($hasActiveFilter ?? false)
                    <span class="w-2 h-2 rounded-full bg-[#A8E63A]"></span>
                @endif
            </button>
        </div>
    </div>

    <!-- 2. Secondary Sub-Filters Bar (Sport Category & Counter) -->
    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 border border-white/90 shadow-2xs">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-0.5">
            <a href="{{ route('games.index', array_merge(request()->except(['page']), ['sport' => 'all'])) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                Semua Cabang
            </a>
            <a href="{{ route('games.index', array_merge(request()->except(['page']), ['sport' => 'tennis'])) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                <i class="fa-solid fa-table-tennis-paddle-ball text-[11px] mr-1"></i> Tennis
            </a>
            <a href="{{ route('games.index', array_merge(request()->except(['page']), ['sport' => 'padel'])) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'padel' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                <i class="fa-solid fa-baseball text-[11px] mr-1"></i> Padel
            </a>
        </div>

        <div class="text-xs text-slate-500 flex items-center gap-2">
            @if(!empty($search))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold">
                    <i class="fa-solid fa-magnifying-glass text-[9px]"></i> "{{ $search }}"
                </span>
            @endif
            <span>
                Menampilkan <strong class="text-[#050608]">{{ $games->total() }}</strong> sesi
                @if(($activeTab ?? 'all') === 'joined')
                    <span>yang kamu ikuti</span>
                @elseif(($activeTab ?? 'all') === 'hosted')
                    <span>yang kamu kelola</span>
                @elseif(($activeTab ?? 'all') === 'venue')
                    <span>di venue milikmu</span>
                @endif
            </span>
        </div>
    </div>

    <!-- Active Filter Tags Bar (if any custom filters applied) -->
    @if($hasActiveFilter ?? false)
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 text-xs">
            <span class="text-slate-400 font-semibold text-[11px] uppercase tracking-wider shrink-0 mr-1">Filter Aktif:</span>
            
            @if(($selectedStatus ?? 'all') !== 'all')
                <a href="{{ route('games.index', array_merge(request()->except(['page', 'status']), ['status' => 'all'])) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#86EFAC] text-[#063B00] font-bold text-xs hover:bg-rose-50 hover:border-rose-300 hover:text-rose-700 transition-all group shrink-0">
                    <span>Status: {{ $selectedStatus === 'upcoming' ? 'Belum Mulai' : ($selectedStatus === 'live' ? 'Sedang Main (LIVE)' : 'Selesai') }}</span>
                    <i class="fa-solid fa-xmark text-[10px] group-hover:scale-125 transition-transform"></i>
                </a>
            @endif

            @if(($selectedSlots ?? 'all') === 'available')
                <a href="{{ route('games.index', array_merge(request()->except(['page', 'slots']), ['slots' => 'all'])) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#86EFAC] text-[#063B00] font-bold text-xs hover:bg-rose-50 hover:border-rose-300 hover:text-rose-700 transition-all group shrink-0">
                    <span>Hanya Slot Tersedia</span>
                    <i class="fa-solid fa-xmark text-[10px] group-hover:scale-125 transition-transform"></i>
                </a>
            @endif

            @if(($selectedTime ?? 'all') !== 'all')
                <a href="{{ route('games.index', array_merge(request()->except(['page', 'time']), ['time' => 'all'])) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#86EFAC] text-[#063B00] font-bold text-xs hover:bg-rose-50 hover:border-rose-300 hover:text-rose-700 transition-all group shrink-0">
                    <span>Waktu: {{ $selectedTime === 'today' ? 'Hari Ini' : ($selectedTime === 'tomorrow' ? 'Besok' : '7 Hari Ke Depan') }}</span>
                    <i class="fa-solid fa-xmark text-[10px] group-hover:scale-125 transition-transform"></i>
                </a>
            @endif

            <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}" 
               class="text-rose-600 hover:text-rose-800 font-bold text-xs ml-2 hover:underline shrink-0">
                Hapus Semua
            </a>
        </div>
    @endif

    <!-- 3. Grid of Games or Empty States -->
    @if($games->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($games as $game)
                <x-game-card :game="$game" />
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
                @elseif($hasActiveFilter ?? false)
                    <i class="fa-solid fa-filter-circle-xmark text-slate-600"></i>
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
                    @elseif($hasActiveFilter ?? false)
                        Tidak Ada Sesi yang Sesuai Filter
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
                    @elseif($hasActiveFilter ?? false)
                        Coba sesuaikan atau atur ulang kombinasi filter status, slot, atau tanggal yang kamu pilih.
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
                @if(!empty($search) || ($hasActiveFilter ?? false))
                    <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-[#A8E63A]"></i> <span>Atur Ulang Semua Filter</span>
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

<!-- Compact & Responsive Filter Modal Dialog -->
<div id="filter-modal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div id="filter-backdrop" onclick="closeFilterModal()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity duration-200 opacity-0"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto p-4 flex items-center justify-center min-h-screen">
        <div id="filter-dialog" class="relative transform overflow-hidden rounded-2xl sm:rounded-3xl bg-white text-left shadow-2xl transition-all duration-200 opacity-0 scale-95 w-full max-w-md border border-slate-100 my-auto">
            <form method="GET" action="{{ route('games.index') }}" id="filter-form">
                <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
                <input type="hidden" name="sport" value="{{ $selectedSport ?? 'all' }}">
                <input type="hidden" name="q" value="{{ $search ?? '' }}">

                <!-- Hidden inputs for radio selections -->
                <input type="hidden" name="status" id="filter-status-input" value="{{ $selectedStatus ?? 'all' }}">
                <input type="hidden" name="time" id="filter-time-input" value="{{ $selectedTime ?? 'all' }}">
                <input type="checkbox" name="slots" value="available" id="filter-slots-input" class="hidden" {{ ($selectedSlots ?? '') === 'available' ? 'checked' : '' }}>

                <!-- Header (Compact) -->
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-[#EBF8D8] text-[#063B00] flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900" id="modal-title">Filter Sesi Mabar</h3>
                            <p class="text-[10.5px] text-slate-500">Sesuaikan pencarian pertandingan</p>
                        </div>
                    </div>
                    <!-- Dynamic Reset button -->
                    <button type="button" id="btn-reset-filter" onclick="resetFilterForm()" 
                            class="text-xs font-bold text-rose-600 hover:text-rose-800 transition-colors hidden cursor-pointer">
                        Atur Ulang
                    </button>
                </div>

                <!-- Body Options (Compact & Clean) -->
                <div class="p-4 sm:p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <!-- 1. Status Pertandingan -->
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-extrabold text-slate-700 uppercase tracking-wider">Status Pertandingan</label>
                        <div class="grid grid-cols-2 gap-2">
                            <!-- Semua Status -->
                            <div class="filter-chip {{ ($selectedStatus ?? 'all') === 'all' ? 'active' : '' }}" 
                                 data-group="status" data-val="all" 
                                 onclick="setRadioFilter('status', 'all')">
                                <i class="fa-solid fa-border-all text-[11px]"></i>
                                <span>Semua Status</span>
                            </div>

                            <!-- Belum Mulai -->
                            <div class="filter-chip {{ ($selectedStatus ?? '') === 'upcoming' ? 'active' : '' }}" 
                                 data-group="status" data-val="upcoming" 
                                 onclick="setRadioFilter('status', 'upcoming')">
                                <i class="fa-solid fa-clock text-[11px]"></i>
                                <span>Belum Mulai</span>
                            </div>

                            <!-- Sedang Main (LIVE) -->
                            <div class="filter-chip {{ ($selectedStatus ?? '') === 'live' ? 'active' : '' }}" 
                                 data-group="status" data-val="live" 
                                 onclick="setRadioFilter('status', 'live')">
                                <i class="fa-solid fa-circle-dot text-[11px]"></i>
                                <span>Sedang Main</span>
                            </div>

                            <!-- Selesai -->
                            <div class="filter-chip {{ ($selectedStatus ?? '') === 'finished' ? 'active' : '' }}" 
                                 data-group="status" data-val="finished" 
                                 onclick="setRadioFilter('status', 'finished')">
                                <i class="fa-solid fa-flag-checkered text-[11px]"></i>
                                <span>Selesai</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Ketersediaan Kuota Slot (iOS/Mobile Toggle Switch) -->
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-extrabold text-slate-700 uppercase tracking-wider">Ketersediaan Slot</label>
                        <div onclick="toggleSlotsSwitch()" class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-slate-100/80 cursor-pointer transition-all select-none">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-emerald-50 border border-emerald-200/60 flex items-center justify-center text-emerald-700 text-xs shrink-0">
                                    <i class="fa-solid fa-user-plus"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Hanya ada slot kosong</div>
                                    <div class="text-[10.5px] text-slate-400 font-normal">Sembunyikan sesi yang kuotanya sudah penuh</div>
                                </div>
                            </div>
                            <!-- Interactive Switch Track -->
                            <div id="filter-slots-track" class="switch-track {{ ($selectedSlots ?? '') === 'available' ? 'active' : '' }}">
                                <div class="switch-thumb"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Waktu Pertandingan -->
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-extrabold text-slate-700 uppercase tracking-wider">Waktu Pertandingan</label>
                        <div class="grid grid-cols-2 gap-2">
                            <!-- Semua Hari -->
                            <div class="filter-chip {{ ($selectedTime ?? 'all') === 'all' ? 'active' : '' }}" 
                                 data-group="time" data-val="all" 
                                 onclick="setRadioFilter('time', 'all')">
                                <i class="fa-solid fa-calendar text-[11px]"></i>
                                <span>Semua Hari</span>
                            </div>

                            <!-- Hari Ini -->
                            <div class="filter-chip {{ ($selectedTime ?? '') === 'today' ? 'active' : '' }}" 
                                 data-group="time" data-val="today" 
                                 onclick="setRadioFilter('time', 'today')">
                                <i class="fa-solid fa-calendar-day text-[11px]"></i>
                                <span>Hari Ini</span>
                            </div>

                            <!-- Besok -->
                            <div class="filter-chip {{ ($selectedTime ?? '') === 'tomorrow' ? 'active' : '' }}" 
                                 data-group="time" data-val="tomorrow" 
                                 onclick="setRadioFilter('time', 'tomorrow')">
                                <i class="fa-solid fa-calendar-week text-[11px]"></i>
                                <span>Besok</span>
                            </div>

                            <!-- 7 Hari Ke Depan -->
                            <div class="filter-chip {{ ($selectedTime ?? '') === 'this_week' ? 'active' : '' }}" 
                                 data-group="time" data-val="this_week" 
                                 onclick="setRadioFilter('time', 'this_week')">
                                <i class="fa-solid fa-calendar-days text-[11px]"></i>
                                <span>7 Hari Ke Depan</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 rounded-b-2xl sm:rounded-b-3xl">
                    <button type="button" onclick="closeFilterModal()" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-800 bg-white border border-slate-200 hover:bg-slate-100 transition-all cursor-pointer">
                        Tutup
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#063B00] hover:bg-[#042a00] shadow-xs transition-all hover:scale-[1.01] cursor-pointer">
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-join-modal />

@push('scripts')
<script>
    function setRadioFilter(groupName, value) {
        const input = document.getElementById(`filter-${groupName}-input`);
        if (input) {
            input.value = value;
        }

        // Update active class on chips
        document.querySelectorAll(`.filter-chip[data-group="${groupName}"]`).forEach(chip => {
            if (chip.getAttribute('data-val') === value) {
                chip.classList.add('active');
            } else {
                chip.classList.remove('active');
            }
        });

        checkFilterState();
    }

    function toggleSlotsSwitch() {
        const input = document.getElementById('filter-slots-input');
        const track = document.getElementById('filter-slots-track');
        if (!input || !track) return;

        input.checked = !input.checked;
        if (input.checked) {
            track.classList.add('active');
        } else {
            track.classList.remove('active');
        }

        checkFilterState();
    }

    function checkFilterState() {
        const resetBtn = document.getElementById('btn-reset-filter');
        if (!resetBtn) return;

        const statusVal = document.getElementById('filter-status-input')?.value || 'all';
        const slotsChecked = document.getElementById('filter-slots-input')?.checked || false;
        const timeVal = document.getElementById('filter-time-input')?.value || 'all';

        const isFiltered = (statusVal !== 'all') || slotsChecked || (timeVal !== 'all');
        if (isFiltered) {
            resetBtn.classList.remove('hidden');
        } else {
            resetBtn.classList.add('hidden');
        }
    }

    function resetFilterForm() {
        setRadioFilter('status', 'all');
        setRadioFilter('time', 'all');

        const slotsInput = document.getElementById('filter-slots-input');
        const slotsTrack = document.getElementById('filter-slots-track');
        if (slotsInput && slotsTrack) {
            slotsInput.checked = false;
            slotsTrack.classList.remove('active');
        }

        checkFilterState();
    }

    function openFilterModal() {
        const modal = document.getElementById('filter-modal');
        const backdrop = document.getElementById('filter-backdrop');
        const dialog = document.getElementById('filter-dialog');
        if (!modal) return;

        checkFilterState();
        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            dialog.classList.remove('opacity-0', 'scale-95');
            dialog.classList.add('opacity-100', 'scale-100');
        }, 10);
    }

    function closeFilterModal() {
        const modal = document.getElementById('filter-modal');
        const backdrop = document.getElementById('filter-backdrop');
        const dialog = document.getElementById('filter-dialog');
        if (!modal) return;

        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        dialog.classList.remove('opacity-100', 'scale-100');
        dialog.classList.add('opacity-0', 'scale-95');

        setTimeout(() => {
            modal.classList.add('hidden');
        }, 200);
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        checkFilterState();
    });
</script>
@endpush
@endsection
