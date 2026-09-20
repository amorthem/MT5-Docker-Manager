<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_dev_can_manage_every_role(): void
    {
        foreach (UserRole::cases() as $role) {
            self::assertTrue(UserRole::Dev->canManage($role));
        }
    }

    public function test_admin_can_manage_only_users_and_support(): void
    {
        self::assertTrue(UserRole::Admin->canManage(UserRole::User));
        self::assertTrue(UserRole::Admin->canManage(UserRole::Support));
        self::assertFalse(UserRole::Admin->canManage(UserRole::Admin));
        self::assertFalse(UserRole::Admin->canManage(UserRole::Dev));
    }

    public function test_support_and_user_cannot_manage_roles(): void
    {
        self::assertFalse(UserRole::Support->canManage(UserRole::User));
        self::assertFalse(UserRole::User->canManage(UserRole::Support));
    }
}