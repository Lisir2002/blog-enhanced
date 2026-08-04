<?php
/** @var array $settings */
ob_start();
?>
<div class="page-header">
    <h2>系统设置</h2>
</div>

<form method="post" action="<?= route('admin.settings.save') ?>">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">

    <div class="card" style="max-width:720px">
        <div class="card-header">
            <h3 class="card-title">基本信息</h3>
        </div>
        <div class="form-group">
            <label>站点名称</label>
            <input type="text" name="site_name" value="<?= e($settings['site_name']) ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>站点描述</label>
            <textarea name="site_description" rows="2" class="form-control"><?= e($settings['site_description']) ?></textarea>
        </div>
        <div class="form-group">
            <label>关键词 <span class="label-hint">用逗号分隔</span></label>
            <input type="text" name="site_keywords" value="<?= e($settings['site_keywords']) ?>" class="form-control" placeholder="关键词1, 关键词2, 关键词3">
        </div>
        <div class="form-group">
            <label>站点 URL</label>
            <input type="url" name="site_url" value="<?= e($settings['site_url']) ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>Logo URL</label>
            <input type="url" name="logo_url" value="<?= e($settings['logo_url']) ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>每页文章数</label>
            <input type="number" name="posts_per_page" value="<?= e($settings['posts_per_page']) ?>" min="1" max="100" class="form-control" style="max-width:120px">
        </div>
        <div class="form-group">
            <label>页脚文字</label>
            <input type="text" name="footer_text" value="<?= e($settings['footer_text']) ?>" class="form-control">
        </div>
    </div>

    <div class="card" style="max-width:720px">
        <div class="card-header">
            <h3 class="card-title">交互设置</h3>
        </div>
        <div class="form-group">
            <label class="form-check">
                <input type="checkbox" name="allow_registration" value="1" <?= $settings['allow_registration'] === '1' ? 'checked' : '' ?>>
                允许用户注册
            </label>
        </div>
        <div class="form-group">
            <label class="form-check">
                <input type="checkbox" name="moderate_comments" value="1" <?= $settings['moderate_comments'] === '1' ? 'checked' : '' ?>>
                评论需审核
            </label>
        </div>
    </div>

    <div class="card" style="max-width:720px">
        <div class="card-header">
            <h3 class="card-title">统计代码</h3>
        </div>
        <div class="form-group">
            <label>百度统计 ID</label>
            <input type="text" name="baidu_analytics" value="<?= e($settings['baidu_analytics']) ?>" class="form-control" placeholder="例如: a1b2c3d4e5f6...">
        </div>
        <div class="form-group">
            <label>Google Analytics ID</label>
            <input type="text" name="google_analytics" value="<?= e($settings['google_analytics']) ?>" class="form-control" placeholder="例如: G-XXXXXXX">
        </div>
    </div>

    <div class="btn-row mt-8">
        <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            保存设置
        </button>
    </div>
</form>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');