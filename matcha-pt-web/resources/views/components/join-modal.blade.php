<!-- Modal Quick Join (100% Real Database) -->
<div id="joinModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 transition-all">
    <div class="glass-card bg-white/95! max-w-lg w-full max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-3xl p-5 sm:p-7 border border-white/80 space-y-5 shadow-2xl relative animate-in fade-in zoom-in duration-150">
        <div class="flex items-start gap-3 pr-9">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-200/80 flex items-center justify-center text-matcha-dark text-base shrink-0 shadow-2xs">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <div class="min-w-0">
                <h3 class="text-base font-bold text-slate-900">Gabung Sesi Mabar</h3>
                <p class="mt-0.5 text-xs text-slate-500">Lengkapi profil untuk bergabung ke sesi ini.</p>
            </div>
        </div>
        <button type="button" onclick="closeJoinModal()" class="absolute top-5 right-5 sm:top-7 sm:right-7 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center transition-colors cursor-pointer" title="Tutup">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>

        <div class="rounded-2xl bg-slate-50 border border-slate-200/80 px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Sesi mabar</p>
            <p id="modalGameTitle" class="mt-1 text-sm font-bold text-slate-900 wrap-break-word"></p>
        </div>

        <form id="joinModalForm" onsubmit="handleJoinSubmit(event)" class="space-y-5 text-sm">
            @csrf

            @auth
            @php
                $authUser = Auth::user();
                $authPlayer = $authUser->player ?? \App\Models\Player::where('user_id', $authUser->user_id)->orWhere('email', $authUser->email)->first();
                $playerName = $authPlayer->nama ?? $authUser->nama ?? 'Pemain Matcha';
                $playerGender = $authPlayer->gender ?? 'Male';
                $playerAge = $authPlayer->usia ?? null;
                $playerLevel = $authPlayer->level ?? 'Intermediate';
                $playerCommunity = $authPlayer->community->nama_community ?? 'Personal';
                $playerPhoto = $authPlayer->foto ?? $authUser->foto ?? null;
            @endphp

            <!-- Card Profil Akun Pemain Terhubung -->
            <div class="p-3.5 rounded-2xl bg-gradient-to-r from-emerald-50/70 via-white to-slate-50 border border-emerald-200/80 flex items-center gap-3.5 shadow-2xs">
                <div class="w-12 h-12 rounded-full bg-[#063B00] border-2 border-[#A8E63A]/50 flex items-center justify-center text-white text-base font-black shrink-0 overflow-hidden shadow-2xs" style="width: 48px; height: 48px; min-width: 48px; min-height: 48px;">
                    @if(!empty($playerPhoto))
                        <img src="{{ $playerPhoto }}" alt="{{ $playerName }}" class="w-full h-full object-cover" style="width: 100%; height: 100%; object-fit: cover;">
                    @else
                        <span>{{ strtoupper(substr($playerName, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex items-center gap-2">
                        <h4 class="text-xs font-extrabold text-slate-900 truncate">{{ $playerName }}</h4>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/20">
                            Akun Terverifikasi
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium truncate">{{ $authUser->email }}</p>
                    <div class="flex flex-wrap gap-1.5 pt-0.5">
                        <span class="px-2 py-0.5 rounded-lg bg-white border border-slate-200 text-slate-700 text-[10px] font-semibold">
                            {{ $playerGender === 'Female' ? '🚺 Perempuan' : '🚹 Laki-laki' }}{{ !empty($playerAge) ? ", {$playerAge} th" : '' }}
                        </span>
                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 border border-slate-200 text-[#063B00] text-[10px] font-bold">
                            ⭐ {{ $playerLevel }}
                        </span>
                        <span class="px-2 py-0.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-semibold">
                            👥 {{ $playerCommunity }}
                        </span>
                    </div>
                </div>
            </div>

            <input type="hidden" name="nama" value="{{ $playerName }}">
            <input type="hidden" name="gender" value="{{ $playerGender }}">
            <input type="hidden" name="usia" value="{{ $playerAge }}">
            <input type="hidden" name="level" value="{{ $playerLevel }}">
            @else
            <!-- Mode Guest / Belum Login -->
            <div class="p-3.5 rounded-2xl bg-[#EBF8D8]/70 border border-matcha-dark/15 flex items-start gap-3 text-xs text-matcha-dark leading-relaxed">
                <span class="w-8 h-8 rounded-xl bg-white/80 border border-matcha-dark/10 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-user-clock text-sm"></i>
                </span>
                <span class="pt-0.5">Mode Tamu <span class="text-[#42623D]">Anda bergabung sebagai</span> <strong>Guest Player</strong> tanpa perlu login.</span>
            </div>

            <div class="space-y-1.5">
                <label class="block text-slate-700 font-semibold text-xs">Nama Pemain <span class="text-rose-500">*</span></label>
                <input type="text" name="nama" id="joinPlayerName" placeholder="Contoh: Alex Pratama" class="w-full h-11 bg-slate-50 border border-slate-200 rounded-xl px-3.5 text-slate-800 font-medium placeholder:text-slate-400 focus:border-matcha-dark focus:bg-white focus:outline-none focus:ring-2 focus:ring-matcha-dark/10 text-sm transition-colors" required>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <!-- Gender -->
                <div class="relative" id="joinGenderContainer">
                    <label class="block text-slate-700 font-semibold mb-1.5 text-xs">Gender <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <select name="gender" id="joinGuestGender" class="sr-only">
                            <option value="Male" selected>Laki-laki</option>
                            <option value="Female">Perempuan</option>
                        </select>
                        <button type="button" onclick="toggleJoinDropdown('joinGenderMenu', 'joinGenderArrow')"
                            class="w-full h-11 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-800 font-semibold hover:border-slate-300 focus:border-matcha-dark focus:bg-white focus:outline-none focus:ring-2 focus:ring-matcha-dark/10 transition-colors text-sm flex items-center justify-between cursor-pointer text-left">
                            <span id="joinGenderLabel" class="truncate">Laki-laki</span>
                            <i id="joinGenderArrow" class="fa-solid fa-chevron-down text-[9px] text-slate-400"></i>
                        </button>
                        <div id="joinGenderMenu" class="hidden absolute z-50 left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl p-1.5 text-sm space-y-1 ring-1 ring-black/5"></div>
                    </div>
                </div>

                <!-- Usia -->
                <div>
                    <label class="block text-slate-700 font-semibold mb-1.5 text-xs">Usia (tahun) <span class="text-rose-500">*</span></label>
                    <input type="number" name="usia" min="10" max="90" placeholder="24" class="w-full h-11 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-800 font-semibold placeholder:text-slate-400 focus:border-matcha-dark focus:bg-white focus:outline-none focus:ring-2 focus:ring-matcha-dark/10 text-sm transition-colors" required>
                </div>

                <!-- Skill Level -->
                <div class="relative" id="joinLevelContainer">
                    <label class="block text-slate-700 font-semibold mb-1.5 text-xs">Level Bermain <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <select name="level" id="joinGuestLevel" class="sr-only">
                            <option value="Newbie">Newbie</option>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate" selected>Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                        <button type="button" onclick="toggleJoinDropdown('joinLevelMenu', 'joinLevelArrow')"
                            class="w-full h-11 bg-slate-50 border border-slate-200 rounded-xl px-3 text-slate-800 font-semibold hover:border-slate-300 focus:border-matcha-dark focus:bg-white focus:outline-none focus:ring-2 focus:ring-matcha-dark/10 transition-colors text-sm flex items-center justify-between cursor-pointer text-left">
                            <span id="joinLevelLabel" class="truncate">Intermediate</span>
                            <i id="joinLevelArrow" class="fa-solid fa-chevron-down text-[9px] text-slate-400"></i>
                        </button>
                        <div id="joinLevelMenu" class="hidden absolute z-50 left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl p-1.5 text-sm max-h-48 overflow-y-auto space-y-1 ring-1 ring-black/5"></div>
                    </div>
                </div>
            </div>
            @endauth

            <div class="flex flex-col-reverse sm:flex-row gap-2.5 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeJoinModal()" class="flex-1 py-3 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition-colors cursor-pointer text-sm">
                    Batal
                </button>
                <button type="submit" id="joinSubmitBtn" class="flex-1 py-3 rounded-xl bg-matcha-dark hover:bg-matcha-dark-hover text-white font-bold shadow-sm transition-colors cursor-pointer text-sm">
                    Konfirmasi Gabung
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    if (typeof currentJoinGameId === 'undefined') {
        var currentJoinGameId = null;
    }

    const joinDropdownList = [
        { selectId: 'joinGuestGender', menuId: 'joinGenderMenu', labelId: 'joinGenderLabel', arrowId: 'joinGenderArrow', containerId: 'joinGenderContainer' },
        { selectId: 'joinGuestLevel', menuId: 'joinLevelMenu', labelId: 'joinLevelLabel', arrowId: 'joinLevelArrow', containerId: 'joinLevelContainer' },
    ];

    function renderJoinSelectDropdown(selectId, menuId, labelId) {
        const select = document.getElementById(selectId);
        const menu = document.getElementById(menuId);
        const label = document.getElementById(labelId);
        if (!select || !menu || !label) return;

        menu.innerHTML = '';
        Array.from(select.options).forEach(opt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const isSelected = String(select.value) === String(opt.value);
            btn.className = `w-full min-h-10 px-3 py-2 rounded-lg text-left text-sm font-semibold transition-colors flex items-center justify-between cursor-pointer ${
                isSelected
                    ? 'bg-[#EBF8D8] text-[#063B00] font-bold'
                    : 'text-slate-700 hover:bg-[#F4FBEA] hover:text-[#063B00]'
            }`;
            btn.innerHTML = `
                <span class="truncate">${opt.textContent.trim()}</span>
                ${isSelected ? '<i class="fa-solid fa-check text-[#063B00] text-[10px] shrink-0 ml-1"></i>' : ''}
            `;
            btn.onclick = (e) => {
                e.stopPropagation();
                select.value = opt.value;
                select.dispatchEvent(new Event('change'));
                label.textContent = opt.textContent.trim();
                renderJoinSelectDropdown(selectId, menuId, labelId);
                menu.classList.add('hidden');
                const arrow = document.getElementById(menuId.replace('Menu', 'Arrow'));
                if (arrow) arrow.classList.remove('rotate-180');
            };
            menu.appendChild(btn);
        });

        const activeOpt = select.options[select.selectedIndex] || select.options[0];
        if (activeOpt) {
            label.textContent = activeOpt.textContent.trim();
        }
    }

    function toggleJoinDropdown(menuId, arrowId) {
        const menu = document.getElementById(menuId);
        const arrow = document.getElementById(arrowId);
        if (!menu) return;

        const willOpen = menu.classList.contains('hidden');
        joinDropdownList.forEach(item => {
            if (item.menuId !== menuId) {
                document.getElementById(item.menuId)?.classList.add('hidden');
                document.getElementById(item.arrowId)?.classList.remove('rotate-180');
            }
        });

        if (willOpen) {
            menu.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
        } else {
            menu.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
        }
    }

    document.addEventListener('click', function(e) {
        joinDropdownList.forEach(item => {
            const container = document.getElementById(item.containerId);
            if (container && !container.contains(e.target)) {
                document.getElementById(item.menuId)?.classList.add('hidden');
                document.getElementById(item.arrowId)?.classList.remove('rotate-180');
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        joinDropdownList.forEach(item => {
            renderJoinSelectDropdown(item.selectId, item.menuId, item.labelId);
        });
    });

    function showJoinModal(gameId, gameTitle) {
        currentJoinGameId = gameId;
        const titleEl = document.getElementById('modalGameTitle');
        if (titleEl) titleEl.innerText = gameTitle;
        const modal = document.getElementById('joinModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                const nameInput = document.getElementById('joinPlayerName');
                if (nameInput && !nameInput.value) {
                    nameInput.focus();
                }
            }, 50);
        }
    }

    function closeJoinModal() {
        const modal = document.getElementById('joinModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    async function handleJoinSubmit(e) {
        e.preventDefault();
        if (!currentJoinGameId) return;

        const submitBtn = document.getElementById('joinSubmitBtn');
        const originalText = submitBtn.innerText;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

        const form = document.getElementById('joinModalForm');
        const formData = new FormData(form);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/games/${currentJoinGameId}/join`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            });

            if (res.status === 401) {
                window.location.href = "{{ route('login') }}";
                return;
            }

            const data = await res.json();

            if (res.ok && data.success) {
                closeJoinModal();
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Berhasil bergabung ke sesi mabar!');
                } else {
                    alert(data.message || 'Berhasil bergabung!');
                }
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Gagal bergabung ke sesi.', 'error');
                } else {
                    alert(data.message || 'Gagal bergabung.');
                }
            }
        } catch (err) {
            console.error('Join error:', err);
            if (typeof showToast === 'function') {
                showToast('Terjadi kesalahan koneksi saat bergabung.', 'error');
            } else {
                alert('Terjadi kesalahan koneksi.');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    }
</script>
