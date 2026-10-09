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

    <!-- 1. Primary Filter Tabs (Semua Sesi / Sesi di Venue Saya / Mabar Saya / Dikelola Saya) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-semibold py-1">
            <!-- Tab 1: Semua Sesi (Eksplorasi) -->
            <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'all'])) }}" 
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
                    <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'venue'])) }}" 
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
                    <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'joined'])) }}" 
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
                    <a href="{{ route('games.index', array_merge(request()->except(['page']), ['tab' => 'hosted'])) }}" 
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

        <!-- Search Bar & Filter Trigger Row -->
        <div class="flex items-center gap-2 w-full md:w-auto">
            <form method="GET" action="{{ route('games.index') }}" class="relative w-full md:w-72 shrink-0">
                <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
                <input type="hidden" name="sport" value="{{ $selectedSport ?? 'all' }}">
                <input type="hidden" name="status" value="{{ $selectedStatus ?? 'all' }}">
                <input type="hidden" name="slots" value="{{ $selectedSlots ?? 'all' }}">
                <input type="hidden" name="time" value="{{ $selectedTime ?? 'all' }}">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari sesi, venue, host..." 
                           class="w-full pl-9 pr-8 py-2 text-xs rounded-xl bg-white border border-slate-200/90 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] shadow-2xs transition-all">
                    @if(!empty($search))
                        <a href="{{ route('games.index', array_merge(request()->except(['page', 'q', 'search']), [])) }}" 
                           class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                           title="Hapus pencarian">
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </a>
                    @endif
                </div>
            </form>

            <!-- Filter Modal Trigger Button -->
            <button type="button" onclick="openFilterModal()" 
                    class="relative px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 border transition-all shrink-0 {{ ($hasActiveFilter ?? false) ? 'bg-[#063B00] text-white border-[#063B00] shadow-xs' : 'glass-card text-slate-700 border-slate-200/90 hover:bg-white hover:text-[#050608]' }}"
                    title="Buka Filter Sesi Mabar">
                <i class="fa-solid fa-sliders text-xs {{ ($hasActiveFilter ?? false) ? 'text-[#A8E63A]' : 'text-slate-500' }}"></i>
                <span class="hidden sm:inline">Filter</span>
                @if($hasActiveFilter ?? false)
                    <span class="w-2 h-2 rounded-full bg-[#A8E63A] animate-pulse"></span>
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

