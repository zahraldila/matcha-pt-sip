@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <a href="{{ route('venues.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Semua Venue
        </a>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#050608]">
                    {{ $venue['name'] }}
                </h1>
                <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1.5">
                    <i class="fa-solid fa-location-dot text-slate-400"></i> {{ $venue['address'] }}
                </p>
            </div>

            <a href="{{ route('games.create') }}" class="px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] inline-flex items-center gap-1.5">
                <i class="fa-solid fa-plus text-[10px]"></i> Buat Mabar di Sini
            </a>
        </div>
    </div>

    <!-- Venue Hero Image & Facilities -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="relative h-64 sm:h-80 rounded-2xl overflow-hidden border border-slate-200 shadow-sm">
                <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="w-full h-full object-cover">
                <div class="absolute top-4 left-4">
                    <x-badge :type="strtolower($venue['sport']) === 'tennis' ? 'tennis' : 'padel'">
                        {{ $venue['sport'] }}
                    </x-badge>
                </div>
            </div>

            <!-- Court List & Status -->
            <div class="clean-card rounded-xl p-5 sm:p-6 space-y-4">
                <h3 class="text-sm font-bold text-slate-900">
                    Daftar Lapangan / Court
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($venue['courts'] as $court)
                        <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-xs text-slate-900">{{ $court['name'] }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded font-semibold {{ $court['status'] === 'Available' ? 'bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                    {{ $court['status'] }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 flex items-center justify-between">
                                <span>Tipe: <strong class="text-slate-700">{{ $court['type'] }}</strong></span>
                                <span class="text-[#063B00] text-[11px] font-semibold">Siap Pakai</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Sidebar: Hours & Availability Logic -->
        <div class="space-y-6">
            <!-- Operating Hours & Unavailability Rules -->
            <div class="clean-card rounded-xl p-5 space-y-4">
                <h3 class="text-sm font-bold text-slate-900">
                    Jam Operasi & Availability
                </h3>

                <div class="space-y-2.5 text-xs">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Jam Operasional Reguler:</span>
                        <strong class="text-slate-900 text-sm block">{{ $venue['operating_hours'] }} WIB</strong>
                    </div>

                    <div class="bg-amber-50 p-3 rounded-lg border border-amber-200 text-amber-900 space-y-1">
                        <span class="font-semibold block flex items-center gap-1.5 text-xs">
                            <i class="fa-solid fa-circle-exclamation text-amber-600"></i> Ketentuan Availability:
                        </span>
                        <p class="text-[11px] leading-relaxed text-amber-800">{{ $venue['unavailability_note'] }}</p>
                    </div>
                </div>

                <!-- PIC / Contact Info -->
                <div class="pt-3 border-t border-slate-100 space-y-1 text-xs">
                    <span class="text-slate-500 block">PIC / Pengelola:</span>
                    <p class="text-slate-900 font-semibold">{{ $venue['pic_name'] }}</p>
                    <p class="text-[#063B00] font-bold">{{ $venue['pic_phone'] }}</p>
                </div>
            </div>

            <!-- Facilities Structured Card -->
            <div class="clean-card rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-bold text-slate-900">
                    Fasilitas Venue
                </h3>

                <ul class="space-y-1.5 text-xs text-slate-600">
                    @foreach($venue['facilities'] as $facility)
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-[#063B00] text-xs"></i>
                            <span>{{ $facility }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
