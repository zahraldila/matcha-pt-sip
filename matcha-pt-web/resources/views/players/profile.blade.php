@extends('layouts.app')

@section('content')
<div class="w-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-8 pb-28 md:pb-12 space-y-5 sm:space-y-6">
    <div class="space-y-5 sm:space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 border-b border-slate-200/60 pb-4">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full {{ $user->isAdmin() ? 'bg-amber-100 border-amber-300 text-amber-900' : 'bg-[#EBF8D8] border-[#063B00]/20 text-[#063B00]' }} text-[10px] font-black uppercase tracking-widest mb-1.5 sm:mb-2 border">
                    <i class="fa-solid {{ $user->isAdmin() ? 'fa-user-shield text-amber-700' : 'fa-id-card text-[#063B00]' }}"></i> {{ $user->isAdmin() ? 'Akun & Profil Administrator' : 'Akun & Profil Pemain' }}
                </div>
                <h1 class="text-xl sm:text-3xl font-extrabold text-[#050608] tracking-tight">
                    {{ $user->isAdmin() ? 'Profil Akun Administrator' : 'Profil Member Pemain' }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                    {{ $user->isAdmin() ? 'Kelola data identitas akun, kontak WhatsApp, dan foto profil Administrator Matcha' : 'Kelola data identitas, kontak WhatsApp, skill level, dan status keanggotaan Host Anda' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if(!$user->isAdmin())
                    <a href="{{ route('player.recap') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-xs shadow-2xs transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-chart-line text-[#063B00]"></i> Lihat Match Recap
                    </a>
                @endif
            </div>

        </div>

        @if(session('info') || request('notice') === 'host_required')
            <div id="hostNoticeAlert" class="p-4 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/30 text-[#063B00] text-xs flex items-start gap-3 shadow-xs animate-in fade-in duration-300">
                <div class="w-7 h-7 rounded-xl bg-[#063B00] text-[#A8E63A] flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="space-y-0.5">
                    <h4 class="font-extrabold text-[#050608] text-xs">Aktifkan Mode Host untuk Membuat Game</h4>
                    <p class="text-[11px] text-slate-600 leading-relaxed">
                        {{ session('info') ?? 'Untuk membuat sesi mabar baru, bagan drawing, atau live scoring, silakan aktifkan status Host Game pada tombol di bawah ini.' }}
                    </p>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2.5 shadow-2xs">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Terdapat kesalahan pada input data profil:</span>
                </div>
                <ul class="list-disc list-inside pl-1 text-[11px] text-rose-600 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!$user->isAdmin())
            <!-- ================================================================= -->
            <!-- CARD TOGGLE STATUS HOST                                          -->
            <!-- ================================================================= -->
            <div class="glass-card rounded-3xl p-4 sm:p-6 border {{ (request('notice') === 'host_required' || session('info')) && !$user->is_host ? 'border-[#063B00] ring-2 ring-[#063B00]/20 shadow-md' : 'border-white/90 shadow-sm' }} bg-gradient-to-r from-emerald-50/50 via-white to-slate-50/50 transition-all">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-800">Status Akses Host Game:</span>
                            @if($user->is_host)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                                    <i class="fa-solid fa-circle text-[8px] text-emerald-500 animate-pulse"></i> Host Game Active
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-600 border border-slate-200">
                                    Pemain / Member Only
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500 leading-relaxed">
                            @if($user->is_host)
                                Mode Host Aktif. Kamu diizinkan untuk membuat jadwal mabar baru, mengelola drawing tim, dan live scoring.
                            @else
                                Aktifkan status Host untuk mendapatkan akses membuat sesi mabar, bagan drawing, dan pencatatan poin langsung.
                            @endif
                        </p>
                    </div>

                    <form action="{{ route('player.profile.toggle-host') }}" method="POST" class="shrink-0 w-full sm:w-auto">
                        @csrf
                        @if($user->is_host)
                            <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-rose-50 hover:bg-rose-100 text-rose-600 font-extrabold text-xs border border-rose-200 transition-all cursor-pointer">
                                <i class="fa-solid fa-power-off text-rose-500"></i>
                                <span>Nonaktifkan Mode Host</span>
                            </button>
                        @else
                            <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer">
                                <i class="fa-solid fa-bolt text-[#A8E63A]"></i>
                                <span>Aktifkan Mode Host</span>
                            </button>
                        @endif
                    </form>
                </div>
            </div>
        @endif

        <div class="glass-card rounded-3xl p-5 sm:p-8 space-y-6 border border-white/90 shadow-sm">
            <!-- Profile Edit Form (Includes Avatar Uploader) -->
            <form action="{{ route('player.profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6 text-xs">
                @csrf

                <!-- Hidden inputs for avatar file and delete flag -->
                <input type="file" id="avatarFileInput" name="foto" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden" onchange="previewAvatar(event)">
                <input type="hidden" id="hapusFotoInput" name="hapus_foto" value="0">

                <!-- Profile Banner & Interactive Avatar -->
                <div class="flex flex-col sm:flex-row items-center gap-5 pb-6 border-b border-slate-200/60">
                    <!-- Interactive Avatar with Camera Overlay (Clickable to open 3 options modal) -->
                    <div class="relative shrink-0 cursor-pointer group" onclick="openPhotoOptionsModal()" title="Klik untuk ubah foto profil" style="width: 96px; height: 96px;">
                        <div id="avatarContainer" class="w-24 h-24 rounded-full bg-[#063B00] border-4 border-[#A8E63A]/40 flex items-center justify-center text-white text-3xl font-black shadow-md overflow-hidden relative group-hover:ring-4 group-hover:ring-[#A8E63A]/40 transition-all" style="width: 96px; height: 96px; min-width: 96px; min-height: 96px;">
                            @if(!empty($user->foto))
                                <img id="avatarImage" src="{{ $user->foto }}" alt="{{ $user->nama }}" class="w-full h-full object-cover" style="width: 100%; height: 100%; object-fit: cover;">
                                <span id="avatarInitial" class="hidden">{{ strtoupper(substr($user->nama ?? 'U', 0, 1)) }}</span>
                            @else
                                <img id="avatarImage" src="" alt="{{ $user->nama }}" class="w-full h-full object-cover hidden" style="width: 100%; height: 100%; object-fit: cover;">
                                <span id="avatarInitial">{{ strtoupper(substr($user->nama ?? 'U', 0, 1)) }}</span>
                            @endif
                        </div>

                        <!-- Camera Action Button Overlay -->
                        <div class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-[#063B00] group-hover:bg-[#042a00] border-2 border-white text-[#A8E63A] flex items-center justify-center text-xs shadow-md transition-transform group-hover:scale-110 active:scale-95 pointer-events-none" style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                    </div>

                    <div class="text-center sm:text-left space-y-2 flex-1 min-w-0">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-center sm:justify-start gap-1.5 sm:gap-2">
                            <h2 class="text-lg font-bold text-slate-900 truncate">{{ $user->nama }}</h2>
                            <span class="inline-block self-center sm:self-auto px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border {{ $user->isAdmin() ? 'bg-amber-100 text-amber-900 border-amber-300' : ($user->is_host ? 'bg-amber-50 text-amber-800 border-amber-200' : ($user->role === 'venue_owner' ? 'bg-sky-50 text-sky-800 border-sky-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200')) }}">
                                {{ $user->isAdmin() ? '👑 Administrator Global' : ($user->role === 'venue_owner' ? '🏢 Venue Owner' : ($user->is_host ? '👑 Host Game & Player' : '🎾 Member Pemain')) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium break-words sm:break-normal">
                            {{ '@' . \Illuminate\Support\Str::slug($user->nama, '_') }} &bull; {{ $user->email }}
                        </p>

                        @if(!$user->isAdmin())
                            <div class="flex flex-wrap gap-1.5 pt-1 justify-center sm:justify-start">
                                <span class="px-2.5 py-0.5 rounded-xl bg-slate-100 text-slate-700 text-[11px] font-semibold border border-slate-200">
                                    ⭐ Skill: <strong class="text-[#063B00]">{{ $player->level ?? 'Intermediate' }}</strong>
                                </span>
                                @if(!empty($player->usia))
                                    <span class="px-2.5 py-0.5 rounded-xl bg-slate-100 text-slate-600 text-[11px] font-semibold border border-slate-200">
                                        🎂 Usia: {{ $player->usia }} thn
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div id="fotoBadgeAlert" class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-amber-50 text-amber-800 font-bold text-[10px] border border-amber-200 animate-pulse">
                            <i class="fa-solid fa-circle-info text-amber-600"></i>
                            <span id="fotoBadgeAlertText">Foto baru terpilih &bull; Klik Simpan</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Nama Lengkap -->
                    <div class="space-y-1 sm:col-span-2">
                        <label class="block font-bold text-slate-800">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama" value="{{ old('nama', $player->nama ?? $user->nama ?? '') }}" required placeholder="Contoh: Billy Santoso" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        <p class="text-[10px] text-slate-400">Nama akun yang digunakan pada identitas sistem.</p>
                    </div>

                    <!-- Email (Akun Utama) -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Alamat Email (Akun Utama) <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required placeholder="contoh: admin@matcha.id" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        <p class="text-[10px] text-slate-400">Email akun terhubung dan digunakan untuk masuk ke sistem.</p>
                    </div>

                    <!-- Nomor WhatsApp -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Nomor WhatsApp / HP <span class="text-rose-500">*</span>
                        </label>
                        <input type="tel" name="no_hp" value="{{ old('no_hp', $player->no_hp ?? $user->no_hp ?? '') }}" required maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="0812xxxxxxxx" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        <p class="text-[10px] text-slate-400">Nomor kontak resmi untuk koordinasi &amp; notifikasi sistem.</p>
                    </div>

                    <!-- Gender -->
                    <div class="space-y-1 relative" id="wrapper_gender">
                        <label class="block font-bold text-slate-800">
                            Jenis Kelamin <span class="text-rose-500">*</span>
                        </label>
                        @php
                            $currGender = old('gender', $player->gender ?? 'Male');
                            $genderMap = [
                                'Male' => 'Laki-laki',
                                'Female' => 'Perempuan',
                            ];
                            $currGenderLabel = $genderMap[$currGender] ?? 'Laki-laki';
                        @endphp
                        <input type="hidden" id="genderInput" name="gender" value="{{ $currGender }}">
                        <div class="relative">
                            <button
                                type="button"
                                id="genderTrigger"
                                onclick="toggleProfileCustomDropdown('gender', event)"
                                class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                            >
                                <span id="genderDisplay" class="truncate font-semibold">{{ $currGenderLabel }}</span>
                                <i id="genderChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                            </button>

                            <div
                                id="genderMenu"
                                class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
                                onclick="event.stopPropagation()"
                            >
                                @foreach($genderMap as $gVal => $gLabel)
                                    <button
                                        type="button"
                                        onclick="selectProfileCustomOption('gender', '{{ $gVal }}', '{{ $gLabel }}')"
                                        class="gender-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all cursor-pointer {{ $currGender === $gVal ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                        data-val="{{ $gVal }}"
                                    >
                                        <span>{{ $gLabel }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if(!$user->isAdmin())
                        <!-- Usia -->
                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Usia (Tahun) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" name="usia" value="{{ old('usia', $player->usia ?? 25) }}" min="10" max="90" required placeholder="Contoh: 26" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>

                        <!-- Skill Level -->
                        <div class="space-y-1 relative" id="wrapper_level">
                            <label class="block font-bold text-slate-800">
                                Kategori Skill Level <span class="text-rose-500">*</span>
                            </label>
                            @php
                                $currLevel = old('level', $player->level ?? 'Intermediate');
                                $levelMap = [
                                    'Newbie' => 'Newbie (Baru mulai / belajar)',
                                    'Beginner' => 'Beginner (Rally dasar lancar)',
                                    'Intermediate' => 'Intermediate (Konsisten match play)',
                                    'Advanced' => 'Advanced (Turnamen & Kompetitif)',
                                ];
                                $currLevelLabel = $levelMap[$currLevel] ?? $currLevel;
                            @endphp
                            <input type="hidden" id="levelInput" name="level" value="{{ $currLevel }}">
                            <div class="relative">
                                <button
                                    type="button"
                                    id="levelTrigger"
                                    onclick="toggleProfileCustomDropdown('level', event)"
                                    class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-left flex items-center justify-between cursor-pointer"
                                >
                                    <span id="levelDisplay" class="truncate font-semibold">{{ $currLevelLabel }}</span>
                                    <i id="levelChevron" class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 pointer-events-none"></i>
                                </button>

                                <div
                                    id="levelMenu"
                                    class="hidden absolute left-0 right-0 top-full mt-1.5 z-50 bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl shadow-xl p-1.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
                                    onclick="event.stopPropagation()"
                                >
                                    @foreach($levelMap as $lVal => $lLabel)
                                        <button
                                            type="button"
                                            onclick="selectProfileCustomOption('level', '{{ $lVal }}', '{{ $lLabel }}')"
                                            class="level-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all cursor-pointer {{ $currLevel === $lVal ? 'bg-[#EBF8D8] text-[#063B00] font-bold border border-[#063B00]/15' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}"
                                            data-val="{{ $lVal }}"
                                        >
                                            <span>{{ $lLabel }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    @endif
                </div>

                <div class="flex justify-end pt-5 border-t border-slate-200/60">
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk text-[#A8E63A] text-xs"></i>
                        <span>Simpan Perubahan Profil</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Photo Options Modal (Bottom Sheet on mobile, Dialog on desktop) -->
<div id="photoOptionsModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-end sm:items-center justify-center p-0 sm:p-4 transition-all" onclick="if(event.target === this) closePhotoOptionsModal();">
    <div class="bg-white/95 backdrop-blur-2xl border border-white/80 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 max-w-sm w-full shadow-2xl space-y-4 animate-in fade-in slide-in-from-bottom sm:zoom-in duration-200" onclick="event.stopPropagation()">
        <!-- Modal Header / Drag handle on mobile -->
        <div class="flex flex-col items-center sm:items-start space-y-1">
            <div class="w-12 h-1 rounded-full bg-slate-200 sm:hidden mb-2"></div>
            <div class="flex items-center justify-between w-full">
                <h3 class="text-sm font-black text-slate-900">Ubah Foto Profil</h3>
                <button type="button" onclick="closePhotoOptionsModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
            <p class="text-[11px] text-slate-500">Pilih opsi foto profil yang ingin Anda gunakan</p>
        </div>

        <!-- Options list -->
        <div class="space-y-2 pt-1">
            <!-- 1. Ambil dari Kamera -->
            <button
                type="button"
                onclick="takePhotoFromCamera()"
                class="w-full p-3 rounded-2xl bg-slate-50 hover:bg-[#EBF8D8] border border-slate-200/80 hover:border-[#063B00]/20 flex items-center gap-3.5 transition-all text-left cursor-pointer group"
            >
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-[#063B00] group-hover:bg-[#063B00] group-hover:text-[#A8E63A] flex items-center justify-center text-sm transition-colors shrink-0">
                    <i class="fa-solid fa-camera"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-900 group-hover:text-[#063B00]">Ambil dari Kamera</div>
                    <div class="text-[10px] text-slate-500">Potret langsung menggunakan kamera perangkat</div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 group-hover:text-[#063B00] transition-colors"></i>
            </button>

            <!-- 2. Pilih dari Galeri -->
            <button
                type="button"
                onclick="choosePhotoFromGallery()"
                class="w-full p-3 rounded-2xl bg-slate-50 hover:bg-[#EBF8D8] border border-slate-200/80 hover:border-[#063B00]/20 flex items-center gap-3.5 transition-all text-left cursor-pointer group"
            >
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-[#063B00] group-hover:bg-[#063B00] group-hover:text-[#A8E63A] flex items-center justify-center text-sm transition-colors shrink-0">
                    <i class="fa-solid fa-images"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-900 group-hover:text-[#063B00]">Pilih dari Galeri</div>
                    <div class="text-[10px] text-slate-500">Pilih gambar dari album atau penyimpanan berkas</div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 group-hover:text-[#063B00] transition-colors"></i>
            </button>

            <!-- 3. Hapus Foto Profil -->
            <button
                type="button"
                id="modalOptionHapusFoto"
                onclick="handleRemoveAvatar()"
                class="{{ empty($user->foto) ? 'hidden' : 'flex' }} w-full p-3 rounded-2xl bg-rose-50/70 hover:bg-rose-100 border border-rose-200/80 hover:border-rose-300 items-center gap-3.5 transition-all text-left cursor-pointer group"
            >
                <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 group-hover:bg-rose-600 group-hover:text-white flex items-center justify-center text-sm transition-colors shrink-0">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-rose-700">Hapus Foto Profil</div>
                    <div class="text-[10px] text-rose-500">Gunakan inisial nama sebagai foto profil</div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-rose-300 group-hover:text-rose-600 transition-colors"></i>
            </button>
        </div>

        <div class="pt-2">
            <button type="button" onclick="closePhotoOptionsModal()" class="w-full py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all cursor-pointer">
                Batal
            </button>
        </div>
    </div>
</div>

<script>
    function openPhotoOptionsModal() {
        const modal = document.getElementById('photoOptionsModal');
        const hapusBtn = document.getElementById('modalOptionHapusFoto');
        const img = document.getElementById('avatarImage');
        const hapusInput = document.getElementById('hapusFotoInput');
        
        const hasPhoto = img && img.src && !img.classList.contains('hidden') && (!hapusInput || hapusInput.value !== '1');
        if (hapusBtn) {
            if (hasPhoto) {
                hapusBtn.classList.remove('hidden');
                hapusBtn.classList.add('flex');
            } else {
                hapusBtn.classList.add('hidden');
                hapusBtn.classList.remove('flex');
            }
        }
        
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closePhotoOptionsModal() {
        const modal = document.getElementById('photoOptionsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function takePhotoFromCamera() {
        closePhotoOptionsModal();
        const input = document.getElementById('avatarFileInput');
        if (input) {
            input.setAttribute('capture', 'user');
            input.click();
        }
    }

    function choosePhotoFromGallery() {
        closePhotoOptionsModal();
        const input = document.getElementById('avatarFileInput');
        if (input) {
            input.removeAttribute('capture');
            input.click();
        }
    }

    function handleRemoveAvatar() {
        closePhotoOptionsModal();
        removeAvatar();
    }

    function previewAvatar(event) {
        const input = event.target;
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            // Validasi ukuran frontend (2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file foto maksimal adalah 2 MB.');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('avatarImage');
                const initial = document.getElementById('avatarInitial');
                const badgeAlert = document.getElementById('fotoBadgeAlert');
                const badgeAlertText = document.getElementById('fotoBadgeAlertText');
                const hapusInput = document.getElementById('hapusFotoInput');

                if (img) {
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                }
                if (initial) {
                    initial.classList.add('hidden');
                }
                if (badgeAlert) {
                    badgeAlert.classList.remove('hidden');
                    if (badgeAlertText) {
                        badgeAlertText.textContent = 'Foto baru terpilih • Klik Simpan';
                    }
                }
                if (hapusInput) {
                    hapusInput.value = '0';
                }
            };
            reader.readAsDataURL(file);
        }
    }

    function removeAvatar() {
        const img = document.getElementById('avatarImage');
        const initial = document.getElementById('avatarInitial');
        const input = document.getElementById('avatarFileInput');
        const hapusInput = document.getElementById('hapusFotoInput');
        const badgeAlert = document.getElementById('fotoBadgeAlert');
        const badgeAlertText = document.getElementById('fotoBadgeAlertText');

        if (input) {
            input.value = '';
        }
        if (hapusInput) {
            hapusInput.value = '1';
        }
        if (img) {
            img.src = '';
            img.classList.add('hidden');
        }
        if (initial) {
            initial.classList.remove('hidden');
        }
        if (badgeAlert) {
            badgeAlert.classList.remove('hidden');
            if (badgeAlertText) {
                badgeAlertText.textContent = 'Foto profil akan dihapus saat disimpan • Klik Simpan';
            }
        }
    }

    // Profile Custom Dropdowns (gender, level)
    const profileCustomDropdownIds = ['gender', 'level'];

    function closeProfileCustomDropdowns() {
        profileCustomDropdownIds.forEach(id => {
            const menu = document.getElementById(id + 'Menu');
            const chevron = document.getElementById(id + 'Chevron');
            const wrapper = document.getElementById('wrapper_' + id);
            if (menu) menu.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180');
            if (wrapper) wrapper.style.zIndex = '';
        });
    }

    function toggleProfileCustomDropdown(id, e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById(id + 'Menu');
        const chevron = document.getElementById(id + 'Chevron');
        const wrapper = document.getElementById('wrapper_' + id);
        if (!menu) return;

        const isClosed = menu.classList.contains('hidden');
        closeProfileCustomDropdowns();

        if (isClosed) {
            menu.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-180');
            if (wrapper) wrapper.style.zIndex = '40';
        }
    }

    function selectProfileCustomOption(fieldId, value, label) {
        const input = document.getElementById(fieldId + 'Input');
        const display = document.getElementById(fieldId + 'Display');
        if (input) input.value = value;
        if (display) display.textContent = label;

        document.querySelectorAll('.' + fieldId + '-item-btn').forEach(btn => {
            const isMatch = btn.getAttribute('data-val') === value;
            if (isMatch) {
                btn.className = fieldId + '-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-bold transition-all cursor-pointer bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/15';
            } else {
                btn.className = fieldId + '-item-btn w-full px-3 py-2 rounded-xl text-left text-xs font-semibold transition-all cursor-pointer text-slate-700 hover:bg-slate-100 hover:text-slate-900';
            }
        });

        closeProfileCustomDropdowns();
    }

    document.addEventListener('click', function (e) {
        let isInsideAny = false;
        profileCustomDropdownIds.forEach(id => {
            const menu = document.getElementById(id + 'Menu');
            const trigger = document.getElementById(id + 'Trigger');
            if ((menu && menu.contains(e.target)) || (trigger && trigger.contains(e.target))) {
                isInsideAny = true;
            }
        });
        if (!isInsideAny) {
            closeProfileCustomDropdowns();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeProfileCustomDropdowns();
            closePhotoOptionsModal();
        }
    });
</script>
@endsection