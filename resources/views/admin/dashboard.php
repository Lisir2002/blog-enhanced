<?php
/** @var int $postCount $publishedCount $draftCount $commentCount $pendingComments $userCount */
/** @var array $recentPosts $recentComments */
ob_start();
?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div>
            <div class="stat-value"><?= $postCount ?></div>
            <div class="stat-label">总文章</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div>
            <div class="stat-value"><?= $publishedCount ?></div>
            <div class="stat-label">已发布</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💬</div>
        <div>
            <div class="stat-value"><?= $commentCount ?></div>
            <div class="stat-label">评论</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div>
            <div class="stat-value"><?= $userCount ?></div>
            <div class="stat-label">用户</div>
        </div>
    </div>
</div>

<?php if ($pendingComments > 0): ?>
<div class="flash flash-error" style="background:#fef3c7;color:#92400e">
    ⚠️ <?= $pendingComments ?> 条评论待审核 <a href="<?= route('admin.comments.index') . '?status=pending' ?>">前往处理 →</a>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div class="card">
        <h3 class="card-title">最新文章</h3>
        <table>
            <tbody>
            <?php foreach ($recentPosts as $r): $post = new \App\Models\Post($r); ?>
                <tr>
                    <td><a href="<?= route('admin.posts.edit', ['id' => $post->getAttribute('id')]) ?>"><?= e($post->getAttribute('title')) ?></a></td>
                    <td style="text-align:right"><span class="badge badge-<?= $post->getAttribute('status') ?>"><?= $post->getAttribute('status') ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentPosts)): ?>
                <tr><td colspan="2" style="text-align:center;color:var(--c-text-muted)">暂无文章</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3 class="card-title">最新评论</h3>
        <table>
            <tbody>
            <?php foreach ($recentComments as $r): $c = new \App\Models\Comment($r); ?>
                <tr>
                    <td><strong><?= e($c->getAttribute('author_name')) ?></strong>: <?= e(mb_substr($c->getAttribute('content'), 0, 30)) ?></td>
                    <td style="text-align:right"><span class="badge badge-<?= $c->getAttribute('status') ?>"><?= $c->getAttribute('status') ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentComments)): ?>
                <tr><td colspan="2" style="text-align:center;color:var(--c-text-muted)">暂无评论</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="action-bar" style="margin-top:24px">
    <a href="<?= route('admin.posts.create') ?>" class="btn btn-primary">📝 写新文章</a>
    <a href="<?= route('admin.media.index') ?>" class="btn btn-secondary">🖼️ 媒体库</a>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
