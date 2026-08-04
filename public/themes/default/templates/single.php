<?php
/**
 * Single post template - 详情页 + TOC (布局 D)
 * @var \App\Models\Post $post
 * @var \App\Models\Category|null $category
 * @var \App\Models\User|null $author
 * @var array $tags
 * @var array $comments
 * @var array $related
 * @var string $pageTitle
 */
get_header();

// 生成 TOC 并给 h2/h3 注入 id
$tocHtml = function_exists('table_of_contents') ? table_of_contents($post->html()) : '';
$contentHtml = $GLOBALS['toc_filtered_html'] ?? $post->html();
$sidebarMode = theme_config('single_sidebar_mode', 'toc');

// TOC 注入的 class 转为 BEM
if ($tocHtml !== '') {
    $tocHtml = preg_replace('/<nav class="toc">/', '<nav class="blog-toc" role="navigation" aria-label="目录">', $tocHtml);
    $tocHtml = preg_replace('/<h3>(.*?)<\/h3>/s', '<h3 class="blog-toc__title">$1</h3>', $tocHtml);
    $tocHtml = preg_replace('/<ul>/', '<ul class="blog-toc__list">', $tocHtml);
    $tocHtml = preg_replace('/<li(\s+style="[^"]*")?>/', '<li class="blog-toc__item">', $tocHtml);
    $tocHtml = preg_replace('/<a\s+href=/', '<a class="blog-toc__link" href=', $tocHtml);
}
?>
<div class="blog-container">
    <div class="blog-single-layout">
        <main class="blog-single-layout__content" role="main">
            <article class="blog-single">
                <header class="blog-single__header">
                    <h1 class="blog-single__title"><?= e($post->getAttribute('title')) ?></h1>
                    <div class="blog-single__meta">
                        <?php if ($author): ?>
                            <span class="blog-single__meta-author">
                                <img src="<?= $author->avatarUrl(20) ?>" alt="" style="width:20px;height:20px;border-radius:50%;vertical-align:middle;margin-right:4px">
                                <a href="<?= url('/author/' . $author->getAttribute('username')) ?>"><?= e($author->displayName()) ?></a>
                            </span>
                        <?php endif; ?>
                        <?php if ($category): ?>
                            <span class="blog-single__meta-category">
                                <a href="<?= $category->url() ?>"><?= e($category->getAttribute('name')) ?></a>
                            </span>
                        <?php endif; ?>
                        <span class="blog-single__meta-date">📅 <?= substr((string) $post->getAttribute('published_at'), 0, 10) ?></span>
                        <span class="blog-single__meta-views">👁 <?= (int) $post->getAttribute('views') ?></span>
                    </div>
                </header>

                <?php if ($post->getAttribute('cover')): ?>
                    <div class="blog-single__cover">
                        <img src="<?= url($post->getAttribute('cover')) ?>" alt="<?= e($post->getAttribute('title')) ?>">
                    </div>
                <?php endif; ?>

                <div class="blog-single__content">
                    <?= $contentHtml ?>
                </div>

                <?php if (!empty($tags)): ?>
                    <div class="blog-single__tags">
                        <?php foreach ($tags as $t): $tag = is_object($t) ? $t : new \App\Models\Tag($t); ?>
                            <a href="<?= $tag->url() ?>" class="blog-single__tag">#<?= e($tag->getAttribute('name')) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($author): ?>
                <div class="blog-single__author">
                    <img class="blog-single__author-avatar" src="<?= $author->avatarUrl(64) ?>" alt="<?= e($author->displayName()) ?>">
                    <div class="blog-single__author-info">
                        <p class="blog-single__author-name"><?= e($author->displayName()) ?></p>
                        <?php if ($author->getAttribute('bio')): ?>
                            <p class="blog-single__author-bio"><?= e($author->getAttribute('bio')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </article>

            <?php if (!empty($related)): ?>
            <section class="blog-related">
                <h3 class="blog-related__title">相关文章</h3>
                <div class="blog-card-list blog-card-list--related">
                    <?php foreach ($related as $r): $p = is_object($r) ? $r : new \App\Models\Post($r); ?>
                        <article class="blog-card blog-card--compact">
                            <div class="blog-card__body">
                                <h4 class="blog-card__title blog-card__title--compact">
                                    <a href="<?= $p->url() ?>"><?= e($p->getAttribute('title')) ?></a>
                                </h4>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php
            do_action('before_comments', $post);
            $sess = app(\Core\Http\Session::class);
            ?>
            <?php if ($sess->get('success')): ?>
                <div class="blog-flash blog-flash--success"><?= e($sess->pull('success')) ?></div>
            <?php endif; ?>
            <?php if ($sess->get('error')): ?>
                <div class="blog-flash blog-flash--error"><?= e($sess->pull('error')) ?></div>
            <?php endif; ?>

            <section class="blog-comments" id="comments">
                <h3 class="blog-comments__title">评论 (<?= count($comments) ?>)</h3>
                <?php if (empty($comments)): ?>
                    <p class="blog-comments__empty blog-muted">暂无评论，快来抢沙发！</p>
                <?php else: ?>
                    <ul class="blog-comments__list">
                        <?php foreach ($comments as $c): $comment = is_object($c) ? $c : new \App\Models\Comment($c); ?>
                            <li class="blog-comments__item" id="comment-<?= $comment->getAttribute('id') ?>">
                                <div class="blog-comments__meta">
                                    <strong class="blog-comments__author"><?= e($comment->getAttribute('author_name')) ?></strong>
                                    · <span class="blog-comments__date"><?= substr((string) $comment->getAttribute('created_at'), 0, 16) ?></span>
                                </div>
                                <div class="blog-comments__body"><?= $comment->html() ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form class="blog-comment-form" id="comment-form" method="post" action="<?= $post->url() ?>/comments">
                    <?= csrf_field() ?>
                    <div class="blog-comment-form__row">
                        <input type="text" name="author_name" placeholder="你的名字 *" required value="<?= e(current_user()?->displayName() ?? old('author_name', '')) ?>">
                    </div>
                    <div class="blog-comment-form__row">
                        <input type="email" name="author_email" placeholder="邮箱（不公开）" value="<?= e(current_user()?->getAttribute('email') ?? old('author_email', '')) ?>">
                    </div>
                    <div class="blog-comment-form__row">
                        <textarea name="content" rows="4" placeholder="说点什么..." required><?= e(old('content', '')) ?></textarea>
                    </div>
                    <div class="blog-comment-form__row blog-comment-form__row--honeypot">
                        <input type="text" name="website_url" placeholder="Website URL" tabindex="-1" autocomplete="off">
                    </div>
                    <button type="submit" class="blog-comment-form__submit">发表评论</button>
                </form>
            </section>
        </main>

        <aside class="blog-single-layout__aside">
            <?php if (($sidebarMode === 'toc' || $sidebarMode === 'both') && $tocHtml !== ''): ?>
                <?= $tocHtml ?>
            <?php endif; ?>
            <?php if ($sidebarMode === 'sidebar' || $sidebarMode === 'both'): ?>
                <?php get_sidebar(); ?>
            <?php endif; ?>
            <?php if ($sidebarMode === 'toc' && $tocHtml === ''): ?>
                <?php get_sidebar(); ?>
            <?php endif; ?>
        </aside>
    </div>
</div>
<?php get_footer(); ?>
