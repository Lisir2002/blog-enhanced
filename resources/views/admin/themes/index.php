<?php
/** @var array $themes
 *  @var string $active
 *  @var string $search
 *  @var string $statusFilter
 */
ob_start();

$search = $search ?? '';
$statusFilter = $statusFilter ?? '';

// 当前激活主题（用于顶部大卡片展示）
$activeTheme = null;
foreach ($themes as $t) {
    if ($t['name'] === $active) {
        $activeTheme = $t;
        break;
    }
}

// 所有主题都参与列表展示（包括当前主题）
$listThemes = $themes;

// 搜索过滤
if ($search !== '') {
    $listThemes = array_filter($listThemes, function ($t) use ($search) {
        return stripos($t['name'], $search) !== false ||
            stripos($t['meta']['name'] ?? '', $search) !== false ||
            stripos($t['meta']['description'] ?? '', $search) !== false ||
            stripos($t['meta']['author'] ?? '', $search) !== false;
    });
}

// 状态筛选
if ($statusFilter === 'active') {
    $listThemes = array_filter($listThemes, fn($t) => $t['name'] === $active);
} elseif ($statusFilter === 'inactive') {
    $listThemes = array_filter($listThemes, fn($t) => $t['name'] !== $active);
}

$listThemes = array_values($listThemes);
?>
<div class="page-header">
    <h2>主题管理</h2>
</div>

<?php if ($activeTheme): ?>
<!-- 当前激活主题：大卡片展示 -->
<div class="theme-featured">
    <div class="theme-featured-preview">
        <?php if (!empty($activeTheme['meta']['screenshot'])): ?>
            <img src="<?= url('themes/' . $activeTheme['name'] . '/' . $activeTheme['meta']['screenshot']) ?>" alt="<?= e($activeTheme['meta']['name'] ?? $activeTheme['name']) ?>">
        <?php else: ?>
            <div class="theme-featured-placeholder">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
        <?php endif; ?>
        <div class="theme-featured-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            当前主题
        </div>
    </div>
    <div class="theme-featured-info">
        <div class="theme-featured-meta">
            <h3><?= e($activeTheme['meta']['name'] ?? $activeTheme['name']) ?></h3>
            <span class="theme-version">v<?= e($activeTheme['meta']['version'] ?? '1.0') ?></span>
        </div>
        <p class="theme-description"><?= e($activeTheme['meta']['description'] ?? '暂无描述') ?></p>

        <div class="theme-meta-list">
            <?php if (!empty($activeTheme['meta']['author'])): ?>
                <div class="theme-meta-item">
                    <span class="meta-label">作者</span>
                    <span class="meta-value"><?= e($activeTheme['meta']['author']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($activeTheme['meta']['license'])): ?>
                <div class="theme-meta-item">
                    <span class="meta-label">许可证</span>
                    <span class="meta-value"><?= e($activeTheme['meta']['license']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($activeTheme['meta']['homepage'])): ?>
                <div class="theme-meta-item">
                    <span class="meta-label">主页</span>
                    <a href="<?= e($activeTheme['meta']['homepage']) ?>" target="_blank" rel="noopener" class="meta-value"><?= e($activeTheme['meta']['homepage']) ?></a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($activeTheme['meta']['sidebars'])): ?>
            <div class="theme-features">
                <span class="feature-label">侧边栏</span>
                <span class="feature-count"><?= count($activeTheme['meta']['sidebars']) ?> 个</span>
            </div>
        <?php endif; ?>
        <?php if (!empty($activeTheme['meta']['menus'])): ?>
            <div class="theme-features">
                <span class="feature-label">菜单位置</span>
                <span class="feature-count"><?= count($activeTheme['meta']['menus']) ?> 个</span>
            </div>
        <?php endif; ?>

        <div class="theme-featured-actions">
            <a href="<?= route('admin.themes.detail', ['name' => $activeTheme['name']]) ?>" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/></svg>
                查看详情
            </a>
            <a href="<?= url('/') ?>" target="_blank" rel="noopener" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                预览站点
            </a>
            <?php if (count($themes) > 1): ?>
                <span class="theme-lock-hint">如需更换主题，请从下方选择</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 上传新主题 -->
<div class="theme-upload-section">
    <h3 class="section-title">安装新主题</h3>
    <p class="section-desc">上传 .zip 格式的主题包，或将主题文件夹手动放入 <code>themes/</code> 目录</p>
    <form method="post" action="<?= route('admin.themes.upload') ?>" enctype="multipart/form-data" class="theme-upload-box" id="themeUploadBox">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div class="upload-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
        <p class="upload-text">拖拽主题包到此处，或 <span class="upload-link">点击选择文件</span></p>
        <p class="upload-hint">仅支持 .zip 格式，最大 20MB</p>
        <input type="file" name="theme_zip" accept=".zip" id="themeZipInput" required hidden>
        <button type="submit" class="btn btn-primary" id="themeUploadBtn" disabled>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            安装主题
        </button>
    </form>
</div>

