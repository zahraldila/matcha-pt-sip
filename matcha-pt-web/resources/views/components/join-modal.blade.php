<!-- Modal Quick Join (100% Real Database) -->
<div id="joinModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="glass-card !bg-white/95 max-w-md w-full rounded-3xl p-6 border border-white space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-[#063B00]"></i> Gabung Sesi Mabar
            </h3>
            <button onclick="closeJoinModal()" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
        </div>

        <p class="text-xs text-slate-600">
            Sesi mabar: <strong id="modalGameTitle" class="text-slate-900"></strong>
        </p>

        <form id="joinModalForm" onsubmit="handleJoinSubmit(event)" class="space-y-4 text-xs">
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
            <div class="p-2.5 rounded-xl bg-[#EBF8D8]/80 border border-[#063B00]/15 flex items-center gap-2 text-[11px] text-[#063B00] font-semibold">
                <i class="fa-solid fa-user-clock text-xs text-[#063B00]"></i>
                <span>Mode Tamu: Anda bergabung sebagai <strong>Guest Player</strong> tanpa perlu login.</span>
            </div>

            <div>
                <label class="block text-slate-700 font-semibold mb-1">Nama Pemain <span class="text-rose-500">*</span></label>
                <input type="text" name="nama" id="joinPlayerName" placeholder="Contoh: Alex Pratama" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 font-semibold focus:border-[#063B00] focus:bg-white focus:outline-none" required>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-slate-700 font-semibold mb-1">Gender <span class="text-rose-500">*</span></label>
                    <select name="gender" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2 py-2 text-slate-800 font-semibold focus:border-[#063B00] focus:bg-white focus:outline-none text-xs">
                        <option value="Male">Laki-laki</option>
                        <option value="Female">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-700 font-semibold mb-1">Usia (Thn) <span class="text-rose-500">*</span></label>
                    <input type="number" name="usia" min="10" max="90" placeholder="24" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-slate-800 font-semibold focus:border-[#063B00] focus:bg-white focus:outline-none text-xs" required>
                </div>
                <div>
                    <label class="block text-slate-700 font-semibold mb-1">Skill Level <span class="text-rose-500">*</span></label>
                    <select name="level" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2 py-2 text-slate-800 font-semibold focus:border-[#063B00] focus:bg-white focus:outline-none text-xs">
                        <option value="Newbie">Newbie</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate" selected>Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
            </div>
            @endauth

            <div class="flex gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeJoinModal()" class="flex-1 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" id="joinSubmitBtn" class="flex-1 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold shadow-xs transition-colors cursor-pointer">
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
