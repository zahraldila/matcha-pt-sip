@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#EBF8D8]/30 px-4 py-10 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('venues.index') }}" class="mb-6 inline-flex items-center gap-2 text-sm font-bold text-[#063B00] hover:underline">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke daftar venue
        </a>

        <div class="rounded-3xl border border-white/80 bg-white/80 p-6 shadow-[0_20px_50px_rgba(6,59,0,0.10)] backdrop-blur-2xl sm:p-10">
            <div class="mb-8">
                <span class="rounded-full border border-[#063B00]/20 bg-[#EBF8D8] px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-[#063B00]">
                    Venue Owner Portal
                </span>
                <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Tambah Court Baru</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">
                    Tambahkan detail lapangan untuk venue <strong class="text-[#063B00]">{{ $venue->nama_venue }}</strong>.
                </p>
            </div>

            @if(session('success'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <p class="font-bold">Periksa kembali data court:</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('venues.courts.store', $venue->venue_id) }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label for="nama_court" class="mb-2 block text-sm font-bold text-slate-800">Nama Court</label>
                    <input id="nama_court" name="nama_court" type="text" value="{{ old('nama_court') }}" required maxlength="100" placeholder="Contoh: Court 1"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-semibold text-slate-900 outline-none transition focus:border-[#063B00] focus:bg-white focus:ring-2 focus:ring-[#A8E63A]/30">
                </div>

                <div>
                    <label for="tipe_court" class="mb-2 block text-sm font-bold text-slate-800">Tipe Court</label>
                    <select id="tipe_court" name="tipe_court" required
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-semibold text-slate-900 outline-none transition focus:border-[#063B00] focus:bg-white focus:ring-2 focus:ring-[#A8E63A]/30">
                        <option value="">Pilih tipe court</option>
                        @foreach(['Indoor', 'Outdoor', 'Semi-Indoor'] as $type)
                            <option value="{{ $type }}" @selected(old('tipe_court') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="harga_per_jam" class="mb-2 block text-sm font-bold text-slate-800">Harga per Jam</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">Rp</span>
                        <input id="harga_per_jam" name="harga_per_jam" type="number" min="0" step="1000" value="{{ old('harga_per_jam') }}" required placeholder="150000"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 py-3 pl-11 pr-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-[#063B00] focus:bg-white focus:ring-2 focus:ring-[#A8E63A]/30">
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('venues.index') }}" class="rounded-2xl bg-slate-100 px-5 py-3 text-center text-sm font-bold text-slate-700 transition hover:bg-slate-200">Batal</a>
                    <button type="submit" class="rounded-2xl bg-[#063B00] px-6 py-3 text-sm font-black text-white shadow-md transition hover:bg-[#042a00]">
                        <i class="fa-solid fa-plus mr-2 text-[#A8E63A]"></i>
                        Simpan Court
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
