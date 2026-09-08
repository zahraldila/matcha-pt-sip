@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold text-slate-900">
            Profil Member Pemain
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Data profil untuk autofill otomatis saat bergabung ke sesi mabar</p>
    </div>

    <div class="clean-card rounded-xl p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row items-center gap-5 pb-5 border-b border-slate-100">
            <img src="{{ $recap['player']['avatar'] }}" alt="{{ $recap['player']['name'] }}" class="w-20 h-20 rounded-full object-cover ring-2 ring-emerald-600">
            <div class="text-center sm:text-left space-y-1">
                <div class="flex items-center justify-center sm:justify-start gap-2">
                    <h2 class="text-lg font-bold text-slate-900">{{ $recap['player']['name'] }}</h2>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 text-[10px] font-semibold border border-emerald-200">Verified Host</span>
                </div>
                <p class="text-xs text-slate-500">{{ $recap['player']['username'] }} &bull; Member Terdaftar</p>
                <div class="flex flex-wrap gap-1.5 pt-1 justify-center sm:justify-start">
                    <x-badge type="intermediate">Level: {{ $recap['player']['level'] }}</x-badge>
                    <x-badge type="padel">Komunitas: {{ $recap['player']['community'] }}</x-badge>
                </div>
            </div>
        </div>

        <form onsubmit="handleSaveProfile(event)" class="space-y-3.5 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                    <input type="text" value="Billy Santoso" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Username / ID</label>
                    <input type="text" value="billy_matcha" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nomor WhatsApp</label>
                    <input type="text" value="0812-9988-7766" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Akun Instagram</label>
                    <input type="text" value="@billy.santoso" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Gender</label>
                    <select class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                        <option value="Male" selected>Laki-laki</option>
                        <option value="Female">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Kategori Skill Level</label>
                    <select class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                        <option value="Newbie">Newbie (Baru belajar)</option>
                        <option value="Beginner">Beginner (Bisa rally)</option>
                        <option value="Intermediate" selected>Intermediate (Bisa match play)</option>
                        <option value="Advanced">Advanced (Kompetitif)</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-slate-100">
                <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-colors">
                    Simpan Perubahan Profil
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function handleSaveProfile(e) {
        e.preventDefault();
        showToast('Profil berhasil diperbarui!');
    }
</script>
@endpush
@endsection
