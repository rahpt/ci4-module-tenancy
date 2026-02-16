<?php

use Rahpt\Ci4ModuleTenancy\Tenancy\TenantContext;

if (!function_exists('tenant')) {
    /**
     * Helper function to get the current tenant.
     * 
     * @return mixed|null
     */
    function tenant(): mixed
    {
        return TenantContext::get();
    }
}

if (!function_exists('has_tenant')) {
    /**
     * Helper function to check if a tenant is set.
     */
    function has_tenant(): bool
    {
        return TenantContext::hasTenant();
    }
}
