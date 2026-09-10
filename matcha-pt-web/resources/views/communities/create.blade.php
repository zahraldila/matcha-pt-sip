@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8 relative">

    <!-- Ambient Glowing Background Orbs -->
    <div class="absolute w-96 h-96 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-12 -left-12 -z-10"></div>
    <div class="absolute w-96 h-96 bg-[#063B00]/10 rounded-full blur-3xl pointer-events-none top-1/2 -right-12 -z-10"></div>
    <div class="absolute w-80 h-80 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none -bottom-10 left-1/3 -z-10"></div>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/60 relative z-10">
        <div>
            <a href="{{ route('communities.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-[#063B00] transition-colors mb-3 group">
                <span class="w-7 h-7 rounded-xl bg-white/80 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-[#063B00] group-hover:text-white transition-all shadow-2xs">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                </span>
                Kembali ke Daftar Komunitas
            </a>
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-extrabold uppercase tracking-wider">
                    Community Creator Hub
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-medium text-slate-500">Mulai Klub & Komunitas Baru</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                Pendaftaran Komunitas Baru
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl leading-relaxed">
                Buat wadah mabar dan turnamen untuk para pecinta Tennis dan Padel di kota Anda. Anda akan otomatis menjadi Admin Komunitas.
            </p>
        </div>

        <div class="hidden sm:flex flex-col items-end gap-2">
            <div class="px-4 py-2 rounded-2xl bg-white/60 backdrop-blur-md border border-white/80 shadow-2xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-users text-[#A8E63A]"></i>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Peran Anda</p>
                    <p class="text-xs font-black text-[#063B00]">Club Founder & Admin</p>
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

        <form id="communityForm" action="{{ route('communities.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8 text-xs" novalidate onsubmit="validateCommunityForm(event)">
            @csrf
            
            <!-- SECTION 1: Identitas Komunitas -->
            <div class="space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">1</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Identitas & Profil Komunitas</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Nama Komunitas / Klub <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-users-rectangle absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="input_nama_community" name="nama_community" value="{{ old('nama_community') }}" placeholder="Contoh: JTK Padel Club Bandung" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                        <p id="err_nama_community" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Nama komunitas wajib diisi.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Slogan / Tagline Komunitas</label>
                        <div class="relative">
                            <i class="fa-solid fa-quote-left absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="tagline" placeholder="Contoh: Mabar Seru, Keringat Bareng, Rating Naik!" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Kota Homebase <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="fa-solid fa-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="input_kota" name="kota" value="{{ old('kota') }}" placeholder="Contoh: Bandung, Jakarta, Surabaya" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                        <p id="err_kota" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Kota homebase wajib diisi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Cabang Olahraga & Target Member -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">2</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Fokus Olahraga & Tingkat Kemampuan</h3>
                </div>

                <div class="space-y-2">
                    <label class="block font-bold text-slate-800">Cabang Olahraga Utama <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="sport_focus" value="Padel" class="peer sr-only" checked>
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
                            <input type="radio" name="sport_focus" value="Tennis" class="peer sr-only">
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
                            <input type="radio" name="sport_focus" value="Both" class="peer sr-only">
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">All Racquet Club</h4>
                                    <p class="text-[10px] text-slate-500">Padel & Tennis Campuran</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Target Level Member</label>
                        <div class="relative">
                            <select name="target_level" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="All Levels" selected>Semua Level (Newbie s/d Advanced)</option>
                                <option value="Beginners">Fokus Newbie & Beginner</option>
                                <option value="Intermediate">Intermediate & Competitive</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Status Keanggotaan</label>
                        <div class="relative">
                            <select name="membership_status" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="Open" selected>Terbuka untuk Umum (Free Join)</option>
                                <option value="Approval">Memerlukan Persetujuan Admin</option>
                                <option value="Private">Undangan Khusus (Private)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Deskripsi & Jadwal Mabar Rutin -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">3</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Deskripsi & Jadwal Rutin Mabar</h3>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Deskripsi Lengkap Komunitas <span class="text-rose-500">*</span></label>
                        <textarea rows="3" id="input_deskripsi" name="deskripsi" placeholder="Jelaskan mengenai komunitas Anda, visi bermain, suasana mabar, aturan fair play, dan fasilitas yang biasa dinikmati..." class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">{{ old('deskripsi') }}</textarea>
                        <p id="err_deskripsi" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Deskripsi lengkap komunitas wajib diisi.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block font-bold text-slate-800">Jadwal Mabar Rutin</label>
                            <div class="relative">
                                <i class="fa-regular fa-calendar-days absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" name="jadwal_rutin" placeholder="Contoh: Tiap Rabu Malam & Sabtu Pagi" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-bold text-slate-800">Homebase Venue Utama</label>
                            <div class="relative">
                                <i class="fa-solid fa-map-pin absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" name="venue_utama" placeholder="Contoh: Gelora Racquet Arena" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Keuntungan & Fasilitas Komunitas (Benefit Tags) -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">4</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Benefit & Kegiatan Komunitas</h3>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    @php
                        $benefits = [
                            ['icon' => 'fa-calendar-check', 'name' => 'Sesi Mabar Mingguan'],
                            ['icon' => 'fa-trophy', 'name' => 'Internal Tournament'],
                            ['icon' => 'fa-graduation-cap', 'name' => 'Coaching Clinic'],
                            ['icon' => 'fa-comments', 'name' => 'WhatsApp Group Aktif'],
                            ['icon' => 'fa-tags', 'name' => 'Diskon Sewa Court'],
                            ['icon' => 'fa-shirt', 'name' => 'Jersey Official Club'],
                            ['icon' => 'fa-chart-line', 'name' => 'Tracking Rating Pemain'],
                            ['icon' => 'fa-handshake', 'name' => 'Networking Profesional'],
                        ];
                    @endphp

                    @foreach($benefits as $ben)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="benefits[]" value="{{ $ben['name'] }}" class="peer sr-only" {{ in_array($ben['name'], old('benefits', [])) ? 'checked' : '' }}>
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

            <!-- SECTION 5: Admin Pendaftar & Logo Dropzone -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">5</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Admin Komunitas & Logo Badge</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Admin Card -->
                    <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200/80 flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#063B00] to-emerald-800 text-white flex items-center justify-center text-base font-bold shadow-xs">
                            {{ substr(Auth::user()->nama ?? 'Ahmad Subarjo', 0, 1) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-black text-slate-900">{{ Auth::user()->nama ?? 'Ahmad Subarjo' }}</span>
                                <i class="fa-solid fa-circle-check text-[#063B00] text-[10px]"></i>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-0.5">Admin Utama (Pendaftar Akun Ini)</p>
                            <span class="inline-block px-2 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] text-[9px] font-extrabold mt-1">
                                Verified Founder
                            </span>
                        </div>
                    </div>

                    <!-- Logo Dropzone -->
                    <div class="space-y-1">
                        <div class="relative">
                            <div id="logo-dropzone" onclick="document.getElementById('input_logo').click()" class="border-2 border-dashed border-slate-200 hover:border-[#063B00] rounded-2xl p-4 bg-slate-50/50 text-center transition-all cursor-pointer group flex flex-col items-center justify-center">
                                <img id="logo-preview-img" src="" alt="Preview Logo" class="hidden w-full h-36 sm:h-40 rounded-xl object-cover object-center border border-[#063B00]/30 shadow-xs mb-2">
                                <i id="logo-icon" class="fa-solid fa-image text-slate-400 group-hover:text-[#063B00] text-lg mb-1"></i>
                                <p id="logo-title" class="text-[11px] font-bold text-slate-800">Upload Foto Komunitas</p>
                                <p id="logo-filename" class="text-[9px] text-slate-400">Rasio horizontal format JPG/PNG</p>
                            </div>
                            <!-- Clear button: muncul hanya saat file dipilih -->
                            <button type="button" id="logo-clear-btn" onclick="clearLogoSelection(event)" class="hidden absolute w-5 h-5 rounded-full bg-slate-200 hover:bg-rose-100 hover:text-rose-600 text-slate-500 flex items-center justify-center transition-all z-10 shadow-sm" style="top: -8px; right: -8px;" title="Batal pilih gambar">
                                <i class="fa-solid fa-xmark text-[10px]"></i>
                            </button>
                        </div>
                        <input type="file" id="input_logo" name="logo" accept="image/jpeg,image/jpg,image/png" class="sr-only">
                        <input type="hidden" id="uploaded_logo_url" name="logo_url" value="{{ old('logo_url') }}">
                        <p id="err_logo" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Format tidak valid.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-[11px] text-slate-500 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check text-[#063B00]"></i>
                    Komunitas akan langsung aktif dan dapat mulai membuka sesi mabar.
                </p>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button type="reset" class="px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all w-full sm:w-auto">
                        Reset Form
                    </button>
                    <button type="submit" class="px-7 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2 cursor-pointer w-full sm:w-auto">
                        <i class="fa-solid fa-plus text-[#A8E63A]"></i>
                        <span>Daftarkan Komunitas Sekarang</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Preview & Confirmation -->
<div id="previewModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white/95 backdrop-blur-2xl border border-white/80 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
        <div class="w-14 h-14 rounded-3xl bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] flex items-center justify-center text-2xl mx-auto shadow-xs">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="text-center space-y-1.5">
            <h3 class="text-base font-black text-slate-900">Form Komunitas Siap & Tervalidasi!</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Tampilan form pendaftaran komunitas baru telah selesai diperbarui dengan desain modern classic glassmorphism.
            </p>
        </div>
        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
            <p class="font-bold text-slate-800 flex items-center gap-1.5">
                <i class="fa-solid fa-info-circle text-[#063B00]"></i> Mode Review Tampilan
            </p>
            <p>Data belum disimpan ke database sesuai preferensi pengembangan UI Anda.</p>
        </div>
        <div class="flex gap-2.5">
            <button onclick="closePreviewModal()" class="w-full py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs transition-all">
                Tutup Review
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
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

    let isLogoUploading = false;

    function validateLogoField() {
        const input = document.getElementById('input_logo');
        const errEl = document.getElementById('err_logo');
        const dropzone = document.getElementById('logo-dropzone');
        if (!input || !errEl || !dropzone) return true;

        // No file selected — logo is optional, so pass
        if (!input.files || input.files.length === 0) {
            errEl.classList.add('hidden');
            errEl.classList.remove('flex');
            dropzone.classList.remove('border-rose-400', 'bg-rose-50/40');
            dropzone.classList.add('border-slate-200');
            return true;
        }

        const file = input.files[0];
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        const maxBytes = 2 * 1024 * 1024; // 2 MB

        let logoError = null;
        const fileName = file.name || '';
        const ext = fileName.split('.').pop().toLowerCase();
        const allowedExtensions = ['jpg', 'jpeg', 'png'];

        if (!allowedTypes.includes(file.type.toLowerCase()) && !allowedExtensions.includes(ext)) {
            logoError = 'Format logo tidak valid. Hanya JPG, JPEG, atau PNG yang diterima.';
        } else if (file.size > maxBytes) {
            logoError = 'Ukuran logo maksimal 2 MB.';
        }

        if (logoError) {
            errEl.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> ${logoError}`;
            errEl.classList.remove('hidden');
            errEl.classList.add('flex');
            dropzone.classList.add('border-rose-400', 'bg-rose-50/40');
            dropzone.classList.remove('border-slate-200', 'border-emerald-500/70', 'bg-[#EBF8D8]/30');
            return false;
        }

        // Valid
        errEl.classList.add('hidden');
        errEl.classList.remove('flex');
        dropzone.classList.remove('border-rose-400', 'bg-rose-50/40');
        dropzone.classList.add('border-slate-200');
        return true;
    }

    function clearLogoSelection(e) {
        if (e) e.stopPropagation(); // jangan buka file picker saat klik X
        const input = document.getElementById('input_logo');
        const clearBtn = document.getElementById('logo-clear-btn');
        const filenameEl = document.getElementById('logo-filename');
        const titleEl = document.getElementById('logo-title');
        const iconEl = document.getElementById('logo-icon');
        const previewImg = document.getElementById('logo-preview-img');
        const errEl = document.getElementById('err_logo');
        const dropzone = document.getElementById('logo-dropzone');
        const logoUrlInput = document.getElementById('uploaded_logo_url');

        if (input) input.value = '';
        if (logoUrlInput) logoUrlInput.value = '';
        if (clearBtn) clearBtn.classList.add('hidden');
        if (previewImg) {
            previewImg.src = '';
            previewImg.classList.add('hidden');
        }
        if (titleEl) titleEl.textContent = 'Upload Foto Komunitas';
        if (filenameEl) filenameEl.textContent = 'Rasio horizontal format JPG/PNG';
        if (iconEl) {
            iconEl.className = 'fa-solid fa-image text-slate-400 group-hover:text-[#063B00] text-lg mb-1';
        }
        if (errEl) { errEl.classList.add('hidden'); errEl.classList.remove('flex'); }
        if (dropzone) {
            dropzone.classList.remove('border-rose-400', 'bg-rose-50/40', 'border-emerald-500/70', 'bg-[#EBF8D8]/30');
            dropzone.classList.add('border-slate-200');
        }
        isLogoUploading = false;
    }

    // Validate and upload logo on file selection change
    document.getElementById('input_logo')?.addEventListener('change', function() {
        const filenameEl = document.getElementById('logo-filename');
        const titleEl = document.getElementById('logo-title');
        const iconEl = document.getElementById('logo-icon');
        const previewImg = document.getElementById('logo-preview-img');
        const clearBtn = document.getElementById('logo-clear-btn');
        const errEl = document.getElementById('err_logo');
        const dropzone = document.getElementById('logo-dropzone');
        const logoUrlInput = document.getElementById('uploaded_logo_url');

        if (!this.files || this.files.length === 0) {
            clearLogoSelection();
            return;
        }

        // 1. Validasi format & ukuran SEBELUM upload ke Supabase Storage
        const isValid = validateLogoField();
        if (!isValid) {
            if (logoUrlInput) logoUrlInput.value = '';
            if (clearBtn) clearBtn.classList.remove('hidden');
            if (previewImg) previewImg.classList.add('hidden');
            if (filenameEl) filenameEl.textContent = this.files[0]?.name || 'File tidak valid';
            if (iconEl) iconEl.className = 'fa-solid fa-image text-rose-500 text-lg mb-1';
            return;
        }

        // File valid -> Tampilkan preview & state loading upload
        const file = this.files[0];
        if (previewImg) {
            previewImg.src = URL.createObjectURL(file);
            previewImg.classList.remove('hidden');
        }
        if (filenameEl) filenameEl.textContent = file.name;
        if (titleEl) titleEl.textContent = 'Mengunggah foto ke Supabase Storage...';
        if (iconEl) iconEl.className = 'fa-solid fa-circle-notch fa-spin text-[#063B00] text-lg mb-1';
        if (clearBtn) clearBtn.classList.add('hidden');
        isLogoUploading = true;

        // 2. Upload async ke Supabase Storage via backend route
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
                throw new Error(data.message || 'Gagal mengunggah foto ke Supabase Storage.');
            }
            return data;
        })
        .then(data => {
            isLogoUploading = false;
            if (data.success && data.url) {
                if (logoUrlInput) logoUrlInput.value = data.url;
                if (titleEl) titleEl.textContent = 'Foto Berhasil Diunggah';
                if (filenameEl) filenameEl.textContent = file.name;
                if (iconEl) iconEl.className = 'fa-solid fa-circle-check text-[#063B00] text-lg mb-1';
                if (clearBtn) clearBtn.classList.remove('hidden');
                if (dropzone) {
                    dropzone.classList.remove('border-slate-200', 'border-rose-400', 'bg-rose-50/40');
                    dropzone.classList.add('border-emerald-500/70', 'bg-[#EBF8D8]/30');
                }
                if (errEl) { errEl.classList.add('hidden'); errEl.classList.remove('flex'); }
            } else {
                throw new Error(data.message || 'Gagal mengunggah foto ke Supabase Storage.');
            }
        })
        .catch(error => {
            isLogoUploading = false;
            if (logoUrlInput) logoUrlInput.value = '';
            if (clearBtn) clearBtn.classList.remove('hidden');
            if (previewImg) previewImg.classList.add('hidden');
            if (titleEl) titleEl.textContent = 'Upload Foto Komunitas';
            if (filenameEl) filenameEl.textContent = file.name;
            if (iconEl) iconEl.className = 'fa-solid fa-image text-rose-500 text-lg mb-1';
            if (errEl) {
                errEl.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> ${error.message}`;
                errEl.classList.remove('hidden');
                errEl.classList.add('flex');
            }
            if (dropzone) {
                dropzone.classList.remove('border-slate-200', 'border-emerald-500/70', 'bg-[#EBF8D8]/30');
                dropzone.classList.add('border-rose-400', 'bg-rose-50/40');
            }
        });
    });

    function validateCommunityForm(e) {
        if (isLogoUploading) {
            e.preventDefault();
            const errEl = document.getElementById('err_logo');
            if (errEl) {
                errEl.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-[10px]"></i> Logo sedang diunggah ke Supabase Storage, mohon tunggu beberapa saat...`;
                errEl.classList.remove('hidden');
                errEl.classList.add('flex');
            }
            return false;
        }

        const inputLogo = document.getElementById('input_logo');
        const logoUrlInput = document.getElementById('uploaded_logo_url');
        const errLogo = document.getElementById('err_logo');
        const dropzone = document.getElementById('logo-dropzone');

        // Cegah submit jika file dipilih tapi belum/gagal terupload ke Supabase
        if (inputLogo && inputLogo.files && inputLogo.files.length > 0 && (!logoUrlInput || !logoUrlInput.value)) {
            e.preventDefault();
            if (errLogo) {
                if (errLogo.classList.contains('hidden')) {
                    errLogo.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> Upload logo ke Supabase Storage belum berhasil. Silakan tunggu atau batalkan pilihan logo.`;
                    errLogo.classList.remove('hidden');
                    errLogo.classList.add('flex');
                }
            }
            if (dropzone) {
                dropzone.classList.add('border-rose-400', 'bg-rose-50/40');
                dropzone.classList.remove('border-slate-200', 'border-emerald-500/70', 'bg-[#EBF8D8]/30');
                dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        const nama = document.getElementById('input_nama_community')?.value.trim() || '';
        const kota = document.getElementById('input_kota')?.value.trim() || '';
        const deskripsi = document.getElementById('input_deskripsi')?.value.trim() || '';

        let hasError = false;

        if (setFieldError('input_nama_community', 'err_nama_community', !nama ? 'Nama komunitas / klub wajib diisi.' : null)) hasError = true;
        if (setFieldError('input_kota', 'err_kota', !kota ? 'Kota homebase wajib diisi.' : null)) hasError = true;
        if (setFieldError('input_deskripsi', 'err_deskripsi', !deskripsi ? 'Deskripsi lengkap komunitas wajib diisi.' : null)) hasError = true;
        if (!validateLogoField()) hasError = true;

        if (hasError) {
            e.preventDefault();
            const firstErrorField = document.querySelector('.border-rose-400');
            if (firstErrorField) {
                firstErrorField.focus();
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        return true;
    }

    // Clear error UI immediately as user fills/fixes the field
    ['input_nama_community', 'input_kota', 'input_deskripsi'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function() {
                if (this.value.trim()) {
                    setFieldError(id, 'err_' + id.replace('input_', ''), null);
                }
            });
        }
    });

    // Reset error UI on form reset
    document.getElementById('communityForm')?.addEventListener('reset', function() {
        ['input_nama_community', 'input_kota', 'input_deskripsi'].forEach(id => {
            setFieldError(id, 'err_' + id.replace('input_', ''), null);
        });
        clearLogoSelection();
    });

    function handleCommunityPreview(e) {
        e.preventDefault();
        const modal = document.getElementById('previewModal');
        modal.classList.remove('hidden');
    }

    function closePreviewModal() {
        const modal = document.getElementById('previewModal');
        modal.classList.add('hidden');
    }
</script>
@endpush
@endsection
