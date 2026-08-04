<?php

namespace Core\View;

/**
 * 条件标签 — 模板内判断当前页面类型。
 *
 * 用法：
 *   if (is_single()) { ... }
 *   if (is_category('tech')) { ... }
 */
class Conditional
{
    private static ?string $routeName = null;
    private static ?array $routeParams = null;

    public static function set(?string $routeName, array $params = []): void
    {
        self::$routeName = $routeName;
        self::$routeParams = $params;
    }

    public static function reset(): void
    {
        self::$routeName = null;
        self::$routeParams = null;
    }

    public static function routeName(): ?string
    {
        return self::$routeName;
    }

    public static function routeParam(string $key, mixed $default = null): mixed
    {
        return self::$routeParams[$key] ?? $default;
    }

    public static function isHome(): bool
    {
        return self::$routeName === 'home' || self::$routeName === 'home.paged';
    }

    public static function isFrontPage(): bool
    {
        return self::isHome();
    }

    public static function isSingle(): bool
    {
        return self::$routeName === 'post.show' || self::$routeName === 'post.edit';
    }

    public static function isPage(): bool
    {
        return self::$routeName === 'page.show';
    }

    public static function isCategory(?string $slug = null): bool
    {
        if (self::$routeName !== 'category.show') {
            return false;
        }
        if ($slug === null) {
            return true;
        }
        return self::routeParam('slug') === $slug;
    }

    public static function isTag(?string $slug = null): bool
    {
        if (self::$routeName !== 'tag.show') {
            return false;
        }
        if ($slug === null) {
            return true;
        }
        return self::routeParam('slug') === $slug;
    }

    public static function isSearch(): bool
    {
        return self::$routeName === 'search';
    }

    public static function is404(): bool
    {
        return self::$routeName === null;
    }

    public static function isAuthor(): bool
    {
        return self::$routeName === 'author.show';
    }

    public static function isArchive(): bool
    {
        return self::isCategory() || self::isTag() || self::isAuthor()
            || self::$routeName === 'archive';
    }

    public static function isFeed(): bool
    {
        return self::$routeName === 'feed.rss' || self::$routeName === 'sitemap';
    }

    public static function isAdmin(): bool
    {
        return is_admin_route();
    }
}
