@extends('layouts.app')

@section('content')
<!-- Flatpickr CSS & Custom Matcha Theme (Compact & Refined) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .flatpickr-calendar {
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif !important;
        width: 272px !important;
        border-radius: 1rem !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 12px 28px -4px rgba(6, 59, 0, 0.1), 0 4px 10px rgba(0, 0, 0, 0.03) !important;
        padding: 6px !important;
        background: #ffffff !important;
    }
    .flatpickr-months {
        height: 32px !important;
        align-items: center !important;
    }
    .flatpickr-months .flatpickr-month {
        height: 32px !important;
        color: #063B00 !important;
    }
    .flatpickr-current-month {
        font-size: 13px !important;
        font-weight: 700 !important;
        padding-top: 4px !important;
        height: 28px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 4px !important;
    }
    .flatpickr-current-month .cur-month {
        font-weight: 700 !important;
        color: #063B00 !important;
        font-size: 13px !important;
    }
    .flatpickr-current-month input.cur-year {
        font-weight: 700 !important;
        color: #063B00 !important;
        font-size: 13px !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .flatpickr-months .flatpickr-prev-month,
    .flatpickr-months .flatpickr-next-month {
        height: 26px !important;
        width: 26px !important;
        padding: 5px !important;
        border-radius: 0.5rem !important;
        color: #64748b !important;
        top: 4px !important;
    }
    .flatpickr-months .flatpickr-prev-month:hover,
    .flatpickr-months .flatpickr-next-month:hover {
        background: #f1f5f9 !important;
        color: #063B00 !important;
    }
    .flatpickr-months .flatpickr-prev-month svg,
    .flatpickr-months .flatpickr-next-month svg {
        width: 11px !important;
        height: 11px !important;
    }
    .flatpickr-weekdays {
        height: 24px !important;
    }
    span.flatpickr-weekday {
        font-weight: 700 !important;
        color: #94a3b8 !important;
        font-size: 10px !important;
        text-transform: uppercase !important;
    }
    .flatpickr-days {
        width: 256px !important;
    }
    .dayContainer {
        min-width: 252px !important;
        max-width: 252px !important;
        width: 252px !important;
    }
    .flatpickr-day {
        max-width: 32px !important;
        height: 32px !important;
        line-height: 32px !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        border-radius: 0.5rem !important;
        color: #1e293b !important;
        margin: 2px !important;
        transition: all 0.15s ease !important;
    }
    .flatpickr-day:hover:not(.flatpickr-disabled):not(.selected) {
        background: #EBF8D8 !important;
        color: #063B00 !important;
    }
    .flatpickr-day.today {
        border-color: #063B00 !important;
        color: #063B00 !important;
    }
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: #063B00 !important;
        border-color: #063B00 !important;
        color: #ffffff !important;
        font-weight: 700 !important;
    }
    .flatpickr-day.flatpickr-disabled,
    .flatpickr-day.flatpickr-disabled:hover {
        color: #cbd5e1 !important;
        background: #f8fafc !important;
        cursor: not-allowed !important;
        text-decoration: line-through !important;
        opacity: 0.45 !important;
    }
    /* Jam Mulai dropdown style matching native Durasi select */
    .jam-option-item {
        color: #0f172a;
        background-color: transparent;
        transition: none !important;
    }
    /* Hover hanya pada item yang tepat di bawah kursor */
    .jam-option-item:hover:not(.jam-option-disabled) {
        background-color: #2563eb !important;
        color: #ffffff !important;
    }
    .jam-option-item:hover:not(.jam-option-disabled) * {
        color: #ffffff !important;
    }
    /* Opsi terpilih hanya biru jika menu TIDAK sedang di-hover (sehingga tidak double highlight) */
    #jamDropdownMenu:not(:hover) .jam-option-item.jam-option-selected,
    .jam-option-item.jam-option-selected:hover {
        background-color: #2563eb !important;
        color: #ffffff !important;
    }
    #jamDropdownMenu:not(:hover) .jam-option-item.jam-option-selected *,
    .jam-option-item.jam-option-selected:hover * {
        color: #ffffff !important;
    }
