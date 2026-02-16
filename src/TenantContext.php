<?php

namespace Rahpt\Ci4ModuleTenancy;

/**
 * TenantContext - Holds current tenant information globally.
 */
class TenantContext
{
    protected static mixed $tenant = null;

    /**
     * Sets the current tenant.
     */
    public static function set(mixed $tenant): void
    {
        self::$tenant = $tenant;
    }

    /**
     * Gets the current tenant.
     */
    public static function get(): mixed
    {
        return self::$tenant;
    }

    /**
     * Clears the current tenant.
     */
    public static function clear(): void
    {
        self::$tenant = null;
    }

    /**
     * Checks if a tenant is set.
     */
    public static function hasTenant(): bool
    {
        return self::$tenant !== null;
    }
}
