@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <a href="{{ route('communities.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Komunitas
        </a>
        <h1 class="text-2xl font-bold text-slate-900">
            Pendaftaran Komunitas Baru
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">User yang mendaftarkan komunitas secara otomatis menjadi Admin Komunitas</p>
    </div>

    <div class="clean-card rounded-xl p-6 sm:p-8 space-y-6">
        <form onsubmit="handleCreateCommunity(event)" class="space-y-4 text-xs">
            <div class="space-y-3.5">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama Komunitas</label>
                    <input type="text" placeholder="Contoh: JTK Padel Club Bandung" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Cabang Olahraga Utama</label>
                    <select class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                        <option value="Tennis">Tennis</option>
                        <option value="Padel" selected>Padel</option>
                        <option value="Both">Tennis & Padel</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Deskripsi & Jadwal Rutin</label>
                    <textarea rows="3" placeholder="Informasi seputar homebase lapangan, jadwal mabar rutin..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required></textarea>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Admin Komunitas (Pendaftar)</label>
                    <input type="text" value="Billy Santoso (Otomatis dari Akun Login)" class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-500 cursor-not-allowed" readonly>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-slate-100">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
                    Daftarkan Komunitas
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function handleCreateCommunity(e) {
        e.preventDefault();
        showToast('Komunitas berhasil dibuat!');
        setTimeout(() => {
            window.location.href = "{{ route('communities.index') }}";
        }, 1200);
    }
</script>
@endpush
@endsection
