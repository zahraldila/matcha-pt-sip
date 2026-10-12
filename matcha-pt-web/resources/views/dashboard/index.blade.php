@extends('layouts.app')

@section('content')
<div class="space-y-12 pb-16">
    
    <!-- Hero Section: Clean Frosted Glass Sport Hub -->
    <div class="py-12 sm:py-16 relative z-20">
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
            <div class="glass-card rounded-3xl p-5 sm:p-6 shadow-sm max-w-5xl border border-white/80 relative z-30">
                <!-- Tabs: Mabar / Venue / Komunitas -->
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-slate-200/50 text-xs font-semibold overflow-x-auto scrollbar-none">
                    <button type="button" onclick="switchDashboardTab('mabar')" id="tab-btn-mabar" class="tab-btn px-4 py-2 rounded-xl bg-[#063B00] text-white shadow-xs flex items-center gap-2 font-bold cursor-pointer transition-all whitespace-nowrap">
                        <i class="fa-solid fa-users text-xs text-[#A8E63A]"></i> <span>Jadwal Mabar</span>
                    </button>
                    <button type="button" onclick="switchDashboardTab('venue')" id="tab-btn-venue" class="tab-btn px-4 py-2 rounded-xl bg-white/80 text-slate-600 hover:text-[#050608] hover:bg-white border border-slate-200/60 flex items-center gap-2 transition-all shadow-xs cursor-pointer whitespace-nowrap">
                        <i class="fa-solid fa-location-dot text-xs text-slate-400"></i> <span>Sewa Lapangan</span>
                    </button>
                    <button type="button" onclick="switchDashboardTab('community')" id="tab-btn-community" class="tab-btn px-4 py-2 rounded-xl bg-white/80 text-slate-600 hover:text-[#050608] hover:bg-white border border-slate-200/60 flex items-center gap-2 transition-all shadow-xs cursor-pointer whitespace-nowrap">
                        <i class="fa-solid fa-shield-halved text-xs text-slate-400"></i> <span>Komunitas</span>
                    </button>
                </div>

                <!-- Search Input Form -->
                <form id="heroFilterForm" action="{{ route('dashboard') }}" method="GET">
                    <input type="hidden" name="tab" id="filterTabInput" value="{{ $activeTab ?? 'mabar' }}">
                    
                    <div class="grid grid-cols-1 {{ ($activeTab ?? 'mabar') === 'community' ? 'sm:grid-cols-3' : 'sm:grid-cols-4' }} gap-3 text-xs" id="filterFieldsGrid">
                        <!-- 1. Cabang Olahraga -->
                        <div class="space-y-1 relative" id="wrapper_dashboard_sport">
                            <label class="block font-medium text-slate-600 mb-1">Cabang Olahraga</label>
                            @php
                                $sportOptions = [
                                    'all' => 'Semua Cabang (All)',
                                    'tennis' => '🎾 Tennis',
                                    'padel' => '🏓 Padel',
                                ];
                                $currSportVal = $selectedSport ?? 'all';
                                $currSportLabel = $sportOptions[$currSportVal] ?? 'Semua Cabang (All)';
                            @endphp
                            <input type="hidden" name="sport" id="filterSportInput" value="{{ $currSportVal }}">
                            <div class="relative">
                                <button
                                    type="button"
                                    id="sportTrigger"
                                    onclick="toggleDashboardDropdown('sport', event)"
                                    class="w-full bg-white/95 border border-slate-200/80 rounded-2xl px-3.5 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                                >
                                    <span id="sportDisplay" class="truncate font-semibold">{{ $currSportLabel }}</span>
                                    <i id="sportChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                                </button>

                                <div
                                    id="sportMenu"
                                    class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-slate-200/90 rounded-2xl shadow-2xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
                                    onclick="event.stopPropagation()"
                                >
                                    @foreach($sportOptions as $sVal => $sLabel)
                                        <button
                                            type="button"
                                            onclick="selectDashboardSport('{{ $sVal }}', '{{ $sLabel }}')"
                                            class="sport-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all cursor-pointer {{ $currSportVal === $sVal ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                            data-val="{{ $sVal }}"
                                        >
                                            <span>{{ $sLabel }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- 2. Kota / Lokasi -->
                        <div class="space-y-1 relative" id="wrapper_dashboard_city">
                            <label class="block font-medium text-slate-600 mb-1">Kota / Lokasi</label>
                            @php
                                $currCityVal = $selectedCity ?? 'all';
                                $currCityLabel = ($currCityVal === 'all' || empty($currCityVal)) ? 'Semua Kota' : $currCityVal;
                            @endphp
                            <input type="hidden" name="city" id="filterCityInput" value="{{ $currCityVal }}">
                            <div class="relative">
                                <button
                                    type="button"
                                    id="cityTrigger"
                                    onclick="toggleDashboardDropdown('city', event)"
                                    class="w-full bg-white/95 border border-slate-200/80 rounded-2xl px-3.5 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                                >
                                    <span id="cityDisplay" class="truncate font-semibold">{{ $currCityLabel }}</span>
                                    <i id="cityChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                                </button>

                                <div
                                    id="cityMenu"
                                    class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-slate-200/90 rounded-2xl shadow-2xl p-2 max-h-72 overflow-y-auto animate-in fade-in zoom-in-95 duration-150"
                                    onclick="event.stopPropagation()"
                                >
                                    <!-- Live Search Bar -->
                                    <div class="sticky top-0 bg-white pb-2 mb-1 border-b border-slate-100 z-10" onclick="event.stopPropagation()">
                                        <div class="relative flex items-center">
                                            <i class="fa-solid fa-magnifying-glass text-slate-400 text-xs absolute left-3 pointer-events-none"></i>
                                            <input
                                                type="text"
                                                id="citySearchInput"
                                                placeholder="Cari kota..."
                                                oninput="filterCityList(this.value)"
                                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-7 py-2 text-xs text-slate-900 placeholder:text-slate-400 font-medium focus:bg-white focus:border-[#063B00] focus:ring-1 focus:ring-[#063B00] focus:outline-none transition-all"
                                                autocomplete="off"
                                            >
                                            <button
                                                type="button"
                                                id="citySearchClearBtn"
                                                onclick="clearCitySearch()"
                                                class="hidden absolute right-2.5 text-slate-400 hover:text-slate-600 text-xs cursor-pointer"
                                                title="Hapus"
                                            >
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- City List Container -->
                                    <div id="cityListItems" class="space-y-1">
                                        <!-- Populated by JavaScript rebuildCityOptions -->
                                    </div>

                                    <!-- Not Found State -->
                                    <div id="cityNotFound" class="hidden text-center py-4 text-xs text-slate-400 font-medium">
                                        Kota tidak ditemukan
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Pilih Tanggal (HANYA UNTUK MABAR & SEWA LAPANGAN, DIHIDDEN SAAT KOMUNITAS) -->
                        <div id="filterDateContainer" class="space-y-1 relative {{ ($activeTab ?? 'mabar') === 'community' ? 'hidden' : '' }}">
                            <label class="block font-medium text-slate-600 mb-1">Pilih Tanggal</label>
                            @php
                                $initDate = $selectedDate ?? date('Y-m-d');
                                $formattedDate = date('d/m/Y', strtotime($initDate));
                            @endphp
                            <input type="hidden" name="date" id="filterDateInput" value="{{ $initDate }}">
                            <div class="relative">
                                <button
                                    type="button"
                                    id="dateTrigger"
                                    onclick="openDatePickerModal()"
                                    class="w-full bg-white/95 border border-slate-200/80 rounded-2xl px-3.5 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                                >
                                    <span id="dateDisplay" class="truncate font-semibold">{{ $formattedDate }}</span>
                                    <i class="fa-regular fa-calendar text-xs text-slate-400 pointer-events-none"></i>
                                </button>
                            </div>
                        </div>

                        <!-- 4. Tombol Cari -->
                        <div class="flex items-end">
                            <button type="submit" id="filterSubmitBtn" class="w-full h-[42px] rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-2 cursor-pointer">
                                <i class="fa-solid fa-magnifying-glass"></i> <span id="filterBtnText">{{ ($activeTab ?? 'mabar') === 'community' ? 'Cari Komunitas' : (($activeTab ?? 'mabar') === 'venue' ? 'Cari Lapangan' : 'Cari Jadwal Mabar') }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 relative z-10">
        
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
                                    <h4 class="font-bold text-slate-900 text-sm truncate" title="{{ $comm['name'] }}">{{ $comm['name'] }}</h4>
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

<!-- Custom Date Picker Modal (Material / Flutter style matching mobile) -->
<div id="datePickerModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" style="display: none;">
    <!-- Backdrop overlay -->
    <div class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity" onclick="closeDatePickerModal()"></div>

    <!-- Dialog Card -->
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-[340px] p-5 sm:p-6 z-10 animate-in fade-in zoom-in-95 duration-150 select-none mx-auto my-auto" style="max-height: 90vh;">
        <!-- Subtitle & Main Header -->
        <div class="pb-3 border-b border-slate-100">
            <span class="block text-xs font-medium text-slate-500">Select date</span>
            <div class="flex items-center justify-between mt-1">
                <span id="pickerHeaderDate" class="text-2xl sm:text-3xl font-medium text-slate-800">Tue, Oct 13</span>
                <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors p-1" title="Select date">
                    <i class="fa-solid fa-pen text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Month Navigation -->
        <div class="flex items-center justify-between pt-4 pb-2">
            <div class="flex items-center gap-1.5 cursor-pointer">
                <span id="pickerMonthYearLabel" class="text-xs sm:text-sm font-semibold text-slate-800">October 2026</span>
                <i class="fa-solid fa-caret-down text-[10px] text-slate-500"></i>
            </div>
            <div class="flex items-center gap-1 text-slate-500">
                <button type="button" onclick="prevPickerMonth()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-slate-100 text-slate-600 transition-colors cursor-pointer" title="Bulan Sebelumnya">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" onclick="nextPickerMonth()" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-slate-100 text-slate-600 transition-colors cursor-pointer" title="Bulan Selanjutnya">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Days of Week Header (Explicit 7 Columns Grid) -->
        <div class="text-center text-xs font-semibold text-slate-500 py-1" style="display: grid !important; grid-template-columns: repeat(7, 1fr) !important; gap: 2px !important;">
            <span>S</span>
            <span>M</span>
            <span>T</span>
            <span>W</span>
            <span>T</span>
            <span>F</span>
            <span>S</span>
        </div>

        <!-- Calendar Days Grid (Explicit 7 Columns Grid) -->
        <div id="pickerCalendarGrid" class="py-1 text-xs" style="display: grid !important; grid-template-columns: repeat(7, 1fr) !important; gap: 2px !important;">
            <!-- Dynamically populated by JavaScript -->
        </div>

        <!-- Action Footer -->
        <div class="flex items-center justify-end gap-2 pt-4 mt-2 border-t border-slate-100">
            <button
                type="button"
                onclick="closeDatePickerModal()"
                class="px-4 py-2 text-xs font-bold text-[#063B00] hover:bg-[#063B00]/5 rounded-xl transition-colors cursor-pointer"
            >
                Cancel
            </button>
            <button
                type="button"
                onclick="confirmDatePicker()"
                class="px-4 py-2 text-xs font-bold text-[#063B00] hover:bg-[#063B00]/10 rounded-xl transition-colors cursor-pointer"
            >
                OK
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Data kota per tab (dari server)
    const citiesPerTab = {
        mabar: @json($mabarCities->values()),
        venue: @json($venueCities->values()),
        community: @json($communityCities->values()),
    };

    // ==========================================
    // CUSTOM DROPDOWN LOGIC (Sport & City)
    // ==========================================
    function toggleDashboardDropdown(type, event) {
        if (event) event.stopPropagation();
        const sportMenu = document.getElementById('sportMenu');
        const sportChevron = document.getElementById('sportChevron');
        const cityMenu = document.getElementById('cityMenu');
        const cityChevron = document.getElementById('cityChevron');
        const sportWrapper = document.getElementById('wrapper_dashboard_sport');
        const cityWrapper = document.getElementById('wrapper_dashboard_city');

        if (type === 'sport') {
            const isHidden = sportMenu.classList.contains('hidden');
            closeDashboardDropdowns();
            if (isHidden) {
                sportMenu.classList.remove('hidden');
                if (sportChevron) sportChevron.classList.add('rotate-180');
                if (sportWrapper) sportWrapper.style.zIndex = '50';
            }
        } else if (type === 'city') {
            const isHidden = cityMenu.classList.contains('hidden');
            closeDashboardDropdowns();
            if (isHidden) {
                cityMenu.classList.remove('hidden');
                if (cityChevron) cityChevron.classList.add('rotate-180');
                if (cityWrapper) cityWrapper.style.zIndex = '50';

                // Reset search when opening & focus
                const searchInput = document.getElementById('citySearchInput');
                if (searchInput) {
                    searchInput.value = '';
                    filterCityList('');
                    setTimeout(() => searchInput.focus(), 60);
                }
            }
        }
    }

    function closeDashboardDropdowns() {
        const sportMenu = document.getElementById('sportMenu');
        const sportChevron = document.getElementById('sportChevron');
        const cityMenu = document.getElementById('cityMenu');
        const cityChevron = document.getElementById('cityChevron');
        const sportWrapper = document.getElementById('wrapper_dashboard_sport');
        const cityWrapper = document.getElementById('wrapper_dashboard_city');

        if (sportMenu) sportMenu.classList.add('hidden');
        if (sportChevron) sportChevron.classList.remove('rotate-180');
        if (cityMenu) cityMenu.classList.add('hidden');
        if (cityChevron) cityChevron.classList.remove('rotate-180');
        if (sportWrapper) sportWrapper.style.zIndex = '';
        if (cityWrapper) cityWrapper.style.zIndex = '';
    }

    function filterCityList(query) {
        const q = (query || '').trim().toLowerCase();
        const clearBtn = document.getElementById('citySearchClearBtn');
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', q.length === 0);
        }

        const items = document.querySelectorAll('#cityListItems .city-item-btn');
        let matchedCount = 0;
        items.forEach(btn => {
            const val = (btn.getAttribute('data-val') || '').toLowerCase();
            const text = (btn.textContent || '').toLowerCase();
            if (!q || val.includes(q) || text.includes(q)) {
                btn.classList.remove('hidden');
                matchedCount++;
            } else {
                btn.classList.add('hidden');
            }
        });

        const notFound = document.getElementById('cityNotFound');
        if (notFound) {
            notFound.classList.toggle('hidden', matchedCount > 0);
        }
    }

    function clearCitySearch() {
        const input = document.getElementById('citySearchInput');
        if (input) {
            input.value = '';
            input.focus();
        }
        filterCityList('');
    }

    function selectDashboardSport(val, label) {
        const input = document.getElementById('filterSportInput');
        const display = document.getElementById('sportDisplay');
        if (input) input.value = val;
        if (display) display.textContent = label;

        document.querySelectorAll('#sportMenu .sport-item-btn').forEach(btn => {
            const isSelected = btn.getAttribute('data-val') === val;
            if (isSelected) {
                btn.className = 'sport-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-bold transition-all cursor-pointer bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/15';
            } else {
                btn.className = 'sport-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all cursor-pointer text-slate-700 hover:bg-slate-100 hover:text-slate-900';
            }
        });

        closeDashboardDropdowns();
    }

    function selectDashboardCity(val, label) {
        const input = document.getElementById('filterCityInput');
        const display = document.getElementById('cityDisplay');
        if (input) input.value = val;
        if (display) display.textContent = label;

        document.querySelectorAll('#cityListItems .city-item-btn').forEach(btn => {
            const isSelected = btn.getAttribute('data-val').toLowerCase() === val.toLowerCase();
            if (isSelected) {
                btn.className = 'city-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-bold transition-all cursor-pointer bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/15';
            } else {
                btn.className = 'city-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all cursor-pointer text-slate-700 hover:bg-slate-100 hover:text-slate-900';
            }
        });

        closeDashboardDropdowns();
    }

    function rebuildCityOptions(tab) {
        const input = document.getElementById('filterCityInput');
        const display = document.getElementById('cityDisplay');
        const container = document.getElementById('cityListItems');
        if (!input || !container) return;

        let currentVal = input.value || 'all';
        const cities = citiesPerTab[tab] || [];

        // Check if currentVal is still available in this tab's city list
        if (currentVal !== 'all' && !cities.some(c => c.toLowerCase() === currentVal.toLowerCase())) {
            currentVal = 'all';
            input.value = 'all';
            if (display) display.textContent = 'Semua Kota';
        }

        let html = `
            <button
                type="button"
                onclick="selectDashboardCity('all', 'Semua Kota')"
                class="city-item-btn w-full px-3 py-2 rounded-xl text-left text-xs transition-all cursor-pointer ${currentVal === 'all' ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold'}"
                data-val="all"
            >
                <span>Semua Kota</span>
            </button>
        `;

        cities.forEach(city => {
            const isSelected = (currentVal.toLowerCase() === city.toLowerCase());
            const safeCity = city.replace(/'/g, "\\'");
            html += `
                <button
                    type="button"
                    onclick="selectDashboardCity('${safeCity}', '${safeCity}')"
                    class="city-item-btn w-full px-3 py-2 rounded-xl text-left text-xs transition-all cursor-pointer ${isSelected ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-semibold'}"
                    data-val="${city}"
                >
                    <span class="truncate">${city}</span>
                </button>
            `;
        });

        container.innerHTML = html;

        // Reset filter
        const searchInput = document.getElementById('citySearchInput');
        if (searchInput && searchInput.value) {
            filterCityList(searchInput.value);
        } else {
            filterCityList('');
        }
    }

    // ==========================================
    // TAB SWITCHER LOGIC
    // ==========================================
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

        // 3. Rebuild city dropdown sesuai tab
        rebuildCityOptions(tab);

        // 4. Toggle Date Field (KOMUNITAS TIDAK ADA PILIH TANGGAL)
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

        // 5. Switch result sections below
        const secMabar = document.getElementById('section-mabar');
        const secVenue = document.getElementById('section-venue');
        const secComm = document.getElementById('section-community');

        if (secMabar) secMabar.classList.toggle('hidden', tab !== 'mabar');
        if (secVenue) secVenue.classList.toggle('hidden', tab !== 'venue');
        if (secComm) secComm.classList.toggle('hidden', tab !== 'community');
    }

    // ==========================================
    // DATE PICKER MODAL CONTROLLER
    // ==========================================
    const dayNamesShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    const monthNamesShort = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];

    let pickerSelectedDate = new Date();
    let pickerTempDate = new Date();
    let pickerViewingYear = pickerTempDate.getFullYear();
    let pickerViewingMonth = pickerTempDate.getMonth();

    function parseYmd(dateStr) {
        if (!dateStr) return new Date();
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        }
        return new Date();
    }

    function formatYmd(d) {
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function formatDisplayDate(d) {
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }

    function openDatePickerModal() {
        const input = document.getElementById('filterDateInput');
        if (input && input.value) {
            pickerSelectedDate = parseYmd(input.value);
        } else {
            pickerSelectedDate = new Date();
        }
        pickerTempDate = new Date(pickerSelectedDate);
        pickerViewingYear = pickerTempDate.getFullYear();
        pickerViewingMonth = pickerTempDate.getMonth();

        renderCalendar();

        const modal = document.getElementById('datePickerModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    }

    function closeDatePickerModal() {
        const modal = document.getElementById('datePickerModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    function confirmDatePicker() {
        pickerSelectedDate = new Date(pickerTempDate);
        const ymd = formatYmd(pickerSelectedDate);
        const displayFormatted = formatDisplayDate(pickerSelectedDate);

        const input = document.getElementById('filterDateInput');
        const display = document.getElementById('dateDisplay');
        if (input) input.value = ymd;
        if (display) display.textContent = displayFormatted;

        closeDatePickerModal();
    }

    function prevPickerMonth() {
        pickerViewingMonth--;
        if (pickerViewingMonth < 0) {
            pickerViewingMonth = 11;
            pickerViewingYear--;
        }
        renderCalendar();
    }

    function nextPickerMonth() {
        pickerViewingMonth++;
        if (pickerViewingMonth > 11) {
            pickerViewingMonth = 0;
            pickerViewingYear++;
        }
        renderCalendar();
    }

    function selectPickerDate(day) {
        pickerTempDate = new Date(pickerViewingYear, pickerViewingMonth, day);
        renderCalendar();
    }

    function renderCalendar() {
        // 1. Update Header (e.g. "Tue, Oct 13")
        const headerEl = document.getElementById('pickerHeaderDate');
        if (headerEl) {
            const dayName = dayNamesShort[pickerTempDate.getDay()];
            const monthName = monthNamesShort[pickerTempDate.getMonth()];
            const dayNum = pickerTempDate.getDate();
            headerEl.textContent = `${dayName}, ${monthName} ${dayNum}`;
        }

        // 2. Update Month & Year label (e.g. "October 2026")
        const monthYearLabel = document.getElementById('pickerMonthYearLabel');
        if (monthYearLabel) {
            monthYearLabel.textContent = `${monthNames[pickerViewingMonth]} ${pickerViewingYear}`;
        }

        // 3. Render Calendar Days
        const grid = document.getElementById('pickerCalendarGrid');
        if (!grid) return;

        const firstDayIndex = new Date(pickerViewingYear, pickerViewingMonth, 1).getDay();
        const totalDays = new Date(pickerViewingYear, pickerViewingMonth + 1, 0).getDate();
        const today = new Date();

        let gridHtml = '';

        // Empty padding cells before first day
        for (let i = 0; i < firstDayIndex; i++) {
            gridHtml += `<div class="mx-auto" style="width: 32px; height: 32px;"></div>`;
        }

        // Day cells
        for (let d = 1; d <= totalDays; d++) {
            const isSelected = (
                pickerTempDate.getFullYear() === pickerViewingYear &&
                pickerTempDate.getMonth() === pickerViewingMonth &&
                pickerTempDate.getDate() === d
            );

            const isToday = (
                today.getFullYear() === pickerViewingYear &&
                today.getMonth() === pickerViewingMonth &&
                today.getDate() === d
            );

            if (isSelected) {
                gridHtml += `
                    <button
                        type="button"
                        onclick="selectPickerDate(${d})"
                        style="width: 32px; height: 32px;"
                        class="rounded-full mx-auto flex items-center justify-center font-bold bg-[#063B00] text-white shadow-xs cursor-pointer transition-transform scale-105"
                    >
                        ${d}
                    </button>
                `;
            } else if (isToday) {
                gridHtml += `
                    <button
                        type="button"
                        onclick="selectPickerDate(${d})"
                        style="width: 32px; height: 32px;"
                        class="rounded-full mx-auto flex items-center justify-center font-bold border border-[#063B00] text-[#063B00] hover:bg-slate-100 cursor-pointer transition-colors"
                    >
                        ${d}
                    </button>
                `;
            } else {
                gridHtml += `
                    <button
                        type="button"
                        onclick="selectPickerDate(${d})"
                        style="width: 32px; height: 32px;"
                        class="rounded-full mx-auto flex items-center justify-center font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-900 cursor-pointer transition-colors"
                    >
                        ${d}
                    </button>
                `;
            }
        }

        grid.innerHTML = gridHtml;
    }

    // ==========================================
    // GLOBAL EVENT LISTENERS
    // ==========================================
    document.addEventListener('click', function(e) {
        const sportWrapper = document.getElementById('wrapper_dashboard_sport');
        const cityWrapper = document.getElementById('wrapper_dashboard_city');
        if ((!sportWrapper || !sportWrapper.contains(e.target)) && (!cityWrapper || !cityWrapper.contains(e.target))) {
            closeDashboardDropdowns();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDashboardDropdowns();
            closeDatePickerModal();
        }
    });

    // Initialize state on page load based on activeTab
    document.addEventListener('DOMContentLoaded', function() {
        switchDashboardTab('{{ $activeTab ?? 'mabar' }}');
    });
</script>
@endpush
@endsection
