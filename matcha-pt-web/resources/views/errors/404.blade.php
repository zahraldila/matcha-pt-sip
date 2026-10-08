@extends('layouts.app')

@section('title', 'Halaman Tidak Ditemukan — Matcha')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-xl text-center space-y-8 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Glowing Mascot / Icon Area (Rock-solid dimensions) -->
        <div class="relative mx-auto flex items-center justify-center" style="width: 112px; height: 112px; min-width: 112px; min-height: 112px;">
            <!-- Ambient Glow -->
            <div class="absolute inset-0 bg-[#A8E63A]/25 rounded-full blur-2xl animate-pulse" style="width: 100%; height: 100%;"></div>
            
            <!-- Outer Icon Box -->
            <div class="relative rounded-3xl bg-white border border-[#063B00]/10 shadow-xl flex items-center justify-center transform -rotate-3 hover:rotate-0 transition-transform duration-300 shrink-0" style="width: 100px; height: 100px; min-width: 100px; min-height: 100px;">
                <!-- Inner Icon Gradient -->
                <div class="rounded-2xl bg-gradient-to-br from-[#EBF8D8] to-[#d4f2a7] flex items-center justify-center text-[#063B00] shadow-inner shrink-0" style="width: 76px; height: 76px; min-width: 76px; min-height: 76px;">
                    <i class="fa-solid fa-table-tennis-paddle-ball text-3xl transform -rotate-12"></i>
                </div>
            </div>

            <!-- Exclamation Badge -->
            <div class="absolute flex items-center justify-center" style="top: 0px; right: 0px; width: 26px; height: 26px; z-index: 10;">
                <span class="animate-ping absolute inline-flex rounded-full bg-rose-400 opacity-75" style="width: 100%; height: 100%;"></span>
                <span class="relative inline-flex rounded-full bg-rose-500 text-white font-black items-center justify-center shadow-md" style="width: 24px; height: 24px; font-size: 12px; line-height: 1;">!</span>
            </div>
        </div>

        <!-- Text Content -->
        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#EBF8D8] border border-[#063B00]/15 text-[#063B00] text-xs font-black tracking-wider uppercase">
                <span class="w-2 h-2 rounded-full bg-[#063B00] shrink-0" style="width: 8px; height: 8px;"></span>
                404 • Not Found
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Oops! Jadwal atau Halaman Tidak Ditemukan
            </h1>

            <p class="text-sm text-slate-600 max-w-md mx-auto leading-relaxed">
                {{ !empty($exception) && $exception->getMessage() ? $exception->getMessage() : 'Link sesi mabar yang kamu tuju mungkin salah ketik, sudah kedaluwarsa, atau sesi telah dibatalkan oleh penyelenggara.' }}
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
            <a 
                href="{{ route('games.index') }}" 
                class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-[#063B00] text-[#A8E63A] font-extrabold text-xs sm:text-sm hover:bg-[#063B00]/90 transition-all shadow-md flex items-center justify-center gap-2 group cursor-pointer"
            >
                <i class="fa-solid fa-calendar-days text-[#A8E63A] group-hover:scale-110 transition-transform"></i>
                <span>Jelajahi Jadwal Mabar</span>
            </a>

            <a 
                href="{{ route('dashboard') }}" 
                class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-white text-slate-700 hover:text-slate-900 border border-slate-200/80 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-all shadow-2xs flex items-center justify-center gap-2 cursor-pointer"
            >
                <i class="fa-solid fa-house text-slate-400"></i>
                <span>Kembali ke Beranda</span>
            </a>
        </div>

        <!-- Quick Helpful Links -->
        <div class="pt-6 border-t border-slate-200/60 max-w-md mx-auto">
            <p class="text-xs text-slate-400 font-medium mb-3">Atau coba akses menu berikut:</p>
            <div class="flex flex-wrap items-center justify-center gap-2 text-xs font-semibold text-slate-600">
                <a href="{{ route('venues.index') }}" class="px-3 py-1.5 rounded-xl bg-slate-100/80 hover:bg-[#EBF8D8] hover:text-[#063B00] transition-colors">
                    <i class="fa-solid fa-location-dot text-slate-400 mr-1"></i> Cari Venue
                </a>
                <a href="{{ route('communities.index') }}" class="px-3 py-1.5 rounded-xl bg-slate-100/80 hover:bg-[#EBF8D8] hover:text-[#063B00] transition-colors">
                    <i class="fa-solid fa-users text-slate-400 mr-1"></i> Komunitas
                </a>
                <a href="{{ route('games.schedule') }}" class="px-3 py-1.5 rounded-xl bg-slate-100/80 hover:bg-[#EBF8D8] hover:text-[#063B00] transition-colors">
                    <i class="fa-solid fa-plus text-slate-400 mr-1"></i> Buat Sesi Baru
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
