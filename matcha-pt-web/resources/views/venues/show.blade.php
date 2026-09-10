@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-xs font-bold text-emerald-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif

    <div class="border-b border-slate-200 pb-4">
        <a href="{{ route('venues.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Semua Venue
        </a>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#050608]">
                    {{ $venue['name'] }}
                </h1>
                <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1.5">
                    <i class="fa-solid fa-location-dot text-slate-400"></i> {{ $venue['address'] }}
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('games.create') }}" class="px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-[10px]"></i> Buat Mabar di Sini
                </a>
            </div>
        </div>
    </div>

    <!-- Venue Hero Image & Interactive Gallery -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @php
                $galleryPhotos = $venue['gallery'] ?? [$venue['image']];
            @endphp

            <div class="space-y-3">
                <!-- Main Featured Photo View -->
                <div class="relative h-64 sm:h-84 rounded-2xl overflow-hidden border border-slate-200 shadow-sm bg-slate-900 group">
                    <img id="mainVenuePhoto" src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="w-full h-full object-cover transition-all duration-300">
                    
                    <div class="absolute top-4 left-4 z-10">
                        <x-badge :type="strtolower($venue['sport']) === 'tennis' ? 'tennis' : (strtolower($venue['sport']) === 'padel' ? 'padel' : 'multi')">
                            {{ $venue['sport'] }}
                        </x-badge>
                    </div>

                    @if(count($galleryPhotos) > 1)
                        <div class="absolute bottom-3 right-3 bg-black/60 backdrop-blur-md text-white px-3 py-1 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-camera text-[#A8E63A] text-[11px]"></i>
                            <span>{{ count($galleryPhotos) }} Foto Suasana</span>
                        </div>
                    @endif

                    @if(auth()->check() && (auth()->user()->role === 'venue_owner' || !empty($venue['is_mine'])))
                        <button type="button" onclick="openPhotoModal()" class="absolute top-4 right-4 z-10 px-3 py-1.5 rounded-xl bg-black/60 hover:bg-black/80 text-white font-bold text-xs backdrop-blur-md border border-white/20 flex items-center gap-1.5 shadow-sm transition-all cursor-pointer">
                            <i class="fa-solid fa-camera-rotate text-[#A8E63A] text-[11px]"></i> Kelola Foto
                        </button>
                    @endif
                </div>

                <!-- Google Maps Style Gallery Thumbnails (if > 1 photo) -->
                @if(count($galleryPhotos) > 1)
                    <div class="flex items-center gap-2.5 overflow-x-auto pb-1 scrollbar-thin">
                        @foreach($galleryPhotos as $idx => $photoUrl)
                            <button
                                type="button"
                                onclick="document.getElementById('mainVenuePhoto').src = '{{ $photoUrl }}'; document.querySelectorAll('.venue-thumb').forEach(el => el.classList.remove('ring-2', 'ring-[#063B00]', 'opacity-100')); this.classList.add('ring-2', 'ring-[#063B00]', 'opacity-100');"
                                class="venue-thumb relative h-16 w-24 sm:h-20 sm:w-28 shrink-0 rounded-xl overflow-hidden border border-slate-200 transition-all cursor-pointer hover:opacity-100 {{ $idx === 0 ? 'ring-2 ring-[#063B00] opacity-100' : 'opacity-70 hover:scale-105' }}"
                            >
                                <img src="{{ $photoUrl }}" alt="Foto {{ $idx + 1 }}" class="w-full h-full object-cover">
                                @if($idx === 0)
                                    <span class="absolute bottom-1 left-1 bg-[#063B00] text-white text-[8px] font-bold px-1.5 py-0.5 rounded shadow-xs">
                                        Cover
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Court List & Status -->
            <div class="clean-card rounded-xl p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Daftar Lapangan / Court
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Daftar court aktif dan jenis arena lapangan.</p>
                    </div>

                    @if(auth()->check() && (auth()->user()->role === 'venue_owner' || !empty($venue['is_mine'])))
                        <a href="{{ route('venues.courts.create', $venue['id']) }}" class="px-3 py-1.5 rounded-lg bg-[#EBF8D8] hover:bg-[#d8f3b8] text-[#063B00] font-bold text-xs inline-flex items-center gap-1.5 border border-[#063B00]/20 transition-all">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            Tambah Court
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse($venue['courts'] as $court)
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2 hover:border-[#063B00]/40 transition-all group relative">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-slate-900">{{ $court['name'] }}</span>
                                    <span class="text-[9px] px-2 py-0.5 rounded-full font-bold {{ strtolower($court['sport']) === 'tennis' ? 'bg-[#A8E63A]/25 text-[#050608] border border-[#7FAF25]/35' : 'bg-[#7FAF25]/20 text-[#050608] border border-[#7FAF25]/35' }}">
                                        {{ $court['sport'] }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] px-2 py-0.5 rounded font-semibold {{ $court['status'] === 'Available' ? 'bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                        {{ $court['status'] }}
                                    </span>
                                    @if(auth()->check() && (auth()->user()->role === 'venue_owner' || !empty($venue['is_mine'])) && !empty($court['id']))
                                        <form action="{{ route('venues.courts.destroy', [$venue['id'], $court['id']]) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus court {{ $court['name'] }}?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer" title="Hapus Lapangan">
                                                <i class="fa-solid fa-trash-can text-[11px]"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 flex items-center justify-between">
                                <span>Tipe: <strong class="text-slate-700">{{ $court['type'] }}</strong></span>
                                <span class="text-[#063B00] text-[11px] font-semibold">Siap Pakai</span>
                            </div>
                        </div>
                    @empty
                        <div class="sm:col-span-2 text-center py-6 text-slate-400 text-xs bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            Belum ada court yang terdaftar untuk venue ini.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar: Hours & Availability Logic -->
        <div class="space-y-6">
            <!-- Operating Hours & Unavailability Rules -->
            <div class="clean-card rounded-xl p-5 space-y-4">
                <h3 class="text-sm font-bold text-slate-900">
                    Jam Operasi & Availability
                </h3>

                <div class="space-y-2.5 text-xs">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Jam Operasional Reguler:</span>
                        <strong class="text-slate-900 text-sm block">{{ str_contains($venue['operating_hours'], 'WIB') ? $venue['operating_hours'] : $venue['operating_hours'] . ' WIB' }}</strong>
                    </div>

                    <div class="bg-amber-50 p-3 rounded-lg border border-amber-200 text-amber-900 space-y-1">
                        <span class="font-semibold block flex items-center gap-1.5 text-xs">
                            <i class="fa-solid fa-circle-exclamation text-amber-600"></i> Catatan Khusus & Ketentuan Operasional:
                        </span>
                        <p class="text-[11px] leading-relaxed text-amber-800">{{ $venue['catatan'] ?? $venue['unavailability_note'] ?? 'Sesuai jadwal ketersediaan lapangan reguler.' }}</p>
                    </div>
                </div>

                <!-- PIC / Contact Info -->
                <div class="pt-3 border-t border-slate-100 space-y-1 text-xs">
                    <span class="text-slate-500 block">PIC / Pengelola:</span>
                    <p class="text-slate-900 font-semibold">{{ $venue['pic_name'] }}</p>
                    <p class="text-[#063B00] font-bold">{{ $venue['pic_phone'] }}</p>
                </div>
            </div>

            <!-- Facilities Structured Card -->
            <div class="clean-card rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-bold text-slate-900">
                    Fasilitas Venue
                </h3>

                <ul class="space-y-1.5 text-xs text-slate-600">
                    @foreach($venue['facilities'] as $facility)
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-[#063B00] text-xs"></i>
                            <span>{{ $facility }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- MODAL KELOLA / EDIT FOTO VENUE -->
@if(auth()->check() && (auth()->user()->role === 'venue_owner' || !empty($venue['is_mine'])))
    @php
        $rawPhotosList = $venue['raw_photos'] ?? $venue['gallery'] ?? [];
    @endphp
    <div id="photoModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-2xl w-full shadow-2xl space-y-6 my-8 animate-in fade-in zoom-in duration-200 border border-slate-100">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-[#063B00] flex items-center justify-center text-base border border-emerald-100">
                        <i class="fa-solid fa-images"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Kelola Galeri Foto Venue</h3>
                        <p class="text-xs text-slate-500">Tambah foto baru atau hapus foto lama dari galeri.</p>
                    </div>
                </div>
                <button type="button" onclick="closePhotoModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center text-xs transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="{{ route('venues.photos.update', $venue['id']) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- Foto yang Sudah Ada -->
                <div class="space-y-2.5">
                    <label class="block font-bold text-xs text-slate-700">
                        Foto Saat Ini (<span id="existingPhotosCount">{{ count($rawPhotosList) }}</span> Foto)
                    </label>
                    <div id="existingPhotosGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($rawPhotosList as $pIdx => $rawPhoto)
                            @php
                                $photoDisplay = str_starts_with($rawPhoto, 'http') ? $rawPhoto : asset($rawPhoto);
                            @endphp
                            <div class="existing-photo-item relative group rounded-2xl overflow-hidden border border-slate-200 aspect-video bg-slate-900 shadow-2xs">
                                <img src="{{ $photoDisplay }}" alt="Foto {{ $pIdx + 1 }}" class="w-full h-full object-cover">
                                
                                <input type="hidden" name="existing_photos[]" value="{{ $rawPhoto }}" class="existing-photo-input">

                                @if($pIdx === 0)
                                    <span class="absolute top-2 left-2 bg-[#063B00] text-white text-[9px] font-bold px-2 py-0.5 rounded-md shadow-xs">
                                        Cover
                                    </span>
                                @endif

                                <button
                                    type="button"
                                    onclick="removeExistingPhoto(this)"
                                    class="absolute top-2 right-2 w-7 h-7 rounded-xl bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-xs shadow-md transition-all cursor-pointer"
                                    title="Hapus foto ini"
                                >
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <p id="noExistingNotice" class="hidden text-xs text-slate-400 italic py-2">Semua foto lama akan dihapus.</p>
                </div>

                <!-- Upload Foto Baru -->
                <div class="space-y-2.5 pt-2 border-t border-slate-100">
                    <label class="block font-bold text-xs text-slate-700">
                        Tambah Foto Baru
                    </label>

                    <div
                        id="newDropArea"
                        onclick="document.getElementById('newPhotosInput').click()"
                        class="border-2 border-dashed border-slate-200 hover:border-[#063B00] bg-slate-50/60 hover:bg-[#EBF8D8]/20 rounded-2xl p-6 text-center cursor-pointer transition-all space-y-2 group"
                    >
                        <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200 group-hover:border-[#063B00] text-[#063B00] flex items-center justify-center text-base mx-auto transition-transform group-hover:scale-110 shadow-2xs">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-700 group-hover:text-[#063B00]">Klik atau seret foto baru ke sini</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Mendukung format JPG, PNG, WEBP (Maks 5MB per foto)</p>
                        </div>
                    </div>

                    <input type="file" id="newPhotosInput" name="new_photos[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden">

                    <!-- Preview Foto Baru -->
                    <div id="newPhotosPreviewContainer" class="hidden space-y-2 pt-2">
                        <span class="text-[11px] font-bold text-[#063B00] block">Foto Baru yang Akan Ditambahkan:</span>
                        <div id="newPhotosGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3"></div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button
                        type="button"
                        onclick="closePhotoModal()"
                        class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-6 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.02] cursor-pointer flex items-center gap-2"
                    >
                        <i class="fa-solid fa-floppy-disk text-[#A8E63A]"></i> Simpan Perubahan Foto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let newUploadedFiles = [];

        function openPhotoModal() {
            document.getElementById('photoModal').classList.remove('hidden');
        }

        function closePhotoModal() {
            document.getElementById('photoModal').classList.add('hidden');
        }

        function removeExistingPhoto(btn) {
            const item = btn.closest('.existing-photo-item');
            if (item) {
                item.remove();
                updateExistingCount();
            }
        }

        function updateExistingCount() {
            const grid = document.getElementById('existingPhotosGrid');
            const items = grid.querySelectorAll('.existing-photo-item');
            const countSpan = document.getElementById('existingPhotosCount');
            const notice = document.getElementById('noExistingNotice');
            if (countSpan) countSpan.textContent = items.length;
            if (items.length === 0) {
                if (notice) notice.classList.remove('hidden');
            } else {
                if (notice) notice.classList.add('hidden');
            }
        }

        const newPhotosInput = document.getElementById('newPhotosInput');
        const newPreviewContainer = document.getElementById('newPhotosPreviewContainer');
        const newPhotosGrid = document.getElementById('newPhotosGrid');
        const newDropArea = document.getElementById('newDropArea');

        if (newPhotosInput) {
            newPhotosInput.addEventListener('change', function(e) {
                handleNewFiles(Array.from(e.target.files));
            });
        }

        if (newDropArea) {
            ['dragenter', 'dragover'].forEach(eventName => {
                newDropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    newDropArea.classList.add('border-[#063B00]', 'bg-[#EBF8D8]/30');
                });
            });
            ['dragleave', 'drop'].forEach(eventName => {
                newDropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    newDropArea.classList.remove('border-[#063B00]', 'bg-[#EBF8D8]/30');
                });
            });
            newDropArea.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    handleNewFiles(Array.from(e.dataTransfer.files));
                }
            });
        }

        function handleNewFiles(files) {
            const validFiles = files.filter(file => file.type.startsWith('image/'));
            if (!validFiles.length) return;

            newUploadedFiles.push(...validFiles);
            renderNewPreviews();
            syncNewFileInput();
        }

        function renderNewPreviews() {
            if (!newPhotosGrid) return;
            newPhotosGrid.innerHTML = '';

            if (newUploadedFiles.length === 0) {
                newPreviewContainer.classList.add('hidden');
                return;
            }

            newPreviewContainer.classList.remove('hidden');

            newUploadedFiles.forEach((file, idx) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const card = document.createElement('div');
                    card.className = 'relative group rounded-2xl overflow-hidden border border-emerald-300 aspect-video bg-slate-900 shadow-2xs';
                    card.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}" class="w-full h-full object-cover">
                        <span class="absolute top-2 left-2 bg-emerald-600 text-white text-[9px] font-bold px-2 py-0.5 rounded-md shadow-xs">
                            Baru
                        </span>
                        <button
                            type="button"
                            onclick="removeNewFile(${idx})"
                            class="absolute top-2 right-2 w-7 h-7 rounded-xl bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-xs shadow-md transition-all cursor-pointer"
                            title="Batal tambah foto ini"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    `;
                    newPhotosGrid.appendChild(card);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeNewFile(index) {
            newUploadedFiles.splice(index, 1);
            renderNewPreviews();
            syncNewFileInput();
        }

        function syncNewFileInput() {
            const dt = new DataTransfer();
            newUploadedFiles.forEach(f => dt.items.add(f));
            newPhotosInput.files = dt.files;
        }
    </script>
@endif
@endsection

