<?php

namespace Rahpt\Ci4ModuleTenancy\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Rahpt\Ci4ModuleTenancy\Tenancy\TenantContext;
use Config\Tenancy as TenancyConfig;

class TenantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config('Tenancy') ?? new \Rahpt\Ci4ModuleTenancy\Config\Tenancy();
        $tenantId = null;

        switch ($config->detectionMode) {
            case 'subdomain':
                $hostname = $request->getUri()->getHost();
                $parts = explode('.', $hostname);
                $index = (int)$config->detectionKey;
                if (isset($parts[$index]) && count($parts) > 2) {
                    $tenantId = $parts[$index];
                }
                break;

            case 'header':
                $tenantId = $request->header($config->headerName)?->getValue();
                break;

            case 'session':
                $session = service('session');
                $tenantId = $session->get($config->detectionKey);
                break;
        }

        if ($tenantId) {
            // For now, we set the ID. In a real app, you might want to fetch a Tenant object from DB.
            TenantContext::set($tenantId);
        } elseif ($config->requireTenant) {
            return service('response')->setStatusCode(403, 'Tenant Required');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed
    }
}
