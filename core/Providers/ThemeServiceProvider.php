<?php

namespace Core\Providers;

use Core\View\AssetManager;
use Core\View\MenuManager;
use Core\View\Shortcode;
use Core\View\ThemeManager;
use Core\View\WidgetManager;

class ThemeServiceProvider extends Provider
{
    public function register(): void
    {
        // Register theme system managers
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(WidgetManager::class);
        $this->app->singleton(MenuManager::class);
        $this->app->singleton(AssetManager::class);
        $this->app->singleton(Shortcode::class);
    }

    public function boot(): void
    {
        $theme = $this->app->get(ThemeManager::class);
        $theme->boot();

        // Register built-in shortcodes
        $this->registerBuiltinShortcodes();

        // Trigger enqueue hook for themes to register assets
        do_action('wp_enqueue');
    }

    private function registerBuiltinShortcodes(): void
    {
        $sc = $this->app->get(Shortcode::class);

        // [gallery ids="1,2,3"] — basic image gallery
        $sc->add('gallery', function ($attrs) {
            $ids = $attrs['ids'] ?? '';
            if ($ids === '') {
                return '';
            }
            $idArr = array_filter(explode(',', $ids));
            $html = '<div class="gallery">';
            foreach ($idArr as $id) {
                $media = \App\Models\Media::find((int) $id);
                if ($media) {
                    $html .= '<a href="' . $media->url() . '" class="gallery-item">'
                        . '<img src="' . $media->thumbnailUrl() . '" alt="' . e($media->filename()) . '">'
                        . '</a>';
                }
            }
            return $html . '</div>';
        });

        // [youtube id="dQw4w9WgXcQ"] — embed YouTube
        $sc->add('youtube', function ($attrs) {
            $id = $attrs['id'] ?? '';
            if ($id === '') {
                return '';
            }
            return '<div class="video-embed"><iframe width="560" height="315" '
                . 'src="https://www.youtube.com/embed/' . e($id) . '" '
                . 'title="YouTube video player" frameborder="0" '
                . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
                . 'allowfullscreen></iframe></div>';
        });

        // [quote]text[/quote] — blockquote
        // Note: our shortcode system currently only handles self-closing tags
        // This is a simplified version
        $sc->add('quote', function ($attrs) {
            $text = $attrs['text'] ?? '';
            $author = $attrs['author'] ?? '';
            $html = '<blockquote class="shortcode-quote">';
            if ($text) {
                $html .= '<p>' . e($text) . '</p>';
            }
            if ($author) {
                $html .= '<cite>' . e($author) . '</cite>';
            }
            return $html . '</blockquote>';
        });
    }
}
