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
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-8 pb-28 md:pb-12 space-y-6">

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

        <form action="{{ route('games.schedule.post') }}" method="POST" class="space-y-6 text-xs" id="scheduleForm" novalidate>
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
                <div class="space-y-1.5 pt-1 relative" id="wrapper_scoringSystem">
                    <label class="block font-bold text-slate-800 flex items-center justify-between">
                        <span>Sistem Skor Pertandingan</span>
                        <span class="text-[10px] text-slate-400 font-normal">Pilih sistem poin yang digunakan</span>
                    </label>
                    @php
                        $currScoring = old('scoring_system', 'Total of 3');
                        $scoringLabels = [
                            'Total of 3' => 'Total of 3 Poin',
                            'Total of 4' => 'Total of 4 Poin',
                            'Total of 5' => 'Total of 5 Poin',
                            'Total of 6' => 'Total of 6 Poin',
                            'Total of 7' => 'Total of 7 Poin',
                            'First to 8' => 'First to 8 Poin (Tuntas)',
                            'First to 11' => 'First to 11 Poin (Tuntas)',
                            'First to 15' => 'First to 15 Poin (Tuntas)',
                            'First to 21' => 'First to 21 Poin (Tuntas)',
                        ];
                        $currScoringLabel = $scoringLabels[$currScoring] ?? 'Total of 3 Poin';
                    @endphp
                    <input type="hidden" name="scoring_system" id="scoringSystemInput" value="{{ $currScoring }}">
                    <div class="relative">
                        <button
                            type="button"
                            id="scoringSystemTrigger"
                            onclick="toggleScheduleDropdown('scoringSystem', event)"
                            class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                        >
                            <span id="scoringSystemDisplay" class="truncate font-semibold">{{ $currScoringLabel }}</span>
                            <i id="scoringSystemChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                        </button>

                        <div
                            id="scoringSystemMenu"
                            class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150 max-h-64 overflow-y-auto"
                            onclick="event.stopPropagation()"
                        >
                            <div class="px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-100 mb-1">
                                Sistem Rotasi Poin (Total of X)
                            </div>
                            @foreach(['Total of 3' => 'Total of 3 Poin', 'Total of 4' => 'Total of 4 Poin', 'Total of 5' => 'Total of 5 Poin', 'Total of 6' => 'Total of 6 Poin', 'Total of 7' => 'Total of 7 Poin'] as $sVal => $sLabel)
                                <button
                                    type="button"
                                    onclick="selectScheduleOption('scoringSystem', '{{ $sVal }}', '{{ $sLabel }}', onFormatOrScoringChanged)"
                                    class="scoringSystem-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer {{ $currScoring === $sVal ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                    data-val="{{ $sVal }}"
                                >
                                    <span>{{ $sLabel }}</span>
                                    <i class="fa-solid fa-circle-check text-[#063B00] text-sm shrink-0 {{ $currScoring === $sVal ? '' : 'hidden' }}"></i>
                                </button>
                            @endforeach

                            <div class="px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-100 mt-2 mb-1">
                                Sistem Langsung Tuntas (First to X)
                            </div>
                            @foreach(['First to 8' => 'First to 8 Poin (Tuntas)', 'First to 11' => 'First to 11 Poin (Tuntas)', 'First to 15' => 'First to 15 Poin (Tuntas)', 'First to 21' => 'First to 21 Poin (Tuntas)'] as $sVal => $sLabel)
                                <button
                                    type="button"
                                    onclick="selectScheduleOption('scoringSystem', '{{ $sVal }}', '{{ $sLabel }}', onFormatOrScoringChanged)"
                                    class="scoringSystem-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer {{ $currScoring === $sVal ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                    data-val="{{ $sVal }}"
                                >
                                    <span>{{ $sLabel }}</span>
                                    <i class="fa-solid fa-circle-check text-[#063B00] text-sm shrink-0 {{ $currScoring === $sVal ? '' : 'hidden' }}"></i>
                                </button>
                            @endforeach
                        </div>
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
                    <input type="text" name="nama_session" id="namaSessionInput" value="{{ old('nama_session') }}" placeholder="Contoh: Mabar Padel JTK Bonang 6 Players" class="w-full bg-slate-50/80 border @error('nama_session') border-rose-400 bg-rose-50/50 @else border-slate-200/80 @enderror rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                    <p id="namaSessionError" class="text-[10px] text-rose-600 font-bold hidden"></p>
                    @error('nama_session')
                        <p class="text-[10px] text-rose-600 font-bold mt-1">⚠️ {{ $message }}</p>
                    @enderror
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
                    <div class="space-y-1.5 min-w-0 relative" id="wrapper_venue">
                        <label class="block font-bold text-slate-800 truncate">Pilih Venue / Tempat <span class="text-rose-500">*</span></label>
                        <input type="hidden" name="venue_id" id="venueIdInput" value="{{ old('venue_id') }}" required>
                        <div class="relative min-w-0">
                            <button
                                type="button"
                                id="venueTrigger"
                                onclick="toggleScheduleDropdown('venue', event)"
                                class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                            >
                                <span id="venueDisplay" class="truncate font-semibold text-slate-400">Pilih venue sesuai olahraga</span>
                                <i id="venueChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                            </button>

                            <div
                                id="venueMenu"
                                class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150 max-h-60 overflow-y-auto"
                                onclick="event.stopPropagation()"
                            >
                                <!-- Populated dynamically via filterCourtsBySport() -->
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5 min-w-0 relative" id="wrapper_court">
                        <label class="block font-bold text-slate-800 truncate">Pilih Court / Lapangan <span class="text-rose-500">*</span></label>
                        <input type="hidden" name="court_id" id="courtIdInput" value="{{ old('court_id') }}" required>
                        <div class="relative min-w-0">
                            <button
                                type="button"
                                id="courtTrigger"
                                onclick="toggleScheduleDropdown('court', event)"
                                class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                            >
                                <span id="courtDisplay" class="truncate font-semibold text-slate-400">Pilih court</span>
                                <i id="courtChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                            </button>

                            <div
                                id="courtMenu"
                                class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150 max-h-60 overflow-y-auto"
                                onclick="event.stopPropagation()"
                            >
                                <!-- Populated dynamically via updateCourtsDropdown() -->
                            </div>
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
                            <span id="jamOperasionalBadge" class="hidden text-[9px] font-extrabold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200">
                                06:00 - 23:00 WIB
                            </span>
                        </div>
                        <div class="relative">
                            <button
                                type="button"
                                id="jamDropdownTrigger"
                                onclick="openClockPicker()"
                                class="w-full bg-slate-50/80 hover:bg-white border border-slate-200/80 hover:border-[#063B00] rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs flex items-center justify-between text-left cursor-pointer group"
                            >
                                <span id="jamSelectedText" class="text-slate-400 group-hover:text-slate-700">Pilih Jam Mulai</span>
                                <i class="fa-regular fa-clock text-xs text-slate-400 group-hover:text-[#063B00] transition-colors"></i>
                            </button>
                            <input type="hidden" name="jam" id="jamMulaiInput" value="{{ old('jam') }}" required>
                        </div>
                        <p id="jamErrorNotice" class="text-[10px] text-rose-600 font-bold hidden"></p>
                    </div>

                    <div class="space-y-1.5 relative" id="wrapper_durasi">
                        <label class="block font-bold text-slate-800">Durasi <span class="text-rose-500">*</span></label>
                        @php
                            $currDurasi = old('durasi', '2 Jam');
                        @endphp
                        <input type="hidden" name="durasi" id="durasiInput" value="{{ $currDurasi }}" required>
                        <div class="relative">
                            <button
                                type="button"
                                id="durasiTrigger"
                                onclick="toggleScheduleDropdown('durasi', event)"
                                class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                            >
                                <span id="durasiDisplay" class="truncate font-semibold">{{ $currDurasi }}</span>
                                <i id="durasiChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                            </button>

                            <div
                                id="durasiMenu"
                                class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
                                onclick="event.stopPropagation()"
                            >
                                @foreach(['1 Jam', '2 Jam', '3 Jam', '4 Jam'] as $dOpt)
                                    <button
                                        type="button"
                                        onclick="selectScheduleOption('durasi', '{{ $dOpt }}', '{{ $dOpt }}', onDurasiChanged)"
                                        class="durasi-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer {{ $currDurasi === $dOpt ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                        data-val="{{ $dOpt }}"
                                    >
                                        <span>{{ $dOpt }}</span>
                                        <i class="fa-solid fa-circle-check text-[#063B00] text-sm shrink-0 {{ $currDurasi === $dOpt ? '' : 'hidden' }}"></i>
                                    </button>
                                @endforeach
                            </div>
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
                    <div class="space-y-1.5 relative" id="wrapper_jumlahPemain">
                        <label class="block font-bold text-slate-800">Kuota Maksimal Pemain <span class="text-rose-500">*</span></label>
                        <input type="hidden" name="jumlah_pemain" id="jumlahPemainInput" value="{{ old('jumlah_pemain') }}" required>
                        <div class="relative">
                            <button
                                type="button"
                                id="jumlahPemainTrigger"
                                onclick="toggleScheduleDropdown('jumlahPemain', event)"
                                class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                            >
                                <span id="jumlahPemainDisplay" class="truncate font-semibold text-slate-400">Pilih Kuota Pemain</span>
                                <i id="jumlahPemainChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                            </button>

                            <div
                                id="jumlahPemainMenu"
                                class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150 max-h-60 overflow-y-auto"
                                onclick="event.stopPropagation()"
                            >
                                <!-- Populated dynamically via onFormatOrScoringChanged() -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Rule / Constraint Hint Alert -->
                <div id="quotaConstraintNotice" class="p-3 rounded-2xl bg-[#EBF8D8]/70 border border-[#063B00]/20 text-[#063B00] text-[11px] flex items-center gap-2.5 shadow-2xs">
                    <i class="fa-solid fa-circle-info text-xs shrink-0"></i>
                    <span id="quotaConstraintText" class="font-medium">Format Americano Double: Kuota fleksibel dengan rotasi bangku cadangan.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                    <div class="space-y-1.5 relative" id="wrapper_levelRekomendasi">
                        <label class="block font-bold text-slate-800">Rekomendasi Level</label>
                        @php
                            $currLevelRek = old('level_rekomendasi', 'All Level');
                            $levelRekMap = [
                                'All Level' => 'All Level Welcome (Bebas Semua Level)',
                                'Newbie - Beginner' => 'Newbie & Beginner Only',
                                'Intermediate' => 'Intermediate Only',
                                'Advanced' => 'Advanced / Competitive Only',
                            ];
                            $currLevelRekLabel = $levelRekMap[$currLevelRek] ?? 'All Level Welcome (Bebas Semua Level)';
                        @endphp
                        <input type="hidden" name="level_rekomendasi" id="levelRekomendasiInput" value="{{ $currLevelRek }}">
                        <div class="relative">
                            <button
                                type="button"
                                id="levelRekomendasiTrigger"
                                onclick="toggleScheduleDropdown('levelRekomendasi', event)"
                                class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                            >
                                <span id="levelRekomendasiDisplay" class="truncate font-semibold">{{ $currLevelRekLabel }}</span>
                                <i id="levelRekomendasiChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                            </button>

                            <div
                                id="levelRekomendasiMenu"
                                class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
                                onclick="event.stopPropagation()"
                            >
                                @foreach($levelRekMap as $lVal => $lLabel)
                                    <button
                                        type="button"
                                        onclick="selectScheduleOption('levelRekomendasi', '{{ $lVal }}', '{{ $lLabel }}')"
                                        class="levelRekomendasi-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer {{ $currLevelRek === $lVal ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                        data-val="{{ $lVal }}"
                                    >
                                        <span class="truncate pr-2">{{ $lLabel }}</span>
                                        <i class="fa-solid fa-circle-check text-[#063B00] text-sm shrink-0 {{ $currLevelRek === $lVal ? '' : 'hidden' }}"></i>
                                    </button>
                                @endforeach
                            </div>
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
                <div class="relative" id="wrapper_quickVenueSport">
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                        Cabang Olahraga <span class="text-rose-500">*</span>
                    </label>
                    <input type="hidden" id="quickVenueSport" value="Padel">
                    <div class="relative">
                        <button
                            type="button"
                            id="quickVenueSportTrigger"
                            onclick="toggleScheduleDropdown('quickVenueSport', event)"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-semibold focus:bg-white focus:border-[#063B00] focus:outline-none transition-colors text-left flex items-center justify-between cursor-pointer"
                        >
                            <span id="quickVenueSportDisplay" class="truncate font-semibold">Padel</span>
                            <i id="quickVenueSportChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                        </button>

                        <div
                            id="quickVenueSportMenu"
                            class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-xl shadow-xl p-1 space-y-1 animate-in fade-in zoom-in-95 duration-150"
                            onclick="event.stopPropagation()"
                        >
                            @foreach(['Padel', 'Tennis'] as $sName)
                                <button
                                    type="button"
                                    onclick="selectScheduleOption('quickVenueSport', '{{ $sName }}', '{{ $sName }}')"
                                    class="quickVenueSport-item-btn w-full px-2.5 py-1.5 rounded-lg text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer {{ $sName === 'Padel' ? 'bg-[#EBF8D8] text-[#063B00] font-bold' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                    data-val="{{ $sName }}"
                                >
                                    <span>{{ $sName }}</span>
                                    <i class="fa-solid fa-circle-check text-[#063B00] text-xs shrink-0 {{ $sName === 'Padel' ? '' : 'hidden' }}"></i>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="relative" id="wrapper_quickVenueCourtCount">
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                        Jumlah Lapangan <span class="text-rose-500">*</span>
                    </label>
                    <input type="hidden" id="quickVenueCourtCount" value="2">
                    <div class="relative">
                        <button
                            type="button"
                            id="quickVenueCourtCountTrigger"
                            onclick="toggleScheduleDropdown('quickVenueCourtCount', event)"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-semibold focus:bg-white focus:border-[#063B00] focus:outline-none transition-colors text-left flex items-center justify-between cursor-pointer"
                        >
                            <span id="quickVenueCourtCountDisplay" class="truncate font-semibold">2 Court</span>
                            <i id="quickVenueCourtCountChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                        </button>

                        <div
                            id="quickVenueCourtCountMenu"
                            class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-xl shadow-xl p-1 space-y-1 animate-in fade-in zoom-in-95 duration-150 max-h-48 overflow-y-auto"
                            onclick="event.stopPropagation()"
                        >
                            @foreach([1 => '1 Court', 2 => '2 Court', 3 => '3 Court', 4 => '4 Court', 6 => '6 Court', 8 => '8 Court'] as $cCount => $cLabel)
                                <button
                                    type="button"
                                    onclick="selectScheduleOption('quickVenueCourtCount', '{{ $cCount }}', '{{ $cLabel }}')"
                                    class="quickVenueCourtCount-item-btn w-full px-2.5 py-1.5 rounded-lg text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer {{ $cCount == 2 ? 'bg-[#EBF8D8] text-[#063B00] font-bold' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                    data-val="{{ $cCount }}"
                                >
                                    <span>{{ $cLabel }}</span>
                                    <i class="fa-solid fa-circle-check text-[#063B00] text-xs shrink-0 {{ $cCount == 2 ? '' : 'hidden' }}"></i>
                                </button>
                            @endforeach
                        </div>
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

<!-- ========================================================= -->
<!-- MATERIAL DESIGN ANALOG & DIGITAL CLOCK PICKER MODAL       -->
<!-- ========================================================= -->
<div id="clockPickerModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeClockPicker();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 flex flex-col gap-4" style="width: 320px; max-width: 95vw; box-sizing: border-box;" onclick="event.stopPropagation();">
        
        <!-- Top Label -->
        <div class="flex items-center justify-between">
            <span id="clockModalLabel" class="text-[11px] font-extrabold tracking-wider text-slate-400 uppercase">
                PILIH JAM MULAI
            </span>
            <button type="button" onclick="closeClockPicker()" class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer p-1">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Digital Time Header Box -->
        <div class="flex items-center justify-center gap-2 pt-1">
            <!-- Hour Box -->
            <button
                type="button"
                id="digitalHourBox"
                onclick="switchClockMode('hour')"
                style="width: 78px; height: 64px; min-width: 78px; min-height: 64px; border-radius: 18px;"
                class="flex items-center justify-center text-4xl font-extrabold font-mono transition-all bg-[#EBF8D8] text-[#063B00] ring-2 ring-[#063B00]/20 cursor-pointer"
            >
                06
            </button>

            <span class="text-3xl font-extrabold text-slate-400">:</span>

            <!-- Minute Box -->
            <button
                type="button"
                id="digitalMinBox"
                onclick="switchClockMode('minute')"
                style="width: 78px; height: 64px; min-width: 78px; min-height: 64px; border-radius: 18px;"
                class="flex items-center justify-center text-4xl font-extrabold font-mono transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 cursor-pointer"
            >
                00
            </button>

            <!-- AM / PM Toggle Column -->
            <div class="border border-slate-200 rounded-xl overflow-hidden flex flex-col ml-1 bg-white">
                <button
                    type="button"
                    id="periodAmBtn"
                    onclick="setClockPeriod('AM')"
                    class="px-3 py-2 text-[11px] font-black transition-all bg-[#EBF8D8] text-[#063B00] cursor-pointer"
                >
                    AM
                </button>
                <div class="h-px bg-slate-200"></div>
                <button
                    type="button"
                    id="periodPmBtn"
                    onclick="setClockPeriod('PM')"
                    class="px-3 py-2 text-[11px] font-black transition-all bg-white text-slate-500 hover:bg-slate-50 cursor-pointer"
                >
                    PM
                </button>
            </div>
        </div>

        <!-- Analog Clock Face (Clean Grey Disc with SVG line and centered numbers) -->
        <div class="flex items-center justify-center py-1">
            <div
                id="clockDialContainer"
                class="relative rounded-full select-none cursor-pointer"
                style="width: 240px; height: 240px; min-width: 240px; min-height: 240px; background-color: #E2E8F0; position: relative; border-radius: 9999px;"
            >
                <!-- SVG Layer for Hand Line & Solid Selection Bubble -->
                <svg id="clockSvg" viewBox="0 0 240 240" style="position: absolute; top: 0; left: 0; width: 240px; height: 240px; pointer-events: none; z-index: 10;">
                    <!-- Hand Line -->
                    <line id="svgHandLine" x1="120" y1="120" x2="120" y2="38" stroke="#063B00" stroke-width="2.5" />
                    <!-- Selected Number Circle Background -->
                    <circle id="svgSelectionBubble" cx="120" cy="38" r="16" fill="#063B00" />
                    <!-- Center Dot -->
                    <circle cx="120" cy="120" r="4" fill="#063B00" />
                </svg>

                <!-- 12 Clickable Number Elements Layer -->
                <div id="clockNumbersGrid" style="position: absolute; top: 0; left: 0; width: 240px; height: 240px; z-index: 20;"></div>
            </div>
        </div>

        <!-- Bottom Actions: CANCEL / OK -->
        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
            <button
                type="button"
                onclick="closeClockPicker()"
                class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-900 transition-colors uppercase tracking-wider cursor-pointer"
            >
                Batal
            </button>
            <button
                type="button"
                onclick="applySelectedTime()"
                class="px-5 py-2 text-xs font-extrabold text-[#063B00] hover:bg-[#EBF8D8] rounded-xl transition-colors uppercase tracking-wider cursor-pointer"
            >
                OK
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Venue and Courts mapping
    let venuesData = @json($venues);
    let currentSportId = parseInt(document.querySelector('input[name="sport_id"]:checked')?.value || 1);

    // =========================================================================
    // Schedule Custom Dropdown System
    // =========================================================================
    const scheduleDropdownIds = [
        'scoringSystem',
        'venue',
        'court',
        'durasi',
        'jumlahPemain',
        'levelRekomendasi',
        'quickVenueSport',
        'quickVenueCourtCount'
    ];

    function closeScheduleDropdowns() {
        scheduleDropdownIds.forEach(id => {
            const menu = document.getElementById(id + 'Menu');
            const chevron = document.getElementById(id + 'Chevron');
            const wrapper = document.getElementById('wrapper_' + id);
            if (menu) menu.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180');
            if (wrapper) wrapper.style.zIndex = '';
        });
    }

    function toggleScheduleDropdown(id, e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById(id + 'Menu');
        const chevron = document.getElementById(id + 'Chevron');
        const wrapper = document.getElementById('wrapper_' + id);
        if (!menu) return;

        const isClosed = menu.classList.contains('hidden');
        closeScheduleDropdowns();

        if (isClosed) {
            menu.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-180');
            if (wrapper) wrapper.style.zIndex = '40';
        }
    }

    function selectScheduleOption(fieldId, value, label, callback) {
        const input = document.getElementById(fieldId + 'Input') || document.getElementById(fieldId);
        const display = document.getElementById(fieldId + 'Display');
        const trigger = document.getElementById(fieldId + 'Trigger');

        if (input) input.value = value;
        if (display) {
            display.textContent = label;
            display.classList.remove('text-slate-400');
            display.classList.add('text-slate-900');
        }
        if (trigger) {
            trigger.classList.remove('border-rose-400', 'bg-rose-50/50');
        }

        document.querySelectorAll('.' + fieldId + '-item-btn').forEach(btn => {
            const isMatch = btn.getAttribute('data-val') === String(value);
            const icon = btn.querySelector('.fa-circle-check');
            if (isMatch) {
                btn.className = fieldId + '-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-bold transition-all flex items-center justify-between cursor-pointer bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/15';
                if (icon) icon.classList.remove('hidden');
            } else {
                btn.className = fieldId + '-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer text-slate-700 hover:bg-slate-100 hover:text-slate-900';
                if (icon) icon.classList.add('hidden');
            }
        });

        closeScheduleDropdowns();
        if (typeof callback === 'function') {
            callback();
        }
    }

    function filterCourtsBySport(sportId, preserveVenueId = null) {
        currentSportId = parseInt(sportId);
        const venueMenu = document.getElementById('venueMenu');
        const venueDisplay = document.getElementById('venueDisplay');
        const venueInput = document.getElementById('venueIdInput');
        if (!venueMenu || !venueInput) return;

        venueMenu.innerHTML = '';

        // Filter venues having courts for this sport with status Available
        const matchingVenues = venuesData.filter(v => {
            if (!v.courts || !Array.isArray(v.courts)) return false;
            return v.courts.some(c => parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available');
        });

        if (matchingVenues.length === 0) {
            venueInput.value = '';
            if (venueDisplay) {
                venueDisplay.textContent = 'Tidak ada venue tersedia untuk cabang olahraga ini';
                venueDisplay.classList.add('text-slate-400');
                venueDisplay.classList.remove('text-slate-900');
            }
            venueMenu.innerHTML = '<div class="p-3 text-center text-xs text-slate-400 font-medium">Tidak ada venue dengan lapangan tersedia</div>';
            updateCourtsDropdown();
            return;
        }

        let selectedVenueObj = null;
        if (preserveVenueId) {
            selectedVenueObj = matchingVenues.find(v => parseInt(v.venue_id) === parseInt(preserveVenueId));
        }
        if (!selectedVenueObj && venueInput.value) {
            selectedVenueObj = matchingVenues.find(v => parseInt(v.venue_id) === parseInt(venueInput.value));
        }

        matchingVenues.forEach(v => {
            const availableCount = v.courts.filter(c => parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available').length;
            const maxNameLen = 35;
            const truncatedName = v.nama_venue && v.nama_venue.length > maxNameLen ? v.nama_venue.substring(0, maxNameLen) + '...' : v.nama_venue;
            const label = `${truncatedName} (${availableCount} Court Tersedia)`;

            const isSelected = selectedVenueObj && parseInt(selectedVenueObj.venue_id) === parseInt(v.venue_id);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `venue-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer ${isSelected ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'}`;
            btn.setAttribute('data-val', v.venue_id);
            btn.title = `${v.nama_venue} (${availableCount} Court Tersedia)`;
            btn.innerHTML = `<span class="truncate pr-2">${label}</span><i class="fa-solid fa-circle-check text-[#063B00] text-sm shrink-0 ${isSelected ? '' : 'hidden'}"></i>`;
            btn.onclick = () => {
                selectScheduleOption('venue', v.venue_id, label, () => {
                    updateCourtsDropdown();
                    onTanggalChanged();
                });
            };
            venueMenu.appendChild(btn);
        });

        if (selectedVenueObj) {
            const availableCount = selectedVenueObj.courts.filter(c => parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available').length;
            const maxNameLen = 35;
            const truncatedName = selectedVenueObj.nama_venue && selectedVenueObj.nama_venue.length > maxNameLen ? selectedVenueObj.nama_venue.substring(0, maxNameLen) + '...' : selectedVenueObj.nama_venue;
            const label = `${truncatedName} (${availableCount} Court Tersedia)`;
            venueInput.value = selectedVenueObj.venue_id;
            if (venueDisplay) {
                venueDisplay.textContent = label;
                venueDisplay.classList.remove('text-slate-400');
                venueDisplay.classList.add('text-slate-900');
            }
        } else {
            venueInput.value = '';
            if (venueDisplay) {
                venueDisplay.textContent = 'Pilih venue sesuai olahraga';
                venueDisplay.classList.add('text-slate-400');
                venueDisplay.classList.remove('text-slate-900');
            }
        }

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
                    const venueInput = document.getElementById('venueIdInput');
                    if (!venueInput || !venueInput.value) return false;
                    const venueId = parseInt(venueInput.value);
                    const selectedVenue = venuesData.find(v => v.venue_id === venueId);
                    return isDateDisabledForVenue(date, selectedVenue);
                }
            ],
            onChange: function(selectedDates, dateStr) {
                onTanggalChanged();
            }
        });
    }

    // =========================================================
    // MATERIAL DESIGN ANALOG & DIGITAL CLOCK PICKER LOGIC
    // =========================================================
    let currentClockMode = 'hour';   // 'hour' | 'minute'
    let selectedHour12 = 6;          // 1 - 12
    let selectedMinute = 0;          // 0 - 59
    let selectedPeriod = 'AM';       // 'AM' | 'PM'

    function openClockPicker() {
        const rawVal = document.getElementById('jamMulaiInput')?.value || '06:00';
        const parts = rawVal.split(':');
        let h24 = parseInt(parts[0] || '6', 10);
        let m = parseInt(parts[1] || '0', 10);

        if (isNaN(h24)) h24 = 6;
        if (isNaN(m)) m = 0;

        selectedPeriod = h24 >= 12 ? 'PM' : 'AM';
        selectedHour12 = h24 % 12;
        if (selectedHour12 === 0) selectedHour12 = 12;
        selectedMinute = m;

        currentClockMode = 'hour';
        renderClockPicker();
        const modal = document.getElementById('clockPickerModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeClockPicker() {
        const modal = document.getElementById('clockPickerModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function switchClockMode(mode) {
        currentClockMode = mode;
        renderClockPicker();
    }

    function setClockPeriod(period) {
        selectedPeriod = period;
        renderClockPicker();
    }

    function renderClockPicker() {
        // 1. Digital Display Boxes
        const hourBox = document.getElementById('digitalHourBox');
        const minBox = document.getElementById('digitalMinBox');
        const periodAmBtn = document.getElementById('periodAmBtn');
        const periodPmBtn = document.getElementById('periodPmBtn');

        if (hourBox) hourBox.textContent = String(selectedHour12).padStart(2, '0');
        if (minBox) minBox.textContent = String(selectedMinute).padStart(2, '0');

        if (currentClockMode === 'hour') {
            if (hourBox) hourBox.className = "flex items-center justify-center text-4xl font-extrabold font-mono transition-all bg-[#EBF8D8] text-[#063B00] ring-2 ring-[#063B00]/20 cursor-pointer";
            if (minBox) minBox.className = "flex items-center justify-center text-4xl font-extrabold font-mono transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 cursor-pointer";
        } else {
            if (hourBox) hourBox.className = "flex items-center justify-center text-4xl font-extrabold font-mono transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 cursor-pointer";
            if (minBox) minBox.className = "flex items-center justify-center text-4xl font-extrabold font-mono transition-all bg-[#EBF8D8] text-[#063B00] ring-2 ring-[#063B00]/20 cursor-pointer";
        }

        if (selectedPeriod === 'AM') {
            if (periodAmBtn) periodAmBtn.className = "px-3 py-2 text-[11px] font-black transition-all bg-[#EBF8D8] text-[#063B00] cursor-pointer";
            if (periodPmBtn) periodPmBtn.className = "px-3 py-2 text-[11px] font-black transition-all bg-white text-slate-500 hover:bg-slate-50 cursor-pointer";
        } else {
            if (periodAmBtn) periodAmBtn.className = "px-3 py-2 text-[11px] font-black transition-all bg-white text-slate-500 hover:bg-slate-50 cursor-pointer";
            if (periodPmBtn) periodPmBtn.className = "px-3 py-2 text-[11px] font-black transition-all bg-[#EBF8D8] text-[#063B00] cursor-pointer";
        }

        // 2. Analog Face & SVG Pointer
        const center = 120;
        const radius = 82;
        const grid = document.getElementById('clockNumbersGrid');
        if (!grid) return;
        grid.innerHTML = '';

        const handLine = document.getElementById('svgHandLine');
        const bubble = document.getElementById('svgSelectionBubble');

        let activeTargetX = center;
        let activeTargetY = center - radius;

        if (currentClockMode === 'hour') {
            const hours = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
            hours.forEach(num => {
                const angleDeg = (num % 12) * 30 - 90;
                const angleRad = (angleDeg * Math.PI) / 180;
                const x = Math.round(center + radius * Math.cos(angleRad));
                const y = Math.round(center + radius * Math.sin(angleRad));

                const isSelected = num === selectedHour12;
                if (isSelected) {
                    activeTargetX = x;
                    activeTargetY = y;
                }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.style.position = 'absolute';
                btn.style.left = `${x}px`;
                btn.style.top = `${y}px`;
                btn.style.transform = 'translate(-50%, -50%)';
                btn.style.width = '32px';
                btn.style.height = '32px';
                btn.style.borderRadius = '9999px';
                btn.style.display = 'flex';
                btn.style.alignItems = 'center';
                btn.style.justifyContent = 'center';
                btn.style.fontSize = '12px';
                btn.style.cursor = 'pointer';
                btn.style.zIndex = '30';
                btn.style.border = 'none';
                btn.style.background = 'transparent';
                btn.style.transition = 'all 0.15s ease';
                btn.style.fontWeight = isSelected ? '900' : '600';
                btn.style.color = isSelected ? '#ffffff' : '#334155';
                btn.textContent = num;

                btn.onclick = (e) => {
                    e.stopPropagation();
                    selectedHour12 = num;
                    renderClockPicker();
                    setTimeout(() => {
                        switchClockMode('minute');
                    }, 180);
                };

                grid.appendChild(btn);
            });
        } else {
            // Minutes 00, 05, 10, 15, ..., 55
            const minutes = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];
            minutes.forEach(min => {
                const angleDeg = (min / 60) * 360 - 90;
                const angleRad = (angleDeg * Math.PI) / 180;
                const x = Math.round(center + radius * Math.cos(angleRad));
                const y = Math.round(center + radius * Math.sin(angleRad));

                const isSelected = min === selectedMinute;
                if (isSelected) {
                    activeTargetX = x;
                    activeTargetY = y;
                }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.style.position = 'absolute';
                btn.style.left = `${x}px`;
                btn.style.top = `${y}px`;
                btn.style.transform = 'translate(-50%, -50%)';
                btn.style.width = '32px';
                btn.style.height = '32px';
                btn.style.borderRadius = '9999px';
                btn.style.display = 'flex';
                btn.style.alignItems = 'center';
                btn.style.justifyContent = 'center';
                btn.style.fontSize = '11px';
                btn.style.cursor = 'pointer';
                btn.style.zIndex = '30';
                btn.style.border = 'none';
                btn.style.background = 'transparent';
                btn.style.transition = 'all 0.15s ease';
                btn.style.fontWeight = isSelected ? '900' : '600';
                btn.style.color = isSelected ? '#ffffff' : '#334155';
                btn.textContent = String(min).padStart(2, '0');

                btn.onclick = (e) => {
                    e.stopPropagation();
                    selectedMinute = min;
                    renderClockPicker();
                };

                grid.appendChild(btn);
            });
        }

        // Update SVG Hand Line and Bubble position
        if (handLine) {
            handLine.setAttribute('x2', activeTargetX);
            handLine.setAttribute('y2', activeTargetY);
        }
        if (bubble) {
            bubble.setAttribute('cx', activeTargetX);
            bubble.setAttribute('cy', activeTargetY);
        }
    }

    function applySelectedTime() {
        let h24 = selectedHour12 % 12;
        if (selectedPeriod === 'PM') {
            h24 += 12;
        }
        const formatted = `${String(h24).padStart(2, '0')}:${String(selectedMinute).padStart(2, '0')}`;

        const jamInput = document.getElementById('jamMulaiInput');
        const jamText = document.getElementById('jamSelectedText');
        const jamTrigger = document.getElementById('jamDropdownTrigger');

        if (jamInput) jamInput.value = formatted;
        if (jamText) {
            jamText.textContent = `${formatted} WIB`;
            jamText.classList.remove('text-slate-400');
            jamText.classList.add('text-slate-900');
        }
        if (jamTrigger) {
            jamTrigger.classList.remove('border-rose-400', 'bg-rose-50/50');
        }

        const jamError = document.getElementById('jamErrorNotice');
        if (jamError) jamError.classList.add('hidden');

        validateTanggalAndJam();
        closeClockPicker();
    }

    function onDurasiChanged() {
        validateTanggalAndJam();
    }

    function onTanggalChanged() {
        const tanggalInput = document.getElementById('tanggalMabarInput');
        const tanggalError = document.getElementById('tanggalErrorNotice');
        if (tanggalInput && tanggalInput.value) {
            tanggalInput.classList.remove('border-rose-400', 'bg-rose-50/50');
            if (tanggalError) tanggalError.classList.add('hidden');
        }

        const venueInput = document.getElementById('venueIdInput');
        if (venueInput && venueInput.value) {
            const venueId = parseInt(venueInput.value);
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
            if (badge) badge.classList.add('hidden');
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
        if (badge) {
            badge.innerText = `${hours.open} - ${hours.close} WIB`;
            badge.classList.remove('hidden');
        }

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

        validateTanggalAndJam();
    }

    function validateTanggalAndJam() {
        const venueInput = document.getElementById('venueIdInput');
        const jamInput = document.getElementById('jamMulaiInput');
        const tanggalInput = document.getElementById('tanggalMabarInput');
        const durasiInput = document.getElementById('durasiInput');
        const jamError = document.getElementById('jamErrorNotice');
        const tanggalError = document.getElementById('tanggalErrorNotice');
        const submitBtn = document.querySelector('button[type="submit"]');

        if (!venueInput || !jamInput || !tanggalInput) return;

        const venueId = parseInt(venueInput.value);
        const selectedVenue = isNaN(venueId) ? null : venuesData.find(v => v.venue_id === venueId);
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
                const durasiHours = parseInt(durasiInput?.value || '2');
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
        const venueInput = document.getElementById('venueIdInput');
        const courtMenu = document.getElementById('courtMenu');
        const courtDisplay = document.getElementById('courtDisplay');
        const courtInput = document.getElementById('courtIdInput');
        if (!courtMenu || !courtInput) return;
        courtMenu.innerHTML = '';

        const venueId = venueInput ? parseInt(venueInput.value) : NaN;
        const selectedVenue = isNaN(venueId) ? null : venuesData.find(v => v.venue_id === venueId);

        // Update jam operasional & ketersediaan venue
        updateVenueOperatingHours(selectedVenue);

        if (!selectedVenue) {
            courtInput.value = '';
            if (courtDisplay) {
                courtDisplay.textContent = 'Pilih venue terlebih dahulu';
                courtDisplay.classList.add('text-slate-400');
                courtDisplay.classList.remove('text-slate-900');
            }
            courtMenu.innerHTML = '<div class="p-3 text-center text-xs text-slate-400 font-medium">Pilih venue terlebih dahulu</div>';
            return;
        }

        const filteredCourts = (selectedVenue.courts || []).filter(c => 
            parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available'
        );

        if (filteredCourts.length === 0) {
            courtInput.value = '';
            if (courtDisplay) {
                courtDisplay.textContent = 'Tidak ada court yang tersedia';
                courtDisplay.classList.add('text-slate-400');
                courtDisplay.classList.remove('text-slate-900');
            }
            courtMenu.innerHTML = '<div class="p-3 text-center text-xs text-slate-400 font-medium">Tidak ada court yang tersedia untuk olahraga ini</div>';
            return;
        }

        let selectedCourtObj = null;
        if (courtInput.value) {
            selectedCourtObj = filteredCourts.find(c => parseInt(c.court_id) === parseInt(courtInput.value));
        }
        if (!selectedCourtObj) {
            selectedCourtObj = filteredCourts[0];
        }

        filteredCourts.forEach(court => {
            const maxCourtLen = 35;
            const truncatedCourt = court.nama_court && court.nama_court.length > maxCourtLen ? court.nama_court.substring(0, maxCourtLen) + '...' : court.nama_court;
            const isSelected = selectedCourtObj && parseInt(selectedCourtObj.court_id) === parseInt(court.court_id);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `court-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer ${isSelected ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'}`;
            btn.setAttribute('data-val', court.court_id);
            btn.title = court.nama_court;
            btn.innerHTML = `<span class="truncate pr-2">${truncatedCourt}</span><i class="fa-solid fa-circle-check text-[#063B00] text-sm shrink-0 ${isSelected ? '' : 'hidden'}"></i>`;
            btn.onclick = () => {
                selectScheduleOption('court', court.court_id, truncatedCourt);
            };
            courtMenu.appendChild(btn);
        });

        if (selectedCourtObj) {
            const maxCourtLen = 35;
            const truncatedCourt = selectedCourtObj.nama_court && selectedCourtObj.nama_court.length > maxCourtLen ? selectedCourtObj.nama_court.substring(0, maxCourtLen) + '...' : selectedCourtObj.nama_court;
            courtInput.value = selectedCourtObj.court_id;
            if (courtDisplay) {
                courtDisplay.textContent = truncatedCourt;
                courtDisplay.classList.remove('text-slate-400');
                courtDisplay.classList.add('text-slate-900');
            }
        }
    }

    // =========================================================================
    // Format, Single/Double, and Quota Constraints Logic
    // =========================================================================
    function onFormatOrScoringChanged() {
        const formatInput = document.querySelector('input[name="format"]:checked')?.value || 'Americano';
        const scoringInput = document.getElementById('scoringSystemInput');
        const scoringSystem = scoringInput ? scoringInput.value : 'Total of 3';
        const isFirstTo = scoringSystem.toLowerCase().startsWith('first to');
        const isTeamAmericano = formatInput.toLowerCase().includes('team');

        const radioDouble = document.getElementById('radioDouble');
        const radioSingle = document.getElementById('radioSingle');
        const labelSingle = document.getElementById('labelSingle');
        const teamLockBadge = document.getElementById('teamLockBadge');
        const quotaMenu = document.getElementById('jumlahPemainMenu');
        const quotaInput = document.getElementById('jumlahPemainInput');
        const quotaDisplay = document.getElementById('jumlahPemainDisplay');
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
        const currentVal = quotaInput ? quotaInput.value : null;
        if (quotaMenu) quotaMenu.innerHTML = '';

        let options = [];
        let noticeMessage = '';

        if (isTeamAmericano) {
            if (isFirstTo) {
                options = [
                    { val: '4', text: '4 Pemain (Tepat 2 Pasang Tim - First to X)' }
                ];
                noticeMessage = `🎯 <strong>Team Americano (${scoringSystem})</strong>: Pertandingan langsung tuntas 1 court, kuota terkunci <strong>tepat 4 pemain (2 tim)</strong>.`;
            } else {
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
                options = [
                    { val: '2', text: '2 Pemain (Tepat 1 vs 1 - First to X)' }
                ];
                noticeMessage = `🎯 <strong>Americano Single (${scoringSystem})</strong>: Pertandingan 1v1 langsung tuntas, kuota terkunci <strong>tepat 2 pemain</strong>.`;
            } else {
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
            if (isFirstTo) {
                options = [
                    { val: '4', text: '4 Pemain (Tepat 2 vs 2 - First to X)' }
                ];
                noticeMessage = `🎯 <strong>Americano Double (${scoringSystem})</strong>: Pertandingan 2v2 langsung tuntas, kuota terkunci <strong>tepat 4 pemain</strong>.`;
            } else {
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

        let selectedOpt = options.find(o => String(o.val) === String(currentVal)) || options[0];

        if (quotaMenu) {
            options.forEach((o) => {
                const isSelected = selectedOpt && String(o.val) === String(selectedOpt.val);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `jumlahPemain-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer ${isSelected ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'}`;
                btn.setAttribute('data-val', o.val);
                btn.innerHTML = `<span class="truncate pr-2">${o.text}</span><i class="fa-solid fa-circle-check text-[#063B00] text-sm shrink-0 ${isSelected ? '' : 'hidden'}"></i>`;
                btn.onclick = () => {
                    selectScheduleOption('jumlahPemain', o.val, o.text);
                };
                quotaMenu.appendChild(btn);
            });
        }

        if (selectedOpt && quotaInput && quotaDisplay) {
            quotaInput.value = selectedOpt.val;
            quotaDisplay.textContent = selectedOpt.text;
            quotaDisplay.classList.remove('text-slate-400');
            quotaDisplay.classList.add('text-slate-900');
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
        const sportInput = document.getElementById('quickVenueSport');
        const sportDisplay = document.getElementById('quickVenueSportDisplay');
        const countInput = document.getElementById('quickVenueCourtCount');
        const countDisplay = document.getElementById('quickVenueCourtCountDisplay');
        const errDiv = document.getElementById('quickVenueError');
        if (errDiv) errDiv.classList.add('hidden');
        
        const activeSportRadio = document.querySelector('input[name="sport_id"]:checked');
        const activeSportName = activeSportRadio ? activeSportRadio.getAttribute('data-sport-name') : 'Padel';
        if (sportInput && activeSportName) {
            sportInput.value = activeSportName;
            if (sportDisplay) sportDisplay.textContent = activeSportName;
            document.querySelectorAll('.quickVenueSport-item-btn').forEach(btn => {
                const isMatch = btn.getAttribute('data-val') === activeSportName;
                const icon = btn.querySelector('.fa-circle-check');
                if (isMatch) {
                    btn.className = 'quickVenueSport-item-btn w-full px-2.5 py-1.5 rounded-lg text-left text-xs font-bold transition-all flex items-center justify-between cursor-pointer bg-[#EBF8D8] text-[#063B00]';
                    if (icon) icon.classList.remove('hidden');
                } else {
                    btn.className = 'quickVenueSport-item-btn w-full px-2.5 py-1.5 rounded-lg text-left text-xs font-semibold transition-all flex items-center justify-between cursor-pointer text-slate-700 hover:bg-slate-100 hover:text-slate-900';
                    if (icon) icon.classList.add('hidden');
                }
            });
        }

        if (countInput) countInput.value = 2;
        if (countDisplay) countDisplay.textContent = '2 Court';

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

        const venueInput = document.getElementById('venueIdInput');
        if (venueInput && venueInput.value) {
            const venueId = parseInt(venueInput.value);
            const selectedVenue = venuesData.find(v => v.venue_id === venueId);
            if (selectedVenue) {
                updateVenueOperatingHours(selectedVenue);
            }
        } else {
            validateTanggalAndJam();
        }

        // Inisialisasi teks Jam Mulai jika sudah ada nilai sebelumnya (misal dari old input)
        const jamInputInit = document.getElementById('jamMulaiInput');
        const jamTextInit = document.getElementById('jamSelectedText');
        if (jamInputInit && jamInputInit.value && jamTextInit) {
            jamTextInit.textContent = `${jamInputInit.value} WIB`;
            jamTextInit.classList.remove('text-slate-400');
            jamTextInit.classList.add('text-slate-900');
        }

        // Listener klik langsung pada dial piringan jam analog
        const dial = document.getElementById('clockDialContainer');
        if (dial) {
            dial.addEventListener('click', function (e) {
                if (e.target.tagName && e.target.tagName.toLowerCase() === 'button') return;
                const rect = dial.getBoundingClientRect();
                const x = e.clientX - rect.left - 120;
                const y = e.clientY - rect.top - 120;
                let angleRad = Math.atan2(y, x);
                let angleDeg = (angleRad * 180) / Math.PI + 90;
                if (angleDeg < 0) angleDeg += 360;

                if (currentClockMode === 'hour') {
                    let hour = Math.round(angleDeg / 30);
                    if (hour === 0) hour = 12;
                    selectedHour12 = hour;
                    renderClockPicker();
                    setTimeout(() => {
                        switchClockMode('minute');
                    }, 180);
                } else {
                    let min = Math.round(angleDeg / 30) * 5;
                    if (min >= 60) min = 0;
                    selectedMinute = min;
                    renderClockPicker();
                }
            });
        }

        // Realtime input listener untuk Judul Sesi Mabar
        const sessionInput = document.getElementById('namaSessionInput');
        const sessionError = document.getElementById('namaSessionError');
        if (sessionInput) {
            sessionInput.addEventListener('input', function() {
                if (this.value.trim()) {
                    if (sessionError) sessionError.classList.add('hidden');
                    this.classList.remove('border-rose-400', 'bg-rose-50/50');
                }
            });
        }

        // Global Outside click listener untuk custom dropdowns
        document.addEventListener('click', function (e) {
            let isInsideAny = false;
            scheduleDropdownIds.forEach(id => {
                const menu = document.getElementById(id + 'Menu');
                const trigger = document.getElementById(id + 'Trigger');
                if ((menu && menu.contains(e.target)) || (trigger && trigger.contains(e.target))) {
                    isInsideAny = true;
                }
            });
            if (!isInsideAny) {
                closeScheduleDropdowns();
            }
        });

        // Global Escape key listener untuk menutup dropdown
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeScheduleDropdowns();
            }
        });

        // Validasi form submit seragam tanpa tooltip browser bawaan
        const form = document.getElementById('scheduleForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                let firstInvalidField = null;

                // 1. Validasi Judul Sesi Mabar
                if (sessionInput && !sessionInput.value.trim()) {
                    if (sessionError) {
                        sessionError.innerText = '⚠️ Judul sesi mabar wajib diisi.';
                        sessionError.classList.remove('hidden');
                    }
                    sessionInput.classList.add('border-rose-400', 'bg-rose-50/50');
                    if (!firstInvalidField) firstInvalidField = sessionInput;
                } else if (sessionInput) {
                    if (sessionError) sessionError.classList.add('hidden');
                    sessionInput.classList.remove('border-rose-400', 'bg-rose-50/50');
                }

                // 2. Validasi Venue
                const vInput = document.getElementById('venueIdInput');
                const vTrigger = document.getElementById('venueTrigger');
                if (vInput && !vInput.value) {
                    if (vTrigger) vTrigger.classList.add('border-rose-400', 'bg-rose-50/50');
                    if (!firstInvalidField) firstInvalidField = vTrigger;
                } else if (vTrigger) {
                    vTrigger.classList.remove('border-rose-400', 'bg-rose-50/50');
                }

                // 3. Validasi Court
                const cInput = document.getElementById('courtIdInput');
                const cTrigger = document.getElementById('courtTrigger');
                if (cInput && !cInput.value) {
                    if (cTrigger) cTrigger.classList.add('border-rose-400', 'bg-rose-50/50');
                    if (!firstInvalidField) firstInvalidField = cTrigger;
                } else if (cTrigger) {
                    cTrigger.classList.remove('border-rose-400', 'bg-rose-50/50');
                }

                // 4. Validasi Tanggal
                const tanggalInput = document.getElementById('tanggalMabarInput');
                const tanggalError = document.getElementById('tanggalErrorNotice');
                if (tanggalInput && !tanggalInput.value) {
                    if (tanggalError) {
                        tanggalError.innerText = '⚠️ Tanggal mabar wajib dipilih.';
                        tanggalError.classList.remove('hidden');
                    }
                    tanggalInput.classList.add('border-rose-400', 'bg-rose-50/50');
                    if (!firstInvalidField) firstInvalidField = tanggalInput;
                }

                // 5. Validasi Jam Mulai
                const jamInput = document.getElementById('jamMulaiInput');
                const jamTrigger = document.getElementById('jamDropdownTrigger');
                const jamError = document.getElementById('jamErrorNotice');
                if (jamInput && !jamInput.value) {
                    if (jamError) {
                        jamError.innerText = '⚠️ Jam mulai wajib dipilih.';
                        jamError.classList.remove('hidden');
                    }
                    if (jamTrigger) jamTrigger.classList.add('border-rose-400', 'bg-rose-50/50');
                    if (!firstInvalidField) firstInvalidField = jamTrigger;
                }

                // 6. Validasi Durasi
                const dInput = document.getElementById('durasiInput');
                const dTrigger = document.getElementById('durasiTrigger');
                if (dInput && !dInput.value) {
                    if (dTrigger) dTrigger.classList.add('border-rose-400', 'bg-rose-50/50');
                    if (!firstInvalidField) firstInvalidField = dTrigger;
                } else if (dTrigger) {
                    dTrigger.classList.remove('border-rose-400', 'bg-rose-50/50');
                }

                // 7. Validasi Kuota Pemain
                const kInput = document.getElementById('jumlahPemainInput');
                const kTrigger = document.getElementById('jumlahPemainTrigger');
                if (kInput && !kInput.value) {
                    if (kTrigger) kTrigger.classList.add('border-rose-400', 'bg-rose-50/50');
                    if (!firstInvalidField) firstInvalidField = kTrigger;
                } else if (kTrigger) {
                    kTrigger.classList.remove('border-rose-400', 'bg-rose-50/50');
                }

                if (firstInvalidField) {
                    e.preventDefault();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof firstInvalidField.focus === 'function') {
                        firstInvalidField.focus();
                    }
                }
            });
        }
    });
</script>
@endpush
@endsection
