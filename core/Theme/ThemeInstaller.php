<?php

namespace Core\Theme;

/**
 * 主题安装器 — 负责 Zip 上传/解压/校验/删除/列举。
 *
 * 职责单一：
 * - 文件系统操作（创建目录、删除目录、解压 Zip）
 * - 安全性校验（Zip Slip 防护、语法检查）
 * - 主题目录扫描与列举
 */
class ThemeInstaller
{
    private string $themeRoot;

    public function __construct(string $themeRoot)
    {
        $this->themeRoot = rtrim($themeRoot, '/');
    }

    /**
     * 从 Zip 文件安装主题。
     *
     * @param string $zipPath 临时 zip 文件路径
     * @return array{name: string, meta: array} 主题名称和元数据
     * @throws \RuntimeException
     */
    public function installFromZip(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive 扩展不可用，无法解压主题包。');
        }
        if (!is_file($zipPath)) {
            throw new \RuntimeException('主题包文件不存在。');
        }

        $zip = new \ZipArchive();
        $code = $zip->open($zipPath);
        if ($code !== true) {
            throw new \RuntimeException("无法打开 Zip 文件，错误码: $code");
        }

        // Zip Slip 防护：检查所有条目路径是否包含 ".."
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_contains($name, '..') || str_starts_with($name, '/')) {
                $zip->close();
                throw new \RuntimeException("安全拒绝：ZIP 条目包含非法路径 [$name]");
            }
        }

        // 解压到临时目录
        $tmpDir = $this->themeRoot . '/.upload-' . substr(md5((string) microtime(true)), 0, 8);
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // 扫描解压目录，找到主题根目录
        $themeDir = null;
        $themeName = '';
        $entries = array_diff(scandir($tmpDir) ?: [], ['.', '..']);

        // 先找包含 theme.json 或 functions.php 的子目录
        foreach ($entries as $entry) {
            $candidate = $tmpDir . '/' . $entry;
            if (is_dir($candidate) && (is_file($candidate . '/theme.json') || is_file($candidate . '/functions.php'))) {
                $themeDir = $candidate;
                $themeName = $entry;
                break;
            }
        }

        // 如果根目录本身就是主题（没有嵌套子目录）
        if ($themeDir === null) {
            if (is_file($tmpDir . '/theme.json') || is_file($tmpDir . '/functions.php')) {
                $themeDir = $tmpDir;
                $themeName = basename($tmpDir);
            } else {
                // 取第一个非空目录
                foreach ($entries as $entry) {
                    $candidate = $tmpDir . '/' . $entry;
                    if (is_dir($candidate) && !str_starts_with($entry, '.')) {
                        $themeDir = $candidate;
                        $themeName = $entry;
                        break;
                    }
                }
            }
        }

        if ($themeDir === null || !is_dir($themeDir)) {
            $this->rrmdir($tmpDir);
            throw new \RuntimeException('无法识别主题包结构，请确保包含 theme.json 或 functions.php。');
        }

        $target = $this->themeRoot . '/' . $themeName;
        if (is_dir($target)) {
            $this->rrmdir($target);
        }

        // 使用 rename 而非 move_uploaded_file，因为是在同一文件系统内
        if (!rename($themeDir, $target)) {
            // 如果 rename 失败（跨分区），则递归复制
            $this->copyDir($themeDir, $target);
        }

        // 清理临时目录
        if (is_dir($tmpDir)) {
            $this->rrmdir($tmpDir);
        }

        // 读取元数据并返回
        $meta = $this->readMetaFromDir($target);
        return ['name' => $themeName, 'meta' => $meta];
    }

    /**
     * 删除主题目录。
     */
    public function deleteTheme(string $name): bool
    {
        $dir = $this->themeRoot . '/' . $name;
        if (!is_dir($dir)) {
            return false;
        }
        $this->rrmdir($dir);
        return true;
    }

    /**
     * 列举所有可用主题。
     *
     * @return array<string, array{name: string, dir: string, meta: array}>
     */
    public function listThemes(): array
    {
        $result = [];
        if (!is_dir($this->themeRoot)) {
            return $result;
        }
        $dirs = glob($this->themeRoot . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return $result;
        }
        foreach ($dirs as $dir) {
            $name = basename($dir);
            // 跳过隐藏目录和临时目录
            if (str_starts_with($name, '.')) {
                continue;
            }
            $meta = $this->readMetaFromDir($dir);
            $result[$name] = ['name' => $name, 'dir' => $dir, 'meta' => $meta];
        }
        return $result;
    }

    /**
     * 判断主题是否存在。
     */
    public function exists(string $name): bool
    {
        $dir = $this->themeRoot . '/' . $name;
        return is_dir($dir) && (is_file($dir . '/theme.json') || is_file($dir . '/functions.php'));
    }

    /**
     * 获取主题目录路径。
     */
    public function getThemeRoot(): string
    {
        return $this->themeRoot;
    }

    /**
     * 语法检查 PHP 文件（使用 token_get_all 轻量校验）。
     */
    public function validatePhpSyntax(string $filePath): bool
    {
        if (!is_file($filePath)) {
            return true; // 文件不存在视为通过
        }
        $content = file_get_contents($filePath);
        if ($content === false || $content === '') {
            return true;
        }
        try {
            $tokens = token_get_all($content);
            // 检查是否有未闭合的括号/花括号
            $braces = 0;
            $parens = 0;
            $brackets = 0;
            foreach ($tokens as $token) {
                if (is_array($token)) {
                    continue;
                }
                match ($token) {
                    '{' => $braces++,
                    '}' => $braces--,
                    '(' => $parens++,
                    ')' => $parens--,
                    '[' => $brackets++,
                    ']' => $brackets--,
                    default => null,
                };
            }
            return $braces === 0 && $parens === 0 && $brackets === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 从目录读取主题元数据。
     */
    private function readMetaFromDir(string $dir): array
    {
        $jsonFile = $dir . '/theme.json';
        if (is_file($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile) ?: '', true);
            return is_array($data) ? $data : [];
        }
        // 兼容旧版：从 functions.php 文件头解析
        $file = $dir . '/functions.php';
        if (is_file($file)) {
            $headers = $this->parseFileHeaders($file, [
                'name' => 'Theme Name',
                'description' => 'Description',
                'version' => 'Version',
                'author' => 'Author',
            ]);
            if (!empty($headers['name'])) {
                return $headers;
            }
        }
        return ['name' => basename($dir), 'version' => '0.0.0'];
    }

    /**
     * 从 PHP 文件注释头解析元数据。
     */
    private function parseFileHeaders(string $file, array $fields): array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }
        $result = [];
        foreach ($fields as $key => $label) {
            if (preg_match('/\*\s*' . preg_quote($label, '/') . '\s*:\s*(.+)/i', $content, $m)) {
                $result[$key] = trim($m[1]);
            }
        }
        return $result;
    }

    /**
     * 递归删除目录。
     */
    public function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $f) {
            $path = "$dir/$f";
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * 递归复制目录。
     */
    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            @mkdir($dst, 0777, true);
        }
        $items = scandir($src);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $f) {
            $sp = "$src/$f";
            $dp = "$dst/$f";
            if (is_dir($sp)) {
                $this->copyDir($sp, $dp);
            } else {
                @copy($sp, $dp);
            }
        }
    }
}