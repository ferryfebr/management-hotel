@extends('layouts.app')
@section('title', 'Check-in Tamu')

@php
    $reservation = $reservation ?? null;
@endphp

@section('content')
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="{{ route('transactions.checkin') }}" enctype="multipart/form-data" class="space-y-4 text-sm">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="relative">
                <label class="block mb-1">Nama Tamu</label>
                <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name', $reservation?->customer->name ?? '') }}" placeholder="Mulai ketik nama..." autocomplete="off" required class="w-full border rounded px-3 py-2">
                <ul id="customer_results" class="hidden absolute z-20 w-full bg-white border rounded shadow-lg mt-1 text-xs max-h-48 overflow-y-auto"></ul>
            </div>
            <div>
                <label class="block mb-1">No. Telepon</label>
                <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone', $reservation?->customer->phone ?? '') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Nomor KTP</label>
                <input type="number" name="id_card_number" id="id_card_number" value="{{ old('id_card_number') }}" required class="w-full border rounded px-3 py-2">
                <div id="id_card_hint" class="mt-1 text-xs hidden"></div>
            </div>
            <div>
                <label class="block mb-1">Foto KTP</label>
                <input type="file" name="id_card_photo" id="id_card_photo" accept="image/*" required class="hidden">
                <div class="flex gap-2 flex-wrap">
                    <x-button variant="primary" type="button" id="btn_camera">Ambil Foto</x-button>
                    <x-button variant="secondary" type="button" id="btn_gallery">Dari Galeri</x-button>
                </div>
                <img id="id_card_preview" class="mt-2 h-32 object-cover rounded border hidden" alt="Pratinjau KTP">
            </div>
        </div>

        <div>
            <label class="block mb-1">Kamar</label>
            <select name="room_id" required class="w-full border rounded px-3 py-2">
                @foreach($rooms as $room)
                    <option value="{{ $room->id }}" @selected(old('room_id', $reservation?->room_id) == $room->id)>
                        Kamar {{ $room->room_number }} — {{ $room->roomType->name }} (Rp {{ number_format($room->roomType->price, 0, ',', '.') }}/malam)
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1">Tanggal Check-in</label>
                <input type="date" name="check_in_date" value="{{ old('check_in_date', $reservation?->check_in_date->format('Y-m-d') ?? '') }}" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Tanggal Check-out</label>
                <input type="date" name="check_out_date" value="{{ old('check_out_date', $reservation?->check_out_date->format('Y-m-d') ?? '') }}" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block mb-1">Jenis Diskon</label>
                <select name="discount_type" class="w-full border rounded px-3 py-2">
                    <option value="">Tanpa Diskon</option>
                    <option value="fixed">Nominal (Rp)</option>
                    <option value="percentage">Persentase (%)</option>
                </select>
            </div>
            <div>
                <label class="block mb-1">Nilai Diskon</label>
                <input type="number" name="discount_amount" min="0" value="0" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block mb-1">Uang Muka / DP (Rp)</label>
                <input type="number" name="down_payment" min="0" value="0" required class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block mb-1">Metode Pembayaran DP</label>
            <select name="payment_method" required class="w-full border rounded px-3 py-2">
                <option value="cash">Tunai</option>
                <option value="transfer">Transfer</option>
                <option value="qris">QRIS</option>
                <option value="debit">Kartu Debit</option>
                <option value="kartu_kredit">Kartu Kredit</option>
            </select>
        </div>

        <x-button variant="primary" type="submit" block>Proses Check-in</x-button>
    </form>
</div>

