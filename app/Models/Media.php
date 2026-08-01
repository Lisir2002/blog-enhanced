<?php

namespace App\Models;

use Core\Database\Model;

class Media extends Model
{
    protected static string $table = 'media';
    protected array $casts = ['id' => 'int', 'size' => 'int'];

    public function url(): string
    {
        return url($this->getAttribute('path'));
    }

    public function thumbnailUrl(): string
    {
        return $this->url();
    }

    public function filename(): string
    {
        return basename($this->getAttribute('path'));
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->getAttribute('filename') ?? '', PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        $mime = $this->getAttribute('mime') ?? '';
        return str_starts_with($mime, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->getAttribute('size');
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
