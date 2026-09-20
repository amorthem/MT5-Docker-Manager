<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Support = 'support';
    case Admin = 'admin';
    case Dev = 'dev';

    public function canManage(UserRole $target): bool
    {
        return match ($this) {
            self::Dev => true,
            self::Admin => in_array($target, [self::User, self::Support], true),
            self::Support, self::User => false,
        };
    }
}