{{-- Modal kamera (getUserMedia) untuk foto KTP --}}
<div id="cameraModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <span class="font-semibold text-sm">Ambil Foto KTP</span>
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
    var KEY = 'checkin_id_card_photo';
    var input = document.getElementById('id_card_photo');
    var preview = document.getElementById('id_card_preview');

    // ==== Autocomplete nama tamu dari daftar pelanggan lama ====
    var nameInput = document.getElementById('customer_name');
    var phoneInput = document.getElementById('customer_phone');
    var nikInput = document.getElementById('id_card_number');
    var custResults = document.getElementById('customer_results');
    var searchUrl = '{{ route("customers.search") }}';
    var custTimer = null;

    function hideCust() { if (custResults) { custResults.classList.add('hidden'); custResults.innerHTML = ''; } }

    if (nameInput && custResults) {
        function renderCust(items) {
            custResults.innerHTML = '';
            if (!items.length) return hideCust();
            items.forEach(function (c) {
                var li = document.createElement('li');
                li.className = 'px-3 py-2 hover:bg-blue-50 cursor-pointer border-b last:border-b-0';
                li.innerHTML = '<div class="font-medium">' + c.name + ' <span class="text-gray-400">(' + c.visit_count + 'x)</span></div>' +
                    '<div class="text-gray-500">' + (c.phone || '-') + '</div>';
                li.addEventListener('click', function () {
                    nameInput.value = c.name;
                    if (phoneInput) phoneInput.value = c.phone || '';
                    if (nikInput && c.id_card_number) {
                        nikInput.value = c.id_card_number;
                        nikInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    hideCust();
                });
                custResults.appendChild(li);
            });
            custResults.classList.remove('hidden');
        }

        nameInput.addEventListener('input', function () {
            clearTimeout(custTimer);
            var v = nameInput.value.trim();
            if (v.length < 2) { hideCust(); return; }
            custTimer = setTimeout(function () {
                fetch(searchUrl + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(renderCust)
                    .catch(function () { hideCust(); });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!nameInput.contains(e.target) && !custResults.contains(e.target)) hideCust();
        });
    }

    function fileToDataUrl(file, cb) {
        var reader = new FileReader();
        reader.onload = function () { cb(reader.result); };
        reader.readAsDataURL(file);
    }

    function renderPreview(dataUrl) {
        if (!dataUrl || !preview) return;
        preview.src = dataUrl;
        preview.classList.remove('hidden');
    }

    function dataUrlToFile(dataUrl) {
        var parts = dataUrl.split(',');
        var mime = (parts[0].match(/:(.*?);/) || [])[1] || 'image/jpeg';
        var bin = atob(parts[1]);
        var len = bin.length;
        var bytes = new Uint8Array(len);
        for (var i = 0; i < len; i++) bytes[i] = bin.charCodeAt(i);
        return new File([bytes], 'id_card_photo.' + (mime.indexOf('png') !== -1 ? 'png' : 'jpg'), { type: mime, lastModified: Date.now() });
    }

    function setInputFile(file) {
        if (!input) return;
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
    }

    function restoreFromStorage() {
        if (!sessionStorage.getItem(KEY)) return;
        var dataUrl = sessionStorage.getItem(KEY);
        renderPreview(dataUrl);
        if (!input || input.files.length) return;
        try { setInputFile(dataUrlToFile(dataUrl)); } catch (e) {}
    }

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        fileToDataUrl(file, function (dataUrl) {
            sessionStorage.setItem(KEY, dataUrl);
            renderPreview(dataUrl);
        });
    });

    if (input.files.length) {
        fileToDataUrl(input.files[0], function (dataUrl) {
            sessionStorage.setItem(KEY, dataUrl);
            renderPreview(dataUrl);
        });
    }

    restoreFromStorage();

    // ==== Format rupiah pada input nominal (200000 -> 200.000) ====
    function formatRupiah(n) {
        return (n.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
    }
    var rupiahInputs = document.querySelectorAll('input[name="discount_amount"], input[name="down_payment"]');
    rupiahInputs.forEach(function (el) {
        el.addEventListener('input', function () { el.value = formatRupiah(el.value); });
        el.addEventListener('blur', function () { if (el.value === '' || el.value === '.') el.value = '0'; });
    });
    var checkinForm = document.querySelector('form[enctype="multipart/form-data"]');
    if (checkinForm) {
        checkinForm.addEventListener('submit', function () {
            rupiahInputs.forEach(function (el) { el.value = el.value.replace(/\./g, ''); });
        });
    }

    // ==== Notif pelanggan lama via NIK KTP (real-time saat ketik) ====
    var nikInput = document.getElementById('id_card_number');
    var hint = document.getElementById('id_card_hint');
    var hintBaseUrl = '{{ route("customers.check-id-card", "__NIK__") }}';
    var nikTimer = null;

    if (nikInput && hint) {
        function checkNik() {
            var nik = nikInput.value.replace(/\D/g, '');
            if (nik.length !== 16) {
                hint.classList.add('hidden');
                return;
            }
            fetch(hintBaseUrl.replace('__NIK__', nik), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.found) {
                        hint.textContent = 'Pelanggan terdahulu/terdaftar: ' + d.name +
                            (d.visit_count ? ' (' + d.visit_count + 'x kunjungan)' : '');
                        hint.className = 'mt-1 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded';
                    } else {
                        hint.classList.add('hidden');
                    }
                })
                .catch(function () { hint.classList.add('hidden'); });
        }
        nikInput.addEventListener('input', function () {
            clearTimeout(nikTimer);
            nikTimer = setTimeout(checkNik, 400);
        });
        setTimeout(checkNik, 300);
    }

    // ==== Kamera / galeri foto KTP ====
    var mainPhoto = document.getElementById('id_card_photo');
    var btnCamera = document.getElementById('btn_camera');
    var btnGallery = document.getElementById('btn_gallery');

    function assignToMain(file) {
        if (!mainPhoto || !file) return;
        var dt = new DataTransfer();
        dt.items.add(file);
        mainPhoto.files = dt.files;
        if (file.type && file.type.indexOf('image') !== -1) {
            mainPhoto.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

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
            assignToMain(tmp.files && tmp.files[0]);
            document.body.removeChild(tmp);
        });
        tmp.click();
    }

    function openCamera() {
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
    }

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
            assignToMain(new File([blob], 'id_card_photo.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
            closeCameraModal();
        }, 'image/jpeg', 0.9);
    });

    if (btnCamera) btnCamera.addEventListener('click', openCamera);
    if (btnGallery) btnGallery.addEventListener('click', function () {
        if (mainPhoto) mainPhoto.click();
    });
})();
</script>
@endsection