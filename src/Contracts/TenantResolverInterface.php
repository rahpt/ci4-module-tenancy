<?php

namespace Rahpt\Ci4ModuleTenancy\Contracts;

use CodeIgniter\HTTP\RequestInterface;

/**
 * TenantResolverInterface - Defines the contract for tenant resolution strategies.
 *
 * Resolvers are responsible ONLY for extracting the tenant identifier from the request.
 * They do NOT authenticate, authorize, or validate membership — that is the filter's job.
 *
 * Implementations:
 *   - SubdomainTenantResolver  (reads from Host header subdomain)
 *   - HeaderTenantResolver     (reads from X-Tenant-ID or custom header)
 *   - SessionTenantResolver    (reads from session)
 *   - ApiTokenTenantResolver   (reads from API token payload)
 */
interface TenantResolverInterface
{
    /**
     * Attempts to resolve a tenant identifier from the request.
     * Returns null if this resolver cannot determine the tenant from this request.
     *
     * IMPORTANT: Returning a non-null value is NOT an authorization grant.
     * The filter must still validate membership and permissions after resolution.
     *
     * @param RequestInterface $request The current HTTP request
     * @return string|null The resolved tenant identifier, or null if not resolvable
     */
    public function resolve(RequestInterface $request): ?string;
}
