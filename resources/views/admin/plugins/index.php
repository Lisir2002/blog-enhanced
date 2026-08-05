<?php
/** @var array $plugins  @var array $active  @var array $errors  @var string $search  @var string $statusFilter  @var array $cycles  @var bool $hasCycles */
ob_start();

// 统计数据
$totalPlugins = count($plugins);
$activeCount = 0;
$inactiveCount = 0;
$errorCount = 0;
foreach ($plugins as $p) {
    if (isset($active[$p['name']])) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if (!empty($errors[$p['name']])) {
        $errorCount++;
    }
}
?>

<!-- 页面头部 -->
<div class="plugin-page-header">
    <div class="plugin-page-title">
        <h2>插件管理</h2>
        <p>管理已安装的插件，激活、停用或上传新插件</p>
    </div>
    <div class="plugin-page-actions">
        <div class="view-toggle" id="pluginViewToggle">
            <button type="button" class="view-toggle-btn active" data-view="grid" title="网格视图">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </button>
            <button type="button" class="view-toggle-btn" data-view="list" title="列表视图">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
        </div>
        <button type="button" class="btn btn-primary" id="uploadToggle">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            上传插件
        </button>
    </div>
</div>

<!-- 上传区域（可折叠） -->
<div class="plugin-upload-section" id="pluginUploadSection" style="display:none;">
    <form method="post" action="<?= route('admin.plugins.upload') ?>" enctype="multipart/form-data" class="plugin-upload-box" id="upload-form">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div class="upload-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
        <p class="upload-text">拖拽插件包到此处，或 <span class="upload-link">点击选择文件</span></p>
        <p class="upload-hint">仅支持 .zip 格式，最大 20MB</p>
        <input type="file" name="plugin_zip" accept=".zip" id="plugin-zip-input" required hidden>
        <button type="submit" class="btn btn-primary" id="upload-btn" disabled>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            安装插件
        </button>
    </form>
</div>

<?php if ($hasCycles): ?>
<div class="alert alert-warning">
    <strong>检测到循环依赖！</strong> 以下插件之间存在循环依赖，可能导致不稳定：
    <ul>
        <?php foreach ($cycles as $cycle): ?>
            <li><?= implode(' &rarr; ', array_map('e', $cycle)) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 统计卡片 -->
<div class="plugin-stats-bar">
    <a href="javascript:;" class="plugin-stat-item <?= $statusFilter === '' ? 'is-current' : '' ?>" data-status="">
        <div class="stat-icon stat-icon-total">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8"/><rect x="14" y="2" width="8" height="8"/><rect x="14" y="14" width="8" height="8"/><rect x="2" y="14" width="8" height="8"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-num"><?= $totalPlugins ?></span>
            <span class="stat-label">总插件</span>
        </div>
    </a>
    <a href="javascript:;" class="plugin-stat-item <?= $statusFilter === 'active' ? 'is-current' : '' ?>" data-status="active">
        <div class="stat-icon stat-icon-active">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-num"><?= $activeCount ?></span>
            <span class="stat-label">已激活</span>
        </div>
    </a>
    <a href="javascript:;" class="plugin-stat-item <?= $statusFilter === 'inactive' ? 'is-current' : '' ?>" data-status="inactive">
        <div class="stat-icon stat-icon-inactive">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-num"><?= $inactiveCount ?></span>
            <span class="stat-label">未激活</span>
        </div>
    </a>
    <?php if ($errorCount > 0): ?>
    <a href="javascript:;" class="plugin-stat-item <?= $statusFilter === 'error' ? 'is-current' : '' ?>" data-status="error">
        <div class="stat-icon stat-icon-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-num"><?= $errorCount ?></span>
            <span class="stat-label">有异常</span>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- 筛选区域 -->
<div class="admin-filter-bar">
    <div class="admin-filter-row-top">
        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="pluginSearchInput" placeholder="搜索插件名称、描述..." value="<?= e($search) ?>" class="form-control" autocomplete="off">
        </div>
    </div>
    <div class="filter-tabs admin-filter-tabs">
        <a href="javascript:;" class="filter-tab is-default <?= $statusFilter === '' ? 'active' : '' ?>" data-status="">全部</a>
        <a href="javascript:;" class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>" data-status="active">已激活</a>
        <a href="javascript:;" class="filter-tab <?= $statusFilter === 'inactive' ? 'active' : '' ?>" data-status="inactive">未激活</a>
        <?php if ($errorCount > 0): ?>
        <a href="javascript:;" class="filter-tab <?= $statusFilter === 'error' ? 'active' : '' ?>" data-status="error">有异常</a>
        <?php endif; ?>
    </div>
