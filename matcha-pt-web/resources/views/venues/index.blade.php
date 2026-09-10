@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-[#050608]">
                Direktori Venue & Court
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Daftar lokasi lapangan Tennis & Padel dengan informasi jam operasional dan fasilitas</p>
        </div>

        @if(Auth::check() && Auth::user()->role === 'venue_owner')
            <a href="{{ route('venues.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
                <i class="fa-solid fa-plus text-[10px]"></i> Daftarkan Venue Baru
            </a>
        @endif
    </div>

    @if(Auth::check() && Auth::user()->role === 'venue_owner')
        <!-- Owner Filter Tabs (Standalone White Cards with Green Active State) -->
        <div class="flex items-center gap-3">
            <button type="button" onclick="switchVenueTab('all')" id="tabAllBtn"
                class="venue-tab-btn px-4 py-2.5 rounded-2xl font-bold text-xs transition-all flex items-center gap-2.5 cursor-pointer {{ ($activeTab ?? 'all') !== 'my_venues' ? 'bg-[#063B00] text-white border border-[#063B00] shadow-sm' : 'bg-white text-slate-700 border border-slate-200/90 hover:bg-slate-50 hover:text-slate-900 shadow-2xs' }}">
                <i class="fa-solid fa-layer-group text-xs"></i> 
                <span>Semua Venue</span>
                <span id="tabAllBadge" class="px-2 py-0.5 rounded-full text-[10px] {{ ($activeTab ?? 'all') !== 'my_venues' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }} font-extrabold">
                    {{ $totalVenuesCount ?? count($venues) }}
                </span>
            </button>
            <button type="button" onclick="switchVenueTab('my_venues')" id="tabMyBtn"
                class="venue-tab-btn px-4 py-2.5 rounded-2xl font-bold text-xs transition-all flex items-center gap-2.5 cursor-pointer {{ ($activeTab ?? 'all') === 'my_venues' ? 'bg-[#063B00] text-white border border-[#063B00] shadow-sm' : 'bg-white text-slate-700 border border-slate-200/90 hover:bg-slate-50 hover:text-slate-900 shadow-2xs' }}">
                <i class="fa-solid fa-crown text-amber-400 text-xs"></i> 
                <span>Venue Saya</span>
                <span id="tabMyBadge" class="px-2 py-0.5 rounded-full text-[10px] {{ ($activeTab ?? 'all') === 'my_venues' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }} font-extrabold">
                    {{ $myVenuesCount ?? 0 }}
                </span>
            </button>
        </div>
    @endif

    <!-- Grid of Venues -->
    <div id="venuesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($venues as $venue)
            <div class="venue-card glass-card rounded-3xl overflow-hidden flex flex-col justify-between group transition-all duration-200 {{ !empty($venue['is_mine']) ? 'border-2 border-emerald-500/40 shadow-sm ring-1 ring-emerald-500/15' : 'border border-white/90' }}"
                data-is-mine="{{ !empty($venue['is_mine']) ? '1' : '0' }}">
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

    <!-- Empty State for "Venue Saya" -->
    <div id="myVenuesEmptyState" class="hidden glass-card rounded-3xl p-12 text-center border border-white/90 max-w-lg mx-auto space-y-4">
        <div class="w-16 h-16 rounded-3xl bg-emerald-50 text-[#063B00] flex items-center justify-center text-2xl mx-auto shadow-2xs border border-emerald-100">
            <i class="fa-solid fa-building-circle-plus"></i>
        </div>
        <div>
            <h3 class="text-base font-bold text-slate-900">Belum Ada Venue yang Didaftarkan</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto leading-relaxed">
                Anda belum memiliki venue aktif di akun ini. Mulai daftarkan venue dan court Anda untuk mulai menerima reservasi jadwal mabar.
            </p>
        </div>
        <div class="pt-2">
            <a href="{{ route('venues.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-sm transition-all hover:scale-[1.02]">
                <i class="fa-solid fa-plus text-xs"></i> Daftarkan Venue Pertama
            </a>
        </div>
    </div>
</div>

<script>
    function switchVenueTab(tab) {
        const url = new URL(window.location);
        if (tab === 'my_venues') {
            url.searchParams.set('tab', 'my_venues');
        } else {
            url.searchParams.delete('tab');
        }
        window.history.replaceState({}, '', url);

        const tabAllBtn = document.getElementById('tabAllBtn');
        const tabMyBtn = document.getElementById('tabMyBtn');
        const tabAllBadge = document.getElementById('tabAllBadge');
        const tabMyBadge = document.getElementById('tabMyBadge');
        const cards = document.querySelectorAll('.venue-card');
        const emptyState = document.getElementById('myVenuesEmptyState');
        const grid = document.getElementById('venuesGrid');

        let visibleCount = 0;

        if (tab === 'my_venues') {
            if (tabAllBtn) {
                tabAllBtn.className = 'venue-tab-btn px-4 py-2.5 rounded-2xl font-bold text-xs transition-all flex items-center gap-2.5 cursor-pointer bg-white text-slate-700 border border-slate-200/90 hover:bg-slate-50 hover:text-slate-900 shadow-2xs';
            }
            if (tabAllBadge) {
                tabAllBadge.className = 'px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600 font-extrabold';
            }
            if (tabMyBtn) {
                tabMyBtn.className = 'venue-tab-btn px-4 py-2.5 rounded-2xl font-bold text-xs transition-all flex items-center gap-2.5 cursor-pointer bg-[#063B00] text-white border border-[#063B00] shadow-sm';
            }
            if (tabMyBadge) {
                tabMyBadge.className = 'px-2 py-0.5 rounded-full text-[10px] bg-white/20 text-white font-extrabold';
            }

            cards.forEach(card => {
                if (card.dataset.isMine === '1') {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                if (emptyState) emptyState.classList.remove('hidden');
                if (grid) grid.classList.add('hidden');
            } else {
                if (emptyState) emptyState.classList.add('hidden');
                if (grid) grid.classList.remove('hidden');
            }
        } else {
            if (tabAllBtn) {
                tabAllBtn.className = 'venue-tab-btn px-4 py-2.5 rounded-2xl font-bold text-xs transition-all flex items-center gap-2.5 cursor-pointer bg-[#063B00] text-white border border-[#063B00] shadow-sm';
            }
            if (tabAllBadge) {
                tabAllBadge.className = 'px-2 py-0.5 rounded-full text-[10px] bg-white/20 text-white font-extrabold';
            }
            if (tabMyBtn) {
                tabMyBtn.className = 'venue-tab-btn px-4 py-2.5 rounded-2xl font-bold text-xs transition-all flex items-center gap-2.5 cursor-pointer bg-white text-slate-700 border border-slate-200/90 hover:bg-slate-50 hover:text-slate-900 shadow-2xs';
            }
            if (tabMyBadge) {
                tabMyBadge.className = 'px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600 font-extrabold';
            }

            cards.forEach(card => {
                card.style.display = 'flex';
            });
            if (emptyState) emptyState.classList.add('hidden');
            if (grid) grid.classList.remove('hidden');
        }
    }

    // Initialize tab state on page load based on URL or server param
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'my_venues') {
            switchVenueTab('my_venues');
        }
    });
</script>
@endsection
