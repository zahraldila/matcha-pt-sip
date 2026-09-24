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
                Edit Komunitas: {{ $community->nama_community }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl leading-relaxed">
                Perbarui informasi klub, jadwal mabar, cabang olahraga, dan homebase komunitas.
            </p>
        </div>

        <div class="hidden sm:flex flex-col items-end gap-2">
            <div class="px-4 py-2 rounded-2xl bg-white/60 backdrop-blur-md border border-white/80 shadow-2xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-users text-[#A8E63A]"></i>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status Keanggotaan</p>
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

        <form id="communityForm" action="{{ route('communities.update', $community->community_id) }}" method="POST" enctype="multipart/form-data" class="space-y-8 text-xs">
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
                            <input type="text" id="input_nama_community" name="nama_community" value="{{ old('nama_community', $community->nama_community) }}" required class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Slogan / Tagline Komunitas</label>
                        <div class="relative">
                            <i class="fa-solid fa-quote-left absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="tagline" value="{{ old('tagline', $community->tagline) }}" placeholder="Contoh: Mabar Seru, Keringat Bareng, Rating Naik!" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Kota Homebase <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="fa-solid fa-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="kota_homebase" value="{{ old('kota_homebase', $community->kota_homebase) }}" required placeholder="Contoh: Jakarta Selatan, Bandung, Surabaya" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <!-- Cabang Olahraga Utama -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Cabang Olahraga <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <select name="sport" class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-4 pr-10 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                                <option value="padel" {{ old('sport', strtolower($community->sport ?? '')) === 'padel' ? 'selected' : '' }}>🏓 Padel</option>
                                <option value="tennis" {{ old('sport', strtolower($community->sport ?? '')) === 'tennis' ? 'selected' : '' }}>🎾 Tennis</option>
                                <option value="all_racquet" {{ in_array(old('sport', strtolower($community->sport ?? '')), ['all_racquet', 'both', 'padel & tennis']) ? 'selected' : '' }}>🏓🎾 Keduanya (All Racquet)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Target Kemampuan Pemain -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Target Level Pemain</label>
                        <div class="relative">
                            <select name="target_level" class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-4 pr-10 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                                <option value="Semua Level (Open to All)" {{ old('target_level', $community->target_level) === 'Semua Level (Open to All)' ? 'selected' : '' }}>Semua Level (Open to All)</option>
                                <option value="Beginner Friendly" {{ old('target_level', $community->target_level) === 'Beginner Friendly' ? 'selected' : '' }}>Beginner Friendly (Newbie - Pemula)</option>
                                <option value="Intermediate" {{ old('target_level', $community->target_level) === 'Intermediate' ? 'selected' : '' }}>Intermediate (Menengah)</option>
                                <option value="Advanced & Competitive" {{ old('target_level', $community->target_level) === 'Advanced & Competitive' ? 'selected' : '' }}>Advanced &amp; Competitive</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Status Keanggotaan -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Status Keanggotaan</label>
                        <div class="relative">
                            <select name="status_keanggotaan" class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-4 pr-10 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                                <option value="Open" {{ old('status_keanggotaan', $community->status_keanggotaan) === 'Open' ? 'selected' : '' }}>Terbuka (Open to Public)</option>
                                <option value="Closed" {{ old('status_keanggotaan', $community->status_keanggotaan) === 'Closed' ? 'selected' : '' }}>Tertutup Sementara (Closed)</option>
                                <option value="Inactive" {{ old('status_keanggotaan', $community->status_keanggotaan) === 'Inactive' ? 'selected' : '' }}>Nonaktif (Inactive)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <!-- Homebase Venue Utama -->
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Homebase Venue Utama</label>
                        <div class="relative">
                            <input type="text" name="homebase_venue" value="{{ old('homebase_venue', $community->homebase_venue) }}" placeholder="Contoh: Gelora Sports Center Jakarta" list="venueList" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-4 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            <datalist id="venueList">
                                @foreach($venues as $v)
                                    <option value="{{ $v->nama_venue }}">{{ $v->kota ? "({$v->kota})" : '' }}</option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    <!-- Jadwal Rutin -->
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">Jadwal Mabar Rutin</label>
                        <div class="relative">
                            <i class="fa-regular fa-calendar-days absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="jadwal_rutin" value="{{ old('jadwal_rutin', $community->jadwal_rutin) }}" placeholder="Contoh: Setiap Selasa & Jumat malam pukul 19:00 WIB" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <!-- Deskripsi Lengkap -->
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Deskripsi Lengkap Komunitas <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="deskripsi" rows="4" required placeholder="Ceritakan latar belakang klub, visi misi, suasana mabar, dan tata cara bergabung..." class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 text-slate-900 font-medium focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs leading-relaxed">{{ old('deskripsi', $community->deskripsi) }}</textarea>
                    </div>

                    <!-- Logo Komunitas -->
                    <div class="sm:col-span-2 space-y-2">
                        <label class="block font-bold text-slate-800">Logo Komunitas</label>
                        <div class="flex items-center gap-4">
                            @if(!empty($community->logo))
                                <img src="{{ $community->logo }}" alt="Logo Saat Ini" class="w-16 h-16 rounded-2xl object-cover border border-slate-200 shadow-sm shrink-0">
                            @endif
                            <div class="flex-1">
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/jpg" class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#EBF8D8] file:text-[#063B00] hover:file:bg-[#A8E63A]/40 file:cursor-pointer transition-all">
                                <p class="text-[11px] text-slate-400 mt-1">Format: JPG, JPEG, PNG (Maksimal 2 MB). Biarkan kosong jika tidak ingin mengubah logo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Action -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="{{ route('communities.show', $community->community_id) }}" class="px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-8 py-3 rounded-2xl bg-gradient-to-r from-[#063B00] to-emerald-900 hover:opacity-95 text-white font-extrabold text-xs shadow-md shadow-[#063B00]/20 flex items-center gap-2 transition-all cursor-pointer hover:scale-[1.01]">
                    <i class="fa-solid fa-check text-[#A8E63A]"></i>
                    <span>Simpan Perubahan Komunitas</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
