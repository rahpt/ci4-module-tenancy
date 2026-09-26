<?php

namespace Rahpt\Ci4ModuleTenancy\Resolvers;

use CodeIgniter\HTTP\RequestInterface;
use Rahpt\Ci4ModuleTenancy\Contracts\TenantResolverInterface;
use Rahpt\Ci4ModuleTenancy\Config\Tenancy;

/**
 * HeaderTenantResolver - Resolves tenant from an HTTP request header (e.g. X-Tenant-ID).
 *
 * Security: Headers are NEVER authorization. A tenant resolved via header MUST still have
 * membership validated by the TenantFilter before TenantContext is activated.
 */
class HeaderTenantResolver implements TenantResolverInterface
{
    public function __construct(protected Tenancy $config) {}

    public function resolve(RequestInterface $request): ?string
    {
        $headerName = $this->config->headerName ?? 'X-Tenant-ID';
        $value = $request->header($headerName)?->getValue();

        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