<!-- 筛选区域 -->
<div class="admin-filter-bar">
    <div class="admin-filter-row-top">
        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="themeSearchInput" placeholder="搜索主题名称、描述、作者..." value="<?= e($search) ?>" class="form-control" autocomplete="off">
        </div>
    </div>
    <div class="filter-tabs admin-filter-tabs">
        <a href="javascript:;" class="filter-tab is-default <?= $statusFilter === '' ? 'active' : '' ?>" data-status="">全部</a>
        <a href="javascript:;" class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>" data-status="active">当前主题</a>
        <a href="javascript:;" class="filter-tab <?= $statusFilter === 'inactive' ? 'active' : '' ?>" data-status="inactive">其他主题</a>
    </div>
</div>

<div class="theme-list-section">
    <div class="theme-list-header">
        <h3 class="section-title">可用主题</h3>
        <span class="theme-count-badge"><?= count($listThemes) ?> 个</span>
    </div>

    <?php if (!empty($listThemes)): ?>
    <div class="theme-grid">
        <?php foreach ($listThemes as $t): $isActiveTheme = ($t['name'] === $active); ?>
            <div class="theme-card <?= $isActiveTheme ? 'active' : '' ?>">
                <div class="theme-card-preview">
                    <?php if (!empty($t['meta']['screenshot'])): ?>
                        <img src="<?= url('themes/' . $t['name'] . '/' . $t['meta']['screenshot']) ?>" alt="<?= e($t['meta']['name'] ?? $t['name']) ?>">
                    <?php else: ?>
                        <div class="theme-card-placeholder">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                    <?php endif; ?>
                    <?php if ($isActiveTheme): ?>
                    <div class="theme-card-active-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        当前主题
                    </div>
                    <?php endif; ?>
                </div>
                <div class="theme-card-body">
                    <div class="theme-card-title-row">
                        <h4><?= e($t['meta']['name'] ?? $t['name']) ?></h4>
                        <span class="theme-card-version">v<?= e($t['meta']['version'] ?? '1.0') ?></span>
                    </div>
                    <p class="theme-card-desc"><?= e($t['meta']['description'] ?? '暂无描述') ?></p>
                    <div class="theme-card-meta">
                        <?php if (!empty($t['meta']['author'])): ?>
                            <span>by <?= e($t['meta']['author']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="theme-card-actions">
                        <a href="<?= route('admin.themes.detail', ['name' => $t['name']]) ?>" class="btn btn-sm btn-secondary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/></svg>
                            详情
                        </a>
                        <?php if (!$isActiveTheme): ?>
                        <form method="post" action="<?= route('admin.themes.activate', ['name' => $t['name']]) ?>" class="inline-form">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                激活
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="theme-empty">
            <div class="theme-empty-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <p><?= ($search !== '' || $statusFilter !== '') ? '未找到匹配的主题' : '暂无可用主题' ?></p>
            <p class="theme-empty-hint"><?= ($search !== '' || $statusFilter !== '') ? '尝试其他搜索条件' : '上传新主题包来扩展外观' ?></p>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var themeIndexUrl = '<?= route('admin.themes.index') ?>';
    var currentSearch = '<?= e($search) ?>';
    var currentStatus = '<?= e($statusFilter) ?>';

    function goFilter(opts) {
        var q = ('q' in opts ? opts.q : currentSearch);
        var s = ('status' in opts ? opts.status : currentStatus);
        var params = [];
        if (q) params.push('q=' + encodeURIComponent(q));
        if (s) params.push('status=' + encodeURIComponent(s));
        window.location.href = themeIndexUrl + (params.length ? '?' + params.join('&') : '');
    }

    // 搜索框：输入防抖跳转
    var searchInput = document.getElementById('themeSearchInput');
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

    // 状态标签：点击切换状态筛选
    document.querySelectorAll('[data-status]').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            var status = this.getAttribute('data-status') || '';
            if (status !== currentStatus) {
                goFilter({ status: status });
            }
        });
    });

    // 上传区域拖拽
    var box = document.getElementById('themeUploadBox');
    var input = document.getElementById('themeZipInput');
    var btn = document.getElementById('themeUploadBtn');
    var link = document.querySelector('.upload-link');

    if (!box || !input || !btn) return;

    function setActive() {
        box.classList.add('is-dragover');
    }
    function setInactive() {
        box.classList.remove('is-dragover');
    }
    function handleFile(file) {
        if (!file) return;
        if (!/\.zip$/i.test(file.name)) {
            alert('请上传 .zip 格式的文件');
            return;
        }
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        btn.disabled = false;
        btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    box.addEventListener('dragover', function(e) { e.preventDefault(); setActive(); });
    box.addEventListener('dragleave', function(e) { e.preventDefault(); setInactive(); });
    box.addEventListener('drop', function(e) {
        e.preventDefault();
        setInactive();
        if (e.dataTransfer && e.dataTransfer.files.length) {
            handleFile(e.dataTransfer.files[0]);
        }
    });
    box.addEventListener('click', function(e) {
        if (e.target.closest('button')) return;
        input.click();
    });
    if (link) {
        link.addEventListener('click', function(e) {
            e.stopPropagation();
            input.click();
        });
    }
    input.addEventListener('change', function() {
        if (input.files.length) {
            btn.disabled = false;
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
