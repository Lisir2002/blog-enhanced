<?php

namespace Core\i18n;

/**
 * 国际化系统 — 翻译函数 + 语言文件加载。
 *
 * 用法:
 *   __('Hello')           → 查找翻译，无则返回原文
 *   _e('Hello')           // echo __('Hello')
 *   __('Welcome, :name', ['name' => '张三'])  // 参数替换
 *
 * 语言文件路径:
 *   resources/themes/{theme}/lang/{locale}.php
 *   resources/lang/{locale}.php
 */
class Translator
{
    private string $locale = 'zh_CN';
    private array $translations = [];

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->load();
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function translate(string $key, array $params = []): string
    {
        $text = $this->translations[$key] ?? $key;
        if (!empty($params)) {
            foreach ($params as $name => $value) {
                $text = str_replace(':' . $name, (string) $value, $text);
            }
        }
        return $text;
    }

    private function load(): void
    {
        $files = [
            resource_path('lang/' . $this->locale . '.php'),
            themes_path(),
        ];

        // Theme lang file
        try {
            $theme = app(\Core\View\ThemeManager::class);
            $themeLang = $theme->path('lang/' . $this->locale . '.php');
            if ($themeLang && is_file($themeLang)) {
                $files[] = $themeLang;
            }
        } catch (\Throwable) {}

        // Plugin lang files
        try {
            $pm = app(\Core\Plugin\PluginManager::class);
            foreach ($pm->activePlugins() as $plugin) {
                $path = plugins_path($plugin . '/lang/' . $this->locale . '.php');
                if (is_file($path)) {
                    $files[] = $path;
                }
            }
        } catch (\Throwable) {}

        foreach ($files as $file) {
            if (is_file($file)) {
                $data = require $file;
                if (is_array($data)) {
                    $this->translations = array_merge($this->translations, $data);
                }
            }
        }
    }
}
