<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\Database\QueryBuilder;

/**
 * QueryBuilder 不可变性测试。
 */
class QueryBuilderTest extends TestCase
{
    private function qb(): QueryBuilder
    {
        return $this->app->get(QueryBuilder::class);
    }

    /**
     * 创建测试用 user + category，避免 FK 约束失败。
     */
    private function seedDependencies(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->qb()->table('users')->insert([
            'username' => 'tester', 'email' => 'test@example.com', 'password' => 'x',
            'role' => 'admin', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->qb()->table('categories')->insert([
            'name' => 'Cat', 'slug' => 'cat', 'parent_id' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_table_returns_new_instance(): void
    {
        $qb = $this->qb();
        $a = $qb->table('posts');
        $b = $qb->table('users');
        $this->assertNotSame($qb, $a);
        $this->assertNotSame($qb, $b);
        $this->assertNotSame($a, $b);
    }

    public function test_where_does_not_mutate_original(): void
    {
        $qb = $this->qb()->table('posts');
        $filtered = $qb->where('status', '=', 'published');
        $this->assertNotSame($qb, $filtered);
        $rows = $qb->get();
        $this->assertIsArray($rows);
    }

    public function test_chained_where_returns_clone(): void
    {
        $qb = $this->qb()->table('posts');
        $a = $qb->where('status', '=', 'draft');
        $b = $qb->where('status', '=', 'published');
        $this->assertNotSame($a, $b);
        $this->assertNotSame($qb, $a);
        $this->assertNotSame($qb, $b);
    }

    public function test_limit_offset_are_immutable(): void
    {
        $qb = $this->qb()->table('posts');
        $limited = $qb->limit(5);
        $offset = $qb->offset(10);
        $this->assertNotSame($qb, $limited);
        $this->assertNotSame($qb, $offset);
        $this->assertNotSame($limited, $offset);
    }

    public function test_insert_and_find(): void
    {
        $this->seedDependencies();
        $qb = $this->qb()->table('posts');
        $now = date('Y-m-d H:i:s');
        $id = $qb->insert([
            'title' => 'Hello', 'slug' => 'test-post', 'content_md' => '', 'content_html' => '',
            'author_id' => 1, 'status' => 'draft',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $row = $qb->where('slug', '=', 'test-post')->first();
        $this->assertIsArray($row);
        $this->assertSame('Hello', $row['title']);
    }

    public function test_count_with_where(): void
    {
        $this->seedDependencies();
        $now = date('Y-m-d H:i:s');
        $this->qb()->table('posts')->insert([
            'title' => 'A', 'slug' => 'a', 'content_md' => '', 'content_html' => '',
            'author_id' => 1, 'status' => 'draft',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->qb()->table('posts')->insert([
            'title' => 'B', 'slug' => 'b', 'content_md' => '', 'content_html' => '',
            'author_id' => 1, 'status' => 'published',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $total = $this->qb()->table('posts')->count();
        $this->assertSame(2, $total);

        $published = $this->qb()->table('posts')->where('status', '=', 'published')->count();
        $this->assertSame(1, $published);
    }

    public function test_update_and_delete(): void
    {
        $this->seedDependencies();
        $now = date('Y-m-d H:i:s');
        $qb = $this->qb()->table('posts');
        $id = $qb->insert([
            'title' => 'Orig', 'slug' => 'orig', 'content_md' => '', 'content_html' => '',
            'author_id' => 1, 'status' => 'draft',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $affected = $this->qb()->table('posts')->where('id', '=', $id)->update(['title' => 'Updated']);
        $this->assertSame(1, $affected);

        $row = $this->qb()->table('posts')->where('id', '=', $id)->first();
        $this->assertSame('Updated', $row['title']);

        $deleted = $this->qb()->table('posts')->where('id', '=', $id)->delete();
        $this->assertSame(1, $deleted);

        $this->assertNull($this->qb()->table('posts')->where('id', '=', $id)->first());
    }

    public function test_order_by_is_immutable(): void
    {
        $qb = $this->qb()->table('posts');
        $ordered = $qb->orderBy('created_at', 'DESC');
        $this->assertNotSame($qb, $ordered);
    }

    public function test_count_empty_table(): void
    {
        $count = $this->qb()->table('posts')->count();
        $this->assertSame(0, $count);
    }

    public function test_first_returns_null_when_empty(): void
    {
        $row = $this->qb()->table('posts')->where('slug', '=', 'nope')->first();
        $this->assertNull($row);
    }
}
