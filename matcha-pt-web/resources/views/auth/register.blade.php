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

            <form id="registerForm" action="{{ route('register.post') }}" method="POST" class="space-y-6 text-xs" novalidate onsubmit="showRegisterConfirmation(event)">
                @csrf

                <!-- ==================== STEP A: PILIHAN ROLE / PERAN ==================== -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                        <h3 class="text-xs font-black text-[#050608] uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">1</span>
                            Pilih Peran Utama Anda
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <!-- Member / Player -->
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="member" class="peer sr-only" checked>
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
                            <input type="radio" name="role" value="host" class="peer sr-only">
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
                            <input type="radio" name="role" value="venue_owner" class="peer sr-only">
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
                                Nama Lengkap <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="nama" id="input_nama" value="{{ old('nama') }}" placeholder="Contoh: Billy Santoso" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            <p id="err_nama" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                                <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Nama lengkap wajib diisi.
                            </p>
                            <span class="text-[10px] text-slate-400 block font-medium mt-0.5">*Nama ini akan tercatat konsisten di papan drawing dan rekap pertandingan.</span>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Nomor WhatsApp / HP <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="no_hp" id="input_no_hp" value="{{ old('no_hp') }}" placeholder="0812-xxxx-xxxx" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            <p id="err_no_hp" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                                <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Nomor WhatsApp / HP wajib diisi.
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Alamat Email <span class="text-rose-500">*</span>
                            </label>
                            <input type="email" name="email" id="input_email" value="{{ old('email') }}" placeholder="nama@email.com" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            <p id="err_email" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                                <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Alamat email wajib diisi.
                            </p>
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
                                Jenis Kelamin <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <select name="gender" id="input_gender" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                    <option value="" disabled {{ old('gender') ? '' : 'selected' }}>Pilih Jenis Kelamin</option>
                                    <option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Laki-laki 🚹</option>
                                    <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Perempuan 🚺</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                            </div>
                            <p id="err_gender" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                                <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Jenis kelamin wajib dipilih.
                            </p>
                            <span class="text-[10px] text-slate-400 block font-medium">*Digunakan algoritma format Mixicano / Mix Americano.</span>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Usia (Tahun) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" name="usia" id="input_usia" value="{{ old('usia') }}" min="10" max="85" placeholder="Contoh: 28" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            <p id="err_usia" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                                <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Usia wajib diisi.
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Kategori Skill Level <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <select name="level" id="input_level" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                    <option value="" disabled {{ old('level') ? '' : 'selected' }}>Pilih Kategori Skill Level</option>
                                    <option value="Newbie" {{ old('level') === 'Newbie' ? 'selected' : '' }}>Newbie (Baru mulai / belajar)</option>
                                    <option value="Beginner" {{ old('level') === 'Beginner' ? 'selected' : '' }}>Beginner (Rally dasar lancar)</option>
                                    <option value="Intermediate" {{ old('level') === 'Intermediate' ? 'selected' : '' }}>Intermediate (Konsisten match play)</option>
                                    <option value="Advanced" {{ old('level') === 'Advanced' ? 'selected' : '' }}>Advanced (Turnamen & Kompetitif)</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                            </div>
                            <p id="err_level" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                                <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Kategori skill level wajib dipilih.
                            </p>
                            <span class="text-[10px] text-slate-400 block font-medium">*Membantu sistem menyusun drawing tim yang seimbang.</span>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-800">
                                Pilihan Komunitas (Opsional)
                            </label>
                            <div class="relative">
                                <select name="community_id" id="input_community_id" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                    <option value="" {{ old('community_id') ? '' : 'selected' }}>Pilih Komunitas</option>
                                    <option value="none" {{ old('community_id') === 'none' ? 'selected' : '' }}>Personal (Non-Community / Belum Ada)</option>
                                    @if(isset($communities))
                                        @foreach($communities as $comm)
                                            @php
                                                $cId = $comm->community_id ?? $comm['id'];
                                                $cName = $comm->nama_community ?? $comm['name'];
                                            @endphp
                                            <option value="{{ $cId }}" {{ old('community_id') == $cId ? 'selected' : '' }}>{{ $cName }}</option>
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
                            Password Login <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="registerPassword" name="password" placeholder="Minimal 6 karakter" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl pl-4 pr-10 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                            <button type="button" onclick="togglePasswordVisibility('registerPassword', 'regEyeIcon')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i class="fa-solid fa-eye text-xs" id="regEyeIcon"></i>
                            </button>
                        </div>
                        <p id="err_password" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                            <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Password wajib diisi (minimal 6 karakter).
                        </p>
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