<!-- Filter Modal / Drawer -->
<div id="filter-modal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div id="filter-backdrop" onclick="closeFilterModal()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity duration-300 opacity-0"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20 flex items-center justify-center">
        <div id="filter-dialog" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 w-full max-w-lg border border-slate-100">
            <form method="GET" action="{{ route('games.index') }}" id="filter-form">
                <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
                <input type="hidden" name="sport" value="{{ $selectedSport ?? 'all' }}">
                <input type="hidden" name="q" value="{{ $search ?? '' }}">

                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-[#EBF8D8] text-[#063B00] flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900" id="modal-title">Filter Sesi Mabar</h3>
                            <p class="text-[11px] text-slate-500">Saring jadwal pertandingan sesuai kebutuhanmu</p>
                        </div>
                    </div>
                    <button type="button" onclick="resetFilterForm()" class="text-xs font-bold text-rose-600 hover:text-rose-800 transition-colors">
                        Atur Ulang
                    </button>
                </div>

                <!-- Body Options -->
                <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                    <!-- 1. Status Pertandingan -->
                    <div class="space-y-2.5">
                        <label class="block text-xs font-bold text-slate-900 uppercase tracking-wider">Status Pertandingan</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="status" value="all" class="peer sr-only" {{ ($selectedStatus ?? 'all') === 'all' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-[#063B00] peer-checked:text-white peer-checked:border-[#063B00] peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-border-all text-[11px]"></i>
                                    <span>Semua Status</span>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="radio" name="status" value="upcoming" class="peer sr-only" {{ ($selectedStatus ?? '') === 'upcoming' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-clock text-[11px]"></i>
                                    <span>Belum Mulai</span>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="radio" name="status" value="live" class="peer sr-only" {{ ($selectedStatus ?? '') === 'live' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-rose-600 peer-checked:text-white peer-checked:border-rose-600 peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-circle-dot text-[11px] animate-pulse"></i>
                                    <span>Sedang Main (LIVE)</span>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="radio" name="status" value="finished" class="peer sr-only" {{ ($selectedStatus ?? '') === 'finished' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-slate-700 peer-checked:text-white peer-checked:border-slate-700 peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-flag-checkered text-[11px]"></i>
                                    <span>Selesai</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 2. Ketersediaan Kuota Slot -->
                    <div class="space-y-2.5">
                        <label class="block text-xs font-bold text-slate-900 uppercase tracking-wider">Ketersediaan Kuota Slot</label>
                        <label class="flex items-center justify-between p-3.5 rounded-2xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-all">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-emerald-600">
                                    <i class="fa-solid fa-user-plus text-xs"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Hanya yang ada slot kosong</div>
                                    <div class="text-[11px] text-slate-500">Sembunyikan sesi mabar yang sudah penuh</div>
                                </div>
                            </div>
                            <input type="checkbox" name="slots" value="available" id="filter-slots-checkbox" class="w-4 h-4 rounded text-[#063B00] focus:ring-[#063B00] border-slate-300" {{ ($selectedSlots ?? '') === 'available' ? 'checked' : '' }}>
                        </label>
                    </div>

                    <!-- 3. Waktu Pertandingan -->
                    <div class="space-y-2.5">
                        <label class="block text-xs font-bold text-slate-900 uppercase tracking-wider">Waktu Pertandingan</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="time" value="all" class="peer sr-only" {{ ($selectedTime ?? 'all') === 'all' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-[#063B00] peer-checked:text-white peer-checked:border-[#063B00] peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-calendar text-[11px]"></i>
                                    <span>Semua Tanggal</span>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="radio" name="time" value="today" class="peer sr-only" {{ ($selectedTime ?? '') === 'today' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-[#063B00] peer-checked:text-white peer-checked:border-[#063B00] peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-calendar-day text-[11px]"></i>
                                    <span>Hari Ini</span>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="radio" name="time" value="tomorrow" class="peer sr-only" {{ ($selectedTime ?? '') === 'tomorrow' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-[#063B00] peer-checked:text-white peer-checked:border-[#063B00] peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-calendar-week text-[11px]"></i>
                                    <span>Besok</span>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="radio" name="time" value="this_week" class="peer sr-only" {{ ($selectedTime ?? '') === 'this_week' ? 'checked' : '' }}>
                                <div class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 peer-checked:bg-[#063B00] peer-checked:text-white peer-checked:border-[#063B00] peer-checked:shadow-xs transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-calendar-days text-[11px]"></i>
                                    <span>7 Hari Ke Depan</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5 rounded-b-3xl">
                    <button type="button" onclick="closeFilterModal()" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-800 bg-white border border-slate-200 hover:bg-slate-100 transition-all">
                        Tutup
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#063B00] hover:bg-[#042a00] shadow-md transition-all hover:scale-[1.01]">
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
    function openFilterModal() {
        const modal = document.getElementById('filter-modal');
        const backdrop = document.getElementById('filter-backdrop');
        const dialog = document.getElementById('filter-dialog');
        if (!modal) return;

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
        }, 250);
    }

    function resetFilterForm() {
        // Select 'all' status
        const statusAll = document.querySelector('input[name="status"][value="all"]');
        if (statusAll) statusAll.checked = true;

        // Uncheck slots
        const slotsCheckbox = document.getElementById('filter-slots-checkbox');
        if (slotsCheckbox) slotsCheckbox.checked = false;

        // Select 'all' time
        const timeAll = document.querySelector('input[name="time"][value="all"]');
        if (timeAll) timeAll.checked = true;
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterModal();
        }
    });
</script>
@endpush
@endsection
