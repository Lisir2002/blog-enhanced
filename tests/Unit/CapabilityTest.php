<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\Auth\Capability;

/**
 * Capability 权限矩阵测试。
 */
class CapabilityTest extends TestCase
{
    public function test_admin_has_all_caps(): void
    {
        $this->assertTrue(Capability::has('admin', 'edit_posts'));
        $this->assertTrue(Capability::has('admin', 'manage_categories'));
        $this->assertTrue(Capability::has('admin', 'anything_unknown'));
    }

    public function test_editor_can_edit_others_posts(): void
    {
        $this->assertTrue(Capability::has('editor', 'edit_posts'));
        $this->assertTrue(Capability::has('editor', 'edit_others_posts'));
        $this->assertFalse(Capability::has('editor', 'manage_options'));
    }

    public function test_author_cannot_edit_others_posts(): void
    {
        $this->assertTrue(Capability::has('author', 'edit_posts'));
        $this->assertFalse(Capability::has('author', 'edit_others_posts'));
    }

    public function test_contributor_can_edit_but_not_publish(): void
    {
        $this->assertTrue(Capability::has('contributor', 'edit_posts'));
        $this->assertFalse(Capability::has('contributor', 'publish_posts'));
    }

    public function test_subscriber_only_read(): void
    {
        $this->assertTrue(Capability::has('subscriber', 'read'));
        $this->assertFalse(Capability::has('subscriber', 'edit_posts'));
    }

    public function test_unknown_role_has_no_caps(): void
    {
        $this->assertFalse(Capability::has('ghost', 'read'));
    }

    public function test_roles_list(): void
    {
        $roles = Capability::roles();
        $this->assertArrayHasKey('admin', $roles);
        $this->assertArrayHasKey('subscriber', $roles);
        $this->assertCount(5, $roles);
    }
}
