@extends('layouts.app')

@section('content')
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
                <div class="flex items-center justify-between">
                    <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">4</span>
                        Lokasi Venue &amp; Lapangan
                    </label>
                    <button type="button" onclick="openQuickAddVenueModal()" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#063B00] bg-[#EBF8D8] hover:bg-[#d9f2b8] px-3 py-1 rounded-full border border-[#063B00]/20 transition-all shadow-2xs hover:scale-[1.02] active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-plus text-[10px]"></i> <span>Tambah Venue</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Pilih Venue / Tempat</label>
                        <div class="relative">
                            <select name="venue_id" id="venueSelect" onchange="updateCourtsDropdown()" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs" required>
                                <option value="" disabled selected>Pilih venue sesuai olahraga</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Pilih Court / Lapangan</label>
                        <div class="relative">
                            <select name="court_id" id="courtSelect" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs" required>
                                <option value="" disabled selected>Pilih court</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
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
                        <input type="date" name="tanggal" value="{{ date('Y-m-d', strtotime('+1 day')) }}" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Jam Mulai</label>
                        <input type="time" name="jam" value="18:30" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Durasi</label>
                        <div class="relative">
                            <select name="durasi" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="1 Jam">1 Jam</option>
                                <option value="2 Jam" selected>2 Jam</option>
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
                            <select name="jumlah_pemain" id="jumlahPemainSelect" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <!-- Options will be dynamically populated by JS -->
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
            <div class="pt-2 flex items-center gap-2">
                <button
                    type="button"
                    onclick="closeQuickAddVenueModal()"
                    class="w-1/3 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    id="btnSubmitQuickVenue"
                    class="w-2/3 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <i class="fa-solid fa-floppy-disk text-xs text-[#A8E63A]"></i> Simpan Venue
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Venue and Courts mapping
    let venuesData = @json($venues);
    let currentSportId = parseInt(document.querySelector('input[name="sport_id"]:checked')?.value || 1);

    function filterCourtsBySport(sportId, preserveVenueId = null) {
        currentSportId = parseInt(sportId);
        const venueSelect = document.getElementById('venueSelect');
        if (!venueSelect) return;

        venueSelect.innerHTML = '';

        // Filter venues having courts for this sport with status Available
        const matchingVenues = venuesData.filter(v => {
            if (!v.courts || !Array.isArray(v.courts)) return false;
            return v.courts.some(c => parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available');
        });

        if (matchingVenues.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.disabled = true;
            opt.selected = true;
            opt.innerText = 'Tidak ada venue dengan lapangan tersedia untuk cabang olahraga ini';
            venueSelect.appendChild(opt);
            updateCourtsDropdown();
            return;
        }

        matchingVenues.forEach(v => {
            const availableCount = v.courts.filter(c => parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available').length;
            const opt = document.createElement('option');
            opt.value = v.venue_id;
            opt.innerText = `${v.nama_venue} (${availableCount} Court Tersedia)`;
            if (preserveVenueId && parseInt(v.venue_id) === parseInt(preserveVenueId)) {
                opt.selected = true;
            }
            venueSelect.appendChild(opt);
        });

        if (!preserveVenueId) {
            venueSelect.selectedIndex = 0;
        }
        updateCourtsDropdown();
    }

    function updateCourtsDropdown() {
        const venueSelect = document.getElementById('venueSelect');
        const courtSelect = document.getElementById('courtSelect');
        if (!venueSelect || !courtSelect) return;
        courtSelect.innerHTML = '';

        const venueId = parseInt(venueSelect.value);
        const selectedVenue = venuesData.find(v => v.venue_id === venueId);

        if (selectedVenue && selectedVenue.courts && selectedVenue.courts.length > 0) {
            const filteredCourts = selectedVenue.courts.filter(c => 
                parseInt(c.sport_id) === currentSportId && c.status_ketersediaan === 'Available'
            );

            if (filteredCourts.length > 0) {
                filteredCourts.forEach(court => {
                    const opt = document.createElement('option');
                    opt.value = court.court_id;
                    opt.innerText = court.nama_court;
                    courtSelect.appendChild(opt);
                });
            } else {
                const opt = document.createElement('option');
                opt.value = '';
                opt.disabled = true;
                opt.innerText = 'Tidak ada court yang tersedia untuk olahraga ini';
                courtSelect.appendChild(opt);
            }
        } else {
            const opt = document.createElement('option');
            opt.value = '';
            opt.disabled = true;
            opt.innerText = 'Belum ada lapangan';
            courtSelect.appendChild(opt);
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

        let selected = false;
        options.forEach((o, index) => {
            const opt = document.createElement('option');
            opt.value = o.val;
            opt.innerText = o.text;
            if (currentVal && String(o.val) === String(currentVal)) {
                opt.selected = true;
                selected = true;
            } else if (!selected && (o.val === '4' || o.val === '6' || index === 0)) {
                opt.selected = true;
            }
            if (quotaSelect) quotaSelect.appendChild(opt);
        });

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
            const newVenue = {
                venue_id: data.venue.venue_id,
                nama_venue: data.venue.nama_venue,
                courts: (data.courts || []).map(c => ({
                    court_id: c.court_id,
                    nama_court: c.nama_court,
                    sport_id: c.sport_id,
                    status_ketersediaan: 'Available'
                }))
            };
            venuesData.unshift(newVenue);

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
        filterCourtsBySport(currentSportId);
        onFormatOrScoringChanged();
    });
</script>
@endpush
@endsection
