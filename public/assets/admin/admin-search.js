/**
 * 通用后台 AJAX 搜索/筛选/分页/排序模块
 *
 * 统一应用于：文章、分类、标签、用户、评论 等列表页
 *
 * 用法：
 *   window.AdminSearch.init({
 *       searchUrl, pageWrap, tbodyId, paginationId, summaryId, summaryUnit,
 *       searchInputId, toggleBtnId, advancedFiltersId, filterCountBadgeId,
 *       resetBtnId, filterTabSelector, itemsKey, colspan, emptyText,
 *       stateDefaults, countedFilters, filterFields,
 *       buildParams, renderRow, onTabSelect, onReset, onAfterRender,
 *       batchEmptyAlert
 *   });
 *
 * 特性：
 * - 输入即搜（300ms 防抖）
 * - 多维度筛选（可折叠高级筛选面板）
 * - 状态标签横向滚动
 * - 列排序（点击表头切换 asc/desc）
 * - AJAX 分页
 * - 批量选择 + 批量操作
 * - 行内确认对话框、行内 toggle-form 重新绑定
 * - POST body 传参，彻底绕过 URL 编码问题（中文搜索稳定）
 */
(function () {
    'use strict';

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function init(config) {
        var state = Object.assign({}, config.stateDefaults || {});
        var loadingTimer = null;
        var abortController = null;

        var colspan = config.colspan || 7;
        var itemsKey = config.itemsKey || 'items';
        var tabSelector = config.filterTabSelector || '.filter-tab';

        // ===== 构建查询参数 =====
        function buildParams() {
            if (typeof config.buildParams === 'function') {
                return config.buildParams(state);
            }
            var params = new URLSearchParams();
            Object.keys(state).forEach(function (k) {
                var v = state[k];
                if (v !== '' && v !== 0 && v !== false && v !== null && v !== undefined) {
                    params.set(k, v);
                }
            });
            if (!params.has('sort')) params.set('sort', state.sort || 'created_at');
            if (!params.has('order')) params.set('order', state.order || 'desc');
            if (!params.has('page')) params.set('page', state.page || 1);
            return params.toString();
        }

        // ===== 执行搜索 =====
        function search() {
            if (abortController) {
                try { abortController.abort(); } catch (e) {}
            }
            abortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

            var tbody = document.getElementById(config.tbodyId);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="empty-cell">搜索中...</td></tr>';
            }

            fetch(config.searchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: buildParams(),
                credentials: 'same-origin',
                signal: abortController ? abortController.signal : undefined
            })
            .then(function (resp) {
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.json();
            })
            .then(function (data) {
                renderTable(data);
                renderPagination(data);
                renderSummary(data);
                updateSortArrows();
            })
            .catch(function (err) {
                if (err.name === 'AbortError') return;
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="empty-cell">搜索失败：' + esc(err.message) + '</td></tr>';
                }
            });
        }

        function debouncedSearch() {
            if (loadingTimer) clearTimeout(loadingTimer);
            loadingTimer = setTimeout(function () {
                state.page = 1;
                search();
            }, 300);
        }

        // ===== 渲染表格 =====
        function renderTable(data) {
            var tbody = document.getElementById(config.tbodyId);
            if (!tbody) return;

            var items = data[itemsKey] || [];
            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="empty-cell">' +
                    esc(config.emptyText || '暂无数据') + '</td></tr>';
                bindRowEvents();
                return;
            }

            var html = '';
            for (var i = 0; i < items.length; i++) {
                html += config.renderRow(items[i], state, config, esc);
            }
            tbody.innerHTML = html;
            bindRowEvents();
            if (typeof config.onAfterRender === 'function') {
                config.onAfterRender(state);
            }
        }

        // ===== 渲染分页 =====
        function renderPagination(data) {
            var nav = document.getElementById(config.paginationId);
            if (!nav) return;

            var totalPages = data.totalPages || 1;
            var currentPage = data.page || 1;

            if (totalPages <= 1) {
                nav.innerHTML = '';
                return;
            }

            var html = '';
            if (currentPage > 1) {
                html += '<a href="javascript:;" class="page-nav" data-page="' + (currentPage - 1) + '">&lsaquo;</a>';
            } else {
                html += '<span class="disabled page-nav">&lsaquo;</span>';
            }

            var start = Math.max(1, currentPage - 2);
            var end = Math.min(totalPages, currentPage + 2);
            if (start > 1) {
                html += '<a href="javascript:;" data-page="1">1</a>';
                if (start > 2) html += '<span class="page-ellipsis">&hellip;</span>';
            }
            for (var i = start; i <= end; i++) {
                if (i === currentPage) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="javascript:;" data-page="' + i + '">' + i + '</a>';
                }
            }
            if (end < totalPages) {
                if (end < totalPages - 1) html += '<span class="page-ellipsis">&hellip;</span>';
                html += '<a href="javascript:;" data-page="' + totalPages + '">' + totalPages + '</a>';
            }

            if (currentPage < totalPages) {
                html += '<a href="javascript:;" class="page-nav" data-page="' + (currentPage + 1) + '">&rsaquo;</a>';
            } else {
                html += '<span class="disabled page-nav">&rsaquo;</span>';
            }

            nav.innerHTML = html;

            nav.querySelectorAll('a[data-page]').forEach(function (a) {
                a.addEventListener('click', function () {
                    state.page = parseInt(this.getAttribute('data-page'));
                    search();
                });
            });
        }

        // ===== 渲染统计信息 =====
        function renderSummary(data) {
            var el = document.getElementById(config.summaryId);
            if (!el) return;
            var unit = config.summaryUnit || '项';
            el.innerHTML = '共 ' + (data.total || 0) + ' ' + unit + '，第 ' + (data.page || 1) + '/' + (data.totalPages || 1) + ' 页';
        }

        // ===== 更新排序箭头 =====
        function updateSortArrows() {
            var scope = config.pageWrap ? document.querySelector(config.pageWrap) : document;
            if (!scope) scope = document;
            scope.querySelectorAll('th.sortable').forEach(function (th) {
                var sortKey = th.getAttribute('data-sort');
                var arrow = th.querySelector('.sort-arrow');
                if (!arrow) return;
                if (sortKey === state.sort) {
                    th.classList.remove('asc', 'desc');
                    th.classList.add(state.order);
                    arrow.innerHTML = state.order === 'asc'
                        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:1;color:var(--c-primary)"><polyline points="18 15 12 9 6 15"/></svg>'
                        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:1;color:var(--c-primary)"><polyline points="6 9 12 15 18 9"/></svg>';
                } else {
                    th.classList.remove('asc', 'desc');
                    arrow.innerHTML = '';
                }
            });
        }

        // ===== 绑定行事件（确认框、批量选择、toggle-form） =====
        function bindRowEvents() {
            var scope = config.pageWrap ? document.querySelector(config.pageWrap) : document;
            if (!scope) scope = document;

            // 确认对话框（行内表单）
            scope.querySelectorAll('form[data-confirm]').forEach(function (form) {
                if (form._adminSearchBound) return;
                form._adminSearchBound = true;
                form.addEventListener('submit', function (e) {
                    if (!confirm(this.getAttribute('data-confirm'))) {
                        e.preventDefault();
                    }
                });
            });

            // toggle-form（行内编辑表单折叠）
            scope.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
                if (btn._adminSearchBound) return;
                btn._adminSearchBound = true;
                btn.addEventListener('click', function () {
                    var target = document.getElementById(this.getAttribute('data-toggle-form'));
                    if (target) target.classList.toggle('open');
                });
            });

            // 批量选择
            var checkAll = scope.querySelector('.check-all');
            var checkItems = scope.querySelectorAll('.check-item');
            if (checkAll) {
                checkAll.onchange = function () {
                    checkItems.forEach(function (cb) { cb.checked = checkAll.checked; });
                    updateBatchBar();
                };
            }
            checkItems.forEach(function (cb) {
                cb.onchange = updateBatchBar;
            });
        }

        function updateBatchBar() {
            var scope = config.pageWrap ? document.querySelector(config.pageWrap) : document;
            if (!scope) scope = document;
            var checked = scope.querySelectorAll('.check-item:checked');
            var countEl = scope.querySelector('.batch-count');
            if (countEl) countEl.textContent = checked.length;
            var batchBar = scope.querySelector('.batch-bar');
            if (batchBar) {
                batchBar.style.display = checked.length > 0 ? '' : 'none';
            }
            var ids = Array.from(checked).map(function (cb) { return cb.value; });
            var batchIds = scope.querySelector('input[name="batch_ids"]');
            if (batchIds) batchIds.value = ids.join(',');
        }

        // ===== 更新筛选计数角标 =====
        function updateFilterCount() {
            var count = 0;
            (config.countedFilters || []).forEach(function (key) {
                var v = state[key];
                if (v && v !== '' && v !== 0 && v !== '0') count++;
            });
            var badge = document.getElementById(config.filterCountBadgeId);
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // ===== 初始化事件绑定 =====
        function setup() {
            var scope = config.pageWrap ? document.querySelector(config.pageWrap) : document;
            if (!scope) scope = document;

            // 搜索输入 - 防抖
            var searchInput = document.getElementById(config.searchInputId);
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    state.q = this.value.trim();
                    debouncedSearch();
                });
            }

            // 高级筛选折叠切换
            var btnToggle = document.getElementById(config.toggleBtnId);
            var advancedFilters = document.getElementById(config.advancedFiltersId);
            if (btnToggle && advancedFilters) {
                btnToggle.addEventListener('click', function () {
                    var expanded = advancedFilters.style.display !== 'none';
                    if (expanded) {
                        advancedFilters.style.display = 'none';
                        btnToggle.setAttribute('aria-expanded', 'false');
                        btnToggle.classList.remove('active');
                    } else {
                        advancedFilters.style.display = '';
                        btnToggle.setAttribute('aria-expanded', 'true');
                        btnToggle.classList.add('active');
                    }
                });
            }

            // 状态标签
            scope.querySelectorAll(tabSelector).forEach(function (tab) {
                tab.addEventListener('click', function () {
                    scope.querySelectorAll(tabSelector).forEach(function (t) { t.classList.remove('active'); });
                    this.classList.add('active');
                    if (typeof config.onTabSelect === 'function') {
                        config.onTabSelect(this, state);
                    }
                    state.page = 1;
                    updateFilterCount();
                    search();
                });
            });

            // 高级筛选项
            (config.filterFields || []).forEach(function (f) {
                var el = document.getElementById(f.id);
                if (!el) return;
                el.addEventListener('change', function () {
                    if (f.type === 'int') {
                        state[f.stateKey] = parseInt(this.value) || 0;
                    } else {
                        state[f.stateKey] = this.value;
                    }
                    state.page = 1;
                    updateFilterCount();
                    search();
                });
            });

            // 重置筛选
            var btnReset = document.getElementById(config.resetBtnId);
            if (btnReset) {
                btnReset.addEventListener('click', function () {
                    Object.assign(state, config.stateDefaults || {});
                    state.page = 1;
                    if (searchInput) searchInput.value = '';
                    (config.filterFields || []).forEach(function (f) {
                        var el = document.getElementById(f.id);
                        if (!el) return;
                        el.value = f.type === 'int' ? '0' : '';
                    });
                    scope.querySelectorAll(tabSelector).forEach(function (t) { t.classList.remove('active'); });
                    var defaultTab = scope.querySelector(tabSelector + '.is-default');
                    if (defaultTab) defaultTab.classList.add('active');
                    if (typeof config.onReset === 'function') {
                        config.onReset(state);
                    }
                    updateFilterCount();
                    search();
                });
            }

            // 排序列头
            scope.querySelectorAll('th.sortable').forEach(function (th) {
                th.addEventListener('click', function () {
                    var sortKey = this.getAttribute('data-sort');
                    if (state.sort === sortKey) {
                        state.order = state.order === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.sort = sortKey;
                        state.order = 'asc';
                    }
                    search();
                });
            });

            // 批量操作按钮
            scope.querySelectorAll('[data-batch-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var action = this.getAttribute('data-batch-action');
                    var form = scope.querySelector('.batch-form');
                    if (!form) return;
                    var idsInput = form.querySelector('input[name="batch_ids"]');
                    if (!idsInput || !idsInput.value) {
                        alert(config.batchEmptyAlert || '请先选择项目');
                        return;
                    }
                    if (!confirm(this.getAttribute('data-confirm') || '确定执行此操作？')) return;
                    form.querySelector('input[name="batch_action"]').value = action;
                    form.submit();
                });
            });

            // 初始加载
            search();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }

        return {
            search: search,
            getState: function () { return state; }
        };
    }

    window.AdminSearch = { init: init, esc: esc };
})();
