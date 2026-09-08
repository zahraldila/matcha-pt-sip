@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative">

    <!-- Ambient Glow Effects -->
    <div class="absolute w-80 h-80 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-12 -left-16"></div>
    <div class="absolute w-80 h-80 bg-[#063B00]/15 rounded-full blur-3xl pointer-events-none bottom-10 -right-16"></div>

    <div class="space-y-6 relative z-10">
        
        <!-- Header -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-black uppercase tracking-widest">
                <i class="fa-solid fa-sparkles text-[#063B00]"></i> Matcha Member Hub
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-[#050608] tracking-tight">
                Pendaftaran Akun Baru
            </h1>
            <p class="text-xs text-slate-500 max-w-md mx-auto">
                Daftarkan akun dan profil pemain Anda untuk sinkronisasi otomatis saat drawing, live scoring, dan pencatatan rating
            </p>
        </div>

        <!-- Glassmorphism Registration Card -->
        <div class="glass-card !bg-white/85 !backdrop-blur-2xl rounded-3xl p-6 sm:p-9 space-y-6 border border-white shadow-[0_12px_40px_-10px_rgba(6,59,0,0.08)]">
            
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

            <form action="{{ route('register.post') }}" method="POST" class="space-y-6 text-xs">
                @csrf

                <!-- ==================== STEP A: PILIHAN ROLE / PERAN ==================== -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                        <h3 class="text-xs font-black text-[#050608] uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">1</span>
                            Pilih Peran Utama Anda
                        </h3>
                        <span class="text-[10px] text-slate-400 font-semibold">*Bisa disesuaikan nanti</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <!-- Member / Player -->
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="member" class="peer sr-only" checked onchange="updateRoleBadge('member')">
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:shadow-sm transition-all text-center space-y-1 hover:border-slate-300">
                                <div class="w-8 h-8 mx-auto rounded-xl bg-white border border-slate-200/70 flex items-center justify-center text-sm text-[#063B00] shadow-2xs">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <h4 class="font-extrabold text-slate-900 text-xs">Pemain / Member</h4>
                                <p class="text-[10px] text-slate-500 leading-tight">Ikut mabar & rekap statistik</p>
                            </div>
                        </label>

                        <!-- Host Game -->
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="host" class="peer sr-only" onchange="updateRoleBadge('host')">
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:shadow-sm transition-all text-center space-y-1 hover:border-slate-300">
                                <div class="w-8 h-8 mx-auto rounded-xl bg-white border border-slate-200/70 flex items-center justify-center text-sm text-[#063B00] shadow-2xs">
                                    <i class="fa-solid fa-trophy"></i>
                                </div>
                                <h4 class="font-extrabold text-slate-900 text-xs">Host Game</h4>
                                <p class="text-[10px] text-slate-500 leading-tight">Buat mabar, drawing & scoring</p>
                            </div>
                        </label>

                        <!-- Venue Owner -->
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="venue_owner" class="peer sr-only" onchange="updateRoleBadge('venue_owner')">
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:shadow-sm transition-all text-center space-y-1 hover:border-slate-300">
                                <div class="w-8 h-8 mx-auto rounded-xl bg-white border border-slate-200/70 flex items-center justify-center text-sm text-[#063B00] shadow-2xs">
                                    <i class="fa-solid fa-building"></i>
                                </div>
                                <h4 class="font-extrabold text-slate-900 text-xs">Pemilik Venue</h4>
                                <p class="text-[10px] text-slate-500 leading-tight">Daftarkan & kelola lapangan</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- ==================== STEP B: IDENTITAS PRIBADI ==================== -->
                <div class="space-y-3 pt-1">
                    <div class="pb-2 border-b border-slate-200/60">
                        <h3 class="text-xs font-black text-[#050608] uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">2</span>
                            Data Profil & Kontak
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="sm:col-span-2 space-y-1">
                            <label class="block font-bold text-slate-800">
                                Nama Lengkap
                            </label>
                            <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Contoh: Billy Santoso" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                            <span class="text-[10px] text-slate-400 block font-medium">*Nama ini akan tercatat konsisten di papan drawing dan rekap pertandingan.</span>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Nomor WhatsApp / HP
                            </label>
                            <input type="text" name="no_hp" value="{{ old('no_hp') }}" placeholder="0812-xxxx-xxxx" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Alamat Email
                            </label>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                        </div>
                    </div>
                </div>

                <!-- ==================== STEP C: PROFIL PEMAIN (GENDER, USIA, SKILL LEVEL) ==================== -->
                <div class="space-y-3 pt-1">
                    <div class="pb-2 border-b border-slate-200/60">
                        <h3 class="text-xs font-black text-[#050608] uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">3</span>
                            Parameter Pertandingan & Skill Level
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Jenis Kelamin
                            </label>
                            <div class="relative">
                                <select name="gender" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                    <option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Laki-laki 🚹</option>
                                    <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Perempuan 🚺</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                            </div>
                            <span class="text-[10px] text-slate-400 block font-medium">*Digunakan algoritma format Mixicano / Mix Americano.</span>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Usia (Tahun)
                            </label>
                            <input type="number" name="usia" value="{{ old('usia', 25) }}" min="10" max="85" placeholder="Contoh: 28" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Kategori Skill Level
                            </label>
                            <div class="relative">
                                <select name="level" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                    <option value="Newbie" {{ old('level') === 'Newbie' ? 'selected' : '' }}>Newbie (Baru mulai / belajar)</option>
                                    <option value="Beginner" {{ old('level') === 'Beginner' ? 'selected' : '' }}>Beginner (Rally dasar lancar)</option>
                                    <option value="Intermediate" {{ old('level', 'Intermediate') === 'Intermediate' ? 'selected' : '' }}>Intermediate (Konsisten match play)</option>
                                    <option value="Advanced" {{ old('level') === 'Advanced' ? 'selected' : '' }}>Advanced (Turnamen & Kompetitif)</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                            </div>
                            <span class="text-[10px] text-slate-400 block font-medium">*Membantu sistem menyusun drawing tim yang seimbang.</span>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Pilihan Komunitas (Opsional)
                            </label>
                            <div class="relative">
                                <select name="community_id" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                    <option value="none">Personal (Non-Community / Belum Ada)</option>
                                    @if(isset($communities))
                                        @foreach($communities as $comm)
                                            <option value="{{ $comm->community_id ?? $comm['id'] }}">{{ $comm->nama_community ?? $comm['name'] }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== STEP D: KREDENSIAL PASSWORD ==================== -->
                <div class="space-y-3 pt-1">
                    <div class="pb-2 border-b border-slate-200/60">
                        <h3 class="text-xs font-black text-[#050608] uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">4</span>
                            Keamanan Password Akun
                        </h3>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800">
                            Password Login
                        </label>
                        <div class="relative">
                            <input type="password" id="registerPassword" name="password" placeholder="Minimal 6 karakter" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl pl-4 pr-10 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                            <button type="button" onclick="togglePasswordVisibility('registerPassword', 'regEyeIcon')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i class="fa-solid fa-eye text-xs" id="regEyeIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit CTA -->
                <div class="pt-3 border-t border-slate-200/60">
                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-user-plus text-[#A8E63A] text-xs"></i>
                        <span>Selesaikan Registrasi Member</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer link -->
        <p class="text-center text-xs text-slate-500 font-medium">
            Sudah memiliki akun? 
            <a href="{{ route('login') }}" class="font-extrabold text-[#063B00] hover:underline">Masuk di sini</a>
        </p>
    </div>
</div>

@push('scripts')
<script>
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function updateRoleBadge(role) {
        // Subtle haptic/visual feedback if needed
    }
</script>
@endpush
@endsection
