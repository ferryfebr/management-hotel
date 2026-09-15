@extends('layouts.app')
@section('title', 'Status Kamar')

@section('content')
@php
$accentColor = [
    'available' => 'border-green-300',
    'occupied' => 'border-blue-300',
    'dirty' => 'border-yellow-300',
    'maintenance' => 'border-red-300',
];
@endphp

<div class="max-w-2xl mb-4">
    <input type="text" id="roomSearch" placeholder="Cari nomor kamar..." autocomplete="off"
           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
    @foreach($rooms as $room)
        <div data-room-number="{{ $room->room_number }}" class="room-card bg-white rounded-xl shadow-sm border border-gray-100 border-l-4 {{ $accentColor[$room->status] ?? 'border-gray-200' }} p-4">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="font-semibold text-lg">Kamar {{ $room->room_number }}</p>
                    <p class="text-xs text-gray-500">{{ $room->roomType->name }}</p>
                </div>
                <x-badge status="{{ $room->status }}" />
            </div>

            @php $lastProof = $room->roomLogs->first(); @endphp
            @if($lastProof?->proof_photo)
                <a href="{{ route('room-logs.photo', $lastProof) }}" target="_blank" class="inline-flex items-center gap-2 mb-3 text-xs text-gray-500 hover:text-gray-700">
                    <img src="{{ route('room-logs.photo', $lastProof) }}" alt="Foto bukti terakhir"
                         class="w-12 h-12 object-cover rounded border border-gray-200">
                    Foto bukti terakhir
                </a>
            @endif

            <form method="POST" action="{{ route('room-keeper.update-status', $room) }}" class="space-y-2"
                  enctype="multipart/form-data" x-data="{ status: null }">
                @csrf @method('PATCH')
                <div>
                    <input type="file" name="proof_photo" accept="image/*" id="proof_photo_{{ $room->id }}" class="hidden"
                           :required="status === 'clean' || status === 'maintenance'">
                    <div class="flex gap-2 flex-wrap">
                        <x-button variant="primary" type="button" class="proof-camera"
                                  data-target="proof_photo_{{ $room->id }}" data-preview="proof_preview_{{ $room->id }}">Ambil Foto</x-button>
                        <x-button variant="secondary" type="button" class="proof-gallery"
                                  data-target="proof_photo_{{ $room->id }}">Dari Galeri</x-button>
                    </div>
                    <img id="proof_preview_{{ $room->id }}" class="mt-2 h-24 object-cover rounded border hidden" alt="Pratinjau foto bukti">
                    <p class="mt-1 text-xs text-gray-500">Wajib untuk status Bersih &amp; Rusak, opsional untuk Kotor.</p>
                    @error('proof_photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <x-button variant="success" type="submit" name="status_reported" value="clean" @click="status = 'clean'">✓ Bersih</x-button>
                    <x-button variant="warning" type="submit" name="status_reported" value="dirty" @click="status = 'dirty'">Kotor</x-button>
                    <x-button variant="danger" type="submit" name="status_reported" value="maintenance" @click="status = 'maintenance'">Rusak</x-button>
                </div>
                <textarea name="notes" rows="2" placeholder="Catatan kerusakan (opsional)"
                          class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs"></textarea>
            </form>
        </div>
    @endforeach
</div>

{{-- Modal kamera (getUserMedia) untuk foto bukti kerja --}}
<div id="cameraModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <span class="font-semibold text-sm">Ambil Foto Bukti</span>
            <button id="cameraClose" class="text-gray-500 text-2xl leading-none hover:text-gray-800" aria-label="Tutup">&times;</button>
        </div>
        <div class="bg-black">
            <video id="cameraVideo" autoplay playsinline class="w-full h-72 object-cover"></video>
        </div>
        <div class="p-4 flex gap-2">
            <x-button variant="primary" type="button" id="cameraCapture" class="flex-1">Ambil</x-button>
        </div>
    </div>
</div>

<script>
(function () {
    var search = document.getElementById('roomSearch');
    var cards = document.querySelectorAll('.room-card');
    if (search && cards.length) {
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            cards.forEach(function (card) {
                var no = (card.getAttribute('data-room-number') || '').toLowerCase();
                card.style.display = (!q || no.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    // ==== Kamera / galeri foto bukti ====
    var activeInput = null;
    var activePreview = null;

    function showPreview(input, previewId) {
        var preview = previewId ? document.getElementById(previewId) : null;
        if (!preview || !input || !input.files || !input.files[0]) return;
        var reader = new FileReader();
        reader.onload = function () {
            preview.src = reader.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }

    function assignToInput(file) {
        if (!activeInput || !file) return;
        var dt = new DataTransfer();
        dt.items.add(file);
        activeInput.files = dt.files;
        showPreview(activeInput, activePreview ? activePreview.id : null);
    }

    document.querySelectorAll('.proof-gallery').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-target'));
            if (input) input.click();
        });
    });

    var camStream = null;

    function stopCamera() {
        if (camStream) { camStream.getTracks().forEach(function (t) { t.stop(); }); camStream = null; }
        var v = document.getElementById('cameraVideo');
        if (v) v.srcObject = null;
    }
    function closeCameraModal() {
        document.getElementById('cameraModal').classList.add('hidden');
        stopCamera();
    }
    function filePickFallback() {
        var tmp = document.createElement('input');
        tmp.type = 'file';
        tmp.accept = 'image/*';
        tmp.setAttribute('capture', 'environment');
        tmp.style.display = 'none';
        document.body.appendChild(tmp);
        tmp.addEventListener('change', function () {
            assignToInput(tmp.files && tmp.files[0]);
            document.body.removeChild(tmp);
        });
        tmp.click();
    }

    document.querySelectorAll('.proof-camera').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeInput = document.getElementById(btn.getAttribute('data-target'));
            activePreview = document.getElementById(btn.getAttribute('data-preview'));

            var modal = document.getElementById('cameraModal');
            var video = document.getElementById('cameraVideo');
            var supported = navigator.mediaDevices && navigator.mediaDevices.getUserMedia;

            if (!supported) { filePickFallback(); return; }

            modal.classList.remove('hidden');
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
                .then(function (stream) {
                    camStream = stream;
                    video.srcObject = stream;
                    video.play().catch(function () {});
                })
                .catch(function () {
                    modal.classList.add('hidden');
                    filePickFallback();
                });
        });
    });

    document.querySelectorAll('input[name="proof_photo"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var previewId = input.id.replace('proof_photo_', 'proof_preview_');
            showPreview(input, previewId);
        });
    });

    document.getElementById('cameraClose').addEventListener('click', closeCameraModal);
    document.getElementById('cameraModal').addEventListener('click', function (e) {
        if (e.target.id === 'cameraModal') closeCameraModal();
    });
    document.getElementById('cameraCapture').addEventListener('click', function () {
        var video = document.getElementById('cameraVideo');
        if (!camStream || !video || !video.videoWidth) return;
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
            if (!blob) return;
            assignToInput(new File([blob], 'proof_photo.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
            closeCameraModal();
        }, 'image/jpeg', 0.9);
    });
})();
setTimeout(function tick(){ var a=document.activeElement,typing=a&&(a.tagName==='INPUT'||a.tagName==='TEXTAREA'||a.tagName==='SELECT'); if(!typing&&!document.hidden) location.reload(); setTimeout(tick,15000); },15000);
</script>
@endsection