</div>

<!-- 批量操作 -->
<div class="batch-actions-bar" id="batch-actions" style="display:none">
    <span class="batch-count" id="batch-count">已选择 0 个插件</span>
    <div class="batch-actions-buttons">
        <button type="button" class="btn btn-sm btn-primary" data-batch-action="activate">批量激活</button>
        <button type="button" class="btn btn-sm btn-secondary" data-batch-action="deactivate">批量停用</button>
        <button type="button" class="btn btn-sm btn-danger" data-batch-action="delete" data-confirm="确定批量删除选中的插件？">批量删除</button>
        <button type="button" class="btn btn-sm btn-link" id="batch-clear">取消选择</button>
    </div>
</div>

<form method="post" id="batch-form" action="<?= route('admin.plugins.batch') ?>">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="batch_ids" id="batch-ids" value="">
    <input type="hidden" name="batch_action" id="batch-action" value="">
</form>

<!-- 插件列表 -->
<div class="plugin-list-section" id="pluginListSection" data-view="grid">
    <?php if (!empty($plugins)): ?>
    <div class="plugin-grid">
        <?php foreach ($plugins as $p): $isActive = isset($active[$p['name']]); $pErrors = $errors[$p['name']] ?? null; ?>
            <div class="plugin-card <?= $isActive ? 'active' : '' ?> <?= $pErrors ? 'has-error' : '' ?>">
                <div class="plugin-card-status <?= $isActive ? 'is-on' : 'is-off' ?>"></div>
                <div class="plugin-card-body">
                    <div class="plugin-card-top">
                        <label class="plugin-card-checkbox">
                            <input type="checkbox" class="plugin-checkbox" value="<?= e($p['name']) ?>" data-name="<?= e($p['meta']['name'] ?? $p['name']) ?>">
                            <span class="checkbox-mark"></span>
                        </label>
                        <div class="plugin-card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/><rect x="14" y="14" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/></svg>
                        </div>
                        <div class="plugin-card-headinfo">
                            <h3 class="plugin-card-title">
                                <a href="<?= route('admin.plugins.detail', ['name' => $p['name']]) ?>"><?= e($p['meta']['name'] ?? $p['name']) ?></a>
                                <span class="plugin-card-version">v<?= e($p['meta']['version'] ?? '1.0') ?></span>
                            </h3>
                            <div class="plugin-badges">
                                <?php if ($isActive): ?>
                                    <span class="plugin-badge plugin-badge-active">已激活</span>
                                <?php else: ?>
                                    <span class="plugin-badge plugin-badge-inactive">未激活</span>
                                <?php endif; ?>
                                <?php if ($pErrors): ?>
                                    <span class="plugin-badge plugin-badge-error" title="<?= e($pErrors) ?>">异常</span>
                                <?php endif; ?>
                                <?php if (!empty($p['meta']['update_url'])): ?>
                                    <span class="plugin-badge plugin-badge-update">可更新</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <p class="plugin-card-desc"><?= e($p['meta']['description'] ?? '暂无描述') ?></p>

                    <div class="plugin-card-meta">
                        <?php if (!empty($p['meta']['author'])): ?>
                            <span class="meta-chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?= e($p['meta']['author']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($p['meta']['depends_on'])): $dd = is_string($p['meta']['depends_on']) ? [$p['meta']['depends_on']] : $p['meta']['depends_on']; ?>
                            <span class="meta-chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                依赖 <?= count($dd) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($pErrors): ?>
                        <div class="plugin-card-error" title="<?= e($pErrors) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= e(mb_strlen($pErrors) > 80 ? mb_substr($pErrors, 0, 80) . '...' : $pErrors) ?>
                        </div>
                    <?php endif; ?>

                    <div class="plugin-card-actions">
                        <a href="<?= route('admin.plugins.detail', ['name' => $p['name']]) ?>" class="btn btn-sm btn-secondary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/></svg>
                            详情
                        </a>
                        <?php if ($isActive): ?>
                            <form method="post" action="<?= route('admin.plugins.deactivate', ['name' => $p['name']]) ?>" class="inline-form">
                                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                    停用
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= route('admin.plugins.activate', ['name' => $p['name']]) ?>" class="inline-form">
                                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                    激活
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$isActive): ?>
                            <form method="post" action="<?= route('admin.plugins.delete', ['name' => $p['name']]) ?>" class="inline-form" data-confirm="确定删除此插件？">
                                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    删除
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="plugin-empty">
            <div class="plugin-empty-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/><rect x="14" y="14" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/></svg>
            </div>
            <p><?= $search ? '未找到匹配的插件' : '暂无插件' ?></p>
            <p class="plugin-empty-hint"><?= $search ? '尝试其他搜索条件' : '点击上方"上传插件"安装新插件' ?></p>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var pluginIndexUrl = '<?= route('admin.plugins.index') ?>';
    var currentSearch = '<?= e($search) ?>';
    var currentStatus = '<?= e($statusFilter) ?>';

    // 跳转：根据搜索词和状态筛选拼装 URL
    function goFilter(opts) {
        var q = ('q' in opts ? opts.q : currentSearch);
        var s = ('status' in opts ? opts.status : currentStatus);
        var params = [];
        if (q) params.push('q=' + encodeURIComponent(q));
        if (s) params.push('status=' + encodeURIComponent(s));
        window.location.href = pluginIndexUrl + (params.length ? '?' + params.join('&') : '');
    }

    // 搜索框：输入防抖跳转（对齐其他后台页面的搜索写法）
    var searchInput = document.getElementById('pluginSearchInput');
    if (searchInput) {
        var debounceTimer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                var val = searchInput.value.trim();
                if (val !== currentSearch) {
                    goFilter({ q: val });
                }
            }, 400);
        });
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                goFilter({ q: searchInput.value.trim() });
            }
        });
    }

    // 状态标签 / 统计卡片：点击切换状态筛选
    document.querySelectorAll('[data-status]').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            var status = this.getAttribute('data-status') || '';
            if (status !== currentStatus) {
                goFilter({ status: status });
            }
        });
    });

    // 上传区域折叠
    var uploadToggle = document.getElementById('uploadToggle');
    var uploadSection = document.getElementById('pluginUploadSection');
    if (uploadToggle && uploadSection) {
        uploadToggle.addEventListener('click', function() {
            var isHidden = uploadSection.style.display === 'none';
            uploadSection.style.display = isHidden ? 'block' : 'none';
            uploadToggle.classList.toggle('is-active', isHidden);
            if (isHidden) uploadSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    // 视图切换
    var listSection = document.getElementById('pluginListSection');
    document.querySelectorAll('.view-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var view = this.dataset.view;
            document.querySelectorAll('.view-toggle-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            listSection.dataset.view = view;
        });
    });

    // 批量操作
    var checkboxes = document.querySelectorAll('.plugin-checkbox');
    var batchActions = document.getElementById('batch-actions');
    var batchCount = document.getElementById('batch-count');
    var batchIds = document.getElementById('batch-ids');
    var batchAction = document.getElementById('batch-action');
    var batchForm = document.getElementById('batch-form');
    var clearBtn = document.getElementById('batch-clear');

    function updateBatchUI() {
        var checked = document.querySelectorAll('.plugin-checkbox:checked');
        if (checked.length > 0) {
            batchActions.style.display = 'flex';
            batchCount.textContent = '已选择 ' + checked.length + ' 个插件';
            var ids = Array.from(checked).map(function(cb) { return cb.value; }).join(',');
            batchIds.value = ids;
        } else {
            batchActions.style.display = 'none';
            batchIds.value = '';
        }
    }

    checkboxes.forEach(function(cb) { cb.addEventListener('change', updateBatchUI); });

    document.querySelectorAll('[data-batch-action]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var action = this.dataset.batchAction;
            var confirmMsg = this.dataset.confirm;
            if (confirmMsg && !confirm(confirmMsg)) return;
            batchAction.value = action;
            batchForm.submit();
        });
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            checkboxes.forEach(function(cb) { cb.checked = false; });
            updateBatchUI();
        });
    }

    // 确认删除
    document.querySelectorAll('[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });

    // 拖拽上传
    var uploadBox = document.getElementById('upload-form');
    var fileInput = document.getElementById('plugin-zip-input');
    var uploadBtn = document.getElementById('upload-btn');

    if (uploadBox && fileInput) {
        uploadBox.addEventListener('click', function(e) {
            if (e.target.closest('button')) return;
            fileInput.click();
        });
        uploadBox.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('is-dragover');
        });
        uploadBox.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('is-dragover');
        });
        uploadBox.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('is-dragover');
            var files = e.dataTransfer.files;
            if (files.length > 0 && files[0].name.endsWith('.zip')) {
                fileInput.files = files;
                if (uploadBtn) uploadBtn.disabled = false;
            }
        });
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length && uploadBtn) {
                uploadBtn.disabled = false;
            }
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
