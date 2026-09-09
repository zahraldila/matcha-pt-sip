@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
    <!-- Top Step Progress Bar -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-200/60">
        <div class="flex items-center gap-3">
            <button type="button" onclick="prevStep()" id="backBtn" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-700 flex items-center justify-center hover:bg-slate-50 transition-colors shadow-xs hidden">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </button>
            <div>
                <span class="text-[10px] font-bold tracking-wider text-[#063B00] uppercase" id="stepBadge">Langkah 1 dari 4</span>
                <h1 class="text-xl sm:text-2xl font-black text-[#050608] leading-tight" id="stepTitle">
                    Create new game
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-[#063B00] transition-all" id="indicator1"></span>
            <span class="w-2.5 h-2.5 rounded-full bg-slate-200 transition-all" id="indicator2"></span>
            <span class="w-2.5 h-2.5 rounded-full bg-slate-200 transition-all" id="indicator3"></span>
            <span class="w-2.5 h-2.5 rounded-full bg-slate-200 transition-all" id="indicator4"></span>
        </div>
    </div>

    <!-- Multi-Step Wizard Container -->
    <div class="space-y-6">

        <!-- ==================== STEP 1: SELECT SPORT TYPE ==================== -->
        <div id="step1" class="space-y-4">
            <h2 class="text-sm font-bold text-[#050608]">
                Select sport type
            </h2>

            <div class="space-y-3">
                <!-- Padel -->
                <button type="button" onclick="selectSport('Padel', '🏓')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-[#EBF8D8] border border-[#C4E992] flex items-center justify-center text-xl text-[#063B00] group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Padel</h3>
                            <p class="text-xs text-slate-500">World Padel Tour standard & Americano format</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- Tennis -->
                <button type="button" onclick="selectSport('Tennis', '🎾')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-[#A8E63A]/20 border border-[#7FAF25]/30 flex items-center justify-center text-xl text-[#063B00] group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-baseball"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Tennis</h3>
                            <p class="text-xs text-slate-500">Tennis single, double, sets & game scoring</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>
            </div>
        </div>

        <!-- ==================== STEP 2: SELECT GAME TYPE (FORMAT) ==================== -->
        <div id="step2" class="space-y-4 hidden">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-[#050608]">
                    Select game type (<span id="selectedSportLabel" class="text-[#063B00]">Padel</span>)
                </h2>
                <span class="text-xs text-slate-400">Pilih format turnamen/mabar</span>
            </div>

            <div class="space-y-3">
                <!-- Americano -->
                <button type="button" onclick="selectGameType('Americano', 'All players play with everyone')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-[#EBF8D8] border border-[#C4E992] flex items-center justify-center text-lg text-[#063B00] group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Americano</h3>
                                <span class="text-[10px] font-bold bg-[#EBF8D8] text-[#063B00] border border-[#C4E992] px-2 py-0.5 rounded-full">POPULER</span>
                            </div>
                            <p class="text-xs text-slate-500">Semua pemain berpasangan secara bergantian (Round-Robin)</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- Mexicano -->
                <button type="button" onclick="selectGameType('Mexicano', 'Like Americano but will result in more even games based on scoreboard')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-[#A8E63A]/20 border border-[#7FAF25]/30 flex items-center justify-center text-lg text-[#063B00] group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Mexicano</h3>
                                <span class="text-[10px] font-bold bg-[#A8E63A]/20 text-[#063B00] border border-[#7FAF25]/30 px-2 py-0.5 rounded-full">DINAMIS</span>
                            </div>
                            <p class="text-xs text-slate-500">Rotasi berdasarkan peringkat sementara agar pertandingan selalu imbang</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- Team Americano -->
                <button type="button" onclick="selectGameType('Team Americano', 'Each team plays against all other teams one time with fixed teams')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg text-indigo-700 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Team Americano</h3>
                                <span class="text-[10px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 px-2 py-0.5 rounded-full">FIXED TEAMS</span>
                            </div>
                            <p class="text-xs text-slate-500">Setiap pasangan tim tetap bertanding melawan seluruh tim lainnya</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- Team Mexicano -->
                <button type="button" onclick="selectGameType('Team Mexicano', 'Mexicano with fixed teams')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-sky-50 border border-sky-100 flex items-center justify-center text-lg text-sky-700 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-users-rectangle"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Team Mexicano</h3>
                                <span class="text-[10px] font-bold bg-sky-50 text-sky-800 border border-sky-200 px-2 py-0.5 rounded-full">DYNAMIC</span>
                            </div>
                            <p class="text-xs text-slate-500">Format Mexicano kompetitif dengan pasangan tim yang tetap</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- Mixicano -->
                <button type="button" onclick="selectGameType('Mixicano', 'Woman and a man in each team with Mexicano logic')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-purple-50 border border-purple-100 flex items-center justify-center text-lg text-purple-700 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-venus-mars"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Mixicano</h3>
                                <span class="text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200 px-2 py-0.5 rounded-full">MIX GENDER</span>
                            </div>
                            <p class="text-xs text-slate-500">Sistem selalu memasangkan 1 pria & 1 wanita dalam tiap tim secara dinamis</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- Mix Americano -->
                <button type="button" onclick="selectGameType('Mix Americano', 'The team is drawn with a woman and a man in each team')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-lg text-amber-700 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-heart"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Mix Americano</h3>
                                <span class="text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded-full">MIX GENDER</span>
                            </div>
                            <p class="text-xs text-slate-500">Drawing putaran round-robin dengan komposisi pria dan wanita seimbang</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- King of the Court -->
                <button type="button" onclick="selectGameType('King of the Court', 'Fight your way up to winners court and defend your place')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-lg text-amber-700 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-crown"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">King of the Court</h3>
                                <span class="text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded-full">DEFEND</span>
                            </div>
                            <p class="text-xs text-slate-500">Berjuang naik ke lapangan pemenang (Court 1) dan pertahankan posisi</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>

                <!-- Knockout -->
                <button type="button" onclick="selectGameType('Knockout', 'Bracket-based tournament with knockout rounds')" class="w-full glass-card hover:border-[#063B00] rounded-2xl p-4 flex items-center justify-between group transition-all text-left">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-lg text-slate-700 group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-trophy"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-[#050608] group-hover:text-[#063B00] transition-colors">Knockout</h3>
                                <span class="text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded-full">BRACKET</span>
                            </div>
                            <p class="text-xs text-slate-500">Turnamen sistem gugur berjenjang hingga babak final perebutan juara</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400 group-hover:text-[#063B00] group-hover:translate-x-1 transition-all"></i>
                </button>
            </div>
        </div>

        <!-- ==================== STEP 3: MATCH & SCORING CONFIGURATION ==================== -->
        <div id="step3" class="space-y-6 hidden">
            <!-- Format Banner Header -->
            <div class="glass-card rounded-2xl p-5 text-center space-y-1 border border-[#063B00]/20 bg-gradient-to-r from-[#EBF8D8]/70 via-white/80 to-[#A8E63A]/20 shadow-xs">
                <span class="text-[10px] uppercase font-bold tracking-widest text-[#063B00]">Selected Format</span>
                <h2 class="text-xl sm:text-2xl font-black text-[#050608]" id="configFormatTitle">Americano</h2>
            </div>

            <div class="glass-card rounded-3xl p-6 sm:p-7 space-y-5 border border-white">
                <!-- Activity Name -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-800">
                        Activity Name
                    </label>
                    <input type="text" id="activityName" value="Padel Weekend Mabar" placeholder="Contoh: Padel Weekend Fun / Tenis JTK" class="w-full bg-slate-50/90 border border-slate-200/80 rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/20 focus:outline-none transition-all shadow-2xs" required>
                </div>

                <!-- Number of Courts -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-800">
                        Numbers of Court
                    </label>
                    <div class="relative">
                        <select id="numCourts" class="w-full bg-slate-50/90 border border-slate-200/80 rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/20 focus:outline-none appearance-none transition-all shadow-2xs">
                            <option value="1">1 Court</option>
                            <option value="2">2 Court</option>
                            <option value="3">3 Court</option>
                            <option value="4">4 Court</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <!-- Venue -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-800">
                        Venue
                    </label>

                    <div class="relative">
                        <select
                            id="venueId"
                            class="w-full bg-slate-50/90 border border-slate-200/80 rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/20 focus:outline-none appearance-none transition-all shadow-2xs"
                            required
                        >
                            <option value="" selected disabled>Pilih venue</option>

                            @foreach ($venues as $venue)
                                <option value="{{ $venue->venue_id }}">
                                    {{ $venue->nama_venue }}
                                </option>
                            @endforeach
                        </select>

                        <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <!-- Scoring System (General Only) -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-800">
                        Scoring System
                    </label>
                    
                    <div class="relative">
                        <select id="scoringGeneralValue" class="w-full bg-slate-50/90 border border-slate-200/80 rounded-2xl px-4 py-3 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/20 focus:outline-none appearance-none transition-all shadow-2xs">
                            <option value="Total of 3" selected>Total of 3</option>
                            <option value="Total of 4">Total of 4</option>
                            <option value="Total of 5">Total of 5</option>
                            <option value="Total of 6">Total of 6</option>
                            <option value="Total of 7">Total of 7</option>
                            <option value="First to 8">First to 8</option>
                            <option value="First to 3">First to 3</option>
                            <option value="First to 4">First to 4</option>
                            <option value="First to 5">First to 5</option>
                            <option value="First to 6">First to 6</option>
                            <option value="First to 7">First to 7</option>
                            <option value="First to 11">First to 11</option>
                            <option value="First to 10">First to 10</option>
                            <option value="First to 15">First to 15</option>
                            <option value="First to 21">First to 21</option>
                            <option value="First to 25">First to 25</option>
                            <option value="First to 30">First to 30</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <!-- Leaderboard Ranked by -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-800">
                        Leaderboard Ranked by
                    </label>
                    <div class="grid grid-cols-2 gap-2.5 p-1 bg-slate-100/80 rounded-2xl border border-slate-200/60">
                        <button type="button" onclick="setRankBy('point')" id="btnRankPoint" class="py-2.5 rounded-xl font-bold text-xs bg-[#063B00] text-white shadow-xs transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-bullseye text-[11px] text-[#A8E63A]"></i> Point
                        </button>
                        <button type="button" onclick="setRankBy('win')" id="btnRankWin" class="py-2.5 rounded-xl font-bold text-xs bg-transparent text-slate-600 hover:text-slate-900 transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-trophy text-[11px] text-slate-400"></i> Win
                        </button>
                    </div>
                </div>

                <div class="pt-3">
                    <button type="button" onclick="goToStep(4)" class="w-full py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2">
                        <span>Lanjut & Atur Daftar Pemain</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A]"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== STEP 4: MANUAL PLAYER LIST MANAGEMENT ==================== -->
        <div id="step4" class="space-y-6 hidden">
            
            <!-- Summary Header Card -->
            <div class="glass-card rounded-2xl p-5 border border-[#063B00]/20 bg-gradient-to-r from-[#EBF8D8]/70 via-white/80 to-[#A8E63A]/20 shadow-xs flex items-center justify-between">
                <div>
                    <h2 class="text-lg sm:text-xl font-extrabold text-[#050608]" id="summaryGameName">Padel Weekend Mabar</h2>
                    <p class="text-xs text-slate-500 font-medium" id="summaryGameFormat">Americano &bull; 1 Court &bull; 24 Points</p>
                </div>
                <span class="text-[10px] font-bold bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/30 px-3 py-1 rounded-full">Host Mode</span>
            </div>

            <!-- Add Player Action Bar -->
            <div class="glass-card rounded-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-[#050608] flex items-center gap-1.5">
                            Player List (<span id="playerCount">0</span>)
                        </h3>
                        <p class="text-[11px] text-slate-500">*Minimal 4 pemain untuk generate drawing</p>
                    </div>
                </div>

                <!-- Add Buttons Row (Sesuai Screenshot Skor App) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <button type="button" onclick="addYourself()" class="py-3 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs shadow-2xs transition-colors flex items-center justify-center gap-2">
                        <i class="fa-solid fa-user-check text-[#063B00]"></i> + ADD YOURSELF
                    </button>
                    <button type="button" onclick="openAddPlayerModal()" class="py-3 px-4 rounded-xl bg-[#EBF8D8] hover:bg-[#A8E63A]/30 text-[#063B00] border border-[#063B00]/30 font-bold text-xs shadow-2xs transition-colors flex items-center justify-center gap-2">
                        <i class="fa-solid fa-user-plus text-[#063B00]"></i> + ADD PLAYER / GUEST
                    </button>
                </div>

                <!-- Player List Container -->
                <div class="space-y-2 pt-2" id="playersContainer">
                    <!-- Empty State Illustration (Sesuai Screenshot) -->
                    <div id="emptyPlayersState" class="py-8 text-center space-y-3">
                        <div class="w-16 h-16 mx-auto rounded-full bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-400 text-2xl shadow-xs">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-xs font-bold text-slate-800">Great moments are meant to be shared.</h4>
                            <p class="text-[11px] text-slate-400">Tambahkan minimal 4 pemain untuk memulai pengacakan drawing tim.</p>
                        </div>
                    </div>
                </div>

                <!-- Generate Drawing CTA Button -->
                <div class="pt-4 border-t border-slate-100">
                    <button type="button" id="btnStartDrawing" onclick="startDrawingAction()" disabled class="w-full py-3.5 rounded-xl bg-slate-200 text-slate-400 font-black text-xs shadow-none cursor-not-allowed transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-shuffle"></i> 🎲 Generate Drawing & Start Game
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Select Player from Database -->
<div id="addPlayerModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="glass-card !bg-white max-w-md w-full rounded-3xl p-6 border border-white space-y-4 shadow-2xl">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-[#063B00]"></i>
                    Tambahkan Player
                </h3>
                <p class="text-[10px] text-slate-400 mt-1">
                    Cari player yang sudah terdaftar
                </p>
            </div>

            <button
                type="button"
                onclick="closeAddPlayerModal()"
                class="text-slate-400 hover:text-slate-600 text-lg leading-none"
            >
                &times;
            </button>
        </div>

        <!-- Search -->
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>

            <input
                type="text"
                id="playerSearchInput"
                placeholder="Cari nama player..."
                autocomplete="off"
                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#063B00] focus:outline-none"
                oninput="searchPlayers()"
            >
        </div>

        <!-- Search Result -->
        <div
            id="playerSearchResults"
            class="space-y-2 max-h-72 overflow-y-auto"
        >
            <div class="py-8 text-center">
                <i class="fa-solid fa-magnifying-glass text-slate-300 text-xl mb-2"></i>
                <p class="text-[11px] text-slate-400">
                    Ketik nama player untuk mencari
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="pt-3 border-t border-slate-100">
            <button
                type="button"
                onclick="closeAddPlayerModal()"
                class="w-full py-2.5 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition-colors text-xs"
            >
                Batal
            </button>
        </div>

    </div>
</div>

@push('scripts')
<script>
    let currentStep = 1;
    let selectedSport = 'Padel';
    let selectedGameType = 'Americano';
    let scoringType = 'points'; // 'points' or 'general'
    let rankBy = 'point'; // 'point' or 'win'

    let players = [
        // Pre-filled with realistic data if Host clicks Add Yourself
    ];

    function selectSport(sport, icon) {
        selectedSport = sport;
        document.getElementById('selectedSportLabel').innerText = `${icon} ${sport}`;
        goToStep(2);
    }

    function selectGameType(format, description) {
        selectedGameType = format;
        document.getElementById('configFormatTitle').innerText = format;
        goToStep(3);
    }

    function setRankBy(type) {
        rankBy = type;
        if (type === 'point') {
            document.getElementById('btnRankPoint').className = 'py-2.5 rounded-xl font-bold text-xs bg-[#063B00] text-white shadow-xs transition-all flex items-center justify-center gap-1.5';
            document.getElementById('btnRankWin').className = 'py-2.5 rounded-xl font-bold text-xs bg-transparent text-slate-600 hover:text-slate-900 transition-all flex items-center justify-center gap-1.5';
        } else {
            document.getElementById('btnRankWin').className = 'py-2.5 rounded-xl font-bold text-xs bg-[#063B00] text-white shadow-xs transition-all flex items-center justify-center gap-1.5';
            document.getElementById('btnRankPoint').className = 'py-2.5 rounded-xl font-bold text-xs bg-transparent text-slate-600 hover:text-slate-900 transition-all flex items-center justify-center gap-1.5';
        }
    }

    function goToStep(step) {
        currentStep = step;

        // Hide all steps
        [1, 2, 3, 4].forEach(s => {
            document.getElementById(`step${s}`).classList.add('hidden');
            document.getElementById(`indicator${s}`).className = 'w-2.5 h-2.5 rounded-full bg-slate-200 transition-all';
        });

        // Show current step
        document.getElementById(`step${step}`).classList.remove('hidden');
        document.getElementById(`stepBadge`).innerText = `Langkah ${step} dari 4`;

        for (let i = 1; i <= step; i++) {
            document.getElementById(`indicator${i}`).className = 'w-2.5 h-2.5 rounded-full bg-[#063B00] transition-all';
        }

        // Show/hide back button
        if (step > 1) {
            document.getElementById('backBtn').classList.remove('hidden');
        } else {
            document.getElementById('backBtn').classList.add('hidden');
        }

        if (step === 4) {
            const actName = document.getElementById('activityName').value || `${selectedSport} Mabar`;
            const numCourt = document.getElementById('numCourts').value;
            const scoreVal = document.getElementById('scoringGeneralValue').value;

            const venueSelect = document.getElementById('venueId');
            const venueName = venueSelect.options[venueSelect.selectedIndex]?.text || 'Venue belum dipilih';

            document.getElementById('summaryGameName').innerText = actName;
            document.getElementById('summaryGameFormat').innerText = `${selectedGameType} • ${numCourt} Court • ${venueName} • ${scoreVal}`;
        }
    }

    function prevStep() {
        if (currentStep > 1) {
            goToStep(currentStep - 1);
        }
    }

    // Player List Management
    function addYourself() {
    @if ($hostPlayer)
        const hostPlayer = {
            player_id: {{ $hostPlayer->player_id }},
            name: @json($hostPlayer->nama),
            gender: @json($hostPlayer->gender ?? 'Male'),
            level: @json($hostPlayer->level ?? 'Intermediate'),
            type: 'Host'
        };

        addPlayerToList(hostPlayer);
    @else
        showToast('Data player untuk akun host belum ditemukan.');
    @endif
    }

    function openAddPlayerModal() {
        const searchInput = document.getElementById('playerSearchInput');

        searchInput.value = '';

        document.getElementById('playerSearchResults').innerHTML = `
            <div class="py-8 text-center">
                <i class="fa-solid fa-magnifying-glass text-slate-300 text-xl mb-2"></i>
                <p class="text-[11px] text-slate-400">
                    Ketik nama player untuk mencari
                </p>
            </div>
        `;

        document.getElementById('addPlayerModal').classList.remove('hidden');
        document.getElementById('addPlayerModal').classList.add('flex');

        setTimeout(() => {
            searchInput.focus();
        }, 100);
    }

    function addPlayerToList(player) {
        const alreadyAdded = players.some(
            p => Number(p.player_id) === Number(player.player_id)
        );

        if (alreadyAdded) {
            showToast(`${player.name} sudah berada di daftar pemain.`);
            return;
        }

        players.push(player);

        renderPlayers();
        showToast(`${player.name} berhasil ditambahkan!`);
    }

    function closeAddPlayerModal() {
        document.getElementById('addPlayerModal').classList.add('hidden');
        document.getElementById('addPlayerModal').classList.remove('flex');
    }

    let playerSearchTimeout = null;

    function searchPlayers() {
        const searchInput = document.getElementById('playerSearchInput');
        const resultsContainer = document.getElementById('playerSearchResults');

        const search = searchInput.value.trim();

        clearTimeout(playerSearchTimeout);

        if (search.length < 2) {
            resultsContainer.innerHTML = `
                <div class="py-8 text-center">
                    <i class="fa-solid fa-magnifying-glass text-slate-300 text-xl mb-2"></i>
                    <p class="text-[11px] text-slate-400">
                        Ketik minimal 2 karakter
                    </p>
                </div>
            `;
            return;
        }

        resultsContainer.innerHTML = `
            <div class="py-8 text-center">
                <i class="fa-solid fa-spinner fa-spin text-[#063B00] text-lg"></i>
                <p class="text-[11px] text-slate-400 mt-2">
                    Mencari player...
                </p>
            </div>
        `;

        playerSearchTimeout = setTimeout(() => {
            fetch(`{{ route('games.players.search') }}?search=${encodeURIComponent(search)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Gagal mengambil data player');
                    }

                    return response.json();
                })
                .then(data => {
                    renderPlayerSearchResults(data);
                })
                .catch(error => {
                    console.error(error);

                    resultsContainer.innerHTML = `
                        <div class="py-8 text-center">
                            <i class="fa-solid fa-circle-exclamation text-rose-400 text-lg"></i>
                            <p class="text-[11px] text-slate-400 mt-2">
                                Gagal mencari player
                            </p>
                        </div>
                    `;
                });
        }, 300);
    }

    function renderPlayerSearchResults(data) {
        const container = document.getElementById('playerSearchResults');

        if (!data.length) {
            container.innerHTML = `
                <div class="py-8 text-center">
                    <i class="fa-solid fa-user-slash text-slate-300 text-xl mb-2"></i>
                    <p class="text-[11px] text-slate-400">
                        Player tidak ditemukan
                    </p>
                </div>
            `;
            return;
        }

        container.innerHTML = data.map(player => {
            const alreadyAdded = players.some(
            p => Number(p.player_id) === Number(player.player_id)
        );

            return `
                <button
                    type="button"
                    onclick="selectDatabasePlayer(${player.player_id}, ${JSON.stringify(player).replace(/"/g, '&quot;')})"
                    ${alreadyAdded ? 'disabled' : ''}
                    class="w-full p-3 rounded-xl border border-slate-200 bg-white text-left flex items-center justify-between transition-all
                        ${alreadyAdded
                            ? 'opacity-50 cursor-not-allowed'
                            : 'hover:border-[#063B00] hover:bg-[#EBF8D8]/30'
                        }"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-[#EBF8D8] flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-user text-xs text-[#063B00]"></i>
                        </div>

                        <div class="min-w-0">
                            <p class="font-bold text-xs text-slate-900 truncate">
                                ${escapeHtml(player.nama)}
                            </p>

                            <p class="text-[10px] text-slate-400">
                                ${escapeHtml(player.gender || '-')}
                                •
                                ${escapeHtml(player.level || 'No Level')}
                            </p>
                        </div>
                    </div>

                    <div class="shrink-0 ml-2">
                        ${
                            alreadyAdded
                            ? `<span class="text-[9px] font-bold text-slate-400">SUDAH DITAMBAHKAN</span>`
                            : `<i class="fa-solid fa-plus text-xs text-[#063B00]"></i>`
                        }
                    </div>
                </button>
            `;
        }).join('');
    }

    function selectDatabasePlayer(playerId, player) {
        addPlayerToList({
            player_id: player.player_id,
            name: player.nama,
            gender: player.gender || 'Male',
            level: player.level || 'Intermediate',
            type: 'Member'
        });

        closeAddPlayerModal();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function removePlayer(idx) {
        players.splice(idx, 1);
        renderPlayers();
        showToast('Pemain dihapus dari daftar.');
    }

    function renderPlayers() {
        const container = document.getElementById('playersContainer');
        const countSpan = document.getElementById('playerCount');
        const startBtn = document.getElementById('btnStartDrawing');
        
        countSpan.innerText = players.length;

        if (players.length === 0) {
            container.innerHTML = `
                <div id="emptyPlayersState" class="py-8 text-center space-y-3">
                    <div class="w-16 h-16 mx-auto rounded-full bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-400 text-2xl shadow-xs">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-slate-800">Great moments are meant to be shared.</h4>
                        <p class="text-[11px] text-slate-400">Tambahkan minimal 4 pemain untuk memulai pengacakan drawing tim.</p>
                    </div>
                </div>
            `;
            startBtn.disabled = true;
            startBtn.className = 'w-full py-3.5 rounded-xl bg-slate-200 text-slate-400 font-black text-xs shadow-none cursor-not-allowed transition-all flex items-center justify-center gap-2';
            return;
        }

        let html = '';
        players.forEach((p, idx) => {
            const badgeClass = p.level === 'Advanced' ? 'bg-orange-50 text-orange-800 border-orange-200' :
                               p.level === 'Intermediate' ? 'bg-indigo-50 text-indigo-800 border-indigo-200' :
                               p.level === 'Beginner' ? 'bg-[#EBF8D8] text-[#063B00] border-[#063B00]/25' :
                               'bg-sky-50 text-sky-800 border-sky-200';

            html += `
                <div class="p-3 bg-white rounded-xl border border-slate-200/80 flex items-center justify-between shadow-2xs text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-600">${idx + 1}</span>
                        <div>
                            <p class="font-bold text-slate-900">${p.name}</p>
                            <p class="text-[10px] text-slate-400">${p.gender} • <span class="font-semibold text-slate-600">${p.type}</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border ${badgeClass}">${p.level}</span>
                        <button type="button" onclick="removePlayer(${idx})" class="text-slate-400 hover:text-rose-600 p-1 text-xs">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        if (players.length >= 4) {
            startBtn.disabled = false;
            startBtn.className = 'w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer flex items-center justify-center gap-2';
        } else {
            startBtn.disabled = true;
            startBtn.className = 'w-full py-3.5 rounded-xl bg-slate-200 text-slate-400 font-black text-xs shadow-none cursor-not-allowed transition-all flex items-center justify-center gap-2';
        }
    }

    function startDrawingAction() {
        if (players.length < 4) {
            showToast('Minimal 4 pemain untuk generate drawing.');
            return;
        }

        const data = {
            _token: '{{ csrf_token() }}',
            nama_session: document.getElementById('activityName').value,
            sport: selectedSport,
            format: selectedGameType,
            num_courts: document.getElementById('numCourts').value,
            venue_id: document.getElementById('venueId').value,
            scoring_system: document.getElementById('scoringGeneralValue').value,
            rank_by: rankBy,
            players: players
        };

        fetch('{{ route('games.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
                return;
            }

            return response.json();
        })
        .catch(error => {
            console.error(error);
            showToast('Gagal membuat game. Silakan coba lagi.');
        });
    }
</script>
@endpush
@endsection
