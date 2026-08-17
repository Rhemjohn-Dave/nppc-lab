<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

class HistoryAccess
{
    public const SETTING_KEY = 'history_visible_roles';

    /** @var list<string> */
    public const AVAILABLE_ROLES = [
        'admin',
        'receiving',
        'analyst',
        'head_analysis',
    ];

    /** @var list<string> */
    public const DEFAULT_ROLES = [
        'admin',
        'head_analysis',
    ];

    /**
     * @return list<string>
     */
    public static function visibleRoles(): array
    {
        $roles = Setting::getValue(self::SETTING_KEY, self::DEFAULT_ROLES);

        $normalized = collect($roles)
            ->filter(fn ($role) => is_string($role))
            ->values()
            ->all();

        $allowed = array_values(array_intersect($normalized, self::AVAILABLE_ROLES));

        return $allowed !== [] ? $allowed : self::DEFAULT_ROLES;
    }

    /**
     * @param  list<string>  $roles
     */
    public static function updateVisibleRoles(array $roles): void
    {
        $normalized = array_values(array_intersect($roles, self::AVAILABLE_ROLES));

        if ($normalized === []) {
            $normalized = ['admin'];
        }

        if (! in_array('admin', $normalized, true)) {
            $normalized[] = 'admin';
        }

        Setting::putValue(self::SETTING_KEY, array_values(array_unique($normalized)));
    }

    public static function userCanAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $visible = self::visibleRoles();

        foreach ($visible as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function roleOptions(): array
    {
        return [
            ['id' => 'admin', 'label' => 'Admin'],
            ['id' => 'receiving', 'label' => 'Receiving'],
            ['id' => 'analyst', 'label' => 'Analyst'],
            ['id' => 'head_analysis', 'label' => 'Head Analysis'],
        ];
    }
}
