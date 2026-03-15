<?php

namespace App;

use App\Models\User;

final class ApiPermissionMatrix
{
    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private const MATRIX = [
        'products' => [
            'viewAny' => ['super_admin', 'owner', 'seller'],
            'view' => ['super_admin', 'owner', 'seller'],
            'create' => ['super_admin', 'owner'],
            'update' => ['super_admin', 'owner'],
            'delete' => ['super_admin', 'owner'],
        ],
        'expenses' => [
            'viewAny' => ['super_admin', 'owner'],
            'view' => ['super_admin', 'owner'],
            'create' => ['super_admin', 'owner'],
            'update' => ['super_admin', 'owner'],
            'delete' => ['super_admin', 'owner'],
        ],
        'currencies' => [
            'viewAny' => ['super_admin', 'owner', 'seller'],
            'view' => ['super_admin', 'owner', 'seller'],
            'update' => ['super_admin'],
        ],
        'debts' => [
            'viewAny' => ['super_admin', 'owner'],
            'view' => ['super_admin', 'owner'],
            'create' => ['super_admin', 'owner'],
            'update' => ['super_admin', 'owner'],
        ],
        'purchases' => [
            'viewAny' => ['super_admin', 'owner'],
            'view' => ['super_admin', 'owner'],
            'create' => ['super_admin', 'owner'],
        ],
        'sales' => [
            'viewAny' => ['super_admin', 'owner', 'seller'],
            'view' => ['super_admin', 'owner', 'seller'],
            'create' => ['super_admin', 'owner', 'seller'],
        ],
        'shops' => [
            'viewAny' => ['super_admin', 'owner', 'seller'],
            'view' => ['super_admin', 'owner', 'seller'],
            'create' => ['super_admin'],
            'update' => ['super_admin'],
            'delete' => ['super_admin'],
        ],
        'users' => [
            'viewAny' => ['super_admin', 'owner'],
            'view' => ['super_admin', 'owner', 'seller'],
            'create' => ['super_admin', 'owner'],
            'update' => ['super_admin', 'owner', 'seller'],
            'delete' => ['super_admin', 'owner'],
        ],
        'settings' => [
            'view' => ['super_admin', 'owner'],
            'update' => ['super_admin', 'owner'],
        ],
        'reports' => [
            'view' => ['super_admin', 'owner'],
        ],
    ];

    public static function allows(User $user, string $resource, string $action): bool
    {
        $allowedRoles = self::MATRIX[$resource][$action] ?? [];
        $role = self::resolveRole($user);

        return in_array($role, $allowedRoles, true);
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    public static function all(): array
    {
        return self::MATRIX;
    }

    private static function resolveRole(User $user): string
    {
        if ($user->role instanceof UserRole) {
            return $user->role->value;
        }

        return (string) $user->role;
    }
}
