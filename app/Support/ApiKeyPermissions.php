<?php

namespace App\Support;

class ApiKeyPermissions
{
    /**
     * Get the supported resources that can be assigned to API keys.
     *
     * @return array<string, string>
     */
    public static function resources(): array
    {
        /** @var array<string, string> $resources */
        $resources = config('api_keys.resources', []);

        return $resources;
    }

    /**
     * Get the supported actions for each API resource.
     *
     * @return array<string, string>
     */
    public static function actions(): array
    {
        /** @var array<string, string> $actions */
        $actions = config('api_keys.actions', []);

        return $actions;
    }

    /**
     * Get every available permission value.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::resources() as $resource => $label) {
            foreach (self::actions() as $action => $actionLabel) {
                $permissions[] = self::permission($resource, $action);
            }
        }

        return $permissions;
    }

    /**
     * Get permissions grouped for form rendering.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::resources() as $resource => $label) {
            foreach (self::actions() as $action => $actionLabel) {
                $grouped[$resource][self::permission($resource, $action)] = $actionLabel;
            }
        }

        return $grouped;
    }

    /**
     * Build a permission value from its resource and action.
     */
    public static function permission(string $resource, string $action): string
    {
        return "{$resource}:{$action}";
    }

    /**
     * Normalize and validate a list of selected permissions.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public static function normalize(array $permissions): array
    {
        $allowedPermissions = self::all();

        return array_values(array_filter(
            array_unique($permissions),
            fn (string $permission): bool => in_array($permission, $allowedPermissions, true)
        ));
    }
}
