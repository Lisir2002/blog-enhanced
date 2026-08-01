<?php
/** @var array $settings */
ob_start();
?>
<form method="post" action="<?= route('admin.settings.save') ?>">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div class="card" style="max-width:700px">
        <h3 class="card-title">基本信息</h3>
        <div class="form-group">
            <label>站点名称</label>
            <input type="text" name="site_name" value="<?= e($settings['site_name']) ?>">
        </div>
        <div class="form-group">
            <label>站点描述</label>
            <textarea name="site_description" rows="2"><?= e($settings['site_description']) ?></textarea>
        </div>
        <div class="form-group">
            <label>关键词（用逗号分隔）</label>
            <input type="text" name="site_keywords" value="<?= e($settings['site_keywords']) ?>">
        </div>
        <div class="form-group">
            <label>站点 URL</label>
            <input type="url" name="site_url" value="<?= e($settings['site_url']) ?>">
        </div>
        <div class="form-group">
            <label>Logo URL</label>
            <input type="url" name="logo_url" value="<?= e($settings['logo_url']) ?>">
        </div>
        <div class="form-group">
            <label>每页文章数</label>
            <input type="number" name="posts_per_page" value="<?= e($settings['posts_per_page']) ?>" min="1" max="100">
        </div>
        <div class="form-group">
            <label>页脚文字</label>
            <input type="text" name="footer_text" value="<?= e($settings['footer_text']) ?>">
        </div>
    </div>

    <div class="card" style="max-width:700px">
        <h3 class="card-title">交互设置</h3>
        <div class="form-group">
            <label><input type="checkbox" name="allow_registration" value="1" <?= $settings['allow_registration'] === '1' ? 'checked' : '' ?>> 允许用户注册</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="moderate_comments" value="1" <?= $settings['moderate_comments'] === '1' ? 'checked' : '' ?>> 评论需审核</label>
        </div>
    </div>

    <div class="card" style="max-width:700px">
        <h3 class="card-title">统计代码</h3>
        <div class="form-group">
            <label>百度统计 ID</label>
            <input type="text" name="baidu_analytics" value="<?= e($settings['baidu_analytics']) ?>" placeholder="例如: a1b2c3d4e5f6...">
        </div>
        <div class="form-group">
            <label>Google Analytics ID</label>
            <input type="text" name="google_analytics" value="<?= e($settings['google_analytics']) ?>" placeholder="例如: G-XXXXXXX">
        </div>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn-primary">保存设置</button>
    </div>
</form>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
