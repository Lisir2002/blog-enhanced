// Admin Panel JS
(function () {
    'use strict';

    // Sidebar toggle (mobile)
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('sidebarToggle');
    if (sidebar && toggle) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // Confirm dialogs
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = this.getAttribute('data-confirm') || '确定执行此操作？';
            if (!confirm(msg)) { e.preventDefault(); }
        });
    });

    // Toggle form visibility (categories, tags, etc.)
    document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(this.getAttribute('data-toggle-form'));
            if (target) {
                target.classList.toggle('open');
            }
        });
    });

    // Markdown preview in post editor
    var md = document.getElementById('post-content-md');
    var preview = document.getElementById('preview-pane');
    if (md && preview) {
        var timer = null;
        function renderPreview() {
            var token = document.querySelector('input[name=_token]');
            fetch('/admin/posts/preview', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(token ? token.value : '') +
                      '&content=' + encodeURIComponent(md.value),
            }).then(function (r) { return r.text(); }).then(function (html) {
                preview.innerHTML = html;
            }).catch(function () {
                preview.innerHTML = '<pre style="white-space:pre-wrap;word-break:break-word;padding:16px;margin:0">' + md.value + '</pre>';
            });
        }
        md.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(renderPreview, 500);
        });
        renderPreview();
    }
})();