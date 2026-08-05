<?php
/**
 * 主题详情页
 * @var array $theme
 * @var string $active
 * @var array $screenshots
 * @var array $changelog
 * @var array $pageTemplates
 * @var array $menuLocations
 * @var array $sidebars
 * @var array $recommendedPlugins
 * @var string $category
 * @var array $tags
 * @var string $requires
 * @var string $requiresPhp
 * @var string $demoUrl
 * @var string|null $parentTheme
 * @var bool $isActive
 */
ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <a href="<?= route('admin.themes.index') ?>" class="btn btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            返回主题列表
        </a>
        <h2><?= e($theme['meta']['name'] ?? $theme['name']) ?></h2>
    </div>
    <div class="page-header-actions">
        <a href="<?= route('admin.themes.customize', ['name' => $theme['name']]) ?>" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            自定义
        </a>
        <?php if (!$isActive): ?>
            <form method="post" action="<?= route('admin.themes.activate', ['name' => $theme['name']]) ?>" class="inline-form">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    激活主题
                </button>
            </form>
        <?php endif; ?>
        <a href="<?= route('admin.themes.preview', ['name' => $theme['name']]) ?>" target="_blank" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            预览
        </a>
    </div>
</div>

<div class="theme-detail-grid">
    <!-- 截图区 -->
    <div class="theme-detail-screenshots">
        <?php if (!empty($screenshots)): ?>
            <div class="theme-detail-preview">
                <img src="<?= url('themes/' . $theme['name'] . '/' . $screenshots[0]) ?>" alt="<?= e($theme['meta']['name'] ?? $theme['name']) ?>" id="themeMainScreenshot">
            </div>
            <?php if (count($screenshots) > 1): ?>
            <div class="theme-detail-thumbs">
                <?php foreach ($screenshots as $idx => $ss): ?>
                    <button class="theme-thumb <?= $idx === 0 ? 'active' : '' ?>" data-src="<?= url('themes/' . $theme['name'] . '/' . $ss) ?>">
                        <img src="<?= url('themes/' . $theme['name'] . '/' . $ss) ?>" alt="截图 <?= $idx + 1 ?>">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="theme-detail-placeholder">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <p>暂无截图</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- 信息区 -->
    <div class="theme-detail-info">
        <?php if ($isActive): ?>
            <div class="theme-detail-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                当前激活
            </div>
        <?php endif; ?>

        <div class="theme-detail-meta">
            <div class="meta-row">
                <span class="meta-label">版本</span>
                <span class="meta-value">v<?= e($theme['meta']['version'] ?? '1.0') ?></span>
            </div>
            <?php if (!empty($theme['meta']['author'])): ?>
            <div class="meta-row">
                <span class="meta-label">作者</span>
                <span class="meta-value"><?= e($theme['meta']['author']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($parentTheme): ?>
            <div class="meta-row">
                <span class="meta-label">父主题</span>
                <span class="meta-value"><?= e($parentTheme) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($category): ?>
            <div class="meta-row">
                <span class="meta-label">分类</span>
                <span class="meta-value"><?= e($category) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($requires): ?>
            <div class="meta-row">
                <span class="meta-label">系统要求</span>
                <span class="meta-value"><?= e($requires) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($requiresPhp): ?>
            <div class="meta-row">
                <span class="meta-label">PHP 要求</span>
                <span class="meta-value"><?= e($requiresPhp) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($theme['meta']['license'])): ?>
            <div class="meta-row">
                <span class="meta-label">许可证</span>
                <span class="meta-value"><?= e($theme['meta']['license']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($demoUrl): ?>
            <div class="meta-row">
                <span class="meta-label">演示</span>
                <a href="<?= e($demoUrl) ?>" target="_blank" rel="noopener" class="meta-value">查看演示</a>
            </div>
            <?php endif; ?>
            <?php if (!empty($theme['meta']['homepage'])): ?>
            <div class="meta-row">
                <span class="meta-label">主页</span>
                <a href="<?= e($theme['meta']['homepage']) ?>" target="_blank" rel="noopener" class="meta-value"><?= e($theme['meta']['homepage']) ?></a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($tags)): ?>
        <div class="theme-detail-tags">
            <?php foreach ($tags as $tag): ?>
                <span class="tag"><?= e($tag) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 功能特性 -->
        <div class="theme-detail-features">
            <h4>功能特性</h4>
            <div class="features-grid">
                <div class="feature-item">
                    <span class="feature-count"><?= count($menuLocations) ?></span>
                    <span class="feature-label">菜单位置</span>
                </div>
                <div class="feature-item">
                    <span class="feature-count"><?= count($sidebars) ?></span>
                    <span class="feature-label">侧边栏</span>
                </div>
                <div class="feature-item">
                    <span class="feature-count"><?= count($pageTemplates) ?></span>
                    <span class="feature-label">页面模板</span>
                </div>
                <?php if (!empty($recommendedPlugins)): ?>
                <div class="feature-item">
                    <span class="feature-count"><?= count($recommendedPlugins) ?></span>
                    <span class="feature-label">推荐插件</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 推荐插件 -->
        <?php if (!empty($recommendedPlugins)): ?>
        <div class="theme-detail-block">
            <h4>推荐插件</h4>
            <div class="plugin-tags">
                <?php foreach ($recommendedPlugins as $p): ?>
                    <span class="tag tag-plugin"><?= e($p) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 变更日志 -->
        <?php if (!empty($changelog)): ?>
        <div class="theme-detail-block">
            <h4>变更日志</h4>
            <div class="changelog-list">
                <?php foreach ($changelog as $version => $note): ?>
                    <div class="changelog-item">
                        <span class="changelog-version">v<?= e($version) ?></span>
                        <span class="changelog-note"><?= e($note) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var thumbs = document.querySelectorAll('.theme-thumb');
    var mainImg = document.getElementById('themeMainScreenshot');
    if (!thumbs.length || !mainImg) return;
    thumbs.forEach(function(btn) {
        btn.addEventListener('click', function() {
            thumbs.forEach(function(t) { t.classList.remove('active'); });
            btn.classList.add('active');
            mainImg.src = btn.dataset.src;
        });
    });
})();
</script>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');