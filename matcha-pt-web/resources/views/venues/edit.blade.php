@extends('layouts.app')

@section('content')
@php
    $currentFasilitas = array_map('trim', explode(',', $venue->fasilitas ?? ''));
    $currentHours = $venue->jam_operasional ?? '06:00 - 23:00 WIB';
    $jamBukaVal = '06:00';
    $jamTutupVal = '23:00';
    if (preg_match('/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/', $currentHours, $hMatches)) {
        $jamBukaVal = $hMatches[1];
        $jamTutupVal = $hMatches[2];
    }

    $timeSlots = [];
    for ($h = 5; $h <= 24; $h++) {
        $formattedH = str_pad($h == 24 ? '24' : $h, 2, '0', STR_PAD_LEFT);
        $timeSlots[] = "$formattedH:00";
        if ($h < 24) {
            $timeSlots[] = "$formattedH:30";
        }
    }
@endphp

<div class="w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-10 pb-28 md:pb-12 space-y-8">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/60 relative z-10">
        <div>
            <a href="{{ route('venues.show', $venue->venue_id) }}"
                class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-[#063B00] transition-colors mb-3 group">
                <span class="w-7 h-7 rounded-xl bg-white/80 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-[#063B00] group-hover:text-white transition-all shadow-2xs">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                </span>
                Kembali ke Detail Venue
            </a>

            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-extrabold uppercase tracking-wider">
                    Venue Management
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-medium text-slate-500">
                    ID Venue #{{ $venue->venue_id }}
                </span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                Edit Informasi Venue
            </h1>

            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl leading-relaxed">
                Perbarui rincian venue, jam operasional reguler, fasilitas, serta kontak PIC penanggung jawab lapangan Anda.
            </p>
        </div>

        <div class="hidden sm:flex flex-col items-end gap-2">
            <div class="px-4 py-2 rounded-2xl bg-white/60 backdrop-blur-md border border-white/80 shadow-2xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-pen-to-square text-[#A8E63A]"></i>
                </div>

                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        Status Edit
                    </p>
                    <p class="text-xs font-black text-[#063B00]">
                        Mode Perubahan Data
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Glassmorphism Form Card -->
    <div class="backdrop-blur-2xl bg-white/80 border border-white/90 rounded-3xl p-6 sm:p-10 shadow-[0_20px_50px_rgba(6,59,0,0.06)] relative z-10 space-y-8">

        <form
            id="venueEditForm"
            action="{{ route('venues.update', $venue->venue_id) }}"
            method="POST"
            class="space-y-8 text-xs"
            novalidate
        >
            @csrf
            @method('PUT')

            <!-- SECTION 1: Informasi Utama Venue -->
            <div class="space-y-4">

                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                        1
                    </span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Informasi Utama Venue
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <!-- Nama Venue -->
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Nama Tempat / Venue
                            <span class="text-rose-500">*</span>
                        </label>

                        <div class="relative">
                            <i class="fa-solid fa-building absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input
                                type="text"
                                id="venueName"
                                name="nama_venue"
                                value="{{ old('nama_venue', $venue->nama_venue) }}"
                                maxlength="100"
                                placeholder="Contoh: Gelora Racquet & Padel Club"
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                        </div>

                        <p id="venueNameError" class="hidden text-[10px] font-semibold text-rose-500">
                            Nama venue wajib diisi.
                        </p>
                        @error('nama_venue')
                            <p class="text-[10px] font-semibold text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Alamat Venue -->
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Alamat Lengkap Venue
                            <span class="text-rose-500">*</span>
                        </label>

                        <div class="relative">
                            <i class="fa-solid fa-map-location-dot absolute left-4 top-3.5 text-slate-400 text-xs"></i>
                            <textarea
                                id="venueAddress"
                                name="alamat"
                                rows="2"
                                placeholder="Jl. Raya Utama No. 88, Kebayoran Baru, Jakarta Selatan..."
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-2.5 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >{{ old('alamat', $venue->alamat) }}</textarea>
                        </div>

                        <p id="venueAddressError" class="hidden text-[10px] font-semibold text-rose-500">
                            Alamat venue wajib diisi.
                        </p>
                    </div>

                    <!-- Kota / Kabupaten & Google Maps -->
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">

                        <!-- Kota / Kabupaten -->
                        <div class="space-y-1.5 w-full">
                            <label class="block font-bold text-slate-800">
                                Kota / Kabupaten
                                <span class="text-rose-500">*</span>
                            </label>

                            <div class="relative w-full" id="kotaWilayahWrapper">
                                <input
                                    type="hidden"
                                    id="kotaWilayah"
                                    name="kota"
                                    value="{{ old('kota', $venue->kota ?: 'Jakarta') }}"
                                >

                                <button
                                    type="button"
                                    id="kotaWilayahButton"
                                    class="w-full h-[48px] flex items-center justify-between bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left"
                                >
                                    <i class="fa-solid fa-city absolute left-4 text-slate-400 text-xs pointer-events-none"></i>
                                    <span id="kotaWilayahSelected" class="truncate text-slate-900 font-bold">
                                        {{ old('kota', $venue->kota ?: 'Pilih Kota / Kabupaten') }}
                                    </span>
                                    <i id="kotaWilayahArrow" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200"></i>
                                </button>

                                <!-- Dropdown -->
                                <div
                                    id="kotaWilayahDropdown"
                                    class="hidden absolute z-50 left-0 right-0 mt-2 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden"
                                >
                                    <div class="p-3 border-b border-slate-100">
                                        <div class="relative">
                                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                            <input
                                                type="text"
                                                id="kotaWilayahSearch"
                                                placeholder="Cari kota atau kabupaten..."
                                                autocomplete="off"
                                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2.5 text-sm text-slate-800 focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none"
                                            >
                                        </div>
                                    </div>
                                    <div id="kotaWilayahOptions" class="max-h-64 overflow-y-auto py-1">
                                        <div class="px-4 py-3 text-sm text-slate-400">
                                            Memuat Kota / Kabupaten...
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p id="kotaWilayahError" class="hidden text-[10px] font-semibold text-rose-500">
                                Kota / Kabupaten wajib dipilih.
                            </p>
                        </div>

                        <!-- Google Maps -->
                        <div class="space-y-1.5 w-full">
                            <label class="block font-bold text-slate-800">
                                Link Google Maps
                                <span class="font-normal text-slate-400">(Opsional)</span>
                            </label>

                            <div class="relative w-full">
                                <i class="fa-solid fa-link absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                                <input
                                    type="url"
                                    id="googleMapsUrl"
                                    name="google_maps_url"
                                    value="{{ old('google_maps_url', $venue->google_maps_url) }}"
                                    placeholder="https://maps.app.goo.gl/..."
                                    class="w-full h-[48px] bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Cabang Olahraga & Karakteristik -->
            <div class="space-y-4 pt-2">

                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                        2
                    </span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Cabang Olahraga & Karakteristik Lapangan
                    </h3>
                </div>

                <!-- Kategori Lapangan -->
                <div class="space-y-2">
                    <label class="block font-bold text-slate-800">
                        Kategori Lapangan Utama
                        <span class="text-rose-500">*</span>
                    </label>

                    @php
                        $selectedSport = old('sport_type', $venue->sport_type ?: 'Padel');
                    @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Padel -->
                        <label class="cursor-pointer">
                            <input
                                type="radio"
                                name="sport_type"
                                value="Padel"
                                class="peer sr-only"
                                {{ $selectedSport === 'Padel' ? 'checked' : '' }}
                            >
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Padel Court</h4>
                                    <p class="text-[10px] text-slate-500">Khusus Lapangan Padel</p>
                                </div>
                            </div>
                        </label>

                        <!-- Tennis -->
                        <label class="cursor-pointer">
                            <input
                                type="radio"
                                name="sport_type"
                                value="Tennis"
                                class="peer sr-only"
                                {{ $selectedSport === 'Tennis' ? 'checked' : '' }}
                            >
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-baseball"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Tennis Court</h4>
                                    <p class="text-[10px] text-slate-500">Khusus Lapangan Tenis</p>
                                </div>
                            </div>
                        </label>

                        <!-- Multi-Racquet -->
                        <label class="cursor-pointer">
                            <input
                                type="radio"
                                name="sport_type"
                                value="Multi-Racquet"
                                class="peer sr-only"
                                {{ $selectedSport === 'Multi-Racquet' || $selectedSport === 'Padel & Tennis' ? 'checked' : '' }}
                            >
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-trophy"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Multi-Racquet</h4>
                                    <p class="text-[10px] text-slate-500">Padel & Tennis Gabungan</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Tipe Arena & Jenis Permukaan -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <!-- Tipe Arena -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Tipe Arena Lapangan
                        </label>
                        @php
                            $selectedArena = old('tipe_arena', $venue->tipe_arena ?: 'Semi-Indoor');
                        @endphp
                        <div class="relative">
                            <select
                                id="arenaType"
                                name="tipe_arena"
                                class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 pr-10 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                                <option value="Indoor" @selected($selectedArena === 'Indoor')>Indoor (Full AC / Beratap Tertutup)</option>
                                <option value="Outdoor" @selected($selectedArena === 'Outdoor')>Outdoor (Terbuka)</option>
                                <option value="Semi-Indoor" @selected($selectedArena === 'Semi-Indoor')>Semi-Indoor (Beratap Kanopi)</option>
                                <option value="Rooftop" @selected($selectedArena === 'Rooftop')>Rooftop Arena</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Jenis Permukaan -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Jenis Permukaan / Karpet
                        </label>
                        @php
                            $selectedSurface = old('jenis_permukaan', $venue->jenis_permukaan ?: 'Artificial Grass / Rumput Sintetis (Padel)');
                            $isPredefined = in_array($selectedSurface, [
                                'Artificial Grass / Rumput Sintetis (Padel)',
                                'Hard Court / Acrylic (Tenis)',
                                'Clay / Tanah Liat',
                                'Grass / Rumput Alami',
                                'Taraflex / Vinyl Pro',
                                'Karpet Interlock'
                            ]);
                        @endphp
                        <div class="relative">
                            <select
                                id="surfaceType"
                                name="jenis_permukaan"
                                class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 pr-10 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                                <option value="Artificial Grass / Rumput Sintetis (Padel)" @selected($selectedSurface === 'Artificial Grass / Rumput Sintetis (Padel)')>Artificial Grass / Rumput Sintetis (Padel)</option>
                                <option value="Hard Court / Acrylic (Tenis)" @selected($selectedSurface === 'Hard Court / Acrylic (Tenis)')>Hard Court / Acrylic (Tenis)</option>
                                <option value="Clay / Tanah Liat" @selected($selectedSurface === 'Clay / Tanah Liat')>Clay / Tanah Liat</option>
                                <option value="Grass / Rumput Alami" @selected($selectedSurface === 'Grass / Rumput Alami')>Grass / Rumput Alami</option>
                                <option value="Taraflex / Vinyl Pro" @selected($selectedSurface === 'Taraflex / Vinyl Pro')>Taraflex / Vinyl Pro</option>
                                <option value="Karpet Interlock" @selected($selectedSurface === 'Karpet Interlock')>Karpet Interlock</option>
                                <option value="Other" @selected(!$isPredefined)>Lainnya (Tulis Manual)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Input Lainnya jika Other -->
                    <div id="otherSurfaceWrapper" class="{{ !$isPredefined ? '' : 'hidden' }} sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">Sebutkan Jenis Permukaan</label>
                        <input
                            type="text"
                            id="otherSurface"
                            name="jenis_permukaan_lainnya"
                            value="{{ !$isPredefined ? $selectedSurface : '' }}"
                            placeholder="Contoh: ModuCourt Cushioned Modular"
                            class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                        >
                        <p id="otherSurfaceError" class="hidden text-[10px] font-semibold text-rose-500">Silakan sebutkan jenis permukaan.</p>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Jadwal Operasional & Kontak PIC -->
            <div class="space-y-4 pt-2">

                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                        3
                    </span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Jadwal Operasional & Kontak Pengelola
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <!-- Jam Buka & Tutup (Modern Select Dropdown) -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-800">
                                Jam Operasional Reguler <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[10px] font-bold text-[#063B00] bg-[#EBF8D8] px-2 py-0.5 rounded-md border border-[#063B00]/10">
                                WIB
                            </span>
                        </div>

                        <input type="hidden" id="jamOperasionalHidden" name="jam_operasional" value="{{ old('jam_operasional', $venue->jam_operasional ?: '06:00 - 23:00 WIB') }}">

                        <div class="grid grid-cols-2 gap-3">
                            <!-- Jam Buka -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 flex items-center gap-1">
                                    <i class="fa-regular fa-clock text-slate-400"></i> Jam Buka
                                </span>
                                <div class="relative">
                                    <select
                                        id="jamBukaSelect"
                                        class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-3.5 pr-8 py-3 text-slate-900 font-bold text-xs focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs cursor-pointer"
                                    >
                                        @foreach($timeSlots as $slot)
                                            <option value="{{ $slot }}" @selected($jamBukaVal === $slot)>{{ $slot }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <!-- Jam Tutup -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-500 flex items-center gap-1">
                                    <i class="fa-regular fa-clock text-slate-400"></i> Jam Tutup
                                </span>
                                <div class="relative">
                                    <select
                                        id="jamTutupSelect"
                                        class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-3.5 pr-8 py-3 text-slate-900 font-bold text-xs focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs cursor-pointer"
                                    >
                                        @foreach($timeSlots as $slot)
                                            <option value="{{ $slot }}" @selected($jamTutupVal === $slot)>{{ $slot }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                        </div>

                        <p id="operatingHoursError" class="hidden text-[10px] font-semibold text-rose-500">Jam buka dan jam tutup wajib ditentukan.</p>
                    </div>

                    <!-- Hari Buka -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Hari Operasional
                        </label>
                        @php
                            $selectedHari = old('hari_buka', $venue->hari_buka ?: 'Setiap Hari (Senin - Minggu)');
                        @endphp
                        <div class="relative pt-4 sm:pt-0">
                            <select
                                id="hariBuka"
                                name="hari_buka"
                                class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 pr-10 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                                <option value="Setiap Hari (Senin - Minggu)" @selected($selectedHari === 'Setiap Hari (Senin - Minggu)')>Setiap Hari (Senin - Minggu)</option>
                                <option value="Senin - Jumat (Weekday Only)" @selected($selectedHari === 'Senin - Jumat (Weekday Only)')>Senin - Jumat (Weekday Only)</option>
                                <option value="Sabtu - Minggu (Weekend Only)" @selected($selectedHari === 'Sabtu - Minggu (Weekend Only)')>Sabtu - Minggu (Weekend Only)</option>
                                <option value="Selasa - Minggu (Senin Libur)" @selected($selectedHari === 'Selasa - Minggu (Senin Libur)')>Selasa - Minggu (Senin Libur)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Nama PIC -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Nama PIC / Pengelola Lapangan
                            <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-user-tie absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input
                                type="text"
                                id="picName"
                                name="nama_pic"
                                value="{{ old('nama_pic', $venue->nama_pic) }}"
                                maxlength="150"
                                placeholder="Contoh: Bpk. Bambang Pamungkas"
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                        </div>
                        <p id="picNameError" class="hidden text-[10px] font-semibold text-rose-500">Nama PIC wajib diisi.</p>
                    </div>

                    <!-- Nomor WhatsApp PIC -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Nomor WhatsApp / Hotline PIC
                            <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-brands fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-emerald-600 text-sm"></i>
                            <input
                                type="tel"
                                id="picPhone"
                                name="no_whatsapp"
                                value="{{ old('no_whatsapp', $venue->no_whatsapp) }}"
                                maxlength="50"
                                placeholder="Contoh: 081234567890"
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                        </div>
                        <p id="picPhoneError" class="hidden text-[10px] font-semibold text-rose-500">Nomor WhatsApp wajib diisi.</p>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Fasilitas & Catatan Operasional -->
            <div class="space-y-4 pt-2">

                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                        4
                    </span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Fasilitas & Catatan Tambahan
                    </h3>
                </div>

                <!-- Fasilitas Checkboxes -->
                <div class="space-y-2">
                    <label class="block font-bold text-slate-800">
                        Pilih Fasilitas yang Tersedia di Venue
                    </label>

                    @php
                        $availableFacilities = [
                            'Parkir Luas' => 'fa-square-parking',
                            'Toilet / Restroom' => 'fa-restroom',
                            'Ruang Ganti' => 'fa-shirt',
                            'Kantin / Coffee Shop' => 'fa-mug-hot',
                            'Musholla' => 'fa-mosque',
                            'Wi-Fi Gratis' => 'fa-wifi',
                            'Pro Shop / Raket Rental' => 'fa-shop',
                            'Loker Penyimpanan' => 'fa-lock',
                            'Shower Air Hangat' => 'fa-shower',
                            'Tribun Penonton' => 'fa-users',
                            'Penerangan Lampu Malam (LED)' => 'fa-lightbulb',
                            'First Aid / P3K' => 'fa-kit-medical',
                        ];
                    @endphp

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                        @foreach($availableFacilities as $facName => $facIcon)
                            @php
                                $isChecked = in_array($facName, $currentFasilitas) || in_array(strtolower($facName), array_map('strtolower', $currentFasilitas)) || in_array(explode(' ', $facName)[0], $currentFasilitas);
                            @endphp
                            <label class="cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="facilities[]"
                                    value="{{ $facName }}"
                                    class="peer sr-only"
                                    {{ $isChecked ? 'checked' : '' }}
                                >
                                <div class="p-3 rounded-xl border border-slate-200/80 bg-slate-50/50 peer-checked:bg-[#EBF8D8]/70 peer-checked:border-[#063B00] peer-checked:text-[#063B00] transition-all flex items-center gap-2.5 group hover:border-slate-300">
                                    <i class="fa-solid {{ $facIcon }} text-slate-400 peer-checked:text-[#063B00] text-xs"></i>
                                    <span class="font-bold text-[11px] text-slate-700 peer-checked:text-[#063B00] truncate">{{ $facName }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Catatan Khusus & Ketentuan Operasional -->
                <div class="space-y-1.5 pt-2">
                    <label class="block font-bold text-slate-800">
                        Catatan Khusus & Ketentuan Operasional
                        <span class="font-normal text-slate-400">(Opsional)</span>
                    </label>
                    <textarea
                        id="catatanOperasional"
                        name="catatan"
                        rows="3"
                        placeholder="Contoh: Wajib menggunakan sepatu khusus tennis/padel non-marking sole. Pembatalan sewa maksimal 24 jam sebelum jadwal sesi..."
                        class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                    >{{ old('catatan', $venue->catatan) }}</textarea>
                </div>
            </div>

            <!-- Form Action Buttons -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-end gap-3.5">
                <a
                    href="{{ route('venues.show', $venue->venue_id) }}"
                    class="w-full sm:w-auto px-6 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs text-center transition-colors cursor-pointer shrink-0"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    id="submitBtn"
                    class="w-full sm:w-auto min-w-[210px] px-6 py-3 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md shadow-[#063B00]/15 inline-flex items-center justify-center gap-2.5 transition-all cursor-pointer hover:scale-[1.01] shrink-0"
                >
                    <i class="fa-solid fa-floppy-disk text-xs text-[#A8E63A]"></i>
                    <span class="whitespace-nowrap">Simpan Perubahan Venue</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('venueEditForm');
    const venueName = document.getElementById('venueName');
    const venueNameError = document.getElementById('venueNameError');
    const venueAddress = document.getElementById('venueAddress');
    const venueAddressError = document.getElementById('venueAddressError');

    const kotaHidden = document.getElementById('kotaWilayah');
    const kotaButton = document.getElementById('kotaWilayahButton');
    const kotaSelected = document.getElementById('kotaWilayahSelected');
    const kotaArrow = document.getElementById('kotaWilayahArrow');
    const kotaDropdown = document.getElementById('kotaWilayahDropdown');
    const kotaSearch = document.getElementById('kotaWilayahSearch');
    const kotaOptions = document.getElementById('kotaWilayahOptions');
    const kotaError = document.getElementById('kotaWilayahError');

    const jamBukaSelect = document.getElementById('jamBukaSelect');
    const jamTutupSelect = document.getElementById('jamTutupSelect');
    const jamOperasionalHidden = document.getElementById('jamOperasionalHidden');
    const operatingHoursError = document.getElementById('operatingHoursError');

    const picName = document.getElementById('picName');
    const picNameError = document.getElementById('picNameError');
    const picPhone = document.getElementById('picPhone');
    const picPhoneError = document.getElementById('picPhoneError');

    const surfaceType = document.getElementById('surfaceType');
    const otherSurfaceWrapper = document.getElementById('otherSurfaceWrapper');
    const otherSurface = document.getElementById('otherSurface');
    const otherSurfaceError = document.getElementById('otherSurfaceError');

    // List Kota Indonesia
    const daftarKota = [
        "Jakarta", "Jakarta Selatan", "Jakarta Barat", "Jakarta Pusat", "Jakarta Timur", "Jakarta Utara",
        "Bandung", "Bandung Barat", "Kabupaten Bandung", "Bekasi", "Bogor", "Cimahi", "Cirebon",
        "Depok", "Garut", "Karawang", "Kuningan", "Majalengka", "Pangandaran", "Purwakarta",
        "Subang", "Sukabumi", "Sumedang", "Tasikmalaya", "Banjarnegara", "Banyumas", "Batang",
        "Blora", "Boyolali", "Brebes", "Cilacap", "Demak", "Grobogan", "Jepara", "Karanganyar",
        "Kebumen", "Kendal", "Klaten", "Kudus", "Magelang", "Pati", "Pekalongan", "Pemalang",
        "Purbalingga", "Purworejo", "Rembang", "Salatiga", "Semarang", "Surakarta (Solo)",
        "Surabaya", "Malang", "Sidoarjo", "Gresik", "Kediri", "Madiun", "Batu", "Blitar",
        "Denpasar", "Badung", "Gianyar", "Tabanan", "Medan", "Padang", "Palembang", "Pekanbaru",
        "Batam", "Bandar Lampung", "Pontianak", "Balikpapan", "Samarinda", "Banjarmasin", "Makassar", "Manado"
    ];

    function renderKotaOptions(filterText = '') {
        kotaOptions.innerHTML = '';
        const searchLower = filterText.toLowerCase().trim();
        const filtered = daftarKota.filter(k => k.toLowerCase().includes(searchLower));

        if (filtered.length === 0) {
            kotaOptions.innerHTML = '<div class="px-4 py-3 text-xs text-slate-400 text-center italic">Kota tidak ditemukan</div>';
            return;
        }

        filtered.forEach(kota => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-4 py-2.5 text-xs text-slate-700 hover:bg-[#EBF8D8] hover:text-[#063B00] transition-colors flex items-center justify-between group';
            btn.innerHTML = `<span>${kota}</span><i class="fa-solid fa-check text-xs text-[#063B00] opacity-0 group-hover:opacity-100 ${kotaHidden.value === kota ? '!opacity-100' : ''}"></i>`;
            btn.onclick = function () {
                kotaHidden.value = kota;
                kotaSelected.textContent = kota;
                kotaSelected.classList.remove('text-slate-400');
                kotaSelected.classList.add('text-slate-900', 'font-bold');
                closeKotaDropdown();
                clearError(kotaButton, kotaError);
            };
            kotaOptions.appendChild(btn);
        });
    }

    function openKotaDropdown() {
        kotaDropdown.classList.remove('hidden');
        kotaArrow.classList.add('rotate-180');
        renderKotaOptions();
        setTimeout(() => kotaSearch.focus(), 50);
    }

    function closeKotaDropdown() {
        kotaDropdown.classList.add('hidden');
        kotaArrow.classList.remove('rotate-180');
        kotaSearch.value = '';
    }

    if (kotaButton) {
        kotaButton.addEventListener('click', function (e) {
            e.stopPropagation();
            if (kotaDropdown.classList.contains('hidden')) {
                openKotaDropdown();
            } else {
                closeKotaDropdown();
            }
        });
    }

    if (kotaSearch) {
        kotaSearch.addEventListener('input', function () {
            renderKotaOptions(this.value);
        });
    }

    document.addEventListener('click', function (e) {
        if (kotaDropdown && !kotaDropdown.contains(e.target) && !kotaButton.contains(e.target)) {
            closeKotaDropdown();
        }
    });

    function syncOperatingHours() {
        if (jamBukaSelect && jamTutupSelect && jamOperasionalHidden) {
            jamOperasionalHidden.value = `${jamBukaSelect.value} - ${jamTutupSelect.value} WIB`;
        }
    }
    if (jamBukaSelect) jamBukaSelect.addEventListener('change', syncOperatingHours);
    if (jamTutupSelect) jamTutupSelect.addEventListener('change', syncOperatingHours);

    if (surfaceType) {
        surfaceType.addEventListener('change', function () {
            if (this.value === 'Other') {
                otherSurfaceWrapper.classList.remove('hidden');
            } else {
                otherSurfaceWrapper.classList.add('hidden');
            }
        });
    }

    function setError(inputEl, errorEl, message) {
        if (inputEl) {
            inputEl.classList.add('!border-rose-500', 'ring-2', 'ring-rose-500/20');
        }
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }
    }

    function clearError(inputEl, errorEl) {
        if (inputEl) {
            inputEl.classList.remove('!border-rose-500', 'ring-2', 'ring-rose-500/20');
        }
        if (errorEl) {
            errorEl.classList.add('hidden');
        }
    }

    [venueName, venueAddress, picName, picPhone].forEach(el => {
        if (el) {
            el.addEventListener('input', function () {
                this.classList.remove('!border-rose-500', 'ring-2', 'ring-rose-500/20');
                const err = document.getElementById(this.id + 'Error');
                if (err) err.classList.add('hidden');
            });
        }
    });

    form.addEventListener('submit', function (e) {
        let isValid = true;
        let firstInvalid = null;
        const hasScriptTag = (val) => /<[^>]*script/i.test(val) || /[<>]/.test(val);

        if (!venueName.value.trim()) {
            setError(venueName, venueNameError, 'Nama venue wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = venueName;
        } else if (hasScriptTag(venueName.value)) {
            setError(venueName, venueNameError, 'Nama venue tidak boleh mengandung script atau karakter khusus (< >).');
            isValid = false;
            if (!firstInvalid) firstInvalid = venueName;
        } else {
            clearError(venueName, venueNameError);
        }

        if (!venueAddress.value.trim()) {
            setError(venueAddress, venueAddressError, 'Alamat lengkap venue wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = venueAddress;
        } else {
            clearError(venueAddress, venueAddressError);
        }

        if (!kotaHidden.value.trim()) {
            setError(kotaButton, kotaError, 'Kota / Kabupaten wajib dipilih.');
            isValid = false;
            if (!firstInvalid) firstInvalid = kotaButton;
        } else {
            clearError(kotaButton, kotaError);
        }

        if (!jamBukaSelect.value || !jamTutupSelect.value) {
            setError(jamBukaSelect, operatingHoursError, 'Jam buka dan tutup wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = jamBukaSelect;
        } else {
            syncOperatingHours();
            clearError(jamBukaSelect, operatingHoursError);
        }

        if (!picName.value.trim()) {
            setError(picName, picNameError, 'Nama PIC wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = picName;
        } else {
            clearError(picName, picNameError);
        }

        if (!picPhone.value.trim()) {
            setError(picPhone, picPhoneError, 'Nomor WhatsApp wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = picPhone;
        } else {
            clearError(picPhone, picPhoneError);
        }

        if (surfaceType && surfaceType.value === 'Other' && otherSurface && !otherSurface.value.trim()) {
            setError(otherSurface, otherSurfaceError, 'Silakan sebutkan jenis permukaan.');
            isValid = false;
            if (!firstInvalid) firstInvalid = otherSurface;
        }

        if (!isValid) {
            e.preventDefault();
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof firstInvalid.focus === 'function') firstInvalid.focus();
            }
        }
    });
});
</script>
@endsection
