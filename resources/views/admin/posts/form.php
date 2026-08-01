<?php
/** @var \App\Models\Post|null $post
 *  @var array $categories
 *  @var string $action
 *  @var array $tags
 *  @var array $selectedTags
 */
ob_start();
$isNew = !$post;
?>
<form method="post" action="<?= $action ?>">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div class="form-row" style="align-items:flex-start">
        <div style="flex:3;min-width:0">
            <div class="form-group">
                <label for="title">标题</label>
                <input type="text" id="title" name="title" value="<?= e($post ? $post->getAttribute('title') : old('title', '')) ?>" placeholder="文章标题" required style="font-size:18px;font-weight:600">
            </div>
            <div class="form-group">
                <label for="slug">别名 (URL)</label>
                <input type="text" id="slug" name="slug" value="<?= e($post ? $post->getAttribute('slug') : old('slug', '')) ?>" placeholder="post-slug">
            </div>
            <div class="form-group">
                <label>内容 (Markdown)</label>
                <div class="editor-grid">
                    <textarea id="post-content-md" name="content_md" placeholder="用 Markdown 写作..."><?[SYSTEM_NOTE: Content compressed. Read the full version if needed.]bute('excerpt')) ?></textarea>
            </div>
            <div class="form-group">
                <label>SEO 描述</label>
                <textarea name="seo_description" rows="2" placeholder="SEO meta description"><?= e($post ? $post->getAttribute('seo_description') : old('seo_description', '')) ?></textarea>
            </div>
            <div class="form-group">
                <label>SEO 标题</label>
                <input type="text" name="seo_title" value="<?= e($post ? $post->getAttribute('seo_title') : old('seo_title', '')) ?>" placeholder="留空则使用文章标题">
            </div>
        </div>

        <div style="flex:1;min-width:240px">
            <div class="card">
                <h3 class="card-title">发布</h3>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status">
                        <option value="draft" <?= $post && $post->getAttribute('status') === 'draft' ? 'selected' : '' ?>>草稿</option>
                        <option value="published" <?= $post && $post->getAttribute('status') === 'published' ? 'selected' : '' ?>>发布</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>发布时间</label>
                    <input type="text" name="published_at" value="<?= e($post ? ($post->getAttribute('published_at') ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s')) ?>" placeholder="YYYY-MM-DD HH:MM:SS">
                </div>
                <div class="form-group">
                    <label>封面图</label>
                    <input type="text" name="cover" value="<?= e($post ? $post->getAttribute('cover') : '') ?>" placeholder="/uploads/...">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%"><?= $isNew ? '发布' : '保存' ?></button>
            </div>

            <div class="card">
                <h3 class="card-title">分类</h3>
                <div class="form-group">
                    <select name="category_id">
                        <option value="0">— 未分类 —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $post && $post->getAttribute('category_id') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">标签</h3>
                <div class="form-group">
                    <input type="text" name="tags" value="<?= e($post ? implode(', ', array_map(fn($t) => $t['name'], $post->tags())) : '') ?>" placeholder="用逗号分隔">
                </div>
            </div>
        </div>
    </div>
</form>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
