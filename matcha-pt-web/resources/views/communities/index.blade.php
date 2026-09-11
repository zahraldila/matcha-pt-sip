@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#A8E63A]/20 border border-[#063B00]/25 text-[#050608] text-xs font-semibold shadow-xs mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-[#063B00]"></span>
                Klub & Ekosistem Olahraga
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#050608] tracking-tight">
                Komunitas Tennis & Padel
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Gabung bersama komunitas pecinta olahraga raket, perluas koneksi tanding, atau bangun komunitasmu sendiri.</p>
        </div>

        <a href="{{ route('communities.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01] shrink-0">
            <i class="fa-solid fa-plus text-[#A8E63A] text-xs"></i> <span>Buat Komunitas Baru</span>
        </a>
    </div>

    <!-- 1. Top Controls Bar: Community Tabs & Search Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Community Scope Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-semibold py-1">
            <a href="{{ route('communities.index', ['tab' => 'all', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}"
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? 'all') !== 'joined' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-users text-xs {{ ($activeTab ?? 'all') !== 'joined' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i> 
                <span>Semua Komunitas</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? 'all') !== 'joined' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $totalCommunitiesCount ?? $communities->total() }}
                </span>
            </a>

            @auth
                <a href="{{ route('communities.index', ['tab' => 'joined', 'sport' => $selectedSport ?? 'all', 'q' => $search ?? '']) }}"
                   class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? 'all') === 'joined' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                    <i class="fa-solid fa-circle-check text-xs {{ ($activeTab ?? 'all') === 'joined' ? 'text-[#A8E63A]' : 'text-emerald-500' }}"></i> 
                    <span>Komunitas Saya</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? 'all') === 'joined' ? 'bg-[#A8E63A] text-[#063B00]' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                        {{ $myCommunitiesCount ?? 0 }}
                    </span>
                </a>
            @endauth
        </div>

        <!-- Search Bar -->
        <form method="GET" action="{{ route('communities.index') }}" class="relative w-full md:w-80 shrink-0">
            <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
            <input type="hidden" name="sport" value="{{ $selectedSport ?? 'all' }}">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari nama klub, kota, admin..." 
                       class="w-full pl-9 pr-8 py-2 text-xs rounded-xl bg-white border border-slate-200/90 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] shadow-2xs transition-all">
                @if(!empty($search))
                    <a href="{{ route('communities.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all']) }}" 
                       class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                       title="Hapus pencarian">
                        <i class="fa-solid fa-circle-xmark text-xs"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- 2. Sport Category Sub-Filters & Result Counter -->
    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 border border-white/90 shadow-2xs">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-0.5">
            <a href="{{ route('communities.index', ['tab' => $activeTab ?? 'all', 'sport' => 'all', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                Semua Cabang
            </a>
            <a href="{{ route('communities.index', ['tab' => $activeTab ?? 'all', 'sport' => 'tennis', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🎾 Tennis
            </a>
            <a href="{{ route('communities.index', ['tab' => $activeTab ?? 'all', 'sport' => 'padel', 'q' => $search ?? '']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'padel' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🏓 Padel
            </a>
        </div>

        <div class="text-xs text-slate-500 flex items-center gap-2">
            @if(!empty($search))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold">
                    <i class="fa-solid fa-magnifying-glass text-[9px]"></i> "{{ $search }}"
                </span>
            @endif
            <span>
                Menampilkan <strong class="text-[#050608]">{{ $communities->total() }}</strong> komunitas
                @if(($activeTab ?? 'all') === 'joined')
                    <span>yang kamu ikuti</span>
                @endif
            </span>
        </div>
    </div>

    <!-- 3. Grid of Communities or Empty States -->
    @if($communities->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($communities as $comm)
                <div class="glass-card rounded-3xl overflow-hidden flex flex-col justify-between group border border-white/90 transition-all duration-200 hover:shadow-md">
                    <div>
                        <div class="relative h-44 overflow-hidden bg-slate-100">
                            <img src="{{ $comm['image'] }}" alt="{{ $comm['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            <div class="absolute top-3 left-3">
                                <x-badge :type="strtolower($comm['sport']) === 'tennis' ? 'tennis' : (in_array(strtolower($comm['sport']), ['all racquet', 'padel & tennis', 'both', 'all_racquet']) ? 'padel & tennis' : 'padel')">
                                    {{ $comm['sport'] }}
                                </x-badge>
                            </div>
                            @if(!empty($comm['is_member']))
                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-700/95 text-white font-bold text-[10px] shadow-sm backdrop-blur-md flex items-center gap-1.5 border border-emerald-400/40">
                                        <i class="fa-solid fa-circle-check text-[9px] text-[#A8E63A]"></i> Anggota
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="p-5 space-y-3 text-xs">
                            <div>
                                <h3 class="text-base font-bold text-[#050608] leading-tight">{{ $comm['name'] }}</h3>
                                <p class="text-xs text-slate-500 font-medium mt-0.5 flex items-center gap-1.5">
                                    <span><i class="fa-solid fa-users text-slate-400 text-[10px]"></i> {{ $comm['members_count'] }} Anggota</span>
                                    <span class="text-slate-300">•</span>
                                    <span><i class="fa-solid fa-location-dot text-slate-400 text-[10px]"></i> {{ $comm['city'] }}</span>
                                </p>
                            </div>

                            <p class="text-slate-600 line-clamp-2 leading-relaxed">{{ $comm['description'] }}</p>
                            
                            <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-slate-500">
                                <span>Admin: <strong class="text-[#050608]">{{ $comm['admin_name'] }}</strong></span>
                                <span class="text-[#063B00] font-bold">{{ $comm['status'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 pt-0">
                        @if(!empty($comm['is_member']))
                            <div class="w-full py-2.5 rounded-xl bg-green-50 border border-green-200 text-green-700 font-semibold text-xs flex items-center justify-center gap-1.5 shadow-2xs">
                                <i class="fa-solid fa-circle-check text-green-600 text-[11px]"></i>
                                <span>Anggota Komunitas</span>
                            </div>
                        @else
                            <a href="{{ route('communities.show', $comm['id']) }}" class="block text-center w-full py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs transition-all shadow-xs hover:scale-[1.01]">
                                Gabung Komunitas
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination Component -->
        <x-pagination :paginator="$communities" />
    @else
        <!-- Rich Clean Empty State -->
        <div class="glass-card rounded-3xl p-8 sm:p-12 text-center max-w-xl mx-auto border border-white/90 space-y-4 shadow-sm">
            <div class="w-16 h-16 rounded-3xl bg-[#EBF8D8] border border-[#063B00]/20 flex items-center justify-center text-[#063B00] text-2xl mx-auto shadow-xs">
                @if(!empty($search))
                    <i class="fa-solid fa-magnifying-glass text-slate-600"></i>
                @elseif(($activeTab ?? 'all') === 'joined')
                    <i class="fa-solid fa-users-slash text-emerald-700"></i>
                @else
                    <i class="fa-solid fa-users text-slate-600"></i>
                @endif
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base sm:text-lg font-bold text-slate-900">
                    @if(!empty($search))
                        Tidak Ditemukan Hasil Pencarian
                    @elseif(($activeTab ?? 'all') === 'joined')
                        Belum Bergabung dengan Komunitas
                    @else
                        Tidak Ada Komunitas Ditemukan
                    @endif
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                    @if(!empty($search))
                        Tidak ada komunitas yang cocok dengan kata kunci "<strong>{{ $search }}</strong>". Coba gunakan kata kunci lain atau reset pencarian.
                    @elseif(($activeTab ?? 'all') === 'joined')
                        Kamu belum bergabung ke dalam klub komunitas manapun. Temukan komunitas di sekitarmu dan gabung sekarang!
                    @else
                        Belum ada data komunitas olahraga yang tersedia untuk filter yang dipilih.
                    @endif
                </p>
            </div>

            <div class="pt-2">
                @if(!empty($search))
                    <a href="{{ route('communities.index', ['tab' => $activeTab ?? 'all', 'sport' => $selectedSport ?? 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-[#A8E63A]"></i> <span>Reset Pencarian</span>
                    </a>
                @elseif(($activeTab ?? 'all') === 'joined')
                    <a href="{{ route('communities.index', ['tab' => 'all']) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-earth-americas text-[#A8E63A]"></i> <span>Jelajahi Semua Komunitas</span>
                    </a>
                @else
                    <a href="{{ route('communities.index', ['tab' => 'all', 'sport' => 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-slate-400"></i> <span>Reset Filter</span>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
