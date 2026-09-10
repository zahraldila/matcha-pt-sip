@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-[#050608]">
                Komunitas Tennis & Padel
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Gabung bersama komunitas pecinta olahraga raket atau daftarkan komunitas baru</p>
        </div>

        <a href="{{ route('communities.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
            <i class="fa-solid fa-plus text-[10px]"></i> Buat Komunitas Baru
        </a>
    </div>

    <!-- Grid of Communities -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($communities as $comm)
            <div class="glass-card rounded-3xl overflow-hidden flex flex-col justify-between group border border-white/90">
                <div>
                    <div class="relative h-44 overflow-hidden">
                        <img src="{{ $comm['image'] }}" alt="{{ $comm['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute top-3 left-3">
                            <x-badge :type="strtolower($comm['sport']) === 'tennis' ? 'tennis' : (in_array(strtolower($comm['sport']), ['all racquet', 'padel & tennis', 'both', 'all_racquet']) ? 'padel & tennis' : 'padel')">
                                {{ $comm['sport'] }}
                            </x-badge>
                        </div>
                    </div>

                    <div class="p-5 space-y-3 text-xs">
                        <div>
                            <h3 class="text-base font-bold text-[#050608] leading-tight">{{ $comm['name'] }}</h3>
                            <p class="text-xs text-slate-500 font-medium mt-0.5">{{ $comm['members_count'] }} Anggota Aktif</p>
                        </div>

                        <p class="text-slate-600 line-clamp-2 leading-relaxed">{{ $comm['description'] }}</p>
                        
                        <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-slate-500">
                            <span>Admin: <strong class="text-[#050608]">{{ $comm['admin_name'] }}</strong></span>
                            <span class="text-[#063B00] font-bold">{{ $comm['status'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-5 pt-0">
                    <button onclick="showToast('Permintaan bergabung ke komunitas telah dikirim!')" class="w-full py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs transition-all shadow-xs hover:scale-[1.01]">
                        Gabung Komunitas
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
