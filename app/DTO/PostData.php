<?php

namespace App\DTO;

use Core\Http\Request;

/**
 * 文章数据传输对象 — 封装表单输入 + 验证 + 转换为 DB 数组。
 *
 * 用法：
 *   $dto = PostData::fromRequest($request);
 *   if (!$dto->isValid()) { ... 处理错误 ... }
 *   $id = $postService->create($dto, $authorId);
 */
class PostData
{
    public string $title;
    public string $slug;
    public string $content_md;
    public string $excerpt;
    public string $cover;
    public ?int $category_id;
    public string $status;
    public string $seo_title;
    public string $seo_description;
    public ?string $published_at;

    /** @var string 逗号分隔的标签名（非 DB 字段，供 PostService::syncTags 用） */
    public string $tags;

    /** @var array<string, string> */
    private array $errors = [];

    public static function fromRequest(Request $request): static
    {
        $dto = new static();

        $dto->title            = trim((string) $request->input('title', ''));
        $dto->slug             = trim((string) $request->input('slug', ''));
        $dto->content_md       = (string) $request->input('content_md', '');
        $dto->excerpt          = trim((string) $request->input('excerpt', ''));
        $dto->cover            = trim((string) $request->input('cover', ''));
        $dto->category_id      = (int) $request->input('category_id', 0) ?: null;
        $dto->status           = in_array($request->input('status'), ['draft', 'published', 'archived'], true)
                              ? $request->input('status') : 'draft';
        $dto->seo_title        = trim((string) $request->input('seo_title', ''));
        $dto->seo_description  = trim((string) $request->input('seo_description', ''));
        $dto->published_at     = trim((string) $request->input('published_at', '')) ?: null;
        $dto->tags             = trim((string) $request->input('tags', ''));

        return $dto;
    }

    /**
     * 校验返回错误列表。
     *
     * @return array<string, string>
     */
    public function validate(): array
    {
        $this->errors = [];

        if ($this->title === '') {
            $this->errors['title'] = '标题不能为空';
        }

        return $this->errors;
    }

    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 转为 DB 写入数组（不含 tags，tags 由 PostService::syncTags 处理）。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title'            => $this->title,
            'slug'             => $this->slug,
            'content_md'       => $this->content_md,
            'excerpt'          => $this->excerpt,
            'cover'            => $this->cover,
            'category_id'      => $this->category_id,
            'status'           => $this->status,
            'seo_title'        => $this->seo_title,
            'seo_description'  => $this->seo_description,
            'published_at'     => $this->published_at,
        ];
    }
}
