<?php

namespace Rahpt\Ci4ModuleTenancy\Resolvers;

use CodeIgniter\HTTP\RequestInterface;
use Rahpt\Ci4ModuleTenancy\Contracts\TenantResolverInterface;
use Rahpt\Ci4ModuleTenancy\Config\Tenancy;

/**
 * SessionTenantResolver - Resolves tenant from the session.
 *
 * Suitable for UI flows where tenant was previously selected and stored in session.
 * Security: session key must be set only after authenticated tenant selection,
 * never from user-supplied request data directly.
 */
class SessionTenantResolver implements TenantResolverInterface
{
    public function __construct(protected Tenancy $config) {}

    public function resolve(RequestInterface $request): ?string
    {
        try {
            $session  = service('session');
            $key      = $this->config->detectionKey ?? 'tenant_id';
            $tenantId = $session->get($key);
            return ($tenantId !== null && $tenantId !== '') ? (string) $tenantId : null;
        } catch (\Throwable) {
            return null; // Session not initialized (CLI, tests)
        }
    }
}