<!-- Confirmation Modal -->
<div id="registerConfirmModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white/95 backdrop-blur-2xl border border-white/80 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
        <div class="w-14 h-14 rounded-3xl bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] flex items-center justify-center text-2xl mx-auto shadow-xs">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="text-center space-y-1.5">
            <h3 class="text-base font-black text-slate-900">Konfirmasi Pendaftaran</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Apakah Anda yakin data pendaftaran akun sudah benar? Akun Anda akan didaftarkan dan dapat langsung digunakan.
            </p>
        </div>
        <div class="flex gap-2.5 pt-2">
            <button type="button" onclick="closeRegisterConfirmModal()" class="flex-1 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all">
                Periksa Kembali
            </button>
            <button type="button" onclick="submitRegisterForm()" class="flex-1 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all">
                Ya, Selesaikan
            </button>
        </div>
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

    let isFormConfirmed = false;

    function setFieldError(fieldId, errId, message) {
        const input = document.getElementById(fieldId);
        const err = document.getElementById(errId);

        if (message) {
            input.classList.add('border-rose-400', 'bg-rose-50/40', 'focus:ring-rose-200', 'focus:border-rose-500');
            input.classList.remove('border-slate-200/80', 'bg-slate-50/80');
            err.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> ${message}`;
            err.classList.remove('hidden');
            err.classList.add('flex');
            return true;
        } else {
            input.classList.remove('border-rose-400', 'bg-rose-50/40', 'focus:ring-rose-200', 'focus:border-rose-500');
            input.classList.add('border-slate-200/80', 'bg-slate-50/80');
            err.classList.add('hidden');
            err.classList.remove('flex');
            return false;
        }
    }

    function showRegisterConfirmation(e) {
        if (isFormConfirmed) return true;
        
        e.preventDefault();

        const nama = document.getElementById('input_nama').value.trim();
        const noHp = document.getElementById('input_no_hp').value.trim();
        const email = document.getElementById('input_email').value.trim();
        const gender = document.getElementById('input_gender').value.trim();
        const usia = document.getElementById('input_usia').value.trim();
        const level = document.getElementById('input_level').value.trim();
        const password = document.getElementById('registerPassword').value.trim();

        let hasError = false;

        if (setFieldError('input_nama', 'err_nama', !nama ? 'Nama lengkap wajib diisi.' : null)) hasError = true;
        if (setFieldError('input_no_hp', 'err_no_hp', !noHp ? 'Nomor WhatsApp / HP wajib diisi.' : null)) hasError = true;
        if (setFieldError('input_email', 'err_email', !email ? 'Alamat email wajib diisi.' : null)) hasError = true;
        if (setFieldError('input_gender', 'err_gender', !gender ? 'Jenis kelamin wajib dipilih.' : null)) hasError = true;
        if (setFieldError('input_usia', 'err_usia', !usia ? 'Usia wajib diisi.' : null)) hasError = true;
        if (setFieldError('input_level', 'err_level', !level ? 'Kategori skill level wajib dipilih.' : null)) hasError = true;
        
        if (!password) {
            if (setFieldError('registerPassword', 'err_password', 'Password wajib diisi.')) hasError = true;
        } else if (password.length < 6) {
            if (setFieldError('registerPassword', 'err_password', 'Password minimal 6 karakter.')) hasError = true;
        } else {
            setFieldError('registerPassword', 'err_password', null);
        }

        if (hasError) {
            const firstErrorField = document.querySelector('.border-rose-400');
            if (firstErrorField) {
                firstErrorField.focus();
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        document.getElementById('registerConfirmModal').classList.remove('hidden');
    }

    function closeRegisterConfirmModal() {
        document.getElementById('registerConfirmModal').classList.add('hidden');
    }

    function submitRegisterForm() {
        isFormConfirmed = true;
        document.getElementById('registerForm').submit();
    }
</script>
@endpush
@endsection
