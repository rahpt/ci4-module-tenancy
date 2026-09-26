<?php

namespace Rahpt\Ci4ModuleTenancy;

use RuntimeException;
use SecurityException;

/**
 * TenantContext - Holds current tenant information globally with execution scoping,
 * provenance tracking (source), and audited global bypass controls.
 */
class TenantContext
{
    protected static mixed $tenant = null;
    protected static string $source = 'system';
    protected static bool $globalScopeActive = false;

    /**
     * Sets the current tenant along with its resolution provenance source.
     * Sources: 'subdomain', 'header', 'session', 'api-token', 'cli', 'job', 'system'
     */
    public static function set(mixed $tenant, string $source = 'system'): void
    {
        self::$tenant = $tenant;
        self::$source = $source;
    }

    /**
     * Gets the current tenant.
     */
    public static function get(): mixed
    {
        return self::$tenant;
    }

    /**
     * Gets the resolution source of the current tenant context.
     */
    public static function source(): string
    {
        return self::$source;
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
        if (self::$tenant === null && !self::$globalScopeActive) {
            throw new RuntimeException('Tenant context is required for this operation, but no tenant is active.');
        }

        return self::$tenant;
    }

    /**
     * Clears the current tenant and resets the source.
     */
    public static function clear(): void
    {
        self::$tenant = null;
        self::$source = 'system';
        self::$globalScopeActive = false;
    }

    /**
     * Checks if a tenant is set or if global scope is explicitly active.
     */
    public static function hasTenant(): bool
    {
        return self::$tenant !== null;
    }

    /**
     * Checks if global (tenant-agnostic) execution scope is currently active.
     */
    public static function isGlobal(): bool
    {
        return self::$globalScopeActive;
    }

    /**
     * Runs a callback within the scope of a specific tenant,
     * ensuring the previous tenant context and source are restored even if an exception occurs.
     *
     * @template T
     * @param mixed $tenant
     * @param callable(): T $callback
     * @param string $source
     * @return T
     */
    public static function run(mixed $tenant, callable $callback, string $source = 'system'): mixed
    {
        $previousTenant = self::$tenant;
        $previousSource = self::$source;
        $previousGlobal = self::$globalScopeActive;

        self::$tenant = $tenant;
        self::$source = $source;
        self::$globalScopeActive = false;

        try {
            return $callback();
        } finally {
            self::$tenant = $previousTenant;
            self::$source = $previousSource;
            self::$globalScopeActive = $previousGlobal;
        }
    }

    /**
     * Safely executes an administrative operation across all tenants (global scope).
     * Bypasses tenant scoping for the duration of the callback with mandatory audit logging
     * and optional permission check.
     *
     * @template T
     * @param callable(): T $callback
     * @param string $reason Mandatory business/technical reason for bypassing tenant isolation
     * @param string|null $capability Optional Shield permission required (e.g. 'admin.access')
     * @return T
     */
    public static function runGlobal(callable $callback, string $reason, ?string $capability = null): mixed
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A specific reason must be provided to run in global tenant scope.');
        }

        if ($capability !== null && function_exists('auth') && auth()->loggedIn()) {
            $user = auth()->user();
            if ($user && !$user->can($capability)) {
                throw new RuntimeException("Unauthorized: user lacks capability [{$capability}] required for global tenant scope.");
            }
        }

        $userId = function_exists('auth') && auth()->user() ? auth()->user()->id : 'system';
        log_message('notice', sprintf('[TenantAudit] Global scope granted for user [%s]. Reason: %s', $userId, $reason));

        $previousGlobal = self::$globalScopeActive;
        self::$globalScopeActive = true;

        try {
            return $callback();
        } finally {
            self::$globalScopeActive = $previousGlobal;
        }
    }
}
