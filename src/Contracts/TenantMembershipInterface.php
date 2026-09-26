<?php

namespace Rahpt\Ci4ModuleTenancy\Contracts;

/**
 * TenantMembershipInterface - Contract for validating multi-tenant memberships.
 * Supports enterprise multi-tenancy models (users belong to multiple organizations/tenants).
 */
interface TenantMembershipInterface
{
    /**
     * Checks if a user is an active member of the given tenant.
     */
    public function userBelongsToTenant(object|string|int $user, string $tenantId): bool;

    /**
     * Gets the user's role or status within the given tenant.
     */
    public function getUserTenantRole(object|string|int $user, string $tenantId): ?string;
}
