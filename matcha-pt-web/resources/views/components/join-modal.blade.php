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

        <form id="joinModalForm" onsubmit="handleJoinSubmit(event)" class="space-y-3 text-xs">
            @csrf
            <div>
                <label class="block text-slate-700 font-semibold mb-1">Nama Pemain</label>
                <input type="text" name="nama" id="joinPlayerName" value="{{ Auth::check() ? Auth::user()->nama : '' }}" placeholder="Masukkan nama Anda" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-[#063B00] focus:bg-white focus:outline-none" required>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-slate-700 font-semibold mb-1">Gender</label>
                    <select name="gender" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-[#063B00] focus:bg-white focus:outline-none">
                        <option value="Male">Laki-laki</option>
                        <option value="Female">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-700 font-semibold mb-1">Level Permainan</label>
                    <select name="level" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-[#063B00] focus:bg-white focus:outline-none">
                        <option value="Newbie">Newbie</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate" selected>Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-slate-700 font-semibold mb-1">Nomor WhatsApp</label>
                <input type="text" name="no_hp" id="joinPlayerPhone" value="{{ Auth::check() ? (Auth::user()->no_hp ?? '') : '' }}" placeholder="08123456789" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-[#063B00] focus:bg-white focus:outline-none">
            </div>

            <div class="flex gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeJoinModal()" class="flex-1 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition-colors">
                    Batal
                </button>
                <button type="submit" id="joinSubmitBtn" class="flex-1 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold shadow-xs transition-colors">
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
