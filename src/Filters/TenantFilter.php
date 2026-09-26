<?php

namespace Rahpt\Ci4ModuleTenancy\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Rahpt\Ci4ModuleTenancy\TenantContext;

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
                $index = (int) $config->detectionKey;
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

        // 1. Strict Tenant ID format check (prevents path traversal or header injection)
        if ($tenantId !== null && $config->strictTenantValidation) {
            if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $tenantId)) {
                return service('response')->setStatusCode(400, 'Invalid Tenant Identifier Format');
            }
        }

        // 2. Allowlist check if configured
        if ($tenantId !== null && !empty($config->allowedTenants)) {
            if (!in_array($tenantId, $config->allowedTenants, true)) {
                return service('response')->setStatusCode(403, 'Tenant Not Allowed');
            }
        }

        // 3. User Membership validation (Zero-Trust header/subdomain verification)
        if ($tenantId !== null && $config->validateMembership) {
            if (!$this->validateUserMembership($tenantId, $config)) {
                $userId = function_exists('auth') && auth()->user() ? auth()->user()->id : 'unauthenticated';
                log_message('warning', sprintf('[TenantSecurity] Access denied: user [%s] is not an authorized member of tenant [%s] via [%s]', $userId, $tenantId, $config->detectionMode));
                return service('response')->setStatusCode(403, 'Access to requested tenant is unauthorized for current user');
            }
        }

        // 4. Activate TenantContext with resolution source or enforce requirement
        if ($tenantId !== null && $tenantId !== '') {
            TenantContext::set($tenantId, $config->detectionMode);
        } elseif ($config->requireTenant) {
            return service('response')->setStatusCode(403, 'Tenant Required');
        }
    }

    /**
     * Validates that the currently authenticated user is authorized for the given tenant.
     */
    protected function validateUserMembership(string $tenantId, $config): bool
    {
        if (!function_exists('auth') || !auth()->loggedIn()) {
            // If membership check is required, user must be authenticated
            return false;
        }

        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // Custom membership handler class or callable
        if (!empty($config->membershipHandler)) {
            if (is_string($config->membershipHandler) && class_exists($config->membershipHandler)) {
                $handler = new $config->membershipHandler();
                if ($handler instanceof \Rahpt\Ci4ModuleTenancy\Contracts\TenantMembershipInterface) {
                    return $handler->userBelongsToTenant($user, $tenantId);
                }
            }
            if (is_callable($config->membershipHandler)) {
                return (bool) call_user_func($config->membershipHandler, $user, $tenantId);
            }
        }

        // Check if user entity has an inTenant() method
        if (method_exists($user, 'inTenant')) {
            return (bool) $user->inTenant($tenantId);
        }

        // Check if user has matching tenant_id property or attribute
        if (isset($user->tenant_id)) {
            return (string) $user->tenant_id === (string) $tenantId;
        }

        // If user is superadmin (in admin group), allow access by default
        if (method_exists($user, 'inGroup') && $user->inGroup('admin', 'superadmin')) {
            return true;
        }

        return false;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Clear context after request finishes to avoid memory pollution in long-running processes (e.g. Octane/Roadrunner)
        TenantContext::clear();
    }
}
