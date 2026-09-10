@extends('layouts.app')

@section('content')
<style>
    /* Expand native time picker touch area across the whole input */
    input[type="time"] {
        position: relative;
        cursor: pointer;
    }
    input[type="time"]::-webkit-calendar-picker-indicator {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        cursor: pointer;
        opacity: 0;
    }
</style>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8 relative">

    <!-- Ambient Glowing Orbs Background -->
    <div class="absolute w-96 h-96 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-12 -left-12 -z-10"></div>
    <div class="absolute w-96 h-96 bg-[#063B00]/10 rounded-full blur-3xl pointer-events-none top-1/2 -right-12 -z-10"></div>
    <div class="absolute w-80 h-80 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none -bottom-10 left-1/3 -z-10"></div>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/60 relative z-10">
        <div>
            <a href="{{ route('venues.index') }}"
                class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-[#063B00] transition-colors mb-3 group">
                <span class="w-7 h-7 rounded-xl bg-white/80 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-[#063B00] group-hover:text-white transition-all shadow-2xs">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                </span>
                Kembali ke Daftar Venue
            </a>

            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-extrabold uppercase tracking-wider">
                    Venue Owner Portal
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-medium text-slate-500">
                    Registrasi Arena Olahraga
                </span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                Form Pendaftaran Venue Baru
            </h1>

            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl leading-relaxed">
                Lengkapi informasi lapangan olahraga Anda untuk ditampilkan pada direktori Matcha dan diakses oleh ratusan komunitas Tennis & Padel.
            </p>
        </div>

        <div class="hidden sm:flex flex-col items-end gap-2">
            <div class="px-4 py-2 rounded-2xl bg-white/60 backdrop-blur-md border border-white/80 shadow-2xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-building-circle-check text-[#A8E63A]"></i>
                </div>

                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        Status Mitra
                    </p>
                    <p class="text-xs font-black text-[#063B00]">
                        Verified Venue Partner
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Glassmorphism Form Card -->
    <div class="backdrop-blur-2xl bg-white/80 border border-white/90 rounded-3xl p-6 sm:p-10 shadow-[0_20px_50px_rgba(6,59,0,0.06)] relative z-10 space-y-8">

        <!--
            novalidate digunakan agar browser tidak menampilkan
            popup bawaan "Please fill out this field".
            Validasi wajib ditangani oleh JavaScript di bawah.
        -->
        <form
            id="venueForm"
            action="{{ route('venues.store') }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-8 text-xs"
            novalidate
        >
            @csrf

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
                                value="{{ old('nama_venue') }}"
                                placeholder="Contoh: Gelora Racquet & Padel Club"
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                        </div>

                        <p id="venueNameError" class="hidden text-[10px] font-semibold text-rose-500">
                            Nama venue wajib diisi.
                        </p>
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
                            >{{ old('alamat') }}</textarea>
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
                value="{{ old('kota', old('kota_wilayah')) }}"
            >

            <button
                type="button"
                id="kotaWilayahButton"
                class="w-full h-[48px] flex items-center justify-between bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left"
            >

                <i class="fa-solid fa-city absolute left-4 text-slate-400 text-xs pointer-events-none"></i>

                <span
                    id="kotaWilayahSelected"
                    class="truncate text-slate-400"
                >
                    Pilih Kota / Kabupaten
                </span>

                <i
                    id="kotaWilayahArrow"
                    class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200"
                ></i>

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

                <div
                    id="kotaWilayahOptions"
                    class="max-h-64 overflow-y-auto py-1"
                >
                    <div class="px-4 py-3 text-sm text-slate-400">
                        Memuat Kota / Kabupaten...
                    </div>
                </div>

            </div>

        </div>

        <p
            id="kotaWilayahError"
            class="hidden text-[10px] font-semibold text-rose-500"
        >
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
                value="{{ old('google_maps_url') }}"
                placeholder="https://maps.app.goo.gl/..."
                class="w-full h-[48px] bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
            >

        </div>

    </div>

</div>
            </div>


            <!-- SECTION 2: Cabang Olahraga & Tipe Lapangan -->
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
                        Pilih Kategori Lapangan Utama
                        <span class="text-rose-500">*</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                        <!-- Padel -->
                        <label class="cursor-pointer">

                            <input
                                type="radio"
                                name="sport_type"
                                value="Padel"
                                class="peer sr-only"
                                {{ old('sport_type', 'Padel') === 'Padel' ? 'checked' : '' }}
                            >

                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">

                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                                </div>

                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">
                                        Padel Court
                                    </h4>

                                    <p class="text-[10px] text-slate-500">
                                        Khusus Lapangan Padel
                                    </p>
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
                                {{ old('sport_type') === 'Tennis' ? 'checked' : '' }}
                            >

                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">

                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-globe"></i>
                                </div>

                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">
                                        Tennis Court
                                    </h4>

                                    <p class="text-[10px] text-slate-500">
                                        Khusus Lapangan Tenis
                                    </p>
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
                                {{ old('sport_type') === 'Multi-Racquet' ? 'checked' : '' }}
                            >

                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">

                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-trophy"></i>
                                </div>

                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">
                                        Multi-Racquet
                                    </h4>

                                    <p class="text-[10px] text-slate-500">
                                        Padel & Tennis Gabungan
                                    </p>
                                </div>
                            </div>
                        </label>

                    </div>

                    <p id="sportTypeError" class="hidden text-[10px] font-semibold text-rose-500">
                        Pilih kategori lapangan utama.
                    </p>
                </div>


                <!-- Karakteristik Lapangan -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">

                    <!-- Jumlah Court -->
                    <div class="space-y-1.5">

                        <label class="block font-bold text-slate-800">
                            Jumlah Lapangan (Court)
                        </label>

                        <div class="relative">

                            <select
                                id="jumlahCourt"
                                name="jumlah_court"
                                class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 pr-10 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                                <option value="1" @selected(old('jumlah_court', '1') === '1')>
                                    1 Court
                                </option>

                                <option value="2" @selected(old('jumlah_court') === '2')>
                                    2 Courts
                                </option>

                                <option value="3" @selected(old('jumlah_court') === '3')>
                                    3 Courts
                                </option>

                                <option value="4" @selected(old('jumlah_court') === '4')>
                                    4 Courts
                                </option>

                                <option value="6" @selected(old('jumlah_court') === '6')>
                                    6+ Courts (Arena Besar)
                                </option>
                            </select>

                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>

                        </div>
                    </div>


                    <!-- Tipe Arena -->
                    <div class="space-y-1.5">

                        <label class="block font-bold text-slate-800">
                            Tipe Arena
                        </label>

                        <div class="relative">

                            <select
                                id="tipeArena"
                                name="tipe_arena"
                                class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 pr-10 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                                <option value="Semi-Indoor" @selected(old('tipe_arena', 'Semi-Indoor') === 'Semi-Indoor')>
                                    Semi-Indoor (Atap Pelindung)
                                </option>

                                <option value="Indoor" @selected(old('tipe_arena') === 'Indoor')>
                                    Indoor (Full AC / Tertutup)
                                </option>

                                <option value="Outdoor" @selected(old('tipe_arena') === 'Outdoor')>
                                    Outdoor (Terbuka)
                                </option>
                            </select>

                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>

                        </div>
                    </div>


                    <!-- Jenis Permukaan -->
                    <div class="space-y-1.5">

                        <label class="block font-bold text-slate-800">
                            Jenis Permukaan
                        </label>

                        <div class="relative">

                            <select
                                id="surfaceType"
                                name="jenis_permukaan"
                                class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 pr-10 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                                <option value="Artificial Turf" @selected(old('jenis_permukaan', 'Artificial Turf') === 'Artificial Turf')>
                                    Artificial Turf (Rumput Sintetis Padel)
                                </option>

                                <option value="Hard Court" @selected(old('jenis_permukaan') === 'Hard Court')>
                                    Hard Court (Plexipave / Acrylic)
                                </option>

                                <option value="Clay" @selected(old('jenis_permukaan') === 'Clay')>
                                    Clay Court (Tanah Liat)
                                </option>

                                <option value="Grass" @selected(old('jenis_permukaan') === 'Grass')>
                                    Grass Court (Rumput Alami)
                                </option>

                                <option value="Other" @selected(old('jenis_permukaan') === 'Other')>
                                    Lainnya
                                </option>
                            </select>

                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>

                        </div>


                        <!-- Input jika memilih Lainnya -->
                        <div
                            id="otherSurfaceWrapper"
                            class="{{ old('jenis_permukaan') === 'Other' ? '' : 'hidden' }} mt-2"
                        >

                            <label
                                for="otherSurface"
                                class="block text-[10px] font-bold text-slate-700 mb-1"
                            >
                                Sebutkan Jenis Permukaan
                            </label>

                            <input
                                type="text"
                                id="otherSurface"
                                name="jenis_permukaan_lainnya"
                                value="{{ old('jenis_permukaan_lainnya') }}"
                                placeholder="Contoh: Rubber Court"
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >

                            <p
                                id="otherSurfaceError"
                                class="hidden text-[10px] font-semibold text-rose-500 mt-1"
                            >
                                Silakan sebutkan jenis permukaan.
                            </p>

                        </div>

                    </div>

                </div>
            </div>


            <!-- SECTION 3: Jam Operasional & Kontak Pengelola -->
            <div class="space-y-4 pt-2">

                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">

                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                        3
                    </span>

                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Jam Operasional & Kontak Pengelola
                    </h3>

                </div>


                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <!-- Jam Operasional (Dibagi 2: Jam Buka & Jam Tutup) -->
                    <div class="space-y-1.5">

                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-800">
                                Jam Operasional Reguler
                                <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">
                                WIB
                            </span>
                        </div>

                        <!-- Hidden input agar format gabungan "06:00 - 23:00 WIB" tetap terkirim rapi -->
                        <input
                            type="hidden"
                            id="operatingHours"
                            name="jam_operasional"
                            value="{{ old('jam_operasional', (old('jam_buka', '06:00') . ' - ' . old('jam_tutup', '23:00') . ' WIB')) }}"
                        >

                        <div class="grid grid-cols-2 gap-2.5">

                            <!-- Jam Buka -->
                            <div class="relative group cursor-pointer" onclick="try{document.getElementById('jamBuka').showPicker()}catch(e){}">
                                <label for="jamBuka" class="absolute left-3.5 top-1.5 text-[9px] font-extrabold uppercase tracking-wider text-slate-400 pointer-events-none z-10">
                                    Buka
                                </label>

                                <input
                                    type="time"
                                    id="jamBuka"
                                    name="jam_buka"
                                    value="{{ old('jam_buka', '06:00') }}"
                                    class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-3.5 pr-8 pt-4 pb-1.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs cursor-pointer"
                                >

                                <i class="fa-regular fa-clock absolute right-3 bottom-2.5 text-slate-400 text-xs pointer-events-none group-hover:text-[#063B00] transition-colors"></i>
                            </div>

                            <!-- Jam Tutup -->
                            <div class="relative group cursor-pointer" onclick="try{document.getElementById('jamTutup').showPicker()}catch(e){}">
                                <label for="jamTutup" class="absolute left-3.5 top-1.5 text-[9px] font-extrabold uppercase tracking-wider text-slate-400 pointer-events-none z-10">
                                    Tutup
                                </label>

                                <input
                                    type="time"
                                    id="jamTutup"
                                    name="jam_tutup"
                                    value="{{ old('jam_tutup', '23:00') }}"
                                    class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-3.5 pr-8 pt-4 pb-1.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs cursor-pointer"
                                >

                                <i class="fa-regular fa-clock absolute right-3 bottom-2.5 text-slate-400 text-xs pointer-events-none group-hover:text-[#063B00] transition-colors"></i>
                            </div>

                        </div>

                        <p id="operatingHoursError" class="hidden text-[10px] font-semibold text-rose-500">
                            Jam buka dan jam tutup wajib ditentukan.
                        </p>

                    </div>


                    <!-- Hari Operasional (Dropdown Pilihan) -->
                    <div class="space-y-1.5">

                        <label for="openingDays" class="block font-bold text-slate-800">
                            Hari Operasional
                        </label>

                        <div class="relative">

                            <select
                                id="openingDays"
                                name="hari_buka"
                                class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-10 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >
                                <option value="Setiap Hari (Senin - Minggu)" @selected(old('hari_buka', 'Setiap Hari (Senin - Minggu)') === 'Setiap Hari (Senin - Minggu)')>
                                    Setiap Hari (Senin - Minggu)
                                </option>

                                <option value="Senin - Sabtu (Minggu Libur)" @selected(old('hari_buka') === 'Senin - Sabtu (Minggu Libur)')>
                                    Senin - Sabtu (Minggu Libur)
                                </option>

                                <option value="Senin - Jumat (Hari Kerja)" @selected(old('hari_buka') === 'Senin - Jumat (Hari Kerja)')>
                                    Senin - Jumat (Hari Kerja Saja)
                                </option>

                                <option value="Selasa - Minggu (Senin Libur)" @selected(old('hari_buka') === 'Selasa - Minggu (Senin Libur)')>
                                    Selasa - Minggu (Senin Libur)
                                </option>

                                <option value="Sabtu & Minggu (Weekend Saja)" @selected(old('hari_buka') === 'Sabtu & Minggu (Weekend Saja)')>
                                    Sabtu & Minggu (Weekend Saja)
                                </option>
                            </select>

                            <i class="fa-regular fa-calendar-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>

                        </div>

                    </div>


                    <!-- Nama PIC (Default Kosong) -->
                    <div class="space-y-1.5">

                        <label for="picName" class="block font-bold text-slate-800">
                            Nama PIC Venue / Pengelola
                            <span class="text-rose-500">*</span>
                        </label>

                        <div class="relative">

                            <i class="fa-solid fa-user-tie absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>

                            <input
                                type="text"
                                id="picName"
                                name="nama_pic"
                                value="{{ old('nama_pic') }}"
                                placeholder="Contoh: Budi Santoso"
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >

                        </div>

                        <p id="picNameError" class="hidden text-[10px] font-semibold text-rose-500">
                            Nama PIC wajib diisi.
                        </p>

                    </div>


                    <!-- Nomor WhatsApp (Default Kosong) -->
                    <div class="space-y-1.5">

                        <label for="picPhone" class="block font-bold text-slate-800">
                            Nomor WhatsApp PIC Venue
                            <span class="text-rose-500">*</span>
                        </label>

                        <div class="relative">

                            <i class="fa-brands fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-emerald-600 text-xs font-bold"></i>

                            <input
                                type="tel"
                                id="picPhone"
                                name="no_whatsapp"
                                value="{{ old('no_whatsapp') }}"
                                placeholder="Contoh: 081234567890"
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >

                        </div>

                        <p id="picPhoneError" class="hidden text-[10px] font-semibold text-rose-500">
                            Nomor WhatsApp wajib diisi.
                        </p>

                    </div>


                    <!-- Catatan Jam Operasional Khusus & Ketentuan Lapangan -->
                    <div class="sm:col-span-2 space-y-1.5">

                        <div class="flex items-center justify-between">
                            <label for="catatanOperasional" class="block font-bold text-slate-800">
                                Catatan Jam Operasional Khusus & Ketentuan Lapangan
                            </label>
                            <span class="text-[10px] font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">
                                Opsional
                            </span>
                        </div>

                        <div class="relative">
                            <i class="fa-solid fa-clock-rotate-left absolute left-4 top-3.5 text-slate-400 text-xs"></i>

                            <textarea
                                id="catatanOperasional"
                                name="catatan"
                                rows="2"
                                placeholder="Contoh: Khusus hari Jumat buka mulai pukul 13.00 WIB (setelah sholat Jumat). Lapangan outdoor maintenance setiap Selasa jam 08.00 - 10.00."
                                class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-2.5 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                            >{{ old('catatan', old('maintenance_note')) }}</textarea>
                        </div>

                        <p class="text-[10px] text-slate-400">
                            Gunakan untuk menginfokan penyesuaian jam buka (misal: hari Jumat, hari libur nasional) atau jadwal maintenance rutin.
                        </p>

                    </div>

                </div>
            </div>


            <!-- SECTION 4: Fasilitas Tersedia -->
            <div class="space-y-4 pt-2">

                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">

                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                        4
                    </span>

                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Fasilitas yang Tersedia
                    </h3>

                </div>


                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">

                    @php
                        $facilities = [
                            ['icon' => 'fa-lightbulb', 'name' => 'Lampu Malam (LED)'],
                            ['icon' => 'fa-shower', 'name' => 'Shower & Toilet'],
                            ['icon' => 'fa-mug-hot', 'name' => 'Kantin / Cafe'],
                            ['icon' => 'fa-car', 'name' => 'Parkir Mobil/Motor'],
                            ['icon' => 'fa-snowflake', 'name' => 'AC Waiting Lounge'],
                            ['icon' => 'fa-table-tennis-paddle-ball', 'name' => 'Rental Raket & Bola'],
                            ['icon' => 'fa-lock', 'name' => 'Loker Barang'],
                            ['icon' => 'fa-wifi', 'name' => 'Free High-speed WiFi'],
                        ];
                    @endphp


                    @foreach($facilities as $index => $fac)

                        <label class="cursor-pointer select-none">

                            <input
                                type="checkbox"
                                name="facilities[]"
                                value="{{ $fac['name'] }}"
                                class="peer sr-only"
                                {{ is_array(old('facilities')) && in_array($fac['name'], old('facilities')) ? 'checked' : '' }}
                            >

                            <div class="p-3 rounded-2xl border border-slate-200/70 bg-slate-50/50 hover:bg-slate-100/70 hover:border-slate-300 peer-checked:bg-[#EBF8D8]/70 peer-checked:border-[#063B00] peer-checked:text-[#063B00] transition-all flex items-center gap-2.5">

                                <div class="w-7 h-7 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center text-xs text-slate-500 shadow-2xs">
                                    <i class="fa-solid {{ $fac['icon'] }}"></i>
                                </div>

                                <span class="text-[11px] font-bold text-slate-700">
                                    {{ $fac['name'] }}
                                </span>

                            </div>

                        </label>

                    @endforeach

                </div>
            </div>


            <!-- SECTION 5: Foto Banner & Galeri Lapangan (Google Maps Style) -->
            <div class="space-y-4 pt-2">

                <div class="flex items-center justify-between pb-2 border-b border-slate-100">

                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                            5
                        </span>

                        <div>
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                                Foto Banner & Galeri Lapangan
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                Upload foto banner utama dan foto suasana lapangan / fasilitas (ala Google Maps).
                            </p>
                        </div>
                    </div>

                    <span class="text-[10px] font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">
                        Opsional (Maks. 10 Foto)
                    </span>

                </div>

                <!-- Hidden Native Multi-File Input for Form Submission -->
                <input
                    type="file"
                    id="venuePhotosInput"
                    name="fotos[]"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="hidden"
                >

                <!-- Helper Picker Input (Never Clears Form Submission Input) -->
                <input
                    type="file"
                    id="tempPhotoPicker"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="hidden"
                >

                <!-- Dropzone Box -->
                <div
                    id="photoDropzone"
                    class="border-2 border-dashed border-slate-200 hover:border-[#063B00] rounded-3xl p-6 bg-slate-50/50 hover:bg-[#EBF8D8]/20 transition-all cursor-pointer group relative"
                >
                    <!-- Default Placeholder View -->
                    <div id="photoPlaceholder" class="space-y-3 text-center py-4">
                        <div class="w-14 h-14 rounded-3xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 group-hover:text-[#063B00] group-hover:scale-110 transition-all mx-auto shadow-xs">
                            <i class="fa-solid fa-images text-xl"></i>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-800">
                                Klik untuk upload atau drag & drop beberapa foto lapangan
                            </p>

                            <p class="text-[10px] text-slate-400 mt-1">
                                Format JPG, JPEG, PNG, atau WEBP (maksimal 5MB/foto). Foto pertama akan menjadi Banner Utama.
                            </p>
                        </div>

                        <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white border border-slate-200/80 text-slate-600 text-[10px] font-bold shadow-2xs">
                            <i class="fa-solid fa-arrow-up-from-bracket text-[9px] text-[#063B00]"></i>
                            Pilih 1 atau beberapa foto sekaligus
                        </div>
                    </div>

                    <!-- Selected Multi-Image Gallery Grid -->
                    <div id="photoGalleryWrapper" class="hidden space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200/60">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-800">
                                    Foto Dipilih (<span id="photoCount">0</span>/10)
                                </span>
                                <span class="text-[10px] text-slate-500 hidden sm:inline italic">
                                    • Foto pertama adalah Banner Utama
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    id="addMorePhotosBtn"
                                    class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 hover:border-[#063B00] text-slate-700 hover:text-[#063B00] font-bold text-[11px] shadow-2xs transition-all flex items-center gap-1.5 cursor-pointer"
                                >
                                    <i class="fa-solid fa-plus text-xs text-[#063B00]"></i>
                                    Tambah Foto
                                </button>

                                <button
                                    type="button"
                                    id="clearAllPhotosBtn"
                                    class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-[11px] transition-all flex items-center gap-1.5 cursor-pointer"
                                >
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                    Hapus Semua
                                </button>
                            </div>
                        </div>

                        <div id="photoGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            <!-- Cards will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <p id="photoError" class="hidden text-[10px] font-semibold text-rose-500">
                    Format file tidak didukung atau ukuran melebihi 5MB.
                </p>

                @error('fotos')
                    <p class="text-[10px] font-semibold text-rose-500">{{ $message }}</p>
                @enderror
                @error('fotos.*')
                    <p class="text-[10px] font-semibold text-rose-500">{{ $message }}</p>
                @enderror

            </div>


            <!-- Action Buttons -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">

                <p class="text-[11px] text-slate-500 flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-[#063B00]"></i>
                    Informasi venue akan ditinjau oleh tim kurasi Matcha sebelum tampil publik.
                </p>


                <div class="flex items-center gap-3 w-full sm:w-auto shrink-0">

                    <button
                        type="reset"
                        class="px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all whitespace-nowrap w-full sm:w-auto"
                    >
                        Reset Form
                    </button>


                    <button
                        type="submit"
                        class="px-7 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap shrink-0 w-full sm:w-auto"
                    >
                        <i class="fa-solid fa-paper-plane text-[#A8E63A]"></i>

                        <span class="whitespace-nowrap">
                            Daftarkan Venue Sekarang
                        </span>
                    </button>

                </div>

            </div>

        </form>
    </div>
</div>


<!-- ========================================================= -->
<!-- CUSTOM FORM VALIDATION & SEARCHABLE KOTA / KABUPATEN -->
<!-- ========================================================= -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('venueForm');

    // Field references
    const venueName = document.getElementById('venueName');
    const venueNameError = document.getElementById('venueNameError');

    const venueAddress = document.getElementById('venueAddress');
    const venueAddressError = document.getElementById('venueAddressError');

    const kotaWrapper = document.getElementById('kotaWilayahWrapper');
    const kotaButton = document.getElementById('kotaWilayahButton');
    const kotaDropdown = document.getElementById('kotaWilayahDropdown');
    const kotaSearch = document.getElementById('kotaWilayahSearch');
    const kotaOptions = document.getElementById('kotaWilayahOptions');
    const kotaHidden = document.getElementById('kotaWilayah');
    const kotaSelected = document.getElementById('kotaWilayahSelected');
    const kotaArrow = document.getElementById('kotaWilayahArrow');
    const kotaError = document.getElementById('kotaWilayahError');

    const jamBuka = document.getElementById('jamBuka');
    const jamTutup = document.getElementById('jamTutup');
    const operatingHours = document.getElementById('operatingHours');
    const operatingHoursError = document.getElementById('operatingHoursError');

    const picName = document.getElementById('picName');
    const picNameError = document.getElementById('picNameError');

    const picPhone = document.getElementById('picPhone');
    const picPhoneError = document.getElementById('picPhoneError');

    const surfaceType = document.getElementById('surfaceType');
    const otherSurface = document.getElementById('otherSurface');
    const otherSurfaceWrapper = document.getElementById('otherSurfaceWrapper');
    const otherSurfaceError = document.getElementById('otherSurfaceError');

    // Helper: Show error with red styling
    function setError(input, errorElement, message) {
        if (!input || !errorElement) return false;
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
        input.classList.remove('border-slate-200/80');
        input.classList.add('!border-rose-500', 'ring-2', 'ring-rose-500/20');
        return false;
    }

    // Helper: Clear error
    function clearError(input, errorElement) {
        if (!input || !errorElement) return;
        errorElement.classList.add('hidden');
        input.classList.remove('!border-rose-500', 'ring-2', 'ring-rose-500/20');
        input.classList.add('border-slate-200/80');
    }

    // Sinkronkan jam buka dan tutup ke hidden input
    function syncOperatingHours() {
        if (jamBuka && jamTutup && operatingHours) {
            if (jamBuka.value && jamTutup.value) {
                operatingHours.value = `${jamBuka.value} - ${jamTutup.value} WIB`;
                clearError(jamBuka, operatingHoursError);
                clearError(jamTutup, operatingHoursError);
            }
        }
    }

    // Kota / Wilayah Validation
    function validateKota() {
        if (!kotaHidden.value.trim()) {
            return setError(kotaButton, kotaError, 'Kota / Kabupaten wajib dipilih.');
        }
        clearError(kotaButton, kotaError);
        return true;
    }

    // Live error clearing saat user mengetik
    if (venueName) {
        venueName.addEventListener('input', () => clearError(venueName, venueNameError));
    }
    if (venueAddress) {
        venueAddress.addEventListener('input', () => clearError(venueAddress, venueAddressError));
    }
    if (jamBuka) {
        jamBuka.addEventListener('input', syncOperatingHours);
        jamBuka.addEventListener('change', syncOperatingHours);
        jamBuka.addEventListener('click', function () {
            if (typeof this.showPicker === 'function') {
                try { this.showPicker(); } catch (e) {}
            }
        });
    }
    if (jamTutup) {
        jamTutup.addEventListener('input', syncOperatingHours);
        jamTutup.addEventListener('change', syncOperatingHours);
        jamTutup.addEventListener('click', function () {
            if (typeof this.showPicker === 'function') {
                try { this.showPicker(); } catch (e) {}
            }
        });
    }
    if (picName) {
        picName.addEventListener('input', () => clearError(picName, picNameError));
    }
    if (picPhone) {
        picPhone.addEventListener('input', () => clearError(picPhone, picPhoneError));
    }
    if (otherSurface) {
        otherSurface.addEventListener('input', () => clearError(otherSurface, otherSurfaceError));
    }

    // Surface Type "Other" toggle
    function toggleOtherSurface() {
        if (!surfaceType || !otherSurfaceWrapper) return;
        if (surfaceType.value === 'Other') {
            otherSurfaceWrapper.classList.remove('hidden');
        } else {
            otherSurfaceWrapper.classList.add('hidden');
            if (otherSurface) {
                otherSurface.value = '';
                clearError(otherSurface, otherSurfaceError);
            }
        }
    }
    if (surfaceType) {
        surfaceType.addEventListener('change', toggleOtherSurface);
        toggleOtherSurface();
    }

    // =========================================================
    // KOTA / KABUPATEN SE-INDONESIA DROPDOWN & SEARCH
    // =========================================================
    let regenciesList = [];

    async function loadRegencies() {
        try {
            // Mengambil data lengkap 514 Kota/Kabupaten Indonesia secara instan
            const res = await fetch("{{ asset('data/indonesia-regencies.json') }}");
            if (!res.ok) throw new Error('Local file failed');
            regenciesList = await res.json();
        } catch (e) {
            console.warn('Gagal memuat local data, mencoba fallback ke API...', e);
            try {
                const provRes = await fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json');
                const provs = await provRes.json();
                const reqs = provs.map(p => 
                    fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${p.id}.json`)
                        .then(r => r.json())
                        .then(regs => regs.map(r => ({ name: r.name, province: p.name })))
                );
                const all = await Promise.all(reqs);
                regenciesList = all.flat();
            } catch (err) {
                if (kotaOptions) {
                    kotaOptions.innerHTML = `
                        <div class="px-4 py-3 text-xs text-rose-500">
                            Gagal memuat daftar kota. Silakan coba beberapa saat lagi.
                        </div>
                    `;
                }
                return;
            }
        }

        renderRegencies(regenciesList);

        // Restore old value jika ada (misal setelah submit form redirect kembali)
        const oldValue = kotaHidden.value.trim();
        if (oldValue) {
            const found = regenciesList.find(item => item.name.toLowerCase() === oldValue.toLowerCase());
            if (found) {
                selectKota(found.name);
            } else {
                selectKota(oldValue);
            }
        }
    }

    function selectKota(name) {
        kotaHidden.value = name;
        kotaSelected.textContent = name;
        kotaSelected.classList.remove('text-slate-400');
        kotaSelected.classList.add('text-slate-900', 'font-bold');
        clearError(kotaButton, kotaError);
        closeDropdown();
    }

    function renderRegencies(list) {
        if (!kotaOptions) return;
        kotaOptions.innerHTML = '';

        if (!list || list.length === 0) {
            kotaOptions.innerHTML = `
                <div class="px-4 py-4 text-xs text-slate-400 text-center">
                    Kota / Kabupaten tidak ditemukan.
                </div>
            `;
            return;
        }

        // Tampilkan maksimal 80 item agar render DOM super cepat saat search luas
        const isFiltered = list.length !== regenciesList.length;
        const displayList = isFiltered ? list.slice(0, 100) : list.slice(0, 70);

        // Grouping berdasarkan provinsi
        const grouped = {};
        displayList.forEach(item => {
            const prov = item.province || 'Indonesia';
            if (!grouped[prov]) grouped[prov] = [];
            grouped[prov].push(item);
        });

        Object.keys(grouped).forEach(prov => {
            const provHeader = document.createElement('div');
            provHeader.className = 'px-4 pt-2.5 pb-1 text-[10px] font-black uppercase tracking-wider text-emerald-900/60 bg-slate-50/90 sticky top-0 backdrop-blur-xs border-y border-slate-100';
            provHeader.textContent = prov;
            kotaOptions.appendChild(provHeader);

            grouped[prov].forEach(item => {
                const optBtn = document.createElement('button');
                optBtn.type = 'button';
                const isSelected = kotaHidden.value.toLowerCase() === item.name.toLowerCase();

                optBtn.className = `w-full text-left px-4 py-2 text-xs transition-colors flex items-center justify-between ${
                    isSelected 
                        ? 'bg-[#EBF8D8] text-[#063B00] font-bold' 
                        : 'text-slate-700 hover:bg-[#F0F8E8] hover:text-[#063B00]'
                }`;

                optBtn.innerHTML = `
                    <span>${item.name}</span>
                    ${isSelected ? '<i class="fa-solid fa-check text-[#063B00] text-xs"></i>' : ''}
                `;

                optBtn.addEventListener('click', () => {
                    selectKota(item.name);
                    kotaSearch.value = '';
                });

                kotaOptions.appendChild(optBtn);
            });
        });

        if (list.length > displayList.length) {
            const notice = document.createElement('div');
            notice.className = 'px-4 py-2 text-[10px] text-center text-slate-400 italic border-t border-slate-100';
            notice.textContent = `Menampilkan ${displayList.length} dari ${list.length} wilayah. Ketik nama kota untuk hasil lebih spesifik.`;
            kotaOptions.appendChild(notice);
        }
    }

    function openDropdown() {
        if (!kotaDropdown) return;
        kotaDropdown.classList.remove('hidden');
        if (kotaArrow) kotaArrow.classList.add('rotate-180');
        setTimeout(() => {
            if (kotaSearch) kotaSearch.focus();
        }, 50);
    }

    function closeDropdown() {
        if (!kotaDropdown) return;
        kotaDropdown.classList.add('hidden');
        if (kotaArrow) kotaArrow.classList.remove('rotate-180');
    }

    if (kotaButton) {
        kotaButton.addEventListener('click', (e) => {
            e.stopPropagation();
            if (kotaDropdown.classList.contains('hidden')) {
                openDropdown();
            } else {
                closeDropdown();
            }
        });
    }

    if (kotaSearch) {
        kotaSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            if (!query) {
                renderRegencies(regenciesList);
                return;
            }
            const filtered = regenciesList.filter(item => 
                item.name.toLowerCase().includes(query) || 
                (item.province && item.province.toLowerCase().includes(query))
            );
            renderRegencies(filtered);
        });
    }

    document.addEventListener('click', function (e) {
        if (kotaWrapper && !kotaWrapper.contains(e.target)) {
            closeDropdown();
        }
    });

    loadRegencies();

    // =========================================================
    // FOTO VENUE & GALERI LAPANGAN (MULTI-UPLOAD PREVIEW)
    // =========================================================
    const photoInput = document.getElementById('venuePhotosInput');
    const tempPhotoPicker = document.getElementById('tempPhotoPicker');
    const photoDropzone = document.getElementById('photoDropzone');
    const photoPlaceholder = document.getElementById('photoPlaceholder');
    const photoGalleryWrapper = document.getElementById('photoGalleryWrapper');
    const photoGrid = document.getElementById('photoGrid');
    const photoCount = document.getElementById('photoCount');
    const photoError = document.getElementById('photoError');

    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5MB
    const MAX_FILES = 10;
    let selectedFiles = [];

    function syncInputFiles() {
        if (!photoInput) return;
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        photoInput.files = dt.files;
    }

    function addFiles(files) {
        if (photoError) photoError.classList.add('hidden');
        let hasError = false;
        let errorMessage = '';

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (selectedFiles.length >= MAX_FILES) {
                hasError = true;
                errorMessage = `Maksimal ${MAX_FILES} foto yang dapat diunggah.`;
                break;
            }

            const fileExt = file.name.split('.').pop().toLowerCase();
            const isValidExt = ALLOWED_EXTENSIONS.includes(fileExt);
            const isValidMime = file.type.startsWith('image/');

            if (!isValidExt || !isValidMime) {
                hasError = true;
                errorMessage = 'Beberapa file tidak didukung. Harap pilih format JPG, JPEG, PNG, atau WEBP.';
                continue;
            }

            if (file.size > MAX_SIZE_BYTES) {
                hasError = true;
                errorMessage = 'Beberapa file melebihi batas ukuran 5MB.';
                continue;
            }

            const isDuplicate = selectedFiles.some(f => f.name === file.name && f.size === file.size);
            if (!isDuplicate) {
                selectedFiles.push(file);
            }
        }

        if (hasError && photoError) {
            photoError.textContent = errorMessage;
            photoError.classList.remove('hidden');
        }

        syncInputFiles();
        renderGallery();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        syncInputFiles();
        renderGallery();
    }

    function clearAllFiles() {
        selectedFiles = [];
        syncInputFiles();
        renderGallery();
        if (photoError) photoError.classList.add('hidden');
    }

    function renderGallery() {
        if (photoCount) photoCount.textContent = selectedFiles.length;

        if (selectedFiles.length === 0) {
            if (photoPlaceholder) photoPlaceholder.classList.remove('hidden');
            if (photoGalleryWrapper) photoGalleryWrapper.classList.add('hidden');
            if (photoGrid) photoGrid.innerHTML = '';
            return;
        }

        if (photoPlaceholder) photoPlaceholder.classList.add('hidden');
        if (photoGalleryWrapper) photoGalleryWrapper.classList.remove('hidden');
        if (!photoGrid) return;

        photoGrid.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const card = document.createElement('div');
            card.className = `relative group rounded-2xl overflow-hidden border shadow-xs bg-white transition-all ${
                index === 0 ? 'border-[#063B00] ring-2 ring-[#A8E63A]/40' : 'border-slate-200'
            }`;

            const sizeKb = (file.size / 1024).toFixed(0);
            const sizeStr = file.size > 1024 * 1024 
                ? `${(file.size / (1024 * 1024)).toFixed(1)} MB` 
                : `${sizeKb} KB`;

            const isMain = index === 0;

            card.innerHTML = `
                <div class="h-28 sm:h-32 w-full overflow-hidden bg-slate-100 relative">
                    <img src="" alt="${file.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" id="previewImg_${index}">
                    
                    <div class="absolute top-2 left-2">
                        ${isMain 
                            ? '<span class="px-2 py-0.5 rounded-md bg-[#063B00] text-white text-[9px] font-black uppercase tracking-wider shadow-xs flex items-center gap-1"><i class="fa-solid fa-star text-[#A8E63A] text-[8px]"></i> Banner Utama</span>' 
                            : `<span class="px-2 py-0.5 rounded-md bg-black/60 text-white text-[9px] font-bold backdrop-blur-xs">Galeri #${index + 1}</span>`
                        }
                    </div>

                    <button type="button" class="delete-photo-btn absolute top-2 right-2 w-7 h-7 rounded-xl bg-white/90 hover:bg-rose-500 hover:text-white text-slate-700 flex items-center justify-center text-xs shadow-xs transition-all cursor-pointer" data-index="${index}" title="Hapus Foto">
                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                    </button>
                </div>

                <div class="p-2.5 bg-white flex items-center justify-between gap-1.5 border-t border-slate-100">
                    <p class="text-[10px] font-bold text-slate-700 truncate max-w-[90px]" title="${file.name}">${file.name}</p>
                    <span class="text-[9px] font-semibold text-slate-400 shrink-0">${sizeStr}</span>
                </div>
            `;

            const img = card.querySelector(`#previewImg_${index}`);
            const reader = new FileReader();
            reader.onload = e => { if (img) img.src = e.target.result; };
            reader.readAsDataURL(file);

            const delBtn = card.querySelector('.delete-photo-btn');
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeFile(index);
            });

            photoGrid.appendChild(card);
        });
    }

    function triggerPhotoPicker() {
        if (tempPhotoPicker) {
            tempPhotoPicker.click();
        } else if (photoInput) {
            photoInput.click();
        }
    }

    if (tempPhotoPicker) {
        tempPhotoPicker.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                addFiles(Array.from(this.files));
                this.value = ''; // Safe to reset helper picker
            }
        });
    }

    if (photoDropzone) {
        photoDropzone.addEventListener('click', (e) => {
            if (e.target.closest('#addMorePhotosBtn')) {
                e.stopPropagation();
                triggerPhotoPicker();
            } else if (e.target.closest('#clearAllPhotosBtn')) {
                e.stopPropagation();
                clearAllFiles();
            } else if (e.target.closest('.delete-photo-btn')) {
                // Handled in renderGallery
            } else if (selectedFiles.length > 0) {
                // Clicked inside gallery cards area
            } else {
                triggerPhotoPicker();
            }
        });

        // Drag & Drop
        ['dragenter', 'dragover'].forEach(eventName => {
            photoDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                photoDropzone.classList.add('!border-[#063B00]', 'bg-[#EBF8D8]/40');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            photoDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                photoDropzone.classList.remove('!border-[#063B00]', 'bg-[#EBF8D8]/40');
            });
        });

        photoDropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                addFiles(Array.from(dt.files));
            }
        });
    }

    // =========================================================
    // SUBMIT VALIDATION
    // =========================================================
    form.addEventListener('submit', function (event) {
        syncInputFiles(); // Pastikan file terpasang di input sebelum dikirim
        let isValid = true;
        let firstInvalid = null;

        // 1. Nama Venue
        if (!venueName.value.trim()) {
            setError(venueName, venueNameError, 'Nama venue wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = venueName;
        } else {
            clearError(venueName, venueNameError);
        }

        // 2. Alamat Venue
        if (!venueAddress.value.trim()) {
            setError(venueAddress, venueAddressError, 'Alamat lengkap venue wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = venueAddress;
        } else {
            clearError(venueAddress, venueAddressError);
        }

        // 3. Kota / Kabupaten
        if (!validateKota()) {
            isValid = false;
            if (!firstInvalid) firstInvalid = kotaButton;
        }

        // 4. Jam Operasional (Jam Buka & Tutup)
        if (!jamBuka.value || !jamTutup.value) {
            setError(jamBuka, operatingHoursError, 'Jam buka dan jam tutup wajib ditentukan.');
            setError(jamTutup, operatingHoursError, 'Jam buka dan jam tutup wajib ditentukan.');
            isValid = false;
            if (!firstInvalid) firstInvalid = jamBuka;
        } else {
            syncOperatingHours();
            clearError(jamBuka, operatingHoursError);
            clearError(jamTutup, operatingHoursError);
        }

        // 5. Nama PIC
        if (!picName.value.trim()) {
            setError(picName, picNameError, 'Nama PIC wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = picName;
        } else {
            clearError(picName, picNameError);
        }

        // 6. Nomor WhatsApp
        if (!picPhone.value.trim()) {
            setError(picPhone, picPhoneError, 'Nomor WhatsApp wajib diisi.');
            isValid = false;
            if (!firstInvalid) firstInvalid = picPhone;
        } else {
            clearError(picPhone, picPhoneError);
        }

        // 7. Jika jenis permukaan = Lainnya
        if (surfaceType && surfaceType.value === 'Other' && otherSurface) {
            if (!otherSurface.value.trim()) {
                setError(otherSurface, otherSurfaceError, 'Silakan sebutkan jenis permukaan.');
                isValid = false;
                if (!firstInvalid) firstInvalid = otherSurface;
            } else {
                clearError(otherSurface, otherSurfaceError);
            }
        }

        // Cegah submit dan scroll ke field pertama yang error
        if (!isValid) {
            event.preventDefault();
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof firstInvalid.focus === 'function') {
                    firstInvalid.focus();
                }
            }
        }
    });

    // =========================================================
    // RESET FORM HANDLER
    // =========================================================
    form.addEventListener('reset', function () {
        setTimeout(function () {
            const errorElements = form.querySelectorAll('[id$="Error"]');
            errorElements.forEach(function (error) {
                error.classList.add('hidden');
            });

            const redInputs = form.querySelectorAll('.\\!border-rose-500');
            redInputs.forEach(function (input) {
                input.classList.remove('!border-rose-500', 'ring-2', 'ring-rose-500/20');
                input.classList.add('border-slate-200/80');
            });

            kotaHidden.value = '';
            kotaSelected.textContent = 'Pilih Kota / Kabupaten';
            kotaSelected.classList.remove('text-slate-900', 'font-bold');
            kotaSelected.classList.add('text-slate-400');

            clearAllFiles();
            toggleOtherSurface();
        }, 0);
    });
});
</script>

@endsection