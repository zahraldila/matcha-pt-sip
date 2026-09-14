@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 relative">
    <!-- Ambient Glow Effects -->
    <div class="absolute w-72 h-72 bg-[#A8E63A]/15 rounded-full blur-3xl pointer-events-none -top-10 -left-12"></div>
    <div class="absolute w-72 h-72 bg-[#063B00]/10 rounded-full blur-3xl pointer-events-none bottom-10 -right-12"></div>

    <div class="relative z-10 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-black uppercase tracking-widest mb-2">
                    <i class="fa-solid fa-id-card text-[#063B00]"></i> Akun &amp; Profil Pemain
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-[#050608] tracking-tight">
                    Profil Member Pemain
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                    Kelola data identitas, kontak WhatsApp, skill level, dan keanggotaan komunitas Anda
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('player.recap') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-xs shadow-2xs transition-all hover:scale-[1.01]">
                    <i class="fa-solid fa-chart-line text-[#063B00]"></i> Lihat Match Recap
                </a>
            </div>
        </div>

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

        <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90 shadow-sm">
            <!-- Profile Banner & Badges -->
            <div class="flex flex-col sm:flex-row items-center gap-5 pb-6 border-b border-slate-200/60">
                <div class="w-20 h-20 rounded-full bg-[#063B00] border-4 border-[#A8E63A]/40 flex items-center justify-center text-white text-2xl font-black shadow-md shrink-0">
                    {{ strtoupper(substr($user->nama ?? 'U', 0, 1)) }}
                </div>
                <div class="text-center sm:text-left space-y-1.5 flex-1 min-w-0">
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <h2 class="text-lg font-bold text-slate-900 truncate">{{ $user->nama }}</h2>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border {{ $user->role === 'host' ? 'bg-amber-50 text-amber-800 border-amber-200' : ($user->role === 'venue_owner' ? 'bg-sky-50 text-sky-800 border-sky-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200') }}">
                            {{ $user->role === 'venue_owner' ? '🏢 Venue Owner' : ($user->role === 'host' ? '👑 Host Game' : '🎾 Member Pemain') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">
                        {{ '@' . \Illuminate\Support\Str::slug($user->nama, '_') }} &bull; {{ $user->email }}
                    </p>
                    <div class="flex flex-wrap gap-1.5 pt-0.5 justify-center sm:justify-start">
                        <span class="px-2.5 py-0.5 rounded-xl bg-slate-100 text-slate-700 text-[11px] font-semibold border border-slate-200">
                            ⭐ Skill: <strong class="text-[#063B00]">{{ $player->level ?? 'Intermediate' }}</strong>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-xl bg-[#EBF8D8] text-[#063B00] text-[11px] font-semibold border border-[#063B00]/20">
                            👥 Komunitas: <strong>{{ $player->community->nama_community ?? 'Personal' }}</strong>
                        </span>
                        @if(!empty($player->usia))
                            <span class="px-2.5 py-0.5 rounded-xl bg-slate-100 text-slate-600 text-[11px] font-semibold border border-slate-200">
                                🎂 Usia: {{ $player->usia }} thn
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Profile Edit Form -->
            <form action="{{ route('player.profile.update') }}" method="POST" class="space-y-4 text-xs">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Nama Lengkap -->
                    <div class="space-y-1 sm:col-span-2">
                        <label class="block font-bold text-slate-800">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama" value="{{ old('nama', $player->nama ?? $user->nama ?? '') }}" required placeholder="Contoh: Billy Santoso" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        <p class="text-[10px] text-slate-400">Nama ini akan digunakan pada papan drawing pertandingan, bracket turnamen, dan leaderboard.</p>
                    </div>

                    <!-- Email (Read-Only) -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Alamat Email (Akun Utama)
                        </label>
                        <input type="email" value="{{ $user->email ?? '' }}" readonly disabled class="w-full bg-slate-100/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-500 font-semibold cursor-not-allowed shadow-2xs">
                        <p class="text-[10px] text-slate-400">Email akun terhubung dan digunakan untuk masuk ke sistem.</p>
                    </div>

                    <!-- Nomor WhatsApp -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Nomor WhatsApp / HP <span class="text-rose-500">*</span>
                        </label>
                        <input type="tel" name="no_hp" value="{{ old('no_hp', $player->no_hp ?? $user->no_hp ?? '') }}" required maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="0812xxxxxxxx" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        <p class="text-[10px] text-slate-400">Nomor kontak untuk koordinasi grup mabar &amp; notifikasi sesi.</p>
                    </div>

                    <!-- Gender -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Jenis Kelamin <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <select name="gender" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="Male" {{ old('gender', $player->gender ?? 'Male') === 'Male' ? 'selected' : '' }}>Laki-laki 🚹</option>
                                <option value="Female" {{ old('gender', $player->gender ?? '') === 'Female' ? 'selected' : '' }}>Perempuan 🚺</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Usia -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Usia (Tahun) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="usia" value="{{ old('usia', $player->usia ?? 25) }}" min="10" max="90" required placeholder="Contoh: 26" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                    </div>

                    <!-- Skill Level -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Kategori Skill Level <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <select name="level" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="Newbie" {{ old('level', $player->level ?? '') === 'Newbie' ? 'selected' : '' }}>Newbie (Baru mulai / belajar)</option>
                                <option value="Beginner" {{ old('level', $player->level ?? '') === 'Beginner' ? 'selected' : '' }}>Beginner (Rally dasar lancar)</option>
                                <option value="Intermediate" {{ old('level', $player->level ?? 'Intermediate') === 'Intermediate' ? 'selected' : '' }}>Intermediate (Konsisten match play)</option>
                                <option value="Advanced" {{ old('level', $player->level ?? '') === 'Advanced' ? 'selected' : '' }}>Advanced (Turnamen &amp; Kompetitif)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Komunitas -->
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Afiliasi Komunitas
                        </label>
                        <div class="relative">
                            <select name="community_id" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="none" {{ empty($player->community_id) ? 'selected' : '' }}>Personal (Non-Community / Belum Ada)</option>
                                @if(isset($communities))
                                    @foreach($communities as $comm)
                                        @php
                                            $cId = $comm->community_id ?? $comm['id'];
                                            $cName = $comm->nama_community ?? $comm['name'];
                                        @endphp
                                        <option value="{{ $cId }}" {{ (old('community_id', $player->community_id ?? null) == $cId) ? 'selected' : '' }}>
                                            {{ $cName }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-5 border-t border-slate-200/60">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-floppy-disk text-[#A8E63A] text-xs"></i>
                        <span>Simpan Perubahan Profil</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
