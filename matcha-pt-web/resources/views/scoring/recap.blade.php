@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
    <!-- Top Action Breadcrumbs -->
    <div class="flex items-center justify-between">
        <a href="{{ route('games.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Mabar
        </a>
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                <i class="fa-solid fa-circle-check text-emerald-600"></i> Match Finished
            </span>
            <button onclick="shareRecap()" class="px-3 py-1 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-share-nodes text-slate-400"></i> Bagikan
            </button>
        </div>
    </div>

    <!-- Match Result Showcase (Glass Hero) -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 text-center relative overflow-hidden border border-white">
        <div class="absolute top-0 right-0 transform translate-x-8 -translate-y-8 w-40 h-40 bg-emerald-100/50 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 transform -translate-x-8 translate-y-8 w-40 h-40 bg-lime-100/50 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative space-y-4">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100/80 border border-amber-200 text-amber-900 text-xs font-bold">
                <i class="fa-solid fa-trophy text-amber-600"></i> Match Winner &bull; Team A
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                {{ $game['title'] ?? 'Padel Weekend Americano' }}
            </h1>
            <p class="text-xs text-slate-500">
                {{ $game['venue_name'] ?? 'GBK Padel Arena' }} &bull; {{ $game['schedule'] ?? 'Hari ini, 16:00 - 18:00 WIB' }}
            </p>

            <!-- Scoreboard Big Visual -->
            <div class="grid grid-cols-3 items-center max-w-lg mx-auto py-4 bg-white/70 backdrop-blur-md rounded-2xl border border-slate-200/80 shadow-xs">
                <!-- Team A -->
                <div class="p-3 text-center space-y-1">
                    <span class="text-[10px] font-bold tracking-wider text-emerald-700 uppercase">Team A</span>
                    <p class="text-xs sm:text-sm font-black text-slate-900 leading-tight">Billy Santoso<br><span class="text-[11px] font-medium text-slate-500">& Gisel A.</span></p>
                    <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">WINNER</span>
                </div>

                <!-- Final Score Points -->
                <div class="text-center space-y-1">
                    <div class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">
                        <span class="text-emerald-600">24</span> : <span>18</span>
                    </div>
                    <span class="text-[10px] font-semibold text-slate-400">Total Points</span>
                </div>

                <!-- Team B -->
                <div class="p-3 text-center space-y-1">
                    <span class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Team B</span>
                    <p class="text-xs sm:text-sm font-black text-slate-900 leading-tight">Fahri Dhani<br><span class="text-[11px] font-medium text-slate-500">& Davina P.</span></p>
                    <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-semibold">Runner-up</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rating & Level Progression (Playtomic Benchmark) -->
    <div class="glass-card rounded-3xl p-6 border border-white space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700 text-xs">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Game-Based Rating Progression</h2>
                    <p class="text-[11px] text-slate-500">Rating dihitung berdasarkan selisih poin & rating lawan (Playtomic Algorithm)</p>
                </div>
            </div>
            <span class="text-xs font-bold text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                Level Confidence: 92%
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Billy Santoso Rating Change -->
            <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80" class="w-7 h-7 rounded-full object-cover border border-slate-200" alt="Billy">
                        <span class="text-xs font-bold text-slate-900">Billy Santoso (You)</span>
                    </div>
                    <span class="text-xs font-black text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">+0.18</span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>3.42 (Intermediate)</span>
                    <i class="fa-solid fa-arrow-right text-slate-400 text-[10px]"></i>
                    <span class="font-bold text-emerald-700">3.60 (Intermediate+)</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-emerald-600 h-1.5 rounded-full" style="width: 72%"></div>
                </div>
            </div>

            <!-- Gisel Anastasia Rating Change -->
            <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?w=80&auto=format&fit=crop&q=80" class="w-7 h-7 rounded-full object-cover border border-slate-200" alt="Gisel">
                        <span class="text-xs font-bold text-slate-900">Gisel Anastasia</span>
                    </div>
                    <span class="text-xs font-black text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">+0.24</span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>2.15 (Beginner)</span>
                    <i class="fa-solid fa-arrow-right text-slate-400 text-[10px]"></i>
                    <span class="font-bold text-emerald-700">2.39 (Beginner+)</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-emerald-600 h-1.5 rounded-full" style="width: 48%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kudos & Compliments Giving (Reclub / Playtomic Benchmark) -->
    <div class="glass-card rounded-3xl p-6 border border-white space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-700 text-xs">
                    <i class="fa-solid fa-medal"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Beri Kudos untuk Teman Main (Kudos System)</h2>
                    <p class="text-[11px] text-slate-500">Apresiasi skill & sportivitas pemain di lapangan</p>
                </div>
            </div>
            <span class="text-[11px] font-semibold text-slate-400">Pilih badge</span>
        </div>

        <!-- Kudos Grid -->
        <div class="space-y-3">
            <!-- Player: Gisel Anastasia -->
            <div class="p-3.5 rounded-2xl bg-white border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-3">
                    <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?w=80&auto=format&fit=crop&q=80" class="w-8 h-8 rounded-full object-cover border border-slate-200" alt="Gisel">
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Gisel Anastasia (Partner)</h4>
                        <p class="text-[10px] text-slate-400">Team A</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" onclick="toggleKudos(this)" class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all">
                        🎾 Super Forehand
                    </button>
                    <button type="button" onclick="toggleKudos(this)" class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all">
                        🛡️ Solid Defense
                    </button>
                    <button type="button" onclick="toggleKudos(this)" class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all">
                        🤝 Fun Partner
                    </button>
                </div>
            </div>

            <!-- Player: Fahri Dhani -->
            <div class="p-3.5 rounded-2xl bg-white border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-3">
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&auto=format&fit=crop&q=80" class="w-8 h-8 rounded-full object-cover border border-slate-200" alt="Fahri">
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Fahri Dhani (Opponent)</h4>
                        <p class="text-[10px] text-slate-400">Team B</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" onclick="toggleKudos(this)" class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all">
                        💥 Killer Smash
                    </button>
                    <button type="button" onclick="toggleKudos(this)" class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all">
                        ⭐ MVP Play
                    </button>
                    <button type="button" onclick="toggleKudos(this)" class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all">
                        ✨ Fair Play
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Strava-Style Activity Recap Banner -->
    <div class="p-6 rounded-3xl bg-gradient-to-r from-emerald-800 to-teal-900 text-white flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
        <div class="space-y-1 text-center sm:text-left">
            <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-300">Strava-like Recap Card</span>
            <h3 class="text-lg font-black">Bagikan Kartu Statistik Pertandinganmu</h3>
            <p class="text-xs text-emerald-100/80">Posting statistik match ke Instagram Story atau WhatsApp Group komunitasmu.</p>
        </div>
        <a href="{{ route('player.recap') }}" class="px-5 py-2.5 rounded-xl bg-lime-400 hover:bg-lime-500 text-slate-950 font-extrabold text-xs shadow-md transition-all hover:scale-105 shrink-0">
            Lihat Kartu Recap &rarr;
        </a>
    </div>

</div>

@push('scripts')
<script>
    function toggleKudos(btn) {
        if (btn.classList.contains('bg-emerald-600')) {
            btn.className = 'px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-800 transition-all';
            showToast('Kudos dibatalkan.');
        } else {
            btn.className = 'px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-emerald-600 bg-emerald-600 text-white shadow-xs transition-all';
            showToast('Kudos berhasil dikirimkan! 🌟');
        }
    }

    function shareRecap() {
        if (navigator.share) {
            navigator.share({
                title: 'Hasil Pertandingan Matcha Arena',
                text: 'Team A menang 24-18 di Padel Weekend Americano! Cek skor selengkapnya di Matcha.',
                url: window.location.href
            }).catch(() => {});
        } else {
            showToast('Link ringkasan pertandingan berhasil disalin ke clipboard!');
        }
    }
</script>
@endpush
@endsection
