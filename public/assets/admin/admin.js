// Admin Panel JS
(function () {
    'use strict';

    // ===== Sidebar toggle (mobile) =====
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('adminOverlay');
    
    function toggleSidebar(open) {
        var willOpen = open !== undefined ? open : !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', willOpen);
        if (overlay) {
            overlay.classList.toggle('active', willOpen);
        }
    }

    if (sidebar && toggle) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidebar();
        });

        // Close when clicking overlay
        if (overlay) {
            overlay.addEventListener('click', function () {
                toggleSidebar(false);
            });
        }

        // Close when clicking outside sidebar
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                toggleSidebar(false);
            }
        });
        
        // Handle resize
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                toggleSidebar(false);
            }
        });
    }

    // ===== Confirm dialogs =====
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = this.getAttribute('data-confirm') || '确定执行此操作？';
            if (!confirm(msg)) { e.preventDefault(); }
        });
    });

    // ===== Toggle form visibility (categories, tags, etc.) =====
    document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(this.getAttribute('data-toggle-form'));
            if (target) {
                target.classList.toggle('open');
            }
        });
    });

    // ===== Markdown preview in post editor =====
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

    // ===== Batch Operations (Check All + Batch Actions) =====
    document.querySelectorAll('.table-wrap').forEach(function (wrap) {
        var checkAll = wrap.querySelector('.check-all');
        if (!checkAll) return;
        var checkboxes = wrap.querySelectorAll('.check-item');
        var batchBar = wrap.querySelector('.batch-bar');
        var batchCount = batchBar ? batchBar.querySelector('.batch-count') : null;

        // Check all toggle
        checkAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) { cb.checked = checkAll.checked; });
            updateBatchBar();
        });

        // Individual checkbox changes
        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                var allChecked = true;
                checkboxes.forEach(function (c) { if (!c.checked) allChecked = false; });
                if (checkAll) checkAll.checked = allChecked;
                updateBatchBar();
            });
        });

        function updateBatchBar() {
            if (!batchBar) return;
            var checked = [];
            checkboxes.forEach(function (cb) {
                if (cb.checked) checked.push(cb.value);
            });
            if (checked.length > 0) {
                batchBar.classList.add('show');
                if (batchCount) batchCount.textContent = checked.length;
            } else {
                batchBar.classList.remove('show');
            }
        }

        // Batch action buttons with improved confirmation showing item names
        if (batchBar) {
            batchBar.querySelectorAll('[data-batch-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var action = this.getAttribute('data-batch-action');
                    var form = wrap.querySelector('.batch-form');
                    if (!form) return;
                    var ids = [];
                    var names = [];
                    checkboxes.forEach(function (cb) {
                        if (cb.checked) {
                            ids.push(cb.value);
                            // Get the item name from the first text column in the same row
                            var row = cb.closest('tr');
                            if (row) {
                                var nameCell = row.querySelector('td:nth-child(2)');
                                if (nameCell) {
                                    var name = nameCell.textContent || nameCell.innerText || '';
                                    names.push(name.trim().substring(0, 30));
                                }
                            }
                        }
                    });
                    if (ids.length === 0) return;

                    var customMsg = this.getAttribute('data-confirm') || '';
                    if (customMsg) {
                        // Custom confirm message already provided
                        if (!confirm(customMsg)) return;
                    } else {
                        // Show item names in confirmation
                        var nameList = names.length > 0 ? '\n\n' + names.slice(0, 5).join('\n') + (names.length > 5 ? '\n... 及其他 ' + (names.length - 5) + ' 项' : '') : '';
                        var msg = '确定对选中的 ' + ids.length + ' 项执行此操作？' + nameList;
                        if (!confirm(msg)) return;
                    }

                    form.querySelector('[name="batch_ids"]').value = ids.join(',');
                    form.querySelector('[name="batch_action"]').value = action;
                    form.submit();
                });
            });
        }
    });

    // ===== Search form: properly encode query params for PHP built-in server =====
    // 仅对非 AJAX 搜索页面生效（posts 页面使用独立的 AJAX 搜索）
    document.querySelectorAll('.filter-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var qInput = this.querySelector('input[name="q"]');
            var sortInput = this.querySelector('input[name="sort"]');
            var orderInput = this.querySelector('input[name="order"]');
            var trashInput = this.querySelector('input[name="trash"]');

            var params = [];
            if (qInput && qInput.value) {
                params.push('q=' + encodeURIComponent(qInput.value));
            }
            if (sortInput && sortInput.value) {
                params.push('sort=' + encodeURIComponent(sortInput.value));
            }
            if (orderInput && orderInput.value) {
                params.push('order=' + encodeURIComponent(orderInput.value));
            }
            if (trashInput && trashInput.value) {
                params.push('trash=' + encodeURIComponent(trashInput.value));
            }
            var baseUrl = window.location.pathname;
            window.location.href = baseUrl + (params.length > 0 ? '?' + params.join('&') : '');
        });
    });

    // ===== Sortable Column Headers =====
    // 如果页面是 AJAX 搜索页面（统一使用 AdminSearchConfig），则跳过此处理器
    // 由 admin-search.js 负责处理列排序
    if (!window.AdminSearchConfig) {
        document.querySelectorAll('th.sortable').forEach(function (th) {
            th.addEventListener('click', function () {
                var sortKey = this.getAttribute('data-sort');
                if (!sortKey) return;
                var url = new URL(window.location.href);
                var currentSort = url.searchParams.get('sort') || '';
                var currentOrder = url.searchParams.get('order') || 'desc';

                if (currentSort === sortKey) {
                    currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    currentOrder = 'desc';
                }

                url.searchParams.set('sort', sortKey);
                url.searchParams.set('order', currentOrder);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            });
        });
    }
})();