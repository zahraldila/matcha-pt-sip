@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-10 pb-28 md:pb-12 space-y-8 relative">

    <!-- Ambient Glowing Background Orbs -->
    <div class="absolute w-96 h-96 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-12 -left-12 -z-10"></div>
    <div class="absolute w-96 h-96 bg-[#063B00]/10 rounded-full blur-3xl pointer-events-none top-1/2 -right-12 -z-10"></div>
    <div class="absolute w-80 h-80 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none -bottom-10 left-1/3 -z-10"></div>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/60 relative z-10">
        <div>
            <a href="{{ route('communities.show', $community->community_id) }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-[#063B00] transition-colors mb-3 group">
                <span class="w-7 h-7 rounded-xl bg-white/80 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-[#063B00] group-hover:text-white transition-all shadow-2xs">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                </span>
                Kembali ke Detail Komunitas
            </a>
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-extrabold uppercase tracking-wider">
                    Community Manager
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-medium text-slate-500">Edit Data Komunitas</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                Edit Profil Komunitas
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl leading-relaxed flex items-center gap-1.5 flex-wrap">
                <span>Perbarui informasi dan rincian untuk klub</span>
                <span class="font-bold text-slate-800 bg-slate-100/90 px-2 py-0.5 rounded-lg border border-slate-200/80 inline-block max-w-xs sm:max-w-md truncate align-middle" title="{{ $community->nama_community }}">{{ $community->nama_community }}</span>
            </p>
        </div>

        <div class="hidden sm:flex flex-col items-end gap-2">
            <div class="px-4 py-2 rounded-2xl bg-white/60 backdrop-blur-md border border-white/80 shadow-2xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-users text-[#A8E63A]"></i>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status Komunitas</p>
                    <p class="text-xs font-black text-[#063B00]">{{ $community->status_keanggotaan ?: 'Active' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Glassmorphism Form Card -->
    <div class="backdrop-blur-2xl bg-white/80 border border-white/90 rounded-3xl p-6 sm:p-10 shadow-[0_20px_50px_rgba(6,59,0,0.06)] relative z-10 space-y-8">

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Mohon periksa data yang belum sesuai:</span>
                </div>
                <ul class="list-disc list-inside pl-1 text-[11px] text-rose-600 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="communityForm" action="{{ route('communities.update', $community->community_id) }}" method="POST" enctype="multipart/form-data" class="space-y-8 text-xs" novalidate onsubmit="validateCommunityForm(event)">
            @csrf
            @method('PUT')
            
            <!-- SECTION 1: Identitas Komunitas -->
            <div class="space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">1</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Identitas &amp; Profil Komunitas</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Nama Komunitas / Klub <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-users-rectangle absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="input_nama_community" name="nama_community" value="{{ old('nama_community', $community->nama_community) }}" required maxlength="70" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                        <p id="err_nama_community" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Nama komunitas wajib diisi.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Slogan / Tagline Komunitas</label>
                        <div class="relative">
                            <i class="fa-solid fa-quote-left absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="input_tagline" name="tagline" value="{{ old('tagline', $community->tagline) }}" placeholder="Contoh: Mabar Seru, Keringat Bareng, Rating Naik!" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Kota Homebase <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="fa-solid fa-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="input_kota" name="kota_homebase" value="{{ old('kota_homebase', $community->kota_homebase) }}" required placeholder="Contoh: Jakarta Selatan, Bandung, Surabaya" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                        <p id="err_kota" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Kota homebase wajib diisi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Cabang Olahraga & Target Member -->
            <div class="space-y-4 pt-2" id="section2Container">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">2</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Fokus Olahraga & Tingkat Kemampuan</h3>
                </div>

                @php
                    $rawSportVal = old('sport', $community->getRawOriginal('sport') ?? $community->sport);
                    $selectedSport = match (strtolower(trim((string)$rawSportVal))) {
                        'tennis' => 'tennis',
                        'all_racquet', 'both', 'all racquet', 'padel & tennis' => 'all_racquet',
                        default => 'padel',
                    };
                @endphp

                <div class="space-y-2">
                    <label class="block font-bold text-slate-800">Cabang Olahraga Utama <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="sport" value="padel" class="peer sr-only" {{ $selectedSport === 'padel' ? 'checked' : '' }}>
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Padel Community</h4>
                                    <p class="text-[10px] text-slate-500">Khusus Pecinta Padel</p>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="sport" value="tennis" class="peer sr-only" {{ $selectedSport === 'tennis' ? 'checked' : '' }}>
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-baseball"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Tennis Community</h4>
                                    <p class="text-[10px] text-slate-500">Khusus Lapangan Tenis</p>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="sport" value="all_racquet" class="peer sr-only" {{ $selectedSport === 'all_racquet' ? 'checked' : '' }}>
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">All Racquet Club</h4>
                                    <p class="text-[10px] text-slate-500">Padel &amp; Tennis Campuran</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Hidden input status keanggotaan agar konsisten -->
                <input type="hidden" name="status_keanggotaan" value="{{ $community->status_keanggotaan ?: 'Open' }}">

                <!-- Target Level Member -->
                @php
                    $currTargetLevel = old('target_level', $community->target_level ?: 'All Levels');
                @endphp
                <div class="space-y-1.5 pt-1 relative" id="targetLevelDropdownContainer">
                    <label class="block font-bold text-slate-800">Target Level Member</label>
                    <div class="relative">
                        <select id="targetLevelSelect" name="target_level" class="sr-only">
                            <option value="All Levels" {{ $currTargetLevel === 'All Levels' || str_contains($currTargetLevel, 'Semua') ? 'selected' : '' }}>Semua Level (Newbie s/d Advanced)</option>
                            <option value="Beginners" {{ $currTargetLevel === 'Beginners' || str_contains($currTargetLevel, 'Beginner') || str_contains($currTargetLevel, 'Newbie') ? 'selected' : '' }}>Fokus Newbie & Beginner</option>
                            <option value="Intermediate" {{ $currTargetLevel === 'Intermediate' || str_contains($currTargetLevel, 'Competitive') ? 'selected' : '' }}>Intermediate & Competitive</option>
                        </select>

                        <button
                            type="button"
                            id="targetLevelTrigger"
                            onclick="toggleDropdownMenu('targetLevelMenu', 'targetLevelArrow')"
                            class="w-full h-[48px] flex items-center justify-between bg-slate-50/70 hover:bg-white border border-slate-200/80 hover:border-[#063B00] rounded-2xl px-4 text-slate-900 font-semibold transition-all shadow-2xs cursor-pointer text-left"
                        >
                            <span id="targetLevelSelected" class="text-xs font-bold text-slate-900 truncate">
                                {{ ($currTargetLevel === 'Beginners' || str_contains($currTargetLevel, 'Beginner')) ? 'Fokus Newbie & Beginner' : (($currTargetLevel === 'Intermediate' || str_contains($currTargetLevel, 'Competitive')) ? 'Intermediate & Competitive' : 'Semua Level (Newbie s/d Advanced)') }}
                            </span>
                            <i id="targetLevelArrow" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform"></i>
                        </button>

                        <div id="targetLevelMenu" class="hidden absolute z-50 left-0 right-0 mt-2 max-h-56 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl ring-1 ring-black/5" style="max-height: 240px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Deskripsi & Jadwal Rutin Mabar -->
            <div class="space-y-4 pt-2 relative" id="section3Container">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">3</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Deskripsi &amp; Jadwal Rutin Mabar</h3>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Deskripsi Lengkap Komunitas <span class="text-rose-500">*</span></label>
                        <textarea rows="3" id="input_deskripsi" name="deskripsi" placeholder="Jelaskan mengenai komunitas Anda, visi bermain, suasana mabar, aturan fair play, dan fasilitas yang biasa dinikmati..." class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">{{ old('deskripsi', $community->deskripsi) }}</textarea>
                        <p id="err_deskripsi" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Deskripsi lengkap komunitas wajib diisi.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block font-bold text-slate-800">Jadwal Mabar Rutin</label>
                            <div class="relative">
                                <i class="fa-regular fa-calendar-days absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" id="input_jadwal" name="jadwal_rutin" value="{{ old('jadwal_rutin', $community->jadwal_rutin) }}" placeholder="Contoh: Tiap Rabu Malam & Sabtu Pagi" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            </div>
                        </div>

                        <div class="space-y-1.5 relative" id="homebaseVenueDropdownContainer">
                            <div class="flex items-center justify-between">
                                <label class="block font-bold text-slate-800">Homebase Venue Utama</label>
                                <button type="button" onclick="openQuickAddVenueModal()" class="inline-flex items-center gap-1.5 text-[10px] font-bold text-[#063B00] bg-[#EBF8D8] hover:bg-[#d9f2b8] px-2.5 py-1 rounded-full border border-[#063B00]/20 transition-all shadow-2xs hover:scale-[1.02] active:scale-95 cursor-pointer">
                                    <i class="fa-solid fa-plus text-[9px]"></i> <span>Tambah Venue</span>
                                </button>
                            </div>

                            <!-- Native hidden select for form submission -->
                            <select id="homebase_venue_select" name="homebase_venue" class="sr-only">
                                <option value="">Pilih Venue Homebase (Opsional)</option>
                                @if(isset($venues) && $venues->isNotEmpty())
                                    @foreach($venues as $v)
                                        <option value="{{ $v->nama_venue }}" {{ old('homebase_venue', $community->homebase_venue) == $v->nama_venue ? 'selected' : '' }}>
                                            {{ $v->nama_venue }} ({{ $v->kota ?: 'Jakarta' }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>

                            <!-- Custom Styled Trigger Button -->
                            <div class="relative">
                                <button type="button" id="homebaseVenueTrigger" onclick="toggleDropdownMenu('homebaseVenueMenu', 'homebaseVenueIcon')"
                                    class="w-full h-[48px] flex items-center justify-between bg-slate-50/70 hover:bg-white border border-slate-200/80 hover:border-[#063B00] rounded-2xl pl-10 pr-4 text-slate-900 font-semibold transition-all shadow-2xs cursor-pointer text-left relative">
                                    <i class="fa-solid fa-map-pin absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                                    <span id="homebaseVenueSelected" class="text-xs font-bold text-slate-900 truncate">
                                        {{ old('homebase_venue', $community->homebase_venue) ?: 'Pilih Venue Homebase (Opsional)' }}
                                    </span>
                                    <i id="homebaseVenueIcon" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform"></i>
                                </button>

                                <div id="homebaseVenueMenu" class="hidden absolute z-50 left-0 right-0 mt-2 max-h-60 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl ring-1 ring-black/5" style="max-height: 250px; overflow-y: auto; overscroll-behavior: contain;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Keuntungan & Fasilitas Komunitas (Benefit Tags) -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">4</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Benefit &amp; Kegiatan Komunitas</h3>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    @php
                        $benefits = [
                            ['icon' => 'fa-calendar-check', 'key' => 'weekly_mabar', 'name' => 'Sesi Mabar Mingguan'],
                            ['icon' => 'fa-trophy', 'key' => 'internal_tournament', 'name' => 'Internal Tournament'],
                            ['icon' => 'fa-graduation-cap', 'key' => 'coaching_clinic', 'name' => 'Coaching Clinic'],
                            ['icon' => 'fa-comments', 'key' => 'whatsapp_group', 'name' => 'WhatsApp Group Aktif'],
                            ['icon' => 'fa-tags', 'key' => 'court_discount', 'name' => 'Diskon Sewa Court'],
                            ['icon' => 'fa-shirt', 'key' => 'official_jersey', 'name' => 'Jersey Official Club'],
                            ['icon' => 'fa-chart-line', 'key' => 'rating_tracking', 'name' => 'Tracking Rating Pemain'],
                            ['icon' => 'fa-handshake', 'key' => 'networking', 'name' => 'Networking Profesional'],
                        ];
                        $savedBenefits = old('benefits');
                        if ($savedBenefits === null) {
                            $savedBenefits = is_array($community->benefits) ? $community->benefits : (json_decode($community->benefits ?? '[]', true) ?: []);
                        }
                    @endphp

                    @foreach($benefits as $ben)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="benefits[]" value="{{ $ben['key'] }}" class="peer sr-only" {{ in_array($ben['key'], (array)$savedBenefits) || in_array($ben['name'], (array)$savedBenefits) ? 'checked' : '' }}>
                            <div class="p-3 rounded-2xl border border-slate-200/70 bg-slate-50/50 peer-checked:bg-[#EBF8D8]/70 peer-checked:border-[#063B00] peer-checked:text-[#063B00] transition-all flex items-center gap-2.5 group hover:border-slate-300">
                                <div class="w-7 h-7 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center text-xs text-slate-600 peer-checked:text-[#063B00] shadow-2xs">
                                    <i class="fa-solid {{ $ben['icon'] }}"></i>
                                </div>
                                <span class="text-[11px] font-bold text-slate-700 peer-checked:text-[#063B00]">{{ $ben['name'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- SECTION 5: Admin Komunitas & Logo Badge -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">5</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Admin Komunitas &amp; Logo Badge</h3>
                </div>

                @php
                    $adminUser = $community->creator ?? Auth::user();
                    $adminName = $adminUser->nama ?? 'Admin Komunitas';
                    $adminInitial = substr($adminName, 0, 1);
                    $hasLogo = !empty($community->logo);
                @endphp

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
                    <!-- Admin Card -->
                    <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200/80 flex items-center gap-3.5 h-full">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#063B00] to-emerald-800 text-white flex items-center justify-center text-base font-bold shadow-xs shrink-0">
                            {{ $adminInitial }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-black text-slate-900 truncate">{{ $adminName }}</span>
                                <i class="fa-solid fa-circle-check text-[#063B00] text-[10px] shrink-0"></i>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-0.5 truncate">Admin Utama Komunitas</p>
                            <span class="inline-block px-2 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] text-[9px] font-extrabold mt-1">
                                Verified Founder
                            </span>
                        </div>
                    </div>

                    <!-- Logo Card -->
                    <div class="space-y-1">
                        <div id="logo-card-container" class="p-3.5 sm:p-4 rounded-2xl bg-slate-50/80 border border-slate-200/80 flex items-center transition-all h-full min-h-[96px]">
                            <!-- State 1: Active Photo Preview (Ada Foto) -->
                            <div id="logo-active-view" class="{{ $hasLogo ? 'flex' : 'hidden' }} items-center gap-3.5 w-full">
                                <div class="w-20 h-14 sm:w-24 sm:h-16 rounded-xl overflow-hidden border border-slate-200 shadow-2xs shrink-0 bg-slate-200 relative">
                                    <img id="logo-preview-img" src="{{ $community->logo ?? '' }}" alt="Foto Komunitas" class="w-full h-full object-cover object-center block">
                                    <div id="logo-upload-spinner" class="hidden absolute inset-0 bg-black/40 backdrop-blur-2xs flex items-center justify-center text-white">
                                        <i class="fa-solid fa-circle-notch fa-spin text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                                    <div class="min-w-0">
                                        <span id="logo-status-title" class="text-xs font-black text-slate-900 block truncate">Foto Komunitas</span>
                                        <p id="logo-status-desc" class="text-[10px] text-slate-500 mt-0.5 truncate">JPG / PNG maks 2 MB</p>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <button
                                            type="button"
                                            onclick="triggerLogoSelect()"
                                            class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:border-[#063B00] hover:text-[#063B00] text-slate-700 text-[11px] font-bold shadow-2xs inline-flex items-center gap-1.5 transition-all cursor-pointer hover:bg-slate-50"
                                            title="Pilih foto baru dari perangkat"
                                        >
                                            <i class="fa-solid fa-camera text-[10px] text-slate-500"></i>
                                            <span>Ganti</span>
                                        </button>
                                        <button
                                            type="button"
                                            onclick="markLogoForDeletion()"
                                            class="px-2.5 py-1 rounded-lg bg-white border border-rose-200 hover:border-rose-400 hover:bg-rose-50 text-rose-600 text-[11px] font-bold shadow-2xs inline-flex items-center gap-1.5 transition-all cursor-pointer"
                                            title="Hapus foto komunitas ini"
                                        >
                                            <i class="fa-regular fa-trash-can text-[10px]"></i>
                                            <span>Hapus</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- State 2: Marked for Deletion (Ditandai untuk Dihapus) -->
                            <div id="logo-deleted-view" class="hidden items-center justify-between gap-3 w-full">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-200 text-rose-500 flex items-center justify-center shrink-0">
                                        <i class="fa-regular fa-trash-can text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-rose-600 truncate">Foto ditandai dihapus</p>
                                        <p class="text-[10px] text-slate-500 truncate">Klik Simpan untuk konfirmasi</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onclick="cancelLogoDeletion()"
                                    class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700 text-[11px] font-bold shadow-2xs transition-all cursor-pointer shrink-0"
                                >
                                    Batalkan
                                </button>
                            </div>

                            <!-- State 3: Empty Dropzone (Belum Ada Foto) -->
                            <div id="logo-empty-view" onclick="triggerLogoSelect()" class="{{ $hasLogo ? 'hidden' : 'flex' }} items-center justify-center gap-3 w-full py-2.5 border-2 border-dashed border-slate-200 hover:border-[#063B00] rounded-xl transition-all cursor-pointer group text-center">
                                <i class="fa-solid fa-cloud-arrow-up text-slate-400 group-hover:text-[#063B00] text-lg transition-colors shrink-0"></i>
                                <div class="text-left">
                                    <p class="text-xs font-bold text-slate-800 group-hover:text-[#063B00] transition-colors">Upload Foto Komunitas</p>
                                    <p class="text-[10px] text-slate-400">JPG / PNG maks 2 MB</p>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden Inputs -->
                        <input type="file" id="input_logo" name="logo" accept="image/jpeg,image/jpg,image/png" class="sr-only">
                        <input type="hidden" id="uploaded_logo_url" name="logo_url" value="{{ old('logo_url') }}">
                        <input type="hidden" id="remove_logo_input" name="remove_logo" value="0">

                        <p id="err_logo" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Format tidak valid.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Submit Action -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-[11px] text-slate-500 flex items-center gap-1.5 text-center sm:text-left">
                    <i class="fa-solid fa-circle-check text-[#063B00] shrink-0"></i>
                    Perubahan profil komunitas akan langsung tersimpan dan aktif.
                </p>

                <div class="flex flex-col-reverse sm:flex-row items-center gap-3 w-full sm:w-auto">
                    <a
                        href="{{ route('communities.show', $community->community_id) }}"
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
                        <span class="whitespace-nowrap">Simpan Perubahan Komunitas</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Quick Add Venue Instant -->
<div id="quickAddVenueModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4 my-8 animate-in fade-in zoom-in duration-200 border border-slate-100">
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
        <form id="quickAddVenueForm" onsubmit="submitQuickAddVenue(event)" class="flex flex-col gap-4" style="display: flex; flex-direction: column; gap: 18px;">
            <!-- Nama Venue -->
            <div class="space-y-2" style="display: flex; flex-direction: column; gap: 8px;">
                <label class="block text-[11px] font-bold text-slate-800">
                    Nama Venue <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input
                        type="text"
                        id="quickVenueName"
                        required
                        placeholder="Contoh: Matcha Padel Arena Dago"
                        class="w-full bg-slate-50/70 hover:bg-white focus:bg-white border border-slate-200/90 hover:border-[#063B00] focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 rounded-2xl pl-10 pr-4 py-3 text-xs text-slate-900 font-semibold placeholder:text-slate-400 placeholder:font-normal focus:outline-none transition-all shadow-2xs"
                        style="height: 48px; min-height: 48px; box-sizing: border-box;"
                    />
                    <i class="fa-solid fa-building absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                </div>
            </div>

            <!-- Cabang Olahraga & Jumlah Court -->
            <div class="grid grid-cols-2 gap-3">
                <div id="quickSportDropdownContainer" class="space-y-2 relative" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="block text-[11px] font-bold text-slate-800">
                        Cabang Olahraga <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            id="quickVenueSport"
                            required
                            class="sr-only"
                        >
                            <option value="Padel">Padel</option>
                            <option value="Tennis">Tennis</option>
                        </select>

                        <button
                            type="button"
                            id="quickSportTrigger"
                            onclick="toggleDropdownMenu('quickSportMenu', 'quickSportArrow')"
                            class="w-full flex items-center justify-between bg-slate-50/70 hover:bg-white border border-slate-200/90 hover:border-[#063B00] rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold focus:outline-none focus:ring-2 focus:ring-[#A8E63A]/25 transition-all shadow-2xs cursor-pointer text-left"
                            style="height: 48px; min-height: 48px; box-sizing: border-box;"
                        >
                            <span id="quickSportSelected" class="truncate font-semibold text-slate-900">Padel</span>
                            <i id="quickSportArrow" class="fa-solid fa-chevron-down text-[11px] text-slate-400 transition-transform"></i>
                        </button>

                        <div id="quickSportMenu" class="hidden absolute z-50 left-0 right-0 mt-1.5 max-h-48 overflow-y-auto rounded-2xl border border-slate-200/90 bg-white/95 backdrop-blur-xl p-1.5 shadow-2xl ring-1 ring-black/5" style="max-height: 200px; overflow-y: auto;"></div>
                    </div>
                </div>

                <div id="quickCourtCountDropdownContainer" class="space-y-2 relative" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="block text-[11px] font-bold text-slate-800">
                        Jumlah Lapangan <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            id="quickVenueCourtCount"
                            required
                            class="sr-only"
                        >
                            <option value="1">1 Court</option>
                            <option value="2" selected>2 Courts</option>
                            <option value="3">3 Courts</option>
                            <option value="4">4 Courts</option>
                            <option value="5">5 Courts</option>
                        </select>

                        <button
                            type="button"
                            id="quickCourtCountTrigger"
                            onclick="toggleDropdownMenu('quickCourtCountMenu', 'quickCourtCountArrow')"
                            class="w-full flex items-center justify-between bg-slate-50/70 hover:bg-white border border-slate-200/90 hover:border-[#063B00] rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold focus:outline-none focus:ring-2 focus:ring-[#A8E63A]/25 transition-all shadow-2xs cursor-pointer text-left"
                            style="height: 48px; min-height: 48px; box-sizing: border-box;"
                        >
                            <span id="quickCourtCountSelected" class="truncate font-semibold text-slate-900">2 Courts</span>
                            <i id="quickCourtCountArrow" class="fa-solid fa-chevron-down text-[11px] text-slate-400 transition-transform"></i>
                        </button>

                        <div id="quickCourtCountMenu" class="hidden absolute z-50 left-0 right-0 mt-1.5 max-h-48 overflow-y-auto rounded-2xl border border-slate-200/90 bg-white/95 backdrop-blur-xl p-1.5 shadow-2xl ring-1 ring-black/5" style="max-height: 200px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>

            <!-- Kota / Wilayah & Alamat -->
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-2" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="block text-[11px] font-bold text-slate-800">
                        Kota / Wilayah
                    </label>
                    <input
                        type="text"
                        id="quickVenueCity"
                        placeholder="Contoh: Bandung"
                        class="w-full bg-slate-50/70 hover:bg-white focus:bg-white border border-slate-200/90 hover:border-[#063B00] focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold placeholder:text-slate-400 placeholder:font-normal focus:outline-none transition-all shadow-2xs"
                        style="height: 48px; min-height: 48px; box-sizing: border-box;"
                    />
                </div>
                <div class="space-y-2" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="block text-[11px] font-bold text-slate-800">
                        Alamat Singkat
                    </label>
                    <input
                        type="text"
                        id="quickVenueAddress"
                        placeholder="Contoh: Jl. Ir. H. Juanda No. 10"
                        class="w-full bg-slate-50/70 hover:bg-white focus:bg-white border border-slate-200/90 hover:border-[#063B00] focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold placeholder:text-slate-400 placeholder:font-normal focus:outline-none transition-all shadow-2xs"
                        style="height: 48px; min-height: 48px; box-sizing: border-box;"
                    />
                </div>
            </div>

            <!-- Info Box -->
            <div class="flex items-center gap-2.5 p-3 rounded-2xl bg-[#F4FBEA] border border-[#d6f3b0]">
                <i class="fa-solid fa-circle-info text-[#063B00] text-xs shrink-0"></i>
                <p class="text-[11px] text-[#063B00] leading-snug font-medium">
                    Sistem otomatis mendaftarkan court dan memilih venue ini sebagai homebase.
                </p>
            </div>

            <!-- Error Banner -->
            <div id="quickVenueError" class="hidden p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation shrink-0"></i>
                <span id="quickVenueErrorText"></span>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-2.5 pt-1" style="display: flex; flex-direction: column; gap: 10px;">
                <button
                    type="submit"
                    id="btnSubmitQuickVenue"
                    class="w-full rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                    style="height: 46px; min-height: 46px;"
                >
                    <i class="fa-solid fa-floppy-disk text-xs text-[#A8E63A]"></i>
                    <span>Simpan &amp; Pilih Venue</span>
                </button>
                <button
                    type="button"
                    onclick="closeQuickAddVenueModal()"
                    class="w-full rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors cursor-pointer flex items-center justify-center"
                    style="height: 42px; min-height: 42px;"
                >
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const initialLogoUrl = @json($community->logo ?? '');

    function setFieldError(fieldId, errId, message) {
        const input = document.getElementById(fieldId);
        const err = document.getElementById(errId);
        if (!input || !err) return false;

        if (message) {
            input.classList.add('border-rose-400', 'bg-rose-50/40', 'focus:ring-rose-200', 'focus:border-rose-500');
            input.classList.remove('border-slate-200/80', 'bg-slate-50/70');
            err.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> ${message}`;
            err.classList.remove('hidden');
            err.classList.add('flex');
            return true;
        } else {
            input.classList.remove('border-rose-400', 'bg-rose-50/40', 'focus:ring-rose-200', 'focus:border-rose-500');
            input.classList.add('border-slate-200/80', 'bg-slate-50/70');
            err.classList.add('hidden');
            err.classList.remove('flex');
            return false;
        }
    }

    const initialLogoUrl = @json($community->logo ?? '');
    let isLogoUploading = false;

    function triggerLogoSelect() {
        const input = document.getElementById('input_logo');
        if (input) input.click();
    }

    function markLogoForDeletion() {
        const removeInput = document.getElementById('remove_logo_input');
        const uploadedUrlInput = document.getElementById('uploaded_logo_url');
        const fileInput = document.getElementById('input_logo');
        const activeView = document.getElementById('logo-active-view');
        const deletedView = document.getElementById('logo-deleted-view');
        const emptyView = document.getElementById('logo-empty-view');
        const errEl = document.getElementById('err_logo');

        if (errEl) {
            errEl.classList.add('hidden');
            errEl.classList.remove('flex');
        }
        if (fileInput) fileInput.value = '';
        if (uploadedUrlInput) uploadedUrlInput.value = '';

        if (initialLogoUrl) {
            if (removeInput) removeInput.value = '1';
            if (activeView) { activeView.classList.add('hidden'); activeView.classList.remove('flex'); }
            if (deletedView) { deletedView.classList.remove('hidden'); deletedView.classList.add('flex'); }
            if (emptyView) { emptyView.classList.add('hidden'); emptyView.classList.remove('flex'); }
        } else {
            if (removeInput) removeInput.value = '0';
            if (activeView) { activeView.classList.add('hidden'); activeView.classList.remove('flex'); }
            if (deletedView) { deletedView.classList.add('hidden'); deletedView.classList.remove('flex'); }
            if (emptyView) { emptyView.classList.remove('hidden'); emptyView.classList.add('flex'); }
        }
    }

    function cancelLogoDeletion() {
        const removeInput = document.getElementById('remove_logo_input');
        const activeView = document.getElementById('logo-active-view');
        const deletedView = document.getElementById('logo-deleted-view');
        const previewImg = document.getElementById('logo-preview-img');
        const titleEl = document.getElementById('logo-status-title');
        const descEl = document.getElementById('logo-status-desc');

        if (removeInput) removeInput.value = '0';
        if (previewImg && initialLogoUrl) {
            previewImg.src = initialLogoUrl;
        }
        if (titleEl) titleEl.textContent = 'Foto Komunitas';
        if (descEl) descEl.textContent = 'JPG / PNG maks 2 MB';

        if (deletedView) { deletedView.classList.add('hidden'); deletedView.classList.remove('flex'); }
        if (activeView) { activeView.classList.remove('hidden'); activeView.classList.add('flex'); }
    }

    function validateLogoField() {
        const input = document.getElementById('input_logo');
        const errEl = document.getElementById('err_logo');
        if (!input || !errEl) return true;

        if (!input.files || input.files.length === 0) {
            errEl.classList.add('hidden');
            errEl.classList.remove('flex');
            return true;
        }

        const file = input.files[0];
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        const ext = (file.name || '').split('.').pop().toLowerCase();
        const allowedExtensions = ['jpg', 'jpeg', 'png'];
        const maxBytes = 2 * 1024 * 1024; // 2 MB

        let errorMsg = null;
        if (!allowedTypes.includes(file.type.toLowerCase()) && !allowedExtensions.includes(ext)) {
            errorMsg = 'Format foto tidak valid. Hanya JPG, JPEG, atau PNG yang diterima.';
        } else if (file.size > maxBytes) {
            errorMsg = 'Ukuran foto maksimal 2 MB.';
        }

        if (errorMsg) {
            errEl.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> ${errorMsg}`;
            errEl.classList.remove('hidden');
            errEl.classList.add('flex');
            return false;
        }

        errEl.classList.add('hidden');
        errEl.classList.remove('flex');
        return true;
    }

    document.getElementById('input_logo')?.addEventListener('change', function() {
        const removeInput = document.getElementById('remove_logo_input');
        const uploadedUrlInput = document.getElementById('uploaded_logo_url');
        const activeView = document.getElementById('logo-active-view');
        const deletedView = document.getElementById('logo-deleted-view');
        const emptyView = document.getElementById('logo-empty-view');
        const previewImg = document.getElementById('logo-preview-img');
        const spinner = document.getElementById('logo-upload-spinner');
        const titleEl = document.getElementById('logo-status-title');
        const descEl = document.getElementById('logo-status-desc');
        const errEl = document.getElementById('err_logo');

        if (!this.files || this.files.length === 0) {
            return;
        }

        if (!validateLogoField()) {
            this.value = '';
            return;
        }

        const file = this.files[0];

        if (removeInput) removeInput.value = '0';

        if (previewImg) previewImg.src = URL.createObjectURL(file);
        if (titleEl) titleEl.textContent = 'Mengunggah foto...';
        if (descEl) descEl.textContent = file.name;
        if (spinner) spinner.classList.remove('hidden');

        if (emptyView) { emptyView.classList.add('hidden'); emptyView.classList.remove('flex'); }
        if (deletedView) { deletedView.classList.add('hidden'); deletedView.classList.remove('flex'); }
        if (activeView) { activeView.classList.remove('hidden'); activeView.classList.add('flex'); }

        isLogoUploading = true;

        const formData = new FormData();
        formData.append('logo', file);
        const csrfToken = document.querySelector('input[name="_token"]')?.value || '';

        fetch("{{ route('communities.upload-logo') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Gagal mengunggah foto.');
            }
            return data;
        })
        .then(data => {
            isLogoUploading = false;
            if (spinner) spinner.classList.add('hidden');
            if (data.success && data.url) {
                if (uploadedUrlInput) uploadedUrlInput.value = data.url;
                if (titleEl) titleEl.textContent = 'Foto Siap Disimpan';
                if (descEl) descEl.textContent = 'Berhasil diunggah (' + file.name + ')';
            } else {
                throw new Error(data.message || 'Gagal mengunggah foto.');
            }
        })
        .catch(error => {
            isLogoUploading = false;
            if (spinner) spinner.classList.add('hidden');
            if (uploadedUrlInput) uploadedUrlInput.value = '';
            if (titleEl) titleEl.textContent = 'Gagal Mengunggah';
            if (descEl) descEl.textContent = error.message || 'Silakan coba lagi';
            if (errEl) {
                errEl.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> ${error.message}`;
                errEl.classList.remove('hidden');
                errEl.classList.add('flex');
            }
        });
    });

    function validateCommunityForm(e) {
        if (isLogoUploading) {
            e.preventDefault();
            const errEl = document.getElementById('err_logo');
            if (errEl) {
                errEl.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-[10px]"></i> Sedang mengunggah foto, mohon tunggu beberapa saat...`;
                errEl.classList.remove('hidden');
                errEl.classList.add('flex');
            }
            return false;
        }

        const inputLogo = document.getElementById('input_logo');
        const logoUrlInput = document.getElementById('uploaded_logo_url');
        const removeInput = document.getElementById('remove_logo_input');
        const errLogo = document.getElementById('err_logo');

        if (inputLogo && inputLogo.files && inputLogo.files.length > 0 && (!logoUrlInput || !logoUrlInput.value) && (!removeInput || removeInput.value !== '1')) {
            e.preventDefault();
            if (errLogo) {
                errLogo.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> Upload foto belum berhasil. Silakan tunggu atau batalkan pilihan foto.`;
                errLogo.classList.remove('hidden');
                errLogo.classList.add('flex');
            }
            return false;
        }

        const nama = document.getElementById('input_nama_community')?.value.trim() || '';
        const kota = document.getElementById('input_kota')?.value.trim() || '';
        const deskripsi = document.getElementById('input_deskripsi')?.value.trim() || '';
        const tagline = document.getElementById('input_tagline')?.value.trim() || '';
        const jadwal = document.getElementById('input_jadwal')?.value.trim() || '';

        const hasScript = (val) => /<[^>]*script/i.test(val) || /<script[\s\S]*?>/i.test(val) || /[<>]/.test(val);

        let hasError = false;

        let namaError = !nama ? 'Nama komunitas / klub wajib diisi.' : (hasScript(nama) ? 'Nama komunitas tidak boleh mengandung tag script atau karakter khusus (< >).' : null);
        let kotaError = !kota ? 'Kota homebase wajib diisi.' : (hasScript(kota) ? 'Kota homebase tidak boleh mengandung tag script atau karakter HTML.' : null);
        let deskripsiError = !deskripsi ? 'Deskripsi lengkap komunitas wajib diisi.' : (hasScript(deskripsi) ? 'Deskripsi tidak boleh mengandung tag script atau karakter HTML.' : null);

        if (setFieldError('input_nama_community', 'err_nama_community', namaError)) hasError = true;
        if (setFieldError('input_kota', 'err_kota', kotaError)) hasError = true;
        if (setFieldError('input_deskripsi', 'err_deskripsi', deskripsiError)) hasError = true;

        if (hasScript(tagline) || hasScript(jadwal)) {
            hasError = true;
            ['input_tagline', 'input_jadwal'].forEach(id => {
                const el = document.getElementById(id);
                if (el && hasScript(el.value)) {
                    el.classList.add('!border-rose-500', 'ring-2', 'ring-rose-500/20');
                }
            });
        }

        if (!validateLogoField()) hasError = true;

        if (hasError) {
            e.preventDefault();
            const firstError = document.querySelector('.border-rose-400, .border-rose-500, ring-rose-500\\/20');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Menyimpan Perubahan...';
        }

        return true;
    }

    function openQuickAddVenueModal() {
        const modal = document.getElementById('quickAddVenueModal');
        const nameInput = document.getElementById('quickVenueName');
        const sportSelect = document.getElementById('quickVenueSport');
        const currentSportRadio = document.querySelector('input[name="sport"]:checked');

        if (sportSelect && currentSportRadio) {
            const val = currentSportRadio.value.toLowerCase() === 'tennis' ? 'Tennis' : 'Padel';
            sportSelect.value = val;
            const sportLabel = document.getElementById('quickSportSelected');
            if (sportLabel) sportLabel.textContent = val;
        }

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
        const venueSelect = document.getElementById('homebase_venue_select');

        const originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Menyimpan...';
        if (errDiv) errDiv.classList.add('hidden');

        try {
            const csrfToken = document.querySelector('input[name="_token"]')?.value || '';
            const res = await fetch("{{ route('venues.quickStore') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrfToken
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

            if (venueSelect) {
                const newOpt = document.createElement('option');
                const cityName = data.venue.kota || cityInput.value.trim() || 'Jakarta';
                newOpt.value = data.venue.nama_venue;
                newOpt.textContent = `${data.venue.nama_venue} (${cityName})`;
                newOpt.selected = true;
                venueSelect.appendChild(newOpt);
                renderSelectDropdown('homebase_venue_select', 'homebaseVenueMenu', 'homebaseVenueSelected', null, 'Pilih Venue Homebase (Opsional)');
            }

            closeQuickAddVenueModal();
        } catch (err) {
            if (errDiv && errText) {
                errText.textContent = err.message;
                errDiv.classList.remove('hidden');
            } else {
                alert(err.message);
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
        }
    }

    const customDropdownList = [
        { selectId: 'targetLevelSelect', menuId: 'targetLevelMenu', labelId: 'targetLevelSelected', arrowId: 'targetLevelArrow', containerId: 'targetLevelDropdownContainer', sectionId: 'section2Container' },
        { selectId: 'homebase_venue_select', menuId: 'homebaseVenueMenu', labelId: 'homebaseVenueSelected', arrowId: 'homebaseVenueIcon', containerId: 'homebaseVenueDropdownContainer', sectionId: 'section3Container', defaultPlaceholder: 'Pilih Venue Homebase (Opsional)' },
        { selectId: 'quickVenueSport', menuId: 'quickSportMenu', labelId: 'quickSportSelected', arrowId: 'quickSportArrow', containerId: 'quickSportDropdownContainer' },
        { selectId: 'quickVenueCourtCount', menuId: 'quickCourtCountMenu', labelId: 'quickCourtCountSelected', arrowId: 'quickCourtCountArrow', containerId: 'quickCourtCountDropdownContainer' },
    ];

    function resetAllDropdownZIndices() {
        customDropdownList.forEach(item => {
            if (item.containerId) {
                const container = document.getElementById(item.containerId);
                if (container) container.style.zIndex = '';
            }
            if (item.sectionId) {
                const section = document.getElementById(item.sectionId);
                if (section) section.style.zIndex = '';
            }
        });
    }

    function renderSelectDropdown(selectId, menuId, labelId, onSelectCallback = null, defaultPlaceholder = null) {
        const select = document.getElementById(selectId);
        const menu = document.getElementById(menuId);
        const label = document.getElementById(labelId);
        if (!select || !menu || !label) return;

        menu.innerHTML = '';

        if (menuId === 'homebaseVenueMenu' && select.options.length > 5) {
            const searchBox = document.createElement('div');
            searchBox.className = 'sticky -top-1.5 z-10 bg-white pt-1 pb-1.5 px-1 border-b border-slate-100 mb-1';
            searchBox.innerHTML = `
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
                    <input type="text" placeholder="Cari venue homebase..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 font-semibold placeholder:text-slate-400 focus:bg-white focus:border-[#063B00] focus:outline-none transition-all">
                </div>
            `;
            const searchInput = searchBox.querySelector('input');
            searchInput.onclick = (e) => e.stopPropagation();
            searchInput.oninput = (e) => {
                const query = e.target.value.toLowerCase().trim();
                const items = menu.querySelectorAll('.dropdown-item-btn');
                items.forEach(btn => {
                    const text = btn.textContent.toLowerCase();
                    const isBlank = btn.getAttribute('data-value') === '';
                    if (!query || text.includes(query) || isBlank) {
                        btn.style.display = '';
                    } else {
                        btn.style.display = 'none';
                    }
                });
            };
            menu.appendChild(searchBox);
        }

        Array.from(select.options).forEach(opt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('data-value', opt.value);
            const isSelected = String(select.value) === String(opt.value);
            btn.className = `dropdown-item-btn w-full px-3.5 py-2.5 rounded-xl text-left text-xs font-semibold transition-colors flex items-center justify-between cursor-pointer ${
                isSelected && opt.value !== ''
                    ? 'bg-[#EBF8D8] text-[#063B00] font-bold'
                    : 'text-slate-700 hover:bg-[#F4FBEA] hover:text-[#063B00]'
            }`;
            btn.innerHTML = `
                <span class="truncate">${opt.textContent.trim()}</span>
                ${isSelected && opt.value !== '' ? '<i class="fa-solid fa-check text-[#063B00] text-xs shrink-0 ml-2"></i>' : ''}
            `;
            btn.onclick = (e) => {
                e.stopPropagation();
                select.value = opt.value;
                select.dispatchEvent(new Event('change'));
                label.textContent = opt.textContent.trim();
                renderSelectDropdown(selectId, menuId, labelId, onSelectCallback, defaultPlaceholder);
                menu.classList.add('hidden');
                const arrow = document.getElementById(menuId.replace('Menu', 'Arrow')) || document.getElementById(menuId.replace('Menu', 'Icon'));
                if (arrow) arrow.classList.remove('rotate-180');
                resetAllDropdownZIndices();
                if (onSelectCallback) onSelectCallback(opt.value);
            };
            menu.appendChild(btn);
        });

        const activeOpt = select.options[select.selectedIndex] || select.options[0];
        if (activeOpt && activeOpt.value !== '') {
            label.textContent = activeOpt.textContent.trim();
        } else if (defaultPlaceholder) {
            label.textContent = defaultPlaceholder;
        }
    }

    window.toggleDropdownMenu = function (menuId, arrowId) {
        const menu = document.getElementById(menuId);
        const arrow = document.getElementById(arrowId);
        if (!menu) return;

        const willOpen = menu.classList.contains('hidden');

        customDropdownList.forEach(item => {
            if (item.menuId !== menuId) {
                document.getElementById(item.menuId)?.classList.add('hidden');
                const otherArrow = document.getElementById(item.arrowId);
                if (otherArrow) otherArrow.classList.remove('rotate-180');
            }
        });
        resetAllDropdownZIndices();

        if (willOpen) {
            menu.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
            const currentItem = customDropdownList.find(item => item.menuId === menuId);
            if (currentItem) {
                if (currentItem.containerId) {
                    const container = document.getElementById(currentItem.containerId);
                    if (container) container.style.zIndex = '50';
                }
                if (currentItem.sectionId) {
                    const section = document.getElementById(currentItem.sectionId);
                    if (section) section.style.zIndex = '40';
                }
            }
            if (menuId === 'homebaseVenueMenu') {
                const searchInput = menu.querySelector('input');
                if (searchInput) {
                    searchInput.value = '';
                    const items = menu.querySelectorAll('.dropdown-item-btn');
                    items.forEach(btn => btn.style.display = '');
                    setTimeout(() => searchInput.focus(), 60);
                }
            }
        } else {
            menu.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
        }
    };

    document.addEventListener('click', function (e) {
        let clickedInsideAnyCustomDropdown = false;
        customDropdownList.forEach(item => {
            if (item.containerId && e.target.closest(`#${item.containerId}`)) {
                clickedInsideAnyCustomDropdown = true;
            } else {
                document.getElementById(item.menuId)?.classList.add('hidden');
                const arrow = document.getElementById(item.arrowId);
                if (arrow) arrow.classList.remove('rotate-180');
            }
        });
        if (!clickedInsideAnyCustomDropdown) {
            resetAllDropdownZIndices();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        customDropdownList.forEach(item => {
            renderSelectDropdown(item.selectId, item.menuId, item.labelId, null, item.defaultPlaceholder);
        });
    });
</script>
@endpush
