@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="space-y-6">
        
        <!-- Header -->
        <div class="text-center space-y-1.5">
            <h1 class="text-2xl font-bold text-slate-900">
                Pendaftaran Member Matcha
            </h1>
            <p class="text-xs text-slate-500">
                Lengkapi profil olahraga Anda untuk sinkronisasi otomatis saat mabar & pencatatan rekap skor
            </p>
        </div>

        <!-- Registration Card -->
        <div class="clean-card rounded-2xl p-6 sm:p-8 space-y-6 shadow-sm">
            <form onsubmit="handleRegisterSubmit(event)" class="space-y-5 text-xs">
                
                <!-- Group 1: Identitas Pribadi -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider pb-1 border-b border-slate-100">
                        1. Data Identitas Pemain
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap (Sesuai Identitas)</label>
                            <input type="text" placeholder="Contoh: Billy Santoso" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                            <span class="text-[10px] text-slate-400 mt-0.5 block">Nama ini akan digunakan untuk konsistensi rekod pertandingan & drawing tim.</span>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nomor WhatsApp / HP</label>
                            <input type="text" placeholder="0812-xxxx-xxxx" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Akun Instagram (Opsional)</label>
                            <input type="text" placeholder="@username" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Jenis Kelamin</label>
                            <select class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                                <option value="Male">Laki-laki</option>
                                <option value="Female">Perempuan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Usia (Tahun)</label>
                            <input type="number" placeholder="Contoh: 28" min="10" max="80" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                        </div>
                    </div>
                </div>

                <!-- Group 2: Skill Level & Komunitas -->
                <div class="space-y-3 pt-2">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider pb-1 border-b border-slate-100">
                        2. Level Permainan & Pilihan Komunitas
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kategori Skill Level</label>
                            <select class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                                <option value="Newbie">Newbie (Baru pertama/belajar)</option>
                                <option value="Beginner">Beginner (Bisa rally dasar)</option>
                                <option value="Intermediate" selected>Intermediate (Konsisten match play)</option>
                                <option value="Advanced">Advanced (Turnamen & Kompetitif)</option>
                            </select>
                            <span class="text-[10px] text-slate-400 mt-0.5 block">Level digunakan algoritma drawing agar pembagian tim seimbang.</span>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Pilihan Komunitas</label>
                            <select class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                                <option value="none">Personal (Non-Community / Belum Ada)</option>
                                @foreach($communities as $comm)
                                    <option value="{{ $comm['id'] }}">{{ $comm['name'] }} ({{ $comm['sport'] }})</option>
                                @endforeach
                            </select>
                            <span class="text-[10px] text-slate-400 mt-0.5 block">Bisa diubah atau mendaftarkan komunitas baru nanti.</span>
                        </div>
                    </div>
                </div>

                <!-- Group 3: Akun Login & Kredensial -->
                <div class="space-y-3 pt-2">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider pb-1 border-b border-slate-100">
                        3. Akun Login
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Alamat Email</label>
                            <input type="email" placeholder="nama@email.com" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Password</label>
                            <input type="password" placeholder="Minimal 8 karakter" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                        </div>
                    </div>

                    <!-- Role Option -->
                    <div class="pt-2">
                        <label class="flex items-start gap-2.5 p-3 rounded-lg bg-slate-50 border border-slate-200 cursor-pointer">
                            <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <span class="font-semibold text-slate-800">Daftar juga sebagai Pemilik Tempat (PIC Venue)</span>
                                <p class="text-[10px] text-slate-500">Centang opsi ini jika Anda memiliki fasilitas lapangan untuk didaftarkan ke direktori Matcha.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100">
                    <button type="submit" class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-colors">
                        Selesaikan Registrasi Member
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-xs text-slate-500">
            Sudah memiliki akun? 
            <a href="{{ route('login') }}" class="font-bold text-emerald-700 hover:underline">Masuk di sini</a>
        </p>
    </div>
</div>

@push('scripts')
<script>
    function handleRegisterSubmit(e) {
        e.preventDefault();
        showToast('Registrasi berhasil! Profil member Anda telah aktif.');
        setTimeout(() => {
            window.location.href = "{{ route('dashboard') }}";
        }, 1200);
    }
</script>
@endpush
@endsection
