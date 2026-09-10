@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 relative">

    <!-- Ambient Glowing Background Orbs -->
    <div class="absolute w-96 h-96 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-12 -left-12 -z-10"></div>
    <div class="absolute w-96 h-96 bg-[#063B00]/10 rounded-full blur-3xl pointer-events-none top-1/2 -right-12 -z-10"></div>
    <div class="absolute w-80 h-80 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none -bottom-10 left-1/3 -z-10"></div>

    <!-- Header Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/60 relative z-10">
        <div>
            <a href="{{ route('communities.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-[#063B00] transition-colors mb-3 group">
                <span class="w-7 h-7 rounded-xl bg-white/80 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-[#063B00] group-hover:text-white transition-all shadow-2xs">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                </span>
                Kembali ke Daftar Komunitas
            </a>
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-extrabold uppercase tracking-wider">
                    {{ $community->sport_utama }}
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-medium text-slate-500">Komunitas Detail</span>
            </div>
            <div class="flex items-center gap-3.5 mt-2">
                @if(!empty($community->logo))
                    <img src="{{ $community->logo }}" alt="{{ $community->nama_community }}" class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl object-cover border border-slate-200/80 shadow-sm">
                @endif
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        {{ $community->nama_community }}
                    </h1>
                </div>
            </div>
        </div>

        <div class="hidden sm:flex flex-col items-end gap-2">
            <div class="px-4 py-2 rounded-2xl bg-white/60 backdrop-blur-md border border-white/80 shadow-2xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-users text-[#A8E63A]"></i>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Anggota Aktif</p>
                    <p class="text-xs font-black text-[#063B00]">{{ $community->players->count() }} Member</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content Column -->
        <div class="lg:col-span-2 space-y-6 relative z-10">

            <!-- Community Details Card -->
            <div class="backdrop-blur-2xl bg-white/80 border border-white/90 rounded-3xl p-6 sm:p-8 shadow-[0_20px_50px_rgba(6,59,0,0.06)] space-y-6">
                
                <!-- Description Section -->
                <div class="space-y-3">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs">
                            <i class="fa-solid fa-align-left"></i>
                        </span>
                        Tentang Komunitas
                    </h3>
                    <p class="text-sm leading-relaxed text-slate-700">{{ $community->deskripsi }}</p>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                    <!-- Sport Type -->
                    @if($community->sport_utama)
                    <div class="space-y-1.5">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cabang Olahraga Utama</p>
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#EBF8D8] text-[#063B00] flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-{{ $community->sport_utama === 'Padel' ? 'table-tennis-paddle-ball' : 'baseball' }}"></i>
                            </div>
                            <span class="font-semibold text-slate-900">{{ $community->sport_utama }}</span>
                        </div>
                    </div>
                    @endif

                    <!-- Schedule -->
                    @if($community->jadwal_rutin)
                    <div class="space-y-1.5">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Jadwal Rutin Mabar</p>
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#EBF8D8] text-[#063B00] flex items-center justify-center text-sm font-bold">
                                <i class="fa-regular fa-calendar-days"></i>
                            </div>
                            <span class="font-semibold text-slate-900">{{ $community->jadwal_rutin }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Members Section -->
            <div class="backdrop-blur-2xl bg-white/80 border border-white/90 rounded-3xl p-6 sm:p-8 shadow-[0_20px_50px_rgba(6,59,0,0.06)] space-y-6">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs">
                        <i class="fa-solid fa-users"></i>
                    </span>
                    Daftar Anggota Komunitas
                </h3>

                @if($community->players && $community->players->count() > 0)
                    <div class="space-y-3">
                        @foreach($community->players as $player)
                            <div class="p-4 rounded-2xl bg-slate-50/60 border border-slate-200/60 hover:bg-slate-50/80 transition-all flex items-center justify-between group">
                                <div class="flex items-center gap-3.5 flex-1">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                        {{ substr($player->nama ?? 'Member', 0, 1) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-900">{{ $player->nama ?? 'Member' }}</p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            @if($player->level)
                                            <span class="px-2 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] text-[10px] font-bold">
                                                Level: {{ $player->level }}
                                            </span>
                                            @endif
                                            @if($player->rating)
                                            <span class="px-2 py-0.5 rounded-md bg-yellow-100 text-yellow-800 text-[10px] font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-star text-[8px]"></i> {{ $player->rating }}
                                            </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 rounded-2xl bg-slate-50/60 border border-dashed border-slate-200 text-center">
                        <i class="fa-solid fa-users-slash text-[#063B00]/30 text-3xl mb-2"></i>
                        <p class="text-sm text-slate-600">Belum ada anggota bergabung di komunitas ini.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 relative z-10">
            <!-- Join/Leave Card -->
            @php
                $userPlayer = Auth::check() ? \App\Models\Player::where('user_id', Auth::id())->first() : null;
                $isMember = $userPlayer && $userPlayer->community_id == $community->community_id;
            @endphp

            <div class="backdrop-blur-2xl bg-white/80 border border-white/90 rounded-3xl p-6 sm:p-8 shadow-[0_20px_50px_rgba(6,59,0,0.06)] space-y-4 sticky top-20">
                
                @if(Auth::check())
                    @if($isMember)
                        <!-- Leave Button -->
                        <div class="space-y-3">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status Keanggotaan</p>
                            <div class="px-3 py-2 rounded-2xl bg-green-100 border border-green-300 flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-green-600 text-xs"></i>
                                <span class="text-xs font-bold text-green-700">Anda adalah Anggota</span>
                            </div>

                            <form action="{{ route('communities.leave', $community->community_id) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full px-4 py-3 rounded-2xl bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs transition-all border border-red-200 flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-sign-out-alt"></i>
                                    Keluar dari Komunitas
                                </button>
                            </form>
                        </div>
                    @else
                        <!-- Join Button -->
                        <div class="space-y-3">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Siap Bergabung?</p>
                            <p class="text-xs text-slate-600 leading-relaxed">Bergabunglah dengan komunitas ini untuk mengikuti sesi mabar dan turnamen!</p>

                            <form action="{{ route('communities.join', $community->community_id) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full px-4 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                                    <i class="fa-solid fa-user-plus text-[#A8E63A]"></i>
                                    <span>Bergabung Sekarang</span>
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    <!-- Login Required -->
                    <div class="space-y-3">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Akses Komunitas</p>
                        <p class="text-xs text-slate-600 leading-relaxed">Silakan login terlebih dahulu untuk bergabung dengan komunitas ini.</p>

                        <a href="{{ route('login') }}" class="w-full px-4 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95 inline-flex items-center justify-center gap-2">
                            <i class="fa-solid fa-sign-in-alt text-[#A8E63A]"></i>
                            <span>Login Terlebih Dahulu</span>
                        </a>
                    </div>
                @endif

                <!-- Info Box -->
                <div class="pt-4 border-t border-slate-100 space-y-2">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-info-circle text-[#063B00] text-xs mt-0.5"></i>
                        <p class="text-[11px] text-slate-600 leading-relaxed">Sebagai anggota, Anda dapat mengikuti sesi mabar, turnamen, dan melihat leaderboard komunitas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
