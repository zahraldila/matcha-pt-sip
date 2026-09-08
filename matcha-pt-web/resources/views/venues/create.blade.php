@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <a href="{{ route('venues.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Venue
        </a>
        <h1 class="text-2xl font-bold text-slate-900">
            Form Pendaftaran Venue Baru
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Daftarkan lapangan olahraga untuk ditampilkan pada direktori Matcha</p>
    </div>

    <div class="clean-card rounded-xl p-6 sm:p-8 space-y-6">
        <form onsubmit="handleCreateVenue(event)" class="space-y-4 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">Nama Tempat / Venue</label>
                    <input type="text" placeholder="Contoh: Gelora Sports Center" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">Alamat Lengkap</label>
                    <input type="text" placeholder="Jl. Raya Utama No. ..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Cabang Olahraga</label>
                    <select class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                        <option value="Tennis">Tennis</option>
                        <option value="Padel">Padel</option>
                        <option value="Both">Tennis & Padel</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Jam Operasional Reguler</label>
                    <input type="text" value="06:00 - 22:00" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama PIC Venue</label>
                    <input type="text" placeholder="Nama pengelola" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nomor WhatsApp PIC</label>
                    <input type="text" placeholder="0812-xxxx-xxxx" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">Ketentuan Jam Tutup / Availability Khusus</label>
                    <input type="text" placeholder="Contoh: Court outdoor tutup jam 10.00-12.00 karena panas terik" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none">
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-slate-100">
                <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-colors">
                    Daftarkan Venue
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function handleCreateVenue(e) {
        e.preventDefault();
        showToast('Venue berhasil didaftarkan!');
        setTimeout(() => {
            window.location.href = "{{ route('venues.index') }}";
        }, 1200);
    }
</script>
@endpush
@endsection
