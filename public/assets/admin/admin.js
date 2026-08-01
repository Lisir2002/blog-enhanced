// Admin panel JS
(function () {
    'use strict';
    // Markdown preview in post editor
    var md = document.getElementById('post-content-md');
    var preview = document.getElementById('post-preview');
    if (md && preview) {
        var timer = null;
        function renderPreview() {
            fetch(url('/admin/posts/preview'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(document.querySelector('input[name=_token]') ? document.querySelector('input[name=_token]').value : '') +
                      '&content=' + encodeURIComponent(md.value),
            }).then(function (r) { return r.text(); }).then(function (html) {
                preview.innerHTML = html;
            }).catch(function (e) {
                // Fallback: local render via regex
                preview.innerHTML = '<pre>' + md.value + '</pre>';
            });
        }
        md.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(renderPreview, 500);
        });
        renderPreview();
    }

    // Confirm delete
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = form.getAttribute('data-confirm') || '确定删除？';
            if (!confirm(msg)) { e.preventDefault(); }
        });
    });

    // Toggle sidebar on mobile
    var toggle = document.querySelector('.menu-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.querySelector('.admin-sidebar').classList.toggle('open');
        });
    }
})();
