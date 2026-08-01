<?php
/**
 * Single post template
 * @var \App\Models\Post $post
 * @var \App\Models\Category|null $category
 * @var \App\Models\User|null $author
 * @var array $tags
 * @var array $comments
 * @var array $related
 * @var string $pageTitle
 */
get_header();
?>
<div class="container">
    <div class="layout-grid">
        <main class="content-area single-post" role="main">
            <article>
                <header class="post-header">
                    <h1 class="post-title"><?= e($post->getAttribute('title')) ?></h1>
                    <div class="post-meta">
                        <?php if ($author): ?>
                            <span><img src="<?= $author->avatarUrl(20) ?>" alt="" style="width:20px;height:20px;border-radius:50%;vertical-align:middle"></span>
                            <span><a href="<?= url('/author/' . $author->getAttribute('username')) ?>"><?= e($author->displayName()) ?></a></span>
                        <?php endif; ?>
                        <?php if ($category): ?>
                            <span><a href="<?= $category->url() ?>"><?= e($category->getAttribute('name')) ?></a></span>
                        <?php endif; ?>
                        <span>📅 <?= substr((string) $post->getAttribute('published_at'), 0, 10) ?></span>
                        <span>👁 <?= (int) $post->getAttribute('views') ?></span>
                    </div>
                </header>

                <?php if ($post->getAttribute('cover')): ?>
                    <div class="post-cover">
                        <img src="<?= url($post->getAttribute('cover')) ?>" alt="<?= e($post->getAttribute('title')) ?>">
                    </div>
                <?php endif; ?>

                <div class="post-content">
                    <?= $post->html() ?>
                </div>

                <?php if (!empty($tags)): ?>
                    <div class="post-tags">
                        <?php foreach ($tags as $t): $tag = new \App\Models\Tag($t); ?>
                            <a href="<?= $tag->url() ?>" class="tag-link">#<?= e($tag->getAttribute('name')) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($author): ?>
                <div class="author-box">
                    <img src="<?= $author->avatarUrl(64) ?>" alt="<?= e($author->displayName()) ?>">
                    <div>
                        <p class="name"><?= e($author->displayName()) ?></p>
                        <?php if ($author->getAttribute('bio')): ?>
                            <p class="bio"><?= e($author->getAttribute('bio')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </article>

            <?php if (!empty($related)): ?>
            <section class="related-posts">
                <h3>相关文章</h3>
                <div class="post-list">
                    <?php foreach ($related as $r): $p = new \App\Models\Post($r); ?>
                        <article class="post-card">
                            <div class="post-card-inner">
                                <div class="body">
                                    <h4 class="title" style="font-size:16px;margin:0">
                                        <a href="<?= $p->url() ?>"><?= e($p->getAttribute('title')) ?></a>
                                    </h4>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php
            do_action('before_comments', $post);
            $sess = app(\Core\Http\Session::class);
            if ($sess->get('success')): ?>
                <div class="flash flash-success"><?= e($sess->pull('success')) ?></div>
            <?php endif; ?>
            <?php if ($sess->get('error')): ?>
                <div class="flash flash-error"><?= e($sess->pull('error')) ?></div>
            <?php endif; ?>

            <section class="comments-area" id="comments">
                <h3>评论 (<?= count($comments) ?>)</h3>
                <?php if (empty($comments)): ?>
                    <p class="muted">暂无评论，快来抢沙发！</p>
                <?php else: ?>
                    <ul class="comment-list">
                        <?php foreach ($comments as $c): $comment = new \App\Models\Comment($c); ?>
                            <li class="comment" id="comment-<?= $comment->getAttribute('id') ?>">
                                <div class="comment-meta">
                                    <strong class="comment-author"><?= e($comment->getAttribute('author_name')) ?></strong>
                                    · <span><?= substr((string) $comment->getAttribute('created_at'), 0, 16) ?></span>
                                </div>
                                <div class="comment-body"><?= $comment->html() ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form class="comment-form" id="comment-form" method="post" action="<?= $post->url() ?>/comments">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <input type="text" name="author_name" placeholder="你的名字 *" required value="<?= e(current_user()?->displayName() ?? old('author_name', '')) ?>">
                    </div>
                    <div class="form-row">
                        <input type="email" name="author_email" placeholder="邮箱（不公开）" value="<?= e(current_user()?->getAttribute('email') ?? old('author_email', '')) ?>">
                    </div>
                    <div class="form-row">
                        <textarea name="content" rows="4" placeholder="说点什么..." required><?= e(old('content', '')) ?></textarea>
                    </div>
                    <div class="form-row" style="display:none">
                        <input type="text" name="website_url" placeholder="Website URL" tabindex="-1" autocomplete="off">
                    </div>
                    <button type="submit">发表评论</button>
                </form>
            </section>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php
get_footer();
