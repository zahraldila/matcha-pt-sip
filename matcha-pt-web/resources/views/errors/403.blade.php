@extends('layouts.app')

@section('title', 'Akses Ditolak — Matcha')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-xl text-center space-y-8 animate-in fade-in zoom-in-95 duration-200">
        
        <div class="relative mx-auto w-32 h-32 flex items-center justify-center">
            <div class="absolute inset-0 bg-amber-400/20 rounded-full blur-2xl animate-pulse"></div>
            <div class="relative w-28 h-28 rounded-3xl bg-white border border-amber-200/50 shadow-xl flex items-center justify-center">
                <div class="w-20 h-20 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-600">
                    <i class="fa-solid fa-lock text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-black tracking-wider uppercase">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                403 • Akses Terbatas
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Halaman Ini Khusus Host / Admin
            </h1>

            <p class="text-sm text-slate-600 max-w-md mx-auto leading-relaxed">
                {{ !empty($exception) && $exception->getMessage() ? $exception->getMessage() : 'Kamu tidak memiliki izin untuk mengakses halaman ini. Silakan masuk menggunakan akun yang berwenang.' }}
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
            <a 
                href="{{ route('dashboard') }}" 
                class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-[#063B00] text-[#A8E63A] font-extrabold text-xs sm:text-sm hover:bg-[#063B00]/90 transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer"
            >
                <i class="fa-solid fa-house text-[#A8E63A]"></i>
                <span>Kembali ke Beranda</span>
            </a>
        </div>

    </div>
</div>
@endsection