</style>
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Header Section -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-200/60 relative z-10">
        <div class="flex items-center gap-3">
            <a href="{{ route('games.index') }}" class="w-9 h-9 rounded-2xl bg-white border border-slate-200 text-slate-700 flex items-center justify-center hover:bg-slate-50 transition-colors shadow-2xs">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-[#063B00]">Host Open Schedule</span>
                <h1 class="text-xl sm:text-2xl font-black text-[#050608] leading-tight">
                    Buka Jadwal Mabar Baru
                </h1>
            </div>
        </div>
        <span class="hidden sm:inline-flex px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-xs font-bold">
            <i class="fa-solid fa-calendar-plus mr-1.5 text-xs"></i> Publikasikan ke Komunitas
        </span>
    </div>

    <!-- Main Glassmorphism Form Card -->
    <div class="glass-card !bg-white/85 !backdrop-blur-2xl rounded-3xl p-6 sm:p-8 space-y-6 border border-white shadow-[0_12px_40px_-10px_rgba(6,59,0,0.08)] relative z-10">

        @if(isset($errors) && $errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Terdapat data yang belum sesuai:</span>
                </div>
                <ul class="list-disc list-inside pl-1 text-[11px] text-rose-600 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('games.schedule.post') }}" method="POST" class="space-y-6 text-xs">
            @csrf

            <!-- 1. Cabang Olahraga -->
            <div class="space-y-2.5">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">1</span>
                    Pilih Cabang Olahraga
                </label>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($sports as $sport)
                        <label class="cursor-pointer">
                            <input type="radio" name="sport_id" value="{{ $sport->sport_id }}" data-sport-name="{{ $sport->nama_sport }}" class="peer sr-only" {{ $loop->first ? 'checked' : '' }} onchange="filterCourtsBySport({{ $sport->sport_id }})">
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:shadow-sm transition-all flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-white border border-slate-200/70 flex items-center justify-center text-base text-[#063B00] shadow-2xs">
                                    <i class="fa-solid {{ strtolower($sport->nama_sport) === 'padel' ? 'fa-table-tennis-paddle-ball' : 'fa-baseball' }}"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">{{ $sport->nama_sport }}</h4>
                                    <p class="text-[10px] text-slate-500">Pertandingan {{ $sport->nama_sport }}</p>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- 2. Format Pertandingan & Sistem Skor -->
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">2</span>
                    Pilih Format Pertandingan &amp; Sistem Skor
                </label>

                <!-- Pilihan Format (Americano vs Team Americano) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="format" value="Americano" class="peer sr-only" checked onchange="onFormatOrScoringChanged()">
                        <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:shadow-sm transition-all flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-base text-[#063B00] shadow-2xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <h4 class="font-extrabold text-slate-900 text-xs">Americano</h4>
                                    <span class="text-[9px] font-extrabold bg-[#EBF8D8] text-[#063B00] px-2 py-0.5 rounded-full border border-[#063B00]/20">POPULER</span>
                                </div>
                                <p class="text-[10px] text-slate-500 leading-relaxed">Semua pemain berpasangan secara bergantian (Round-Robin)</p>
                            </div>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="format" value="Team Americano" class="peer sr-only" onchange="onFormatOrScoringChanged()">
                        <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:shadow-sm transition-all flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-base text-indigo-700 shadow-2xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <h4 class="font-extrabold text-slate-900 text-xs">Team Americano</h4>
                                    <span class="text-[9px] font-extrabold bg-indigo-50 text-indigo-800 px-2 py-0.5 rounded-full border border-indigo-200">FIXED TEAMS</span>
                                </div>
                                <p class="text-[10px] text-slate-500 leading-relaxed">Setiap tim 2 orang tetap bertanding melawan seluruh tim lainnya</p>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Sistem Skor Dropdown -->
                <div class="space-y-1.5 pt-1">
                    <label class="block font-bold text-slate-800 flex items-center justify-between">
                        <span>Sistem Skor Pertandingan</span>
                        <span class="text-[10px] text-slate-400 font-normal">Pilih sistem poin yang digunakan</span>
                    </label>
                    <div class="relative">
                        <select name="scoring_system" id="scoringSystemSelect" onchange="onFormatOrScoringChanged()" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                            <optgroup label="Sistem Rotasi Poin (Total of X)">
                                <option value="Total of 3" selected>Total of 3 Poin</option>
                                <option value="Total of 4">Total of 4 Poin</option>
                                <option value="Total of 5">Total of 5 Poin</option>
                                <option value="Total of 6">Total of 6 Poin</option>
                                <option value="Total of 7">Total of 7 Poin</option>
                            </optgroup>
                            <optgroup label="Sistem Langsung Tuntas (First to X)">
                                <option value="First to 8">First to 8 Poin (Tuntas)</option>
                                <option value="First to 11">First to 11 Poin (Tuntas)</option>
                                <option value="First to 15">First to 15 Poin (Tuntas)</option>
                                <option value="First to 21">First to 21 Poin (Tuntas)</option>
                            </optgroup>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Informasi Sesi Mabar -->
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">3</span>
                    Informasi &amp; Judul Mabar
                </label>

                <div class="space-y-1.5">
                    <label class="block font-bold text-slate-800">Judul Sesi Mabar</label>
                    <input type="text" name="nama_session" value="{{ old('nama_session') }}" placeholder="Contoh: Mabar Padel JTK Bonang 6 Players" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                    <span class="text-[10px] text-slate-400 block font-medium">*Nama ini akan menjadi judul kartu mabar di Dashboard dan Jadwal Mabar.</span>
                </div>
            </div>

            <!-- 4. Lokasi Venue & Lapangan -->
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <div class="flex items-center justify-between gap-3">
                    <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5 shrink-0">
                        <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">4</span>
                        Lokasi Venue &amp; Lapangan
                    </label>
                    <button type="button" onclick="openQuickAddVenueModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-[11px] shadow-xs transition-all hover:scale-[1.02] active:scale-95 cursor-pointer shrink-0">
                        <i class="fa-solid fa-plus text-[#A8E63A] text-[10px]"></i>
                        <span>Tambah Venue</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1.5 min-w-0">
                        <label class="block font-bold text-slate-800 truncate">Pilih Venue / Tempat</label>
                        <div class="relative min-w-0">
                            <select name="venue_id" id="venueSelect" onchange="updateCourtsDropdown()" class="w-full min-w-0 bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 pr-8 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs truncate overflow-hidden" required>
                                <option value="" disabled selected>Pilih venue sesuai olahraga</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5 min-w-0">
                        <label class="block font-bold text-slate-800 truncate">Pilih Court / Lapangan</label>
                        <div class="relative min-w-0">
                            <select name="court_id" id="courtSelect" class="w-full min-w-0 bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 pr-8 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs truncate overflow-hidden" required>
                                <option value="" disabled selected>Pilih court</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- Info Ketersediaan & Jam Operasional Venue Terpilih -->
                <div id="venueAvailabilityInfo" class="hidden p-3 rounded-2xl bg-emerald-50/80 border border-emerald-200/80 text-[11px] text-emerald-950 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-2xs">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-emerald-100 text-[#063B00] flex items-center justify-center text-xs shrink-0">
                            <i class="fa-regular fa-clock"></i>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">Jam Operasional Venue:</span>
                            <strong id="venueOperatingHoursText" class="font-extrabold text-[#063B00] ml-1">06:00 - 23:00 WIB</strong>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-600 font-medium text-[10px] sm:text-[11px]">
                        <i class="fa-solid fa-calendar-check text-emerald-600"></i>
                        <span id="venueHariBukaText">Setiap Hari (Senin - Minggu)</span>
                    </div>
                </div>
            </div>

            <!-- 5. Waktu & Kuota Peserta -->
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">5</span>
                    Jadwal &amp; Kuota Pemain
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Tanggal Mabar</label>
                        <div class="relative">
                            <input type="text" name="tanggal" id="tanggalMabarInput" value="" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs cursor-pointer" placeholder="Pilih Tanggal Mabar" required readonly>
                            <i class="fa-regular fa-calendar absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                        <p id="tanggalErrorNotice" class="text-[10px] text-rose-600 font-bold hidden"></p>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-800">Jam Mulai</label>
                            <span id="jamOperasionalBadge" class="text-[9px] font-extrabold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200">
                                06:00 - 23:00 WIB
                            </span>
                        </div>
                        <div class="relative" id="jamDropdownContainer">
                            <button type="button" id="jamDropdownTrigger" onclick="toggleJamDropdown()" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs flex items-center justify-between text-left cursor-pointer">
                                <span id="jamSelectedText" class="text-slate-400">Pilih Jam Mulai</span>
                                <i id="jamDropdownChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200"></i>
                            </button>
                            <input type="hidden" name="jam" id="jamMulaiInput" value="" required>

                            <!-- Custom dropdown menu strictly capped in height, styled like native select (Durasi) -->
                            <div id="jamDropdownMenu" class="hidden absolute left-0 top-full mt-0.5 w-full overflow-y-auto bg-white border border-slate-400 shadow-md z-50 p-0 scrollbar-thin" style="max-height: 180px;">
                                <!-- Opsi jam operasional dirender secara dinamis -->
                            </div>
                        </div>
                        <p id="jamErrorNotice" class="text-[10px] text-rose-600 font-bold hidden"></p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Durasi</label>
                        <div class="relative">
                            <select name="durasi" id="durasiSelect" onchange="onDurasiChanged()" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs" required>
                                <option value="" disabled selected>Pilih Durasi</option>
                                <option value="1 Jam">1 Jam</option>
                                <option value="2 Jam">2 Jam</option>
                                <option value="3 Jam">3 Jam</option>
                                <option value="4 Jam">4 Jam</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                    <!-- Jenis Permainan (Double vs Single) -->
                    <div class="space-y-1.5" id="jenisPermainanContainer">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-800">Jenis Permainan</label>
                            <span id="teamLockBadge" class="hidden text-[10px] font-bold text-indigo-800 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
                                Fixed Double (2v2)
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 p-1 bg-slate-50/80 rounded-2xl border border-slate-200/80">
                            <label class="cursor-pointer" id="labelDouble">
                                <input type="radio" name="jenis_permainan" id="radioDouble" value="Double" class="peer sr-only" checked onchange="onFormatOrScoringChanged()">
                                <div class="py-2.5 rounded-xl text-center text-xs font-bold text-slate-600 peer-checked:bg-[#063B00] peer-checked:text-white transition-all">
                                    <i class="fa-solid fa-people-group mr-1"></i> Double (2v2)
                                </div>
                            </label>
                            <label class="cursor-pointer" id="labelSingle">
                                <input type="radio" name="jenis_permainan" id="radioSingle" value="Single" class="peer sr-only" onchange="onFormatOrScoringChanged()">
                                <div class="py-2.5 rounded-xl text-center text-xs font-bold text-slate-600 peer-checked:bg-[#063B00] peer-checked:text-white transition-all">
                                    <i class="fa-solid fa-person mr-1"></i> Single (1v1)
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Kuota Maksimal Pemain -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Kuota Maksimal Pemain</label>
                        <div class="relative">
                            <select name="jumlah_pemain" id="jumlahPemainSelect" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs" required>
                                <option value="" disabled selected>Pilih Kuota Pemain</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Rule / Constraint Hint Alert -->
                <div id="quotaConstraintNotice" class="p-3 rounded-2xl bg-[#EBF8D8]/70 border border-[#063B00]/20 text-[#063B00] text-[11px] flex items-center gap-2.5 shadow-2xs">
                    <i class="fa-solid fa-circle-info text-xs shrink-0"></i>
                    <span id="quotaConstraintText" class="font-medium">Format Americano Double: Kuota fleksibel dengan rotasi bangku cadangan.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Rekomendasi Level</label>
                        <div class="relative">
                            <select name="level_rekomendasi" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="All Level">All Level Welcome (Bebas Semua Level)</option>
                                <option value="Newbie - Beginner">Newbie &amp; Beginner Only</option>
                                <option value="Intermediate">Intermediate Only</option>
                                <option value="Advanced">Advanced / Competitive Only</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- Keikutsertaan Host (+ Add Yourself) -->
                <div class="p-3.5 rounded-2xl bg-slate-50/90 border border-slate-200/80 hover:border-[#063B00]/40 transition-colors">
                    <label class="flex items-start gap-3 cursor-pointer select-none">
                        <input type="checkbox" name="include_host_as_player" value="1" {{ old('include_host_as_player') ? 'checked' : '' }} class="mt-0.5 w-4 h-4 rounded text-[#063B00] border-slate-300 focus:ring-[#063B00] accent-[#063B00]">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-xs">Ikut serta bermain sebagai peserta (+ Add Yourself)</span>
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-slate-200/70 text-slate-700">Opsional</span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                Secara default, Anda hanya bertindak sebagai Host/Penyelenggara (tidak memotong kuota). Centang opsi ini jika Anda juga ingin langsung terdaftar sebagai salah satu pemain di sesi mabar ini.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 6. Catatan Deskripsi Mabar -->
            <div class="space-y-1.5 pt-2 border-t border-slate-100">
                <label class="block font-bold text-slate-800">Catatan Khusus / Deskripsi Mabar</label>
                <textarea name="deskripsi" rows="3" placeholder="Contoh: Harap hadir 15 menit sebelum mabar dimulai. Bola sudah disediakan oleh host, sewa raket tersedia di tempat." class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"></textarea>
            </div>

            <!-- Submit Button -->
            <div class="pt-3 border-t border-slate-100">
                <button type="submit" class="w-full py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-paper-plane text-[#A8E63A] text-xs"></i>
                    <span>Publikasikan Sesi Mabar ke Jadwal</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- Quick Add Venue Modal                                                     -->
<!-- ========================================================================= -->
<div id="quickAddVenueModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="glass-card !bg-white max-w-md w-full rounded-3xl p-5 sm:p-6 border border-white shadow-2xl">
        <!-- Header -->
        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
            <div class="w-9 h-9 rounded-xl bg-[#EBF8D8] text-[#063B00] flex items-center justify-center text-sm shadow-xs">
                <i class="fa-solid fa-map-location-dot"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800">Tambah Venue Baru</h3>
                <p class="text-[10px] text-slate-400">Daftarkan venue &amp; court secara instan</p>
            </div>
        </div>

        <!-- Form -->
        <form id="quickAddVenueForm" onsubmit="submitQuickAddVenue(event)" class="mt-4 space-y-3">
            <!-- Nama Venue -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Nama Venue <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input
                        type="text"
                        id="quickVenueName"
                        required
                        maxlength="60"
                        placeholder="Contoh: Matcha Padel Arena Dago"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-800 font-semibold focus:bg-white focus:border-[#063B00] focus:outline-none transition-colors"
                    />
                    <i class="fa-solid fa-building absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                </div>
            </div>

            <!-- Cabang Olahraga & Jumlah Court -->
            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                        Cabang Olahraga <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            id="quickVenueSport"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-3.5 pr-8 py-2.5 text-xs text-slate-800 font-semibold focus:bg-white focus:border-[#063B00] focus:outline-none appearance-none cursor-pointer"
                        >
                            <option value="Padel">Padel</option>
                            <option value="Tennis">Tennis</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                        Jumlah Lapangan <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            id="quickVenueCourtCount"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-3.5 pr-8 py-2.5 text-xs text-slate-800 font-semibold focus:bg-white focus:border-[#063B00] focus:outline-none appearance-none cursor-pointer"
                        >
                            <option value="1">1 Court</option>
                            <option value="2" selected>2 Court</option>
                            <option value="3">3 Court</option>
                            <option value="4">4 Court</option>
                            <option value="6">6 Court</option>
                            <option value="8">8 Court</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    </div>
                </div>
            </div>

            <!-- Kota -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Kota <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input
                        type="text"
                        id="quickVenueCity"
                        required
                        maxlength="80"
                        placeholder="Contoh: Bandung"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-800 font-semibold focus:bg-white focus:border-[#063B00] focus:outline-none transition-colors"
                    />
                    <i class="fa-solid fa-city absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                </div>
            </div>

            <!-- Alamat Lengkap -->
            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Alamat Lengkap <span class="text-rose-500">*</span>
                </label>
                <textarea
                    id="quickVenueAddress"
                    required
                    maxlength="255"
                    rows="2"
                    placeholder="Contoh: Jl. Ir. H. Juanda No. 123, Dago, Coblong"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-800 font-semibold focus:bg-white focus:border-[#063B00] focus:outline-none transition-colors"
                ></textarea>
            </div>

            <!-- Error Banner -->
            <div id="quickVenueError" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation shrink-0"></i>
                <span id="quickVenueErrorText"></span>
            </div>

            <!-- Action Buttons -->
            <div class="pt-3 border-t border-slate-100 flex items-center gap-2.5 w-full">
                <button
                    type="button"
                    onclick="closeQuickAddVenueModal()"
                    class="flex-1 py-2.5 px-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all text-center cursor-pointer shadow-2xs"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    id="btnSubmitQuickVenue"
                    class="flex-1 py-2.5 px-4 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer"
                >
                    <i class="fa-solid fa-floppy-disk text-xs text-[#A8E63A]"></i>
                    <span>Simpan Venue</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Venue and Courts mapping
    let venuesData = @json($venues);
    let currentSportId = parseInt(document.querySelector('input[name="sport_id"]:checked')?.value || 1);

    function filterCourtsBySport(sportId, preserveVenueId = null) {
        currentSportId = parseInt(sportId);
        const venueSelect = document.getElementById('venueSelect');
        if (!venueSelect) return;

        venueSelect.innerHTML = '';

        // Placeholder default kosong
        const placeholderOpt = document.createElement('option');
        placeholderOpt.value = '';
        placeholderOpt.disabled = true;
        placeholderOpt.innerText = 'Pilih venue sesuai olahraga';
        if (!preserveVenueId) {
            placeholderOpt.selected = true;
        }
        venueSelect.appendChild(placeholderOpt);

        // Filter venues having courts for this sport with status Available
        const matchingVenues = venuesData.filter(v => {
            if (!v.courts || !Array.isArray(v.courts)) return false;
            return v.courts.some(c => parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available');
        });

        if (matchingVenues.length === 0) {
            placeholderOpt.innerText = 'Tidak ada venue dengan lapangan tersedia untuk cabang olahraga ini';
            updateCourtsDropdown();
            return;
        }

        matchingVenues.forEach(v => {
            const availableCount = v.courts.filter(c => parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available').length;
            const opt = document.createElement('option');
            opt.value = v.venue_id;
            const maxNameLen = 35;
            const truncatedName = v.nama_venue && v.nama_venue.length > maxNameLen ? v.nama_venue.substring(0, maxNameLen) + '...' : v.nama_venue;
            const label = `${truncatedName} (${availableCount} Court Tersedia)`;
            opt.innerText = label;
            opt.title = `${v.nama_venue} (${availableCount} Court Tersedia)`;
            if (preserveVenueId && parseInt(v.venue_id) === parseInt(preserveVenueId)) {
                opt.selected = true;
            }
            venueSelect.appendChild(opt);
        });

        updateCourtsDropdown();
    }

    function parseVenueHours(jamOperasional) {
        if (!jamOperasional) {
            return { open: '06:00', close: '23:00', text: '06:00 - 23:00 WIB' };
        }
        const m = jamOperasional.match(/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/);
        if (m) {
            const pad = t => {
                const parts = t.split(':');
                return parts[0].padStart(2, '0') + ':' + parts[1];
            };
            return {
                open: pad(m[1]),
                close: pad(m[2]),
                text: jamOperasional
            };
        }
        return { open: '06:00', close: '23:00', text: jamOperasional };
    }

    /**
     * Cari ketersediaan harian dari tb_venue_avail sesuai hari yang dipilih.
     * Mengembalikan { open, close, text } atau null jika tidak ada data.
     */
    function getAvailabilityForDate(selectedVenue, dateStr) {
        if (!selectedVenue || !selectedVenue.availabilities || !selectedVenue.availabilities.length || !dateStr) {
            return null;
        }

        // Mapping: getDay() (0=Minggu, 1=Senin, ..., 6=Sabtu) ke nama hari Indonesia & Inggris
        const dayNames = {
            0: ['minggu', 'sunday', 'sun'],
            1: ['senin', 'monday', 'mon'],
            2: ['selasa', 'tuesday', 'tue'],
            3: ['rabu', 'wednesday', 'wed'],
            4: ['kamis', 'thursday', 'thu'],
            5: ['jumat', 'friday', 'fri'],
            6: ['sabtu', 'saturday', 'sat']
        };

        const date = new Date(dateStr + 'T00:00:00');
        const dayIndex = date.getDay();
        const names = dayNames[dayIndex] || [];

        const match = selectedVenue.availabilities.find(a => {
            if (!a.hari) return false;
            const hariLower = a.hari.toLowerCase().trim();
            return names.some(n => hariLower.includes(n));
        });

        if (!match || !match.jam_buka || !match.jam_tutup) {
            return null;
        }

        const pad = t => {
            const parts = String(t).replace(/[^0-9:]/g, '').split(':');
            return (parts[0] || '00').padStart(2, '0') + ':' + (parts[1] || '00').padStart(2, '0');
        };

        const open = pad(match.jam_buka);
        const close = pad(match.jam_tutup);
        return { open, close, text: `${open} - ${close} WIB` };
    }

    let fpTanggal = null;

    /**
     * Cek apakah sebuah tanggal tutup/libur untuk venue yang dipilih.
     */
    function isDateDisabledForVenue(date, venue) {
        if (!venue) return false;

        const dayOfWeek = date.getDay(); // 0 = Minggu, 1 = Senin, ..., 6 = Sabtu
        const hariBuka = (venue.hari_buka || 'Setiap Hari (Senin - Minggu)').toLowerCase();

        // 1. Cek pola hari_buka umum
        if (hariBuka.includes('senin - jumat') && (dayOfWeek === 0 || dayOfWeek === 6)) {
            return true; // Sabtu & Minggu libur
        }
        if (hariBuka.includes('senin - sabtu') && dayOfWeek === 0) {
            return true; // Minggu libur
        }
        if (hariBuka.includes('sabtu & minggu') && (dayOfWeek >= 1 && dayOfWeek <= 5)) {
            return true; // Hari kerja libur (hanya weekend)
        }
        if (hariBuka.includes('selasa - minggu') && dayOfWeek === 1) {
            return true; // Senin libur
        }

        // 2. Cek tb_venue_avail jika venue mendefinisikan jadwal per-hari
        if (venue.availabilities && venue.availabilities.length > 0) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            const dateStr = `${y}-${m}-${d}`;
            const avail = getAvailabilityForDate(venue, dateStr);
            if (avail === null) {
                return true; // Hari ini tidak terdaftar / unavailable di availabilities
            }
        }

        return false;
    }

    /**
     * Inisialisasi kalender Flatpickr dengan aturan disable hari libur venue
     */
    function initFlatpickr() {
        if (typeof flatpickr === 'undefined') return;

        const inputEl = document.getElementById('tanggalMabarInput');
        if (!inputEl) return;

        const defaultVal = inputEl.value || null;

        fpTanggal = flatpickr(inputEl, {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            minDate: "today",
            defaultDate: defaultVal || undefined,
            monthSelectorType: "static",
            disable: [
                function(date) {
                    const venueSelect = document.getElementById('venueSelect');
                    if (!venueSelect || !venueSelect.value) return false;
                    const venueId = parseInt(venueSelect.value);
                    const selectedVenue = venuesData.find(v => v.venue_id === venueId);
                    return isDateDisabledForVenue(date, selectedVenue);
                }
            ],
            onChange: function(selectedDates, dateStr) {
                onTanggalChanged();
            }
        });
    }

    /**
     * Buka / tutup menu dropdown Jam Mulai dan scroll ke item yang sedang aktif
     */
    function toggleJamDropdown() {
        const menu = document.getElementById('jamDropdownMenu');
        const chevron = document.getElementById('jamDropdownChevron');
        if (!menu) return;

        const isOpen = !menu.classList.contains('hidden');
        if (isOpen) {
            menu.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180');
        } else {
            menu.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-180');
            const selectedItem = menu.querySelector('.jam-option-selected');
            if (selectedItem) {
                selectedItem.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    /**
     * Memilih salah satu jam dari custom dropdown
     */
    function selectJamOption(timeStr, isDisabled) {
        if (isDisabled) return;

        const input = document.getElementById('jamMulaiInput');
        const textSpan = document.getElementById('jamSelectedText');
        const menu = document.getElementById('jamDropdownMenu');
        const chevron = document.getElementById('jamDropdownChevron');

        if (input) input.value = timeStr;
        if (textSpan) {
            textSpan.innerText = `${timeStr} WIB`;
            textSpan.classList.remove('text-slate-400');
            textSpan.classList.add('text-slate-900');
        }
        if (menu) menu.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-180');

        // Update highlight style pada opsi
        if (menu) {
            const allItems = menu.querySelectorAll('.jam-option-item');
            allItems.forEach(item => {
                const val = item.getAttribute('data-value');
                if (val === timeStr) {
                    item.classList.add('jam-option-selected');
                } else {
                    item.classList.remove('jam-option-selected');
                }
            });
        }

        validateTanggalAndJam();
    }

    // Tutup custom dropdown ketika klik di luar area
    document.addEventListener('click', (e) => {
        const container = document.getElementById('jamDropdownContainer');
        const menu = document.getElementById('jamDropdownMenu');
        const chevron = document.getElementById('jamDropdownChevron');
        if (container && menu && !container.contains(e.target)) {
            menu.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180');
        }
    });

    /**
     * Mengisi dropdown Jam Mulai dengan opsi 30-menitan,
     * dibatasi tingginya (max-h-48) agar tidak mentok ke bawah layar.
     */
    function populateJamMulaiDropdown(hours) {
        const menu = document.getElementById('jamDropdownMenu');
        const input = document.getElementById('jamMulaiInput');
        const textSpan = document.getElementById('jamSelectedText');
        if (!menu || !input) return;

        const durasiSelect = document.getElementById('durasiSelect');
        const durasiHours = parseInt(durasiSelect?.value) || 2;
        const currentVal = input.value || '';

        const [openH, openM] = (hours.open || '06:00').split(':').map(Number);
        const [closeH, closeM] = (hours.close || '23:00').split(':').map(Number);
        const openMinutes = openH * 60 + openM;
        const closeMinutes = closeH * 60 + closeM;

        menu.innerHTML = '';

        // Tentukan batas slot waktu berdasarkan jam operasional venue
        const startSlot = Math.floor(openMinutes / 30) * 30;
        const endSlot = Math.ceil(closeMinutes / 30) * 30;

        let availableSlots = [];
        let validSelectedValue = null;

        for (let m = startSlot; m <= endSlot; m += 30) {
            const h = Math.floor(m / 60);
            const min = m % 60;
            if (h >= 24) break;

            const timeStr = `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
            const endM = m + durasiHours * 60;

            const isBeforeOpen = m < openMinutes;
            const isAtOrAfterClose = m >= closeMinutes;
            const isExceedDuration = !isBeforeOpen && !isAtOrAfterClose && endM > closeMinutes;
            const isDisabled = isBeforeOpen || isAtOrAfterClose || isExceedDuration;

            if (!isDisabled) {
                availableSlots.push(timeStr);
                if (currentVal && timeStr === currentVal) {
                    validSelectedValue = timeStr;
                }
            }

            const itemDiv = document.createElement('div');
            itemDiv.setAttribute('data-value', timeStr);
            itemDiv.className = `jam-option-item px-3 py-1.5 text-xs flex items-center justify-between ${
                isDisabled
                    ? 'jam-option-disabled text-slate-400 bg-slate-50 cursor-not-allowed opacity-60'
                    : 'cursor-pointer'
            }`;

            let badgeText = '';
            if (isBeforeOpen) badgeText = 'Belum Buka';
            else if (isAtOrAfterClose) badgeText = 'Jam Tutup';
            else if (isExceedDuration) badgeText = 'Lewat Jam Tutup';

            itemDiv.innerHTML = `
                <span>${timeStr} WIB</span>
                ${badgeText ? `<span class="text-[9px] px-1 rounded bg-slate-200 text-slate-600">${badgeText}</span>` : ''}
            `;

            itemDiv.onclick = () => selectJamOption(timeStr, isDisabled);
            menu.appendChild(itemDiv);
        }

        // Fallback jika tidak ada opsi
        if (availableSlots.length === 0) {
            for (let m = 360; m <= 1380; m += 30) {
                const h = Math.floor(m / 60);
                const min = m % 60;
                const timeStr = `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
                availableSlots.push(timeStr);

                const itemDiv = document.createElement('div');
                itemDiv.setAttribute('data-value', timeStr);
                itemDiv.className = 'jam-option-item px-3 py-1.5 text-xs cursor-pointer flex items-center justify-between';
                itemDiv.innerHTML = `<span>${timeStr} WIB</span>`;
                itemDiv.onclick = () => selectJamOption(timeStr, false);
                menu.appendChild(itemDiv);
            }
        }

        // Tentukan nilai aktif hanya jika sebelumnya sudah ada yang dipilih
        if (currentVal && validSelectedValue) {
            input.value = validSelectedValue;
            if (textSpan) {
                textSpan.innerText = `${validSelectedValue} WIB`;
                textSpan.classList.remove('text-slate-400');
                textSpan.classList.add('text-slate-900');
            }
            const selectedItem = menu.querySelector(`[data-value="${validSelectedValue}"]`);
            if (selectedItem && !selectedItem.classList.contains('jam-option-disabled')) {
                selectedItem.classList.add('jam-option-selected');
            }
        } else {
            input.value = '';
            if (textSpan) {
                textSpan.innerText = 'Pilih Jam Mulai';
                textSpan.classList.add('text-slate-400');
                textSpan.classList.remove('text-slate-900');
            }
        }
    }

    function onDurasiChanged() {
        const venueSelect = document.getElementById('venueSelect');
        if (venueSelect && venueSelect.value) {
            const venueId = parseInt(venueSelect.value);
            const selectedVenue = venuesData.find(v => v.venue_id === venueId);
            if (selectedVenue) {
                const tanggalInput = document.getElementById('tanggalMabarInput');
                const dateStr = tanggalInput ? tanggalInput.value : null;
                const availHours = getAvailabilityForDate(selectedVenue, dateStr);
                const hours = availHours || parseVenueHours(selectedVenue.jam_operasional);
                populateJamMulaiDropdown(hours);
            }
        }
        validateTanggalAndJam();
    }

    function onTanggalChanged() {
        const venueSelect = document.getElementById('venueSelect');
        if (venueSelect && venueSelect.value) {
            const venueId = parseInt(venueSelect.value);
            const selectedVenue = venuesData.find(v => v.venue_id === venueId);
            updateVenueOperatingHours(selectedVenue);
        } else {
            validateTanggalAndJam();
        }
    }

    function updateVenueOperatingHours(selectedVenue) {
        const infoBox = document.getElementById('venueAvailabilityInfo');
        const hoursText = document.getElementById('venueOperatingHoursText');
        const hariBukaText = document.getElementById('venueHariBukaText');
        const badge = document.getElementById('jamOperasionalBadge');
        const tanggalInput = document.getElementById('tanggalMabarInput');

        if (!selectedVenue) {
            if (infoBox) infoBox.classList.add('hidden');
            return;
        }

        // Coba ambil ketersediaan per hari dari tb_venue_avail; fallback ke jam_operasional
        const dateStr = tanggalInput ? tanggalInput.value : null;
        const availHours = getAvailabilityForDate(selectedVenue, dateStr);
        const hours = availHours || parseVenueHours(selectedVenue.jam_operasional);

        const hariBuka = selectedVenue.hari_buka || 'Setiap Hari (Senin - Minggu)';

        if (infoBox) infoBox.classList.remove('hidden');
        if (hoursText) hoursText.innerText = hours.text;
        if (hariBukaText) hariBukaText.innerText = hariBuka;
        if (badge) badge.innerText = `${hours.open} - ${hours.close} WIB`;

        // Update aturan disable di kalender Flatpickr sesuai venue terpilih
        if (fpTanggal) {
            fpTanggal.set('disable', [
                function(date) {
                    return isDateDisabledForVenue(date, selectedVenue);
                }
            ]);

            // Jika tanggal yang sedang dipilih ternyata libur di venue ini, auto-pindah ke hari buka berikutnya
            const curDate = fpTanggal.selectedDates[0];
            if (curDate && isDateDisabledForVenue(curDate, selectedVenue)) {
                let nextValid = new Date(curDate);
                for (let i = 1; i <= 14; i++) {
                    nextValid.setDate(nextValid.getDate() + 1);
                    if (!isDateDisabledForVenue(nextValid, selectedVenue)) {
                        fpTanggal.setDate(nextValid, true); // true agar trigger onChange
                        break;
                    }
                }
            }
        }

        // Perbarui opsi Jam Mulai dropdown
        populateJamMulaiDropdown(hours);

        validateTanggalAndJam();
    }

    function validateTanggalAndJam() {
        const venueSelect = document.getElementById('venueSelect');
        const jamInput = document.getElementById('jamMulaiInput');
        const tanggalInput = document.getElementById('tanggalMabarInput');
        const durasiSelect = document.getElementById('durasiSelect');
        const jamError = document.getElementById('jamErrorNotice');
        const tanggalError = document.getElementById('tanggalErrorNotice');
        const submitBtn = document.querySelector('button[type="submit"]');

        if (!venueSelect || !jamInput || !tanggalInput) return;

        const venueId = parseInt(venueSelect.value);
        const selectedVenue = venuesData.find(v => v.venue_id === venueId);
        if (!selectedVenue) return;

        let hasError = false;

        // 1. Validasi Jam Operasional
        const dateStr = tanggalInput.value;
        const availHours = getAvailabilityForDate(selectedVenue, dateStr);
        const hours = availHours || parseVenueHours(selectedVenue.jam_operasional);
        const jamVal = jamInput.value;
        const triggerBtn = document.getElementById('jamDropdownTrigger');

        if (jamVal && hours.open <= hours.close) {
            if (jamVal < hours.open || jamVal >= hours.close) {
                if (jamError) {
                    jamError.innerText = `⚠️ Jam mulai (${jamVal}) di luar jam buka venue (${hours.open} - ${hours.close} WIB).`;
                    jamError.classList.remove('hidden');
                }
                if (triggerBtn) triggerBtn.classList.add('border-rose-400', 'bg-rose-50/50');
                hasError = true;
            } else {
                // Cek jika jam mulai + durasi melebihi jam tutup
                const durasiHours = parseInt(durasiSelect?.value || '2');
                const [startH, startM] = jamVal.split(':').map(Number);
                const [closeH, closeM] = hours.close.split(':').map(Number);
                const endMinutes = (startH + durasiHours) * 60 + startM;
                const closeMinutes = closeH * 60 + closeM;

                if (endMinutes > closeMinutes) {
                    const endHStr = String(Math.floor(endMinutes / 60)).padStart(2, '0');
                    const endMStr = String(endMinutes % 60).padStart(2, '0');
                    if (jamError) {
                        jamError.innerText = `⚠️ Durasi bermain hingga ${endHStr}:${endMStr} WIB melewati jam tutup venue (${hours.close} WIB).`;
                        jamError.classList.remove('hidden');
                    }
                    if (triggerBtn) triggerBtn.classList.add('border-rose-400', 'bg-rose-50/50');
                    hasError = true;
                } else {
                    if (jamError) jamError.classList.add('hidden');
                    if (triggerBtn) triggerBtn.classList.remove('border-rose-400', 'bg-rose-50/50');
                }
            }
        } else {
            if (jamError) jamError.classList.add('hidden');
            if (triggerBtn) triggerBtn.classList.remove('border-rose-400', 'bg-rose-50/50');
        }

        // 2. Validasi Hari Buka
        if (tanggalInput.value) {
            const date = new Date(tanggalInput.value + 'T00:00:00');
            if (isDateDisabledForVenue(date, selectedVenue)) {
                let dayError = `⚠️ Venue tutup pada tanggal terpilih (${selectedVenue.hari_buka || 'Tutup'}).`;
                if (tanggalError) {
                    tanggalError.innerText = dayError;
                    tanggalError.classList.remove('hidden');
                }
                tanggalInput.classList.add('border-rose-400', 'bg-rose-50/50');
                hasError = true;
            } else {
                if (tanggalError) tanggalError.classList.add('hidden');
                tanggalInput.classList.remove('border-rose-400', 'bg-rose-50/50');
            }
        } else {
            if (tanggalError) tanggalError.classList.add('hidden');
            tanggalInput.classList.remove('border-rose-400', 'bg-rose-50/50');
        }

        if (submitBtn) {
            submitBtn.disabled = hasError;
            if (hasError) {
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    }

    function updateCourtsDropdown() {
        const venueSelect = document.getElementById('venueSelect');
        const courtSelect = document.getElementById('courtSelect');
        if (!venueSelect || !courtSelect) return;
        courtSelect.innerHTML = '';

        const venueId = parseInt(venueSelect.value);
        const selectedVenue = venuesData.find(v => v.venue_id === venueId);

        // Update jam operasional & ketersediaan venue
        updateVenueOperatingHours(selectedVenue);

        if (!selectedVenue) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.disabled = true;
            opt.selected = true;
            opt.innerText = 'Pilih venue terlebih dahulu';
            courtSelect.appendChild(opt);
            return;
        }

        const placeholderOpt = document.createElement('option');
        placeholderOpt.value = '';
        placeholderOpt.disabled = true;
        placeholderOpt.selected = true;
        placeholderOpt.innerText = 'Pilih court / lapangan';
        courtSelect.appendChild(placeholderOpt);

        if (selectedVenue.courts && selectedVenue.courts.length > 0) {
            const filteredCourts = selectedVenue.courts.filter(c => 
                parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available'
            );

            if (filteredCourts.length > 0) {
                filteredCourts.forEach(court => {
                    const opt = document.createElement('option');
                    opt.value = court.court_id;
                    const maxCourtLen = 35;
                    const truncatedCourt = court.nama_court && court.nama_court.length > maxCourtLen ? court.nama_court.substring(0, maxCourtLen) + '...' : court.nama_court;
                    opt.innerText = truncatedCourt;
                    opt.title = court.nama_court;
                    courtSelect.appendChild(opt);
                });
            } else {
                placeholderOpt.innerText = 'Tidak ada court yang tersedia untuk olahraga ini';
            }
        } else {
            placeholderOpt.innerText = 'Belum ada lapangan';
        }
    }

    // =========================================================================
    // Format, Single/Double, and Quota Constraints Logic
    // =========================================================================
    function onFormatOrScoringChanged() {
        const formatInput = document.querySelector('input[name="format"]:checked')?.value || 'Americano';
        const scoringSelect = document.getElementById('scoringSystemSelect');
        const scoringSystem = scoringSelect ? scoringSelect.value : 'Total of 3';
        const isFirstTo = scoringSystem.toLowerCase().startsWith('first to');
        const isTeamAmericano = formatInput.toLowerCase().includes('team');

        const radioDouble = document.getElementById('radioDouble');
        const radioSingle = document.getElementById('radioSingle');
        const labelSingle = document.getElementById('labelSingle');
        const teamLockBadge = document.getElementById('teamLockBadge');
        const quotaSelect = document.getElementById('jumlahPemainSelect');
        const constraintNotice = document.getElementById('quotaConstraintNotice');
        const constraintText = document.getElementById('quotaConstraintText');

        if (isTeamAmericano) {
            // Team Americano is ALWAYS Double (2v2)
            if (radioDouble) radioDouble.checked = true;
            if (labelSingle) labelSingle.classList.add('hidden');
            if (teamLockBadge) teamLockBadge.classList.remove('hidden');
        } else {
            if (labelSingle) labelSingle.classList.remove('hidden');
            if (teamLockBadge) teamLockBadge.classList.add('hidden');
        }

        const isSingle = !isTeamAmericano && radioSingle && radioSingle.checked;
        const currentVal = quotaSelect ? quotaSelect.value : null;
        if (quotaSelect) quotaSelect.innerHTML = '';

        let options = [];
        let noticeMessage = '';

        if (isTeamAmericano) {
            if (isFirstTo) {
                // Team Americano First to X: Exact 4 players (2 fixed teams)
                options = [
                    { val: '4', text: '4 Pemain (Tepat 2 Pasang Tim - First to X)' }
                ];
                noticeMessage = `🎯 <strong>Team Americano (${scoringSystem})</strong>: Pertandingan langsung tuntas 1 court, kuota terkunci <strong>tepat 4 pemain (2 tim)</strong>.`;
            } else {
                // Team Americano Total of X: Even number >= 4
                options = [
                    { val: '4', text: '4 Pemain (2 Pasangan Tim)' },
                    { val: '6', text: '6 Pemain (3 Pasangan Tim)' },
                    { val: '8', text: '8 Pemain (4 Pasangan Tim)' },
                    { val: '10', text: '10 Pemain (5 Pasangan Tim)' },
                    { val: '12', text: '12 Pemain (6 Pasangan Tim)' }
                ];
                noticeMessage = `👥 <strong>Team Americano</strong>: Setiap tim terdiri dari 2 orang tetap. Kuota pemain <strong>wajib genap</strong> (4, 6, 8, dst).`;
            }
        } else if (isSingle) {
            if (isFirstTo) {
                // Americano Single First to X: Exact 2 players (1v1)
                options = [
                    { val: '2', text: '2 Pemain (Tepat 1 vs 1 - First to X)' }
                ];
                noticeMessage = `🎯 <strong>Americano Single (${scoringSystem})</strong>: Pertandingan 1v1 langsung tuntas, kuota terkunci <strong>tepat 2 pemain</strong>.`;
            } else {
                // Americano Single Total of X: 2 to 8 players
                options = [
                    { val: '2', text: '2 Pemain (1 Court Non-Stop 1v1)' },
                    { val: '3', text: '3 Pemain (1 Court Rotasi 1 Istirahat)' },
                    { val: '4', text: '4 Pemain (1 Court Rotasi / 2 Court Single)' },
                    { val: '5', text: '5 Pemain (Single Rotasi Adil)' },
                    { val: '6', text: '6 Pemain (Single Multi-Player)' }
                ];
                noticeMessage = `🎾 <strong>Americano Single (1v1)</strong>: Setiap pemain saling berhadapan secara round-robin individu.`;
            }
        } else {
            // Americano Double (2v2)
            if (isFirstTo) {
                // Americano Double First to X: Exact 4 players (2v2)
                options = [
                    { val: '4', text: '4 Pemain (Tepat 2 vs 2 - First to X)' }
                ];
                noticeMessage = `🎯 <strong>Americano Double (${scoringSystem})</strong>: Pertandingan 2v2 langsung tuntas, kuota terkunci <strong>tepat 4 pemain</strong>.`;
            } else {
                // Americano Double Total of X: 4 to 12 players
                options = [
                    { val: '4', text: '4 Pemain (1 Court Non-Stop 2v2)' },
                    { val: '5', text: '5 Pemain (1 Court Rotasi Bench 1 Istirahat)' },
                    { val: '6', text: '6 Pemain (1 Court Rotasi Bench 2 Istirahat)' },
                    { val: '7', text: '7 Pemain (Rotasi 3 Istirahat)' },
                    { val: '8', text: '8 Pemain (1 Court Rotasi / 2 Court Double)' },
                    { val: '10', text: '10 Pemain (Kompetisi Komunitas)' },
                    { val: '12', text: '12 Pemain (Multi-Court Tournament)' }
                ];
                noticeMessage = `🔄 <strong>Americano Double</strong>: Pasangan partner berganti tiap ronde secara dinamis dan adil.`;
            }
        }

        const quotaPlaceholder = document.createElement('option');
        quotaPlaceholder.value = '';
        quotaPlaceholder.disabled = true;
        quotaPlaceholder.innerText = 'Pilih Kuota Pemain';
        let isSelected = false;

        options.forEach((o) => {
            const opt = document.createElement('option');
            opt.value = o.val;
            opt.innerText = o.text;
            if (currentVal && String(o.val) === String(currentVal)) {
                opt.selected = true;
                isSelected = true;
            }
            if (quotaSelect) quotaSelect.appendChild(opt);
        });

        if (!isSelected) {
            quotaPlaceholder.selected = true;
        }
        if (quotaSelect) {
            quotaSelect.insertBefore(quotaPlaceholder, quotaSelect.firstChild);
        }

        if (constraintText) {
            constraintText.innerHTML = noticeMessage;
        }
    }

    // =========================================================================
    // Quick Add Venue Modal Actions
    // =========================================================================
    function openQuickAddVenueModal() {
        const modal = document.getElementById('quickAddVenueModal');
        const sportSelect = document.getElementById('quickVenueSport');
        const errDiv = document.getElementById('quickVenueError');
        if (errDiv) errDiv.classList.add('hidden');
        
        const activeSportRadio = document.querySelector('input[name="sport_id"]:checked');
        const activeSportName = activeSportRadio ? activeSportRadio.getAttribute('data-sport-name') : 'Padel';
        if (sportSelect && activeSportName) {
            sportSelect.value = activeSportName;
        }

        const nameInput = document.getElementById('quickVenueName');
        if (nameInput) nameInput.value = '';
        const addressInput = document.getElementById('quickVenueAddress');
        if (addressInput) addressInput.value = '';
        const cityInput = document.getElementById('quickVenueCity');
        if (cityInput) cityInput.value = '';

        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => nameInput?.focus(), 50);
        }
    }

    function closeQuickAddVenueModal() {
        const modal = document.getElementById('quickAddVenueModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    async function submitQuickAddVenue(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitQuickVenue');
        const errDiv = document.getElementById('quickVenueError');
        const errText = document.getElementById('quickVenueErrorText');
        const nameInput = document.getElementById('quickVenueName');
        const sportInput = document.getElementById('quickVenueSport');
        const countInput = document.getElementById('quickVenueCourtCount');
        const cityInput = document.getElementById('quickVenueCity');
        const addressInput = document.getElementById('quickVenueAddress');

        const originalBtnHtml = btn.innerHTML;

        const hasScript = (val) => /<[^>]*script/i.test(val) || /<script[\s\S]*?>/i.test(val) || /[<>]/.test(val);
        if (hasScript(nameInput.value) || hasScript(cityInput.value) || hasScript(addressInput.value)) {
            if (errDiv && errText) {
                errText.textContent = 'Input tidak boleh mengandung tag script atau karakter khusus (< >).';
                errDiv.classList.remove('hidden');
            }
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Menyimpan...';
        if (errDiv) errDiv.classList.add('hidden');

        try {
            const res = await fetch("{{ route('venues.quickStore') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    nama_venue: nameInput.value.trim(),
                    sport: sportInput.value,
                    jumlah_court: parseInt(countInput.value),
                    kota: cityInput.value.trim(),
                    alamat: addressInput.value.trim(),
                })
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Gagal menyimpan venue baru.');
            }

            // Tambahkan venue baru ke array venuesData lokal
            const courtsList = (data.courts || (data.venue && data.venue.courts) || []);
            const newVenue = {
                venue_id: data.venue.venue_id,
                nama_venue: data.venue.nama_venue,
                jam_operasional: data.venue.jam_operasional || '06:00 - 23:00 WIB',
                hari_buka: data.venue.hari_buka || 'Setiap Hari (Senin - Minggu)',
                courts: courtsList.map(c => ({
                    court_id: c.court_id,
                    nama_court: c.nama_court,
                    sport_id: c.sport_id,
                    status_ketersediaan: 'Available'
                }))
            };
            venuesData.unshift(newVenue);

            // Jika sport di modal berbeda dengan radio sport yang aktif, pindahkan radio ke sport tersebut
            const targetSportRadio = document.querySelector(`input[name="sport_id"][data-sport-name="${sportInput.value}"]`);
            if (targetSportRadio && !targetSportRadio.checked) {
                targetSportRadio.checked = true;
                currentSportId = parseInt(targetSportRadio.value);
            }

            // Re-render dropdown venue dan pilih venue baru tersebut
            filterCourtsBySport(currentSportId, newVenue.venue_id);

            closeQuickAddVenueModal();
            if (typeof showToast === 'function') {
                showToast(`Venue "${newVenue.nama_venue}" berhasil ditambahkan!`);
            }
        } catch (err) {
            if (errDiv && errText) {
                errText.textContent = err.message || 'Terjadi kesalahan sistem.';
                errDiv.classList.remove('hidden');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initFlatpickr();
        filterCourtsBySport(currentSportId);
        onFormatOrScoringChanged();

        const venueSelect = document.getElementById('venueSelect');
        if (venueSelect && venueSelect.value) {
            const venueId = parseInt(venueSelect.value);
            const selectedVenue = venuesData.find(v => v.venue_id === venueId);
            if (selectedVenue) {
                updateVenueOperatingHours(selectedVenue);
            }
        } else {
            populateJamMulaiDropdown({ open: '06:00', close: '23:00', text: '06:00 - 23:00 WIB' });
            validateTanggalAndJam();
        }
    });
</script>
@endpush
@endsection
