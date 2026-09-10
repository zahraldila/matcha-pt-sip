@extends('layouts.app')

@section('content')
<div class="space-y-12 pb-16">
    
    <!-- Hero Section: Clean Frosted Glass Sport Hub -->
    <div class="py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <div class="max-w-3xl space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#A8E63A]/20 border border-[#063B00]/25 text-[#050608] text-xs font-semibold shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#063B00]"></span>
                    Tennis & Padel Community Platform
                </div>
                <h1 class="text-3xl sm:text-5xl font-extrabold text-[#050608] tracking-tight leading-tight">
                    Main bareng, drawing tim seimbang, dan catat skor pertandingan.
                </h1>
                <p class="text-slate-600 text-sm sm:text-base leading-relaxed max-w-2xl font-normal">
                    Temukan jadwal mabar tenis dan padel di sekitarmu, pembagian tim otomatis dengan drawing seimbang, dan simpan riwayat pertandinganmu.
                </p>
            </div>

            <!-- Clean Frosted Glass Search & Filter Card -->
            <div class="glass-card rounded-3xl p-5 sm:p-6 shadow-sm max-w-5xl border border-white/80">
                <!-- Tabs: Mabar / Venue / Komunitas -->
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-slate-200/50 text-xs font-semibold">
                    <button type="button" onclick="switchDashboardTab('mabar')" id="tab-btn-mabar" class="tab-btn px-4 py-2 rounded-xl bg-[#063B00] text-white shadow-xs flex items-center gap-2 font-bold cursor-pointer transition-all">
                        <i class="fa-solid fa-users text-xs text-[#A8E63A]"></i> <span>Jadwal Mabar</span>
                    </button>
                    <button type="button" onclick="switchDashboardTab('venue')" id="tab-btn-venue" class="tab-btn px-4 py-2 rounded-xl bg-white/80 text-slate-600 hover:text-[#050608] hover:bg-white border border-slate-200/60 flex items-center gap-2 transition-all shadow-xs cursor-pointer">
                        <i class="fa-solid fa-location-dot text-xs text-slate-400"></i> <span>Sewa Lapangan</span>
                    </button>
                    <button type="button" onclick="switchDashboardTab('community')" id="tab-btn-community" class="tab-btn px-4 py-2 rounded-xl bg-white/80 text-slate-600 hover:text-[#050608] hover:bg-white border border-slate-200/60 flex items-center gap-2 transition-all shadow-xs cursor-pointer">
                        <i class="fa-solid fa-shield-halved text-xs text-slate-400"></i> <span>Komunitas</span>
                    </button>
                </div>

                <!-- Search Input Form -->
                <form id="heroFilterForm" action="{{ route('dashboard') }}" method="GET">
                    <input type="hidden" name="tab" id="filterTabInput" value="{{ $activeTab ?? 'mabar' }}">
                    
                    <div class="grid grid-cols-1 {{ ($activeTab ?? 'mabar') === 'community' ? 'sm:grid-cols-3' : 'sm:grid-cols-4' }} gap-3 text-xs" id="filterFieldsGrid">
                        <!-- 1. Cabang Olahraga -->
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">Cabang Olahraga</label>
                            <select name="sport" id="filterSport" class="w-full bg-white/90 border border-slate-200/70 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:border-[#063B00] focus:outline-none shadow-xs">
                                <option value="all" {{ ($selectedSport ?? 'all') === 'all' ? 'selected' : '' }}>Semua Cabang (All)</option>
                                <option value="tennis" {{ ($selectedSport ?? '') === 'tennis' ? 'selected' : '' }}>🎾 Tennis</option>
                                <option value="padel" {{ ($selectedSport ?? '') === 'padel' ? 'selected' : '' }}>🏓 Padel</option>
                            </select>
                        </div>

                        <!-- 2. Kota / Lokasi -->
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">Kota / Lokasi</label>
                            <select name="city" id="filterCity" class="w-full bg-white/90 border border-slate-200/70 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:border-[#063B00] focus:outline-none shadow-xs">
                                <option value="all" {{ ($selectedCity ?? 'all') === 'all' ? 'selected' : '' }}>Semua Kota</option>
                                <option value="jakarta" {{ ($selectedCity ?? '') === 'jakarta' ? 'selected' : '' }}>Jakarta Pusat / Selatan</option>
                                <option value="bandung" {{ ($selectedCity ?? '') === 'bandung' ? 'selected' : '' }}>Bandung</option>
                            </select>
                        </div>

                        <!-- 3. Pilih Tanggal (HANYA UNTUK MABAR & SEWA LAPANGAN, DIHIDDEN SAAT KOMUNITAS) -->
                        <div id="filterDateContainer" class="{{ ($activeTab ?? 'mabar') === 'community' ? 'hidden' : '' }}">
                            <label class="block font-medium text-slate-600 mb-1">Pilih Tanggal</label>
                            <input type="date" name="date" id="filterDate" value="{{ $selectedDate ?? date('Y-m-d') }}" class="w-full bg-white/90 border border-slate-200/70 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:border-[#063B00] focus:outline-none shadow-xs">
                        </div>

                        <!-- 4. Tombol Cari -->
                        <div class="flex items-end">
                            <button type="submit" id="filterSubmitBtn" class="w-full py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-2">
                                <i class="fa-solid fa-magnifying-glass"></i> <span id="filterBtnText">{{ ($activeTab ?? 'mabar') === 'community' ? 'Cari Komunitas' : (($activeTab ?? 'mabar') === 'venue' ? 'Cari Lapangan' : 'Cari Jadwal Mabar') }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        <!-- Category Pill Switcher (Preserving Active Tab) -->
        <div class="flex items-center gap-2.5 overflow-x-auto pb-1">
            <a href="{{ route('dashboard', ['tab' => $activeTab ?? 'mabar', 'sport' => 'all', 'city' => $selectedCity ?? 'all', 'date' => $selectedDate ?? '']) }}" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                <i class="fa-solid fa-layer-group text-[11px]"></i> Semua Cabang
            </a>
            <a href="{{ route('dashboard', ['tab' => $activeTab ?? 'mabar', 'sport' => 'tennis', 'city' => $selectedCity ?? 'all', 'date' => $selectedDate ?? '']) }}" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                <span>🎾</span> Tennis
            </a>
            <a href="{{ route('dashboard', ['tab' => $activeTab ?? 'mabar', 'sport' => 'padel', 'city' => $selectedCity ?? 'all', 'date' => $selectedDate ?? '']) }}" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'padel' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                <span>🏓</span> Padel
            </a>
        </div>

        <!-- 1. Section: Jadwal Mabar Terbuka (Max 6) -->
        <div id="section-mabar" class="space-y-6 {{ ($activeTab ?? 'mabar') === 'mabar' ? '' : 'hidden' }}">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/50 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">
                        Jadwal Mabar Terbuka
                    </h2>
                    <p class="text-xs text-slate-500">Pilih sesi permainan dan gabung ke kuota pemain</p>
                </div>
                <a href="{{ route('games.index') }}" class="text-xs font-bold text-[#063B00] hover:text-[#042a00] hover:underline flex items-center gap-1.5 transition-colors">
                    <span>Lihat Semua Jadwal</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <!-- Games Grid with Glass Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($games as $game)
                    <x-game-card :game="$game" />
                @empty
                    <div class="col-span-full py-12 text-center text-slate-400 glass-card rounded-2xl">
                        <i class="fa-solid fa-calendar-xmark text-3xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-medium">Tidak ada jadwal mabar yang sesuai dengan pencarian Anda.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 2. Section: Venue & Lapangan Rekomendasi (Max 6) -->
        <div id="section-venue" class="space-y-6 {{ ($activeTab ?? 'mabar') === 'venue' ? '' : 'hidden' }}">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/50 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">
                        Venue & Lapangan Rekomendasi
                    </h3>
                    <p class="text-xs text-slate-500">Temukan court tennis dan padel terbaik di sekitarmu</p>
                </div>
                <a href="{{ route('venues.index') }}" class="text-xs font-bold text-[#063B00] hover:text-[#042a00] hover:underline flex items-center gap-1.5 transition-colors">
                    <span>Lihat Semua Venue</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($venues as $venue)
                    <div class="glass-card rounded-2xl overflow-hidden group flex flex-col justify-between">
                        <div>
                            <div class="relative h-44 overflow-hidden">
                                <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute top-3 left-3">
                                    <x-badge :type="strtolower($venue['sport']) === 'tennis' ? 'tennis' : 'padel'">
                                        {{ $venue['sport'] }}
                                    </x-badge>
                                </div>
                            </div>
                            <div class="p-4 space-y-2 text-xs">
                                <h4 class="font-bold text-slate-900 text-sm truncate">{{ $venue['name'] }}</h4>
                                <p class="text-slate-500 text-xs truncate flex items-center gap-1">
                                    <i class="fa-solid fa-location-dot text-slate-400 text-[10px]"></i> {{ $venue['city'] }} &bull; {{ $venue['address'] }}
                                </p>
                                
                                <div class="pt-2 flex justify-between items-center text-slate-500 border-t border-slate-100 text-[11px]">
                                    <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i>{{ $venue['operating_hours'] }}</span>
                                    <span class="font-semibold text-[#063B00] bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">{{ count($venue['courts']) }} Court</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 pt-0">
                            <a href="{{ route('venues.show', $venue['id']) }}" class="block text-center py-2.5 rounded-xl bg-white/90 hover:bg-[#063B00] text-slate-800 hover:text-white font-semibold text-xs border border-slate-200/70 shadow-xs transition-all">
                                Cek Jadwal Court
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-slate-400 glass-card rounded-2xl">
                        <i class="fa-solid fa-building-circle-xmark text-3xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-medium">Belum ada data venue rekomendasi yang sesuai.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 3. Section: Komunitas Tennis & Padel (Max 6) -->
        <div id="section-community" class="space-y-6 {{ ($activeTab ?? 'mabar') === 'community' ? '' : 'hidden' }}">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/50 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">
                        Komunitas Tennis & Padel
                    </h2>
                    <p class="text-xs text-slate-500">Gabung dengan komunitas dan mabar bersama pemain se-frekuensi</p>
                </div>
                <a href="{{ route('communities.index') }}" class="text-xs font-bold text-[#063B00] hover:text-[#042a00] hover:underline flex items-center gap-1.5 transition-colors">
                    <span>Lihat Semua Komunitas</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($communities as $comm)
                    <div class="glass-card rounded-2xl overflow-hidden group flex flex-col justify-between">
                        <div>
                            <div class="relative h-44 overflow-hidden">
                                <img src="{{ $comm['image'] }}" alt="{{ $comm['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute top-3 left-3">
                                    <x-badge :type="strtolower($comm['sport']) === 'tennis' ? 'tennis' : 'padel'">
                                        {{ $comm['sport'] }}
                                    </x-badge>
                                </div>
                            </div>
                            <div class="p-5 space-y-2.5 text-xs">
                                <div>
                                    <h4 class="font-bold text-slate-900 text-sm truncate">{{ $comm['name'] }}</h4>
                                    <p class="text-[11px] text-slate-500 font-medium flex items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-slate-400 text-[10px]"></i> {{ $comm['city'] }} &bull; <i class="fa-solid fa-users text-slate-400 text-[10px]"></i> {{ $comm['members_count'] }} Anggota
                                    </p>
                                </div>
                                <p class="text-slate-600 line-clamp-2 leading-relaxed text-[11px]">
                                    {{ $comm['description'] }}
                                </p>
                                <div class="pt-2 flex justify-between items-center text-slate-500 border-t border-slate-100 text-[11px]">
                                    <span>Admin: <strong class="text-slate-800">{{ $comm['admin_name'] }}</strong></span>
                                    <span class="font-semibold text-[#063B00] bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">{{ $comm['status'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 pt-0">
                            <a href="{{ route('communities.show', $comm['id']) }}" class="block text-center py-2.5 rounded-xl bg-white/90 hover:bg-[#063B00] text-slate-800 hover:text-white font-semibold text-xs border border-slate-200/70 shadow-xs transition-all">
                                Lihat Komunitas
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-slate-400 glass-card rounded-2xl">
                        <i class="fa-solid fa-shield-halved text-3xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-medium">Belum ada data komunitas untuk filter ini.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<x-join-modal />

@push('scripts')
<script>
    function switchDashboardTab(tab) {
        // 1. Update tab styling
        const tabs = ['mabar', 'venue', 'community'];
        tabs.forEach(t => {
            const btn = document.getElementById(`tab-btn-${t}`);
            if (!btn) return;
            const icon = btn.querySelector('i');
            if (t === tab) {
                btn.className = 'tab-btn px-4 py-2 rounded-xl bg-[#063B00] text-white shadow-xs flex items-center gap-2 font-bold cursor-pointer transition-all';
                if (icon) icon.className = icon.className.replace('text-slate-400', 'text-[#A8E63A]');
            } else {
                btn.className = 'tab-btn px-4 py-2 rounded-xl bg-white/80 text-slate-600 hover:text-[#050608] hover:bg-white border border-slate-200/60 flex items-center gap-2 transition-all shadow-xs cursor-pointer';
                if (icon) icon.className = icon.className.replace('text-[#A8E63A]', 'text-slate-400');
            }
        });

        // 2. Update hidden input
        const hiddenInput = document.getElementById('filterTabInput');
        if (hiddenInput) hiddenInput.value = tab;

        // 3. Toggle Date Field (KOMUNITAS TIDAK ADA PILIH TANGGAL)
        const dateContainer = document.getElementById('filterDateContainer');
        const fieldsGrid = document.getElementById('filterFieldsGrid');
        const btnText = document.getElementById('filterBtnText');

        if (tab === 'community') {
            if (dateContainer) dateContainer.classList.add('hidden');
            if (fieldsGrid) {
                fieldsGrid.classList.remove('sm:grid-cols-4');
                fieldsGrid.classList.add('sm:grid-cols-3');
            }
            if (btnText) btnText.innerText = 'Cari Komunitas';
        } else if (tab === 'venue') {
            if (dateContainer) dateContainer.classList.remove('hidden');
            if (fieldsGrid) {
                fieldsGrid.classList.remove('sm:grid-cols-3');
                fieldsGrid.classList.add('sm:grid-cols-4');
            }
            if (btnText) btnText.innerText = 'Cari Lapangan';
        } else {
            if (dateContainer) dateContainer.classList.remove('hidden');
            if (fieldsGrid) {
                fieldsGrid.classList.remove('sm:grid-cols-3');
                fieldsGrid.classList.add('sm:grid-cols-4');
            }
            if (btnText) btnText.innerText = 'Cari Jadwal Mabar';
        }

        // 4. Switch result sections below
        const secMabar = document.getElementById('section-mabar');
        const secVenue = document.getElementById('section-venue');
        const secComm = document.getElementById('section-community');

        if (secMabar) secMabar.classList.toggle('hidden', tab !== 'mabar');
        if (secVenue) secVenue.classList.toggle('hidden', tab !== 'venue');
        if (secComm) secComm.classList.toggle('hidden', tab !== 'community');
    }

    // Initialize state on page load based on activeTab
    document.addEventListener('DOMContentLoaded', function() {
        switchDashboardTab('{{ $activeTab ?? 'mabar' }}');
    });
</script>
@endpush
@endsection
