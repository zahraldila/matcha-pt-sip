@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#A8E63A]/20 border border-[#063B00]/25 text-[#050608] text-xs font-semibold shadow-xs mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-[#063B00]"></span>
                Direktori Mitra Lapangan
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#050608] tracking-tight">
                Direktori Venue & Court
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Daftar lokasi lapangan Tennis & Padel dengan informasi fasilitas, jumlah court, dan jam operasional.</p>
        </div>

        @if(Auth::check() && Auth::user()->role === 'venue_owner')
            <a href="{{ route('venues.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01] shrink-0">
                <i class="fa-solid fa-plus text-[#A8E63A] text-xs"></i> <span>Daftarkan Venue Baru</span>
            </a>
        @endif
    </div>

    <!-- 1. Top Controls Bar: Owner Tabs (if owner) & Search Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        @if(Auth::check() && Auth::user()->role === 'venue_owner')
            <!-- Owner Filter Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-semibold py-1">
                <a href="{{ route('venues.index', ['tab' => 'all', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}"
                    class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? 'all') !== 'my_venues' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                    <i class="fa-solid fa-layer-group text-xs {{ ($activeTab ?? 'all') !== 'my_venues' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i> 
                    <span>Semua Venue</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? 'all') !== 'my_venues' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                        {{ $totalVenuesCount ?? $venues->total() }}
                    </span>
                </a>
                <a href="{{ route('venues.index', ['tab' => 'my_venues', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}"
                    class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? 'all') === 'my_venues' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                    <i class="fa-solid fa-crown text-xs {{ ($activeTab ?? 'all') === 'my_venues' ? 'text-[#A8E63A]' : 'text-amber-500' }}"></i> 
                    <span>Venue Saya</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? 'all') === 'my_venues' ? 'bg-amber-400 text-amber-950' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                        {{ $myVenuesCount ?? 0 }}
                    </span>
                </a>
            </div>
        @else
            <div class="text-xs text-slate-500 hidden md:block">
                Temukan arena terbaik untuk bermain <strong class="text-[#063B00]">Tennis</strong> atau <strong class="text-[#063B00]">Padel</strong> di sekitarmu.
            </div>
        @endif

        <!-- Search Bar -->
        <form method="GET" action="{{ route('venues.index') }}" class="relative w-full md:w-80 shrink-0">
            <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
            <input type="hidden" name="sport" value="{{ $selectedSport ?? 'all' }}">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari venue, kota, fasilitas..." 
                       class="w-full pl-9 pr-8 py-2 text-xs rounded-xl bg-white border border-slate-200/90 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] shadow-2xs transition-all">
                @if(!empty($search))
                    <a href="{{ route('venues.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all']) }}" 
                       class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                       title="Hapus pencarian">
                        <i class="fa-solid fa-circle-xmark text-xs"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- 2. Sport Category Sub-Filters & Result Counter -->
    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 border border-white/90 shadow-2xs">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-0.5">
            <a href="{{ route('venues.index', ['tab' => $activeTab ?? 'all', 'sport' => 'all', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                Semua Cabang
            </a>
            <a href="{{ route('venues.index', ['tab' => $activeTab ?? 'all', 'sport' => 'tennis', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🎾 Tennis
            </a>
            <a href="{{ route('venues.index', ['tab' => $activeTab ?? 'all', 'sport' => 'padel', 'q' => $search ?? '']) }}" 
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
            <span>
                Menampilkan <strong class="text-[#050608]">{{ $venues->total() }}</strong> venue
                @if(($activeTab ?? 'all') === 'my_venues')
                    <span>yang Anda daftarkan</span>
                @endif
            </span>
        </div>
    </div>

    <!-- 3. Grid of Venues or Empty States -->
    @if($venues->count() > 0)
        <div id="venuesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($venues as $venue)
                <div class="venue-card glass-card rounded-3xl overflow-hidden flex flex-col justify-between group transition-all duration-200 {{ !empty($venue['is_mine']) ? 'border-2 border-emerald-500/40 shadow-sm ring-1 ring-emerald-500/15' : 'border border-white/90' }}">
                    <div>
                        <div class="relative h-44 overflow-hidden bg-slate-100">
                            <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            
                            <!-- Sport Badge -->
                            <div class="absolute top-3 left-3">
                                <x-badge :type="strtolower($venue['sport']) === 'tennis' ? 'tennis' : 'padel'">
                                    {{ $venue['sport'] }}
                                </x-badge>
                            </div>

                            <!-- Venue Owner Badge -->
                            @if(!empty($venue['is_mine']))
                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-700/95 text-white font-bold text-[10px] shadow-sm backdrop-blur-md flex items-center gap-1.5 border border-emerald-400/40">
                                        <i class="fa-solid fa-crown text-[9px] text-amber-300"></i> Venue Anda
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="p-5 space-y-3 text-xs">
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-base font-bold text-[#050608] leading-tight truncate">{{ $venue['name'] }}</h3>
                                    @if(!empty($venue['is_mine']))
                                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200/60 shrink-0">
                                            Milik Anda
                                        </span>
                                    @endif
                                </div>
                                <p class="text-slate-500 text-xs mt-0.5 flex items-center gap-1">
                                    <i class="fa-solid fa-location-dot text-slate-400 text-[10px]"></i> {{ $venue['city'] }}
                                </p>
                            </div>

                            <p class="text-slate-600 line-clamp-2 leading-relaxed">{{ $venue['description'] }}</p>

                            <div class="bg-white/70 p-3 rounded-2xl border border-slate-200/60 space-y-1.5">
                                <div class="flex justify-between items-center text-slate-500">
                                    <span>Jam Operasi:</span>
                                    <strong class="text-[#050608]">{{ $venue['operating_hours'] }}</strong>
                                </div>
                                <div class="flex justify-between items-center text-slate-500">
                                    <span>Jumlah Court:</span>
                                    <strong class="text-[#063B00] font-bold">{{ count($venue['courts']) }} Lapangan</strong>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[11px] font-semibold text-slate-500">Fasilitas:</span>
                                <div class="flex flex-wrap gap-1">
                                    @foreach(array_slice($venue['facilities'], 0, 3) as $fac)
                                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-[10px] text-slate-600 border border-slate-200/80">
                                            {{ $fac }}
                                        </span>
                                    @endforeach
                                    @if(count($venue['facilities']) > 3)
                                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-[10px] text-slate-400 border border-slate-200/80">
                                            +{{ count($venue['facilities']) - 3 }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 pt-0">
                        @if(!empty($venue['is_mine']))
                            <div class="flex items-center gap-2">
                                <a href="{{ route('venues.show', $venue['id']) }}" class="flex-1 text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs transition-all shadow-xs hover:scale-[1.01] flex items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-gear text-[11px]"></i> Kelola Venue
                                </a>
                                <a href="{{ route('venues.courts.create', $venue['id']) }}" title="Tambah Court ke Venue ini" class="p-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/80 font-bold text-xs transition-all flex items-center justify-center">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </a>
                            </div>
                        @else
                            <a href="{{ route('venues.show', $venue['id']) }}" class="block text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs transition-all shadow-xs hover:scale-[1.01]">
                                Lihat Court & Jadwal
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination Component -->
        <x-pagination :paginator="$venues" />
    @else
        <!-- Rich Clean Empty State -->
        <div class="glass-card rounded-3xl p-8 sm:p-12 text-center max-w-xl mx-auto border border-white/90 space-y-4 shadow-sm">
            <div class="w-16 h-16 rounded-3xl bg-[#EBF8D8] border border-[#063B00]/20 flex items-center justify-center text-[#063B00] text-2xl mx-auto shadow-xs">
                @if(!empty($search))
                    <i class="fa-solid fa-magnifying-glass text-slate-600"></i>
                @elseif(($activeTab ?? 'all') === 'my_venues')
                    <i class="fa-solid fa-building-circle-plus text-emerald-700"></i>
                @else
                    <i class="fa-solid fa-building text-slate-600"></i>
                @endif
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base sm:text-lg font-bold text-slate-900">
                    @if(!empty($search))
                        Tidak Ditemukan Hasil Pencarian
                    @elseif(($activeTab ?? 'all') === 'my_venues')
                        Belum Ada Venue yang Anda Daftarkan
                    @else
                        Tidak Ada Venue Ditemukan
                    @endif
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                    @if(!empty($search))
                        Tidak ada venue yang cocok dengan kata kunci "<strong>{{ $search }}</strong>". Coba gunakan kata kunci lain atau reset pencarian.
                    @elseif(($activeTab ?? 'all') === 'my_venues')
                        Anda belum memiliki venue aktif di akun ini. Daftarkan venue dan court Anda sekarang untuk mulai menerima sesi mabar komunitas!
                    @else
                        Belum ada data venue yang tersedia untuk filter yang dipilih.
                    @endif
                </p>
            </div>

            <div class="pt-2">
                @if(!empty($search))
                    <a href="{{ route('venues.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-[#A8E63A]"></i> <span>Reset Pencarian</span>
                    </a>
                @elseif(($activeTab ?? 'all') === 'my_venues')
                    <a href="{{ route('venues.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-plus text-[#A8E63A]"></i> <span>Daftarkan Venue Baru</span>
                    </a>
                @else
                    <a href="{{ route('venues.index', ['tab' => 'all', 'sport' => 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-slate-400"></i> <span>Reset Filter</span>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
