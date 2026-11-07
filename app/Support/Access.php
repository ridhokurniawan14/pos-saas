<?php

namespace App\Support;

class Access
{
    public const AREAS = [
        'pos',
        'orders',
        'shifts',
        'customers',
        'products',
        'categories',
        'stock',
        'purchases',
        'suppliers',
        'reports',
        'expenses',
        'payments',
        'settings',
        'users',
    ];

    public const ROLES = [
        'owner' => self::AREAS,
        'admin' => ['pos', 'orders', 'shifts', 'customers', 'products', 'categories', 'stock', 'purchases', 'suppliers', 'reports', 'expenses', 'payments', 'settings'],
        'kasir' => ['pos', 'orders', 'shifts', 'customers'],
        'gudang' => ['products', 'categories', 'stock', 'purchases', 'suppliers'],
    ];

    public static function can(?string $role, string $area): bool
    {
        return $role !== null && in_array($area, self::ROLES[$role] ?? [], true);
    }

    public static function areas(?string $role): array
    {
        return $role !== null ? (self::ROLES[$role] ?? []) : [];
    }
}
