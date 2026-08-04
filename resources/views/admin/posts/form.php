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
<div class="page-header">
    <h2><?= $isNew ? '写文章' : '编辑文章' ?></h2>
</div>

<form method="post" action="<?= $action ?>">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div class="form-row" style="align-items:flex-start">
        <div style="flex:3;min-width:0">
            <div class="form-group">
                <label for="title">标题</label>
                <input type="text" id="title" name="title" value="<?= e($post ? $post->getAttribute('title') : old('title', '')) ?>" placeholder="文章标题" class="form-control" required style="font-size:1.1rem;font-weight:600">
            </div>
            <div class="form-group">
                <label for="slug">别名 <span class="label-hint">URL 中的路径</span></label>
                <input type="text" id="slug" name="slug" value="<?= e($post ? $post->getAttribute('slug') : old('slug', '')) ?>" class="form-control" placeholder="post-slug">
            </div>
            <div class="form-group">
                <label>内容 <span class="label-hint">Markdown 格式</span></label>
                <div class="editor-grid">
                    <textarea id="post-content-md" name="content_md" class="form-control" placeholder="用 Markdown 写作..."><?= e($post ? $post->getAttribute('content_md') ?? $post->getAttribute('content') : old('content_md', '')) ?></textarea>
                    <div class="preview-pane" id="preview-pane">
                        <div class="text-muted" style="text-align:center;padding:40px">预览区域</div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>摘要</label>
                <textarea name="excerpt" rows="2" class="form-control" placeholder="文章摘要，留空自动截取"><?= e($post ? $post->getAttribute('excerpt') : old('excerpt', '')) ?></textarea>
            </div>
            <div class="form-group">
                <label>SEO 描述</label>
                <textarea name="seo_description" rows="2" class="form-control" placeholder="SEO meta description"><?= e($post ? $post->getAttribute('seo_description') : old('seo_description', '')) ?></textarea>
            </div>
            <div class="form-group">
                <label>SEO 标题</label>
                <input type="text" name="seo_title" value="<?= e($post ? $post->getAttribute('seo_title') : old('seo_title', '')) ?>" class="form-control" placeholder="留空则使用文章标题">
            </div>
        </div>

        <div class="side-panel">
            <div class="card">
                <h3 class="card-title">发布</h3>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" class="form-control">
                        <option value="draft" <?= $post && $post->getAttribute('status') === 'draft' ? 'selected' : '' ?>>草稿</option>
                        <option value="published" <?= $post && $post->getAttribute('status') === 'published' ? 'selected' : '' ?>>发布</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>发布时间</label>
                    <input type="text" name="published_at" value="<?= e($post ? ($post->getAttribute('published_at') ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s')) ?>" class="form-control" placeholder="YYYY-MM-DD HH:MM:SS">
                </div>
                <div class="form-group">
                    <label>封面图</label>
                    <input type="text" name="cover" value="<?= e($post ? $post->getAttribute('cover') : '') ?>" class="form-control" placeholder="/uploads/...">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= $isNew ? '发布' : '保存' ?></button>
            </div>

            <div class="card">
                <h3 class="card-title">分类</h3>
                <div class="form-group">
                    <select name="category_id" class="form-control">
                        <option value="0">— 未分类 —</option>
                        <?php foreach ($categories as $c): $cat = $c instanceof \App\Models\Category ? $c : new \App\Models\Category($c); ?>
                            <option value="<?= $cat->getAttribute('id') ?>" <?= $post && $post->getAttribute('category_id') == $cat->getAttribute('id') ? 'selected' : '' ?>><?= e($cat->getAttribute('name')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">标签</h3>
                <div class="form-group">
                    <input type="text" name="tags" value="<?= e($post ? implode(', ', array_map(fn($t) => ($t instanceof \App\Models\Tag ? $t->getAttribute('name') : $t['name']), $post->tags())) : '') ?>" class="form-control" placeholder="用逗号分隔">
                </div>
            </div>
        </div>
    </div>
</form>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');