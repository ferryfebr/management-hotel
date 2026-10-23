@php
    $toastSuccess = session('success');
    $toastErrors = $errors->any() ? $errors->all() : [];
@endphp

<div id="toast-container" class="fixed top-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite"></div>

<script>
(function () {
    var container = document.getElementById('toast-container');

    if (!container) {
        return;
    }

    function dismiss(el) {
        el.classList.add('opacity-0', 'translate-x-2');
        setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 200);
    }

    window.showToast = function (type, message, duration) {
        if (!message) {
            return;
        }

        var isSuccess = type === 'success';
        var box = document.createElement('div');
        box.className = 'pointer-events-auto flex items-start gap-3 rounded-lg border bg-white px-4 py-3 text-sm shadow-lg transition duration-200 '
            + (isSuccess ? 'border-green-200' : 'border-red-200');
        box.setAttribute('role', isSuccess ? 'status' : 'alert');

        var dot = document.createElement('span');
        dot.className = 'mt-1.5 h-2.5 w-2.5 flex-shrink-0 rounded-full '
            + (isSuccess ? 'bg-green-500' : 'bg-red-500');
        box.appendChild(dot);

        var body = document.createElement('div');
        body.className = 'min-w-0 flex-1';

        var title = document.createElement('p');
        title.className = 'font-semibold ' + (isSuccess ? 'text-green-800' : 'text-red-800');
        title.textContent = isSuccess ? 'Berhasil' : 'Gagal';
        body.appendChild(title);

        var text = document.createElement('p');
        text.className = 'mt-0.5 whitespace-pre-line break-words ' + (isSuccess ? 'text-green-700' : 'text-red-700');
        text.textContent = message;
        body.appendChild(text);

        box.appendChild(body);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'flex-shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700';
        close.setAttribute('aria-label', 'Tutup notifikasi');
        close.textContent = '×';
        close.addEventListener('click', function () {
            dismiss(box);
        });
        box.appendChild(close);

        container.appendChild(box);

        setTimeout(function () {
            if (box.parentNode) {
                dismiss(box);
            }
        }, duration || (isSuccess ? 4500 : 8000));
    };

    @if($toastSuccess)
        window.showToast('success', @json($toastSuccess));
    @endif

    @if(! empty($toastErrors))
        window.showToast('error', @json(implode("\n", $toastErrors)));
    @endif
})();
</script>
