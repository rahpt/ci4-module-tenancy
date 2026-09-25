<?php

namespace Rahpt\Ci4ModuleTenancy;

use RuntimeException;

/**
 * TenantContext - Holds current tenant information globally with execution scoping.
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
     * Gets the scalar ID of the current tenant.
     */
    public static function id(): ?string
    {
        if (self::$tenant === null) {
            return null;
        }

        if (is_object(self::$tenant)) {
            return (string) (self::$tenant->id ?? self::$tenant->uuid ?? self::$tenant->slug ?? '');
        }

        if (is_array(self::$tenant)) {
            return (string) (self::$tenant['id'] ?? self::$tenant['uuid'] ?? self::$tenant['slug'] ?? '');
        }

        return (string) self::$tenant;
    }

    /**
     * Gets the current tenant or throws an exception if none is set.
     *
     * @throws RuntimeException
     */
    public static function require(): mixed
    {
        if (self::$tenant === null) {
            throw new RuntimeException('Tenant context is required for this operation, but no tenant is active.');
        }

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

    /**
     * Runs a callback within the scope of a specific tenant,
     * ensuring the previous tenant context is restored even if an exception occurs.
     *
     * @template T
     * @param mixed $tenant
     * @param callable(): T $callback
     * @return T
     */
    public static function run(mixed $tenant, callable $callback): mixed
    {
        $previousTenant = self::$tenant;
        self::$tenant = $tenant;

        try {
            return $callback();
        } finally {
            self::$tenant = $previousTenant;
        }
    }
}
