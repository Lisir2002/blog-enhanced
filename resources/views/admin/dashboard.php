<?php
/** @var int $postCount $publishedCount $draftCount $commentCount $pendingComments $userCount */
/** @var array $recentPosts $recentComments */
ob_start();
?>
<div class="page-header">
    <h2>仪表盘</h2>
    <div class="page-header-actions">
        <a href="<?= route('admin.posts.create') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            写新文章
        </a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-wrap blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $postCount ?></div>
            <div class="stat-label">总文章</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrap green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $publishedCount ?></div>
            <div class="stat-label">已发布</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrap amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $commentCount ?></div>
            <div class="stat-label">评论</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrap red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $userCount ?></div>
            <div class="stat-label">用户</div>
        </div>
    </div>
</div>

<?php if ($pendingComments > 0): ?>
<div class="flash flash-warning">
    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span><?= $pendingComments ?> 条评论待审核 — <a href="<?= route('admin.comments.index') . '?status=pending' ?>">前往处理</a></span>
</div>
<?php endif; ?>

<div class="stats-grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">最新文章</h3>
            <a href="<?= route('admin.posts.index') ?>" class="btn btn-sm btn-ghost">查看全部</a>
        </div>
        <table>
            <tbody>
            <?php foreach ($recentPosts as $r): $post = new \App\Models\Post($r); ?>
                <tr>
                    <td><a href="<?= route('admin.posts.edit', ['id' => $post->getAttribute('id')]) ?>"><?= e($post->getAttribute('title')) ?></a></td>
                    <td class="text-right"><span class="badge badge-<?= $post->getAttribute('status') ?>"><?= $post->getAttribute('status') === 'published' ? '已发布' : '草稿' ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentPosts)): ?>
                <tr><td colspan="2" class="empty-cell">暂无文章</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">最新评论</h3>
            <a href="<?= route('admin.comments.index') ?>" class="btn btn-sm btn-ghost">查看全部</a>
        </div>
        <table>
            <tbody>
            <?php foreach ($recentComments as $r): $c = new \App\Models\Comment($r); ?>
                <tr>
                    <td><strong><?= e($c->getAttribute('author_name')) ?></strong>: <?= e(mb_substr($c->getAttribute('content'), 0, 30)) ?></td>
                    <td class="text-right"><span class="badge badge-<?= $c->getAttribute('status') ?>"><?= $c->getAttribute('status') ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentComments)): ?>
                <tr><td colspan="2" class="empty-cell">暂无评论</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');