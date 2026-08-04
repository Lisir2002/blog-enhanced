<?php

namespace Core\View;

use Core\Database\Connection;

/**
 * 图片处理器 — 生成缩略图、响应式尺寸、srcset。
 *
 * 需要 GD 扩展。无 GD 时降级为原图。
 */
class ImageProcessor
{
    /** @var array<string, array{name:int,width:int,height:int,crop:bool}> */
    private array $sizes = [];

    public function __construct(
        private Connection $connection,
    ) {
        // 内置默认尺寸
        $this->sizes['thumbnail'] = ['name' => 'thumbnail', 'width' => 150, 'height' => 150, 'crop' => true];
        $this->sizes['medium']     = ['name' => 'medium', 'width' => 480, 'height' => 0, 'crop' => false];
        $this->sizes['large']     = ['name' => 'large', 'width' => 1200, 'height' => 0, 'crop' => false];
    }

    public function addSize(string $name, int $width, int $height = 0, bool $crop = false): void
    {
        $this->sizes[$name] = compact('name', 'width', 'height', 'crop');
    }

    public function getSizes(): array
    {
        return $this->sizes;
    }

    /**
     * 为指定图片生成各尺寸缩略图。
     */
    public function generateSizes(string $sourcePath, string $targetDir, string $filename): array
    {
        $results = ['original' => $filename];
        if (!function_exists('imagecreatetruecolor')) {
            return $results; // GD 不可用，只返回原图
        }

        $srcInfo = @getimagesize($sourcePath);
        if (!$srcInfo) {
            return $results;
        }

        [$srcW, $srcH, $srcType] = $srcInfo;
        $createFunc = match ($srcType) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG  => 'imagecreatefrompng',
            IMAGETYPE_GIF  => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            default        => null,
        };

        if ($createFunc === null || !function_exists($createFunc)) {
            return $results;
        }

        $srcImg = @$createFunc($sourcePath);
        if (!$srcImg) {
            return $results;
        }

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        foreach ($this->sizes as $size) {
            $newW = $size['width'];
            $newH = $size['height'];
            if ($newW === 0 && $newH === 0) {
                continue;
            }
            // 计算目标尺寸
            [$dstW, $dstH] = $this->calcDimensions($srcW, $srcH, $newW, $newH, $size['crop']);

            $dstImg = imagecreatetruecolor($dstW, $dstH);
            if ($srcType === IMAGETYPE_PNG || $srcType === IMAGETYPE_GIF) {
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
                imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $transparent);
            }

            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            $outFile = "{$base}-{$size['name']}.{$ext}";
            $outPath = rtrim($targetDir, '/') . '/' . $outFile;

            $saveFunc = match ($srcType) {
                IMAGETYPE_JPEG => 'imagejpeg',
                IMAGETYPE_PNG  => 'imagepng',
                IMAGETYPE_GIF  => 'imagegif',
                IMAGETYPE_WEBP => 'imagewebp',
                default        => 'imagejpeg',
            };
            $saveFunc($dstImg, $outPath, $srcType === IMAGETYPE_JPEG ? 85 : ($srcType === IMAGETYPE_PNG ? 6 : 80));
            imagedestroy($dstImg);

            $results[$size['name']] = $outFile;
        }

        imagedestroy($srcImg);
        return $results;
    }

    /**
     * 计算缩放后尺寸。
     */
    private function calcDimensions(int $srcW, int $srcH, int $dstW, int $dstH, bool $crop): array
    {
        if ($crop) {
            $srcRatio = $srcW / $srcH;
            $dstRatio = $dstW / ($dstH > 0 ? $dstH : $dstW);
            if ($srcRatio > $dstRatio) {
                $tempH = $srcH;
                $tempW = $srcH * $dstRatio;
            } else {
                $tempW = $srcW;
                $tempH = $srcW / $dstRatio;
            }
            return [$dstW > 0 ? $dstW : $srcW, $dstH > 0 ? $dstH : $srcH];
        }
        // 缩放（不裁剪）
        if ($dstW > 0 && $dstH === 0) {
            $ratio = $dstW / $srcW;
            return [$dstW, (int) ($srcH * $ratio)];
        }
        if ($dstW === 0 && $dstH > 0) {
            $ratio = $dstH / $srcH;
            return [(int) ($srcW * $ratio), $dstH];
        }
        $ratio = min($dstW / $srcW, $dstH / $srcH);
        return [(int) ($srcW * $ratio), (int) ($srcH * $ratio)];
    }

    /**
     * 生成 srcset 属性值。
     */
    public function srcset(string $baseUrl, array $sizes): string
    {
        $parts = [];
        foreach ($sizes as $name => $file) {
            if ($name === 'original' || !isset($this->sizes[$name])) {
                continue;
            }
            $w = $this->sizes[$name]['width'];
            if ($w > 0) {
                $parts[] = $baseUrl . $file . ' ' . $w . 'w';
            }
        }
        return implode(', ', $parts);
    }
}
