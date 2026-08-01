<?php

/**
 * 种子数据 - 生成几篇示例文章、分类、标签
 */

$app = new \Core\Application();
$app->bootstrap();

use App\Models\Post;

$now = date('Y-m-d H:i:s');

// Categories
$cats = ['技术' => 'tech', '生活' => 'life', '随笔' => 'essay'];
$catId = [];
foreach ($cats as $name => $slug) {
    $existing = \App\Models\Category::query()->where('slug', '=', $slug)->first();
    if ($existing) {
        $catId[$slug] = $existing['id'];
    } else {
        $catId[$slug] = \App\Models\Category::query()->insert([
            'name' => $name, 'slug' => $slug, 'description' => "$name 分类", 'parent_id' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}

// Tags
$tagData = ['PHP', 'MySQL', 'SQLite', 'CMS', '原生开发', '前端'];
$tagId = [];
foreach ($tagData as $name) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?: 'tag-' . bin2hex(random_bytes(2)));
    $existing = \App\Models\Tag::query()->where('slug', '=', $slug)->first();
    if ($existing) {
        $tagId[$name] = $existing['id'];
    } else {
        $tagId[$name] = \App\Models\Tag::query()->insert([
            'name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}

// Posts
$posts = [
    ['title' => '为什么我们选择原生 PHP 而不是框架', 'slug' => 'why-native-php', 'category' => 'tech',
     'tags' => ['PHP', 'CMS', '原生开发'],
     'content' => "## 选择原生 PHP 的理由\n\n很多团队一上来就用 Laravel/Symfony，但博客这种应用，**原生 PHP + Composer 组件**才是更合适的选择。\n\n### 性能\n\n原生 PHP 没有框架的启动开销，OPcache 直接缓存字节码，单机 QPS 轻松上千。\n\n### 可控性\n\n每一行代码都在你眼皮底下。框架是黑盒，原生是白盒。\n\n### 学习价值\n\n写一遍路由、ORM、模板引擎，你就理解了所有 Web 框架的本质。\n\n### 何时该用框架\n\n- 团队规模 > 5 人\n- 业务复杂度超过 CMS\n- 需要 API 后台、队列、定时任务等基础设施"],
    ['title' => 'SQLite 作为博客数据库的可行性', 'slug' => 'sqlite-for-blog', 'category' => 'tech',
     'tags' => ['SQLite', 'MySQL', 'CMS'],
     'content' => "## SQLite 适合博客吗\n\n**完全适合**。\n\n### 数据特征\n\n- 读多写少\n- 数据量小（< 1GB）\n- 单机部署\n\n这正中 SQLite 的下怀。\n\n### 切换到 MySQL\n\n我们的系统通过 PDO 抽象层，**修改一行 .env 就能切换**：\n\n```\nDB_DRIVER=mysql\nDB_HOST=127.0.0.1\nDB_NAME=blog\nDB_USER=root\nDB_PASS=secret\n```\n\n两个 schema 文件 `schema.sqlite.sql` / `schema.mysql.sql` 都已准备好。"],
    ['title' => '如何设计类 WordPress 主题系统', 'slug' => 'wp-like-theme', 'category' => 'tech',
     'tags' => ['PHP', 'CMS', '前端'],
     'content' => "## 主题系统的核心\n\n一个 WordPress 式主题系统要满足：\n\n1. **模板层级**：`single-post-{slug}.php` → `single-post.php` → `single.php`\n2. **functions.php 入口**：主题激活时执行\n3. **Hook 集成**：主题通过 `add_action/add_filter` 与核心交互\n4. **zip 上传**：后台直接上传 zip 包安装\n5. **元数据**：`theme.json` 声明名称、版本、作者、配置\n\n我们实现的 `ThemeManager` 提供全部这些能力。"],
    ['title' => '写博客的正确姿势', 'slug' => 'how-to-blog', 'category' => 'life',
     'tags' => ['随笔'],
     'content' => "## 写博客不是写日记\n\n写博客是**写给读者**的，不是写给自己的。这意味着：\n\n- 标题要让陌生人想点进来\n- 开头 3 句话要让人读下去\n- 代码要能复制粘贴跑起来\n- 结尾要给读者一个动作\n\n### 频率不重要，质量才重要\n\n一个月一篇 3000 字深度文 > 一周一篇 300 字水文。"],
    ['title' => '我的开发工具链 2025', 'slug' => 'dev-tools-2025', 'category' => 'essay',
     'tags' => ['原生开发'],
     'content' => "## 编辑器\n\nVS Code + PHP Intelephense。够用。\n\n## 终端\n\nfish shell + tmux。\n\n## 数据库\n\nSQLite + DB Browser for SQLite。简单粗暴。\n\n## 部署\n\ngit pull + OPcache reset。不需要 Docker，不需要 K8s。"],
];

foreach ($posts as $p) {
    $existing = Post::query()->where('slug', '=', $p['slug'])->first();
    if ($existing) {
        echo "  - skip: {$p['title']} (exists)\n";
        continue;
    }
    $id = Post::query()->insert([
        'title' => $p['title'],
        'slug' => $p['slug'],
        'content_md' => $p['content'],
        'content_html' => app(\Parsedown::class)->text($p['content']),
        'excerpt' => mb_substr(strip_tags(app(\Parsedown::class)->text($p['content'])), 0, 160),
        'category_id' => $catId[$p['category']],
        'author_id' => 1,
        'status' => 'published',
        'published_at' => date('Y-m-d H:i:s', time() - rand(3600, 86400 * 7)),
        'views' => rand(10, 500),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Link tags
    foreach ($p['tags'] as $tagName) {
        if (isset($tagId[$tagName])) {
            app(\Core\Database\QueryBuilder::class)
                ->table('post_tag')
                ->insert([
                    'post_id' => $id,
                    'tag_id' => $tagId[$tagName],
                ]);
        }
    }
    echo "  + created: {$p['title']}\n";
}

echo "\n✓ Seed complete.\n";
