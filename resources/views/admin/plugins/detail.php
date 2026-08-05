<?php
/** @var array $plugin  @var array|null $updateInfo  @var array $config */
ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <a href="<?= route('admin.plugins.index') ?>" class="btn btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            返回插件列表
        </a>
        <h2>插件详情</h2>
    </div>
    <div class="page-header-actions">
        <?php if ($plugin['active']): ?>
            <form method="post" action="<?= route('admin.plugins.deactivate', ['name' => $plugin['name']]) ?>" class="inline-form">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    停用
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= route('admin.plugins.activate', ['name' => $plugin['name']]) ?>" class="inline-form">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    激活
                </button>
            </form>
        <?php endif; ?>
        <?php if (!$plugin['active']): ?>
            <form method="post" action="<?= route('admin.plugins.delete', ['name' => $plugin['name']]) ?>" class="inline-form" data-confirm="确定删除此插件？">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-danger">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    删除
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="plugin-detail-grid">
    <!-- 左栏：基本信息 + 配置 -->
    <div class="plugin-detail-main">
        <!-- 基本信息卡片 -->
        <div class="plugin-detail-card">
            <div class="plugin-detail-hero">
                <div class="plugin-detail-icon <?= $plugin['active'] ? 'is-active' : '' ?>">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/>
                        <rect x="14" y="14" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/>
                    </svg>
                </div>
                <div class="plugin-detail-heroinfo">
                    <h3><?= e($plugin['meta']['name'] ?? $plugin['name']) ?></h3>
                    <div class="plugin-detail-herometa">
                        <span class="plugin-version-tag">v<?= e($plugin['meta']['version'] ?? '1.0') ?></span>
                        <?php if ($plugin['active']): ?>
                            <span class="plugin-badge plugin-badge-active">已激活</span>
                        <?php else: ?>
                            <span class="plugin-badge plugin-badge-inactive">未激活</span>
                        <?php endif; ?>
                        <?php if ($updateInfo && $updateInfo['update_available']): ?>
                            <span class="plugin-badge plugin-badge-update">新版本 <?= e($updateInfo['latest_version']) ?> 可用</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <p class="plugin-detail-desc"><?= e($plugin['meta']['description'] ?? '暂无描述') ?></p>

            <div class="plugin-detail-meta-grid">
                <?php if (!empty($plugin['meta']['author'])): ?>
                    <div class="detail-meta-item">
                        <span class="detail-meta-label">作者</span>
                        <span class="detail-meta-value">
                            <?php if (!empty($plugin['meta']['author_uri'])): ?>
                                <a href="<?= e($plugin['meta']['author_uri']) ?>" target="_blank"><?= e($plugin['meta']['author']) ?></a>
                            <?php else: ?>
                                <?= e($plugin['meta']['author']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">标识</span>
                    <span class="detail-meta-value"><code><?= e($plugin['name']) ?></code></span>
                </div>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">目录</span>
                    <span class="detail-meta-value"><code><?= e($plugin['dir']) ?></code></span>
                </div>
                <?php if (!empty($plugin['meta']['plugin_uri'])): ?>
                    <div class="detail-meta-item">
                        <span class="detail-meta-label">主页</span>
                        <span class="detail-meta-value"><a href="<?= e($plugin['meta']['plugin_uri']) ?>" target="_blank"><?= e($plugin['meta']['plugin_uri']) ?></a></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($plugin['meta']['license'])): ?>
                    <div class="detail-meta-item">
                        <span class="detail-meta-label">许可</span>
                        <span class="detail-meta-value"><?= e($plugin['meta']['license']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($plugin['meta']['requires_core'])): ?>
                    <div class="detail-meta-item">
                        <span class="detail-meta-label">核心版本</span>
                        <span class="detail-meta-value">≥ <?= e($plugin['meta']['requires_core']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($plugin['meta']['requires_php'])): ?>
                    <div class="detail-meta-item">
                        <span class="detail-meta-label">PHP 版本</span>
                        <span class="detail-meta-value">≥ <?= e($plugin['meta']['requires_php']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($plugin['errors'])): ?>
            <div class="plugin-detail-error-box">
                <h4>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    错误信息
                </h4>
                <div class="plugin-error-msg"><?= e($plugin['errors']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 插件配置 -->
        <?php if (!empty($config)): ?>
        <div class="plugin-detail-card">
            <h4 class="plugin-detail-card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                插件配置
            </h4>
            <form method="post" action="<?= route('admin.plugins.config', ['name' => $plugin['name']]) ?>">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <div class="plugin-config-grid">
                    <?php foreach ($config as $ckey => $cvalue): ?>
                        <?php if (is_string($cvalue) || is_numeric($cvalue)): ?>
                        <div class="config-row">
                            <label class="config-label"><?= e($ckey) ?></label>
                            <input type="text" name="<?= e($ckey) ?>" value="<?= e($cvalue) ?>" class="config-input">
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    保存配置
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右栏：依赖、更新信息 -->
    <div class="plugin-detail-side">
        <!-- 依赖信息 -->
        <div class="plugin-detail-card">
            <h4 class="plugin-detail-card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                依赖关系
            </h4>
            <div class="dep-section">
                <span class="dep-section-label">依赖</span>
                <?php $deps = $plugin['dependencies'] ?? []; ?>
                <?php if (empty($deps)): ?>
                    <span class="dep-empty">无依赖</span>
                <?php else: ?>
                    <div class="dep-tags">
                        <?php foreach ($deps as $dep): ?>
                            <span class="dep-tag"><?= e($dep) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="dep-section">
                <span class="dep-section-label">被依赖</span>
                <?php $dependents = $plugin['dependents'] ?? []; ?>
                <?php if (empty($dependents)): ?>
                    <span class="dep-empty">无插件依赖本插件</span>
                <?php else: ?>
                    <div class="dep-tags">
                        <?php foreach ($dependents as $dep): ?>
                            <span class="dep-tag"><?= e($dep) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 更新信息 -->
        <?php if (!empty($plugin['meta']['update_url'])): ?>
        <div class="plugin-detail-card">
            <h4 class="plugin-detail-card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><polyline points="21 3 21 8 16 8"/></svg>
                更新管理
            </h4>
            <form method="post" action="<?= route('admin.plugins.check-update', ['name' => $plugin['name']]) ?>" class="inline-form" style="margin-bottom:12px;">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-sm btn-secondary" style="width:100%;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    检查更新
                </button>
            </form>
            <?php if ($updateInfo): ?>
                <div class="update-info-box <?= $updateInfo['update_available'] ? 'has-update' : 'no-update' ?>">
                    <div class="update-info-row">
                        <span class="update-info-label">当前版本</span>
                        <span class="update-info-value"><?= e($plugin['meta']['version'] ?? '1.0') ?></span>
                    </div>
                    <div class="update-info-row">
                        <span class="update-info-label">最新版本</span>
                        <span class="update-info-value"><?= e($updateInfo['latest_version']) ?></span>
                    </div>
                    <?php if (!empty($updateInfo['changelog'])): ?>
                    <div class="update-info-row">
                        <span class="update-info-label">更新日志</span>
                        <div class="update-info-changelog"><?= nl2br(e($updateInfo['changelog'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($updateInfo['update_available']): ?>
                    <form method="post" action="<?= route('admin.plugins.do-update', ['name' => $plugin['name']]) ?>" class="inline-form" data-confirm="确定更新到 v<?= e($updateInfo['latest_version']) ?>？">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-sm btn-warning" style="width:100%;margin-top:8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            立即更新
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('[data-confirm]').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!confirm(this.dataset.confirm)) e.preventDefault();
    });
});
</script>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
