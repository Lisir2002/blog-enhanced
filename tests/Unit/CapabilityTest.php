<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\Auth\Capability;

/**
 * Capability 权限矩阵测试（新五级角色体系）。
 */
class CapabilityTest extends TestCase
{
    public function test_super_admin_has_all_caps(): void
    {
        $this->assertTrue(Capability::has('super_admin', 'edit_posts'));
        $this->assertTrue(Capability::has('super_admin', 'manage_categories'));
        $this->assertTrue(Capability::has('super_admin', 'manage_users'));
        $this->assertTrue(Capability::has('super_admin', 'anything_unknown'));
    }

    public function test_senior_admin_can_edit_others_posts(): void
    {
        $this->assertTrue(Capability::has('senior_admin', 'edit_posts'));
        $this->assertTrue(Capability::has('senior_admin', 'edit_others_posts'));
        $this->assertTrue(Capability::has('senior_admin', 'manage_users'));
        $this->assertFalse(Capability::has('senior_admin', 'manage_options'));
    }

    public function test_editor_admin_can_review_and_delete_own(): void
    {
        $this->assertTrue(Capability::has('editor_admin', 'edit_posts'));
        $this->assertTrue(Capability::has('editor_admin', 'edit_others_posts'));
        $this->assertTrue(Capability::has('editor_admin', 'delete_posts'));
    }

    public function test_editor_writer_can_edit_but_not_publish(): void
    {
        $this->assertTrue(Capability::has('editor_writer', 'edit_posts'));
        $this->assertFalse(Capability::has('editor_writer', 'publish_posts'));
    }

    public function test_visitor_only_read(): void
    {
        $this->assertTrue(Capability::has('visitor', 'read'));
        $this->assertFalse(Capability::has('visitor', 'edit_posts'));
    }

    public function test_unknown_role_has_no_caps(): void
    {
        $this->assertFalse(Capability::has('ghost', 'read'));
    }

    public function test_roles_list(): void
    {
        $roles = Capability::roles();
        $this->assertArrayHasKey('super_admin', $roles);
        $this->assertArrayHasKey('visitor', $roles);
        $this->assertCount(5, $roles);
    }
}