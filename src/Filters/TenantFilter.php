<?php

namespace Rahpt\Ci4ModuleTenancy\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Rahpt\Ci4ModuleTenancy\Contracts\TenantResolverInterface;
use Rahpt\Ci4ModuleTenancy\Resolvers\SubdomainTenantResolver;
use Rahpt\Ci4ModuleTenancy\Resolvers\HeaderTenantResolver;
use Rahpt\Ci4ModuleTenancy\Resolvers\SessionTenantResolver;
use Rahpt\Ci4ModuleTenancy\TenantContext;

/**
 * TenantFilter - HTTP filter that resolves tenant context and validates membership.
 *
 * Resolution pipeline:
 *   Request -> Resolver -> TenantContext -> MembershipService -> Authorization
 *
 * Security:
 *   - Resolution (header/subdomain/session) is NEVER authorization.
 *   - Membership validation is always required when $config->validateMembership = true.
 *   - Trusted base domains must be configured for subdomain mode.
 */
class TenantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config   = config('Tenancy') ?? new \Rahpt\Ci4ModuleTenancy\Config\Tenancy();
        $resolver = $this->makeResolver($config);
        $tenantId = $resolver->resolve($request);

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

        // 3. Membership validation (Zero-Trust: header/subdomain alone are never authorization)
        if ($tenantId !== null && $config->validateMembership) {
            if (!$this->validateUserMembership($tenantId, $config)) {
                $userId = function_exists('auth') && auth()->user() ? auth()->user()->id : 'unauthenticated';
                log_message('warning', sprintf(
                    '[TenantSecurity] Access denied: user [%s] is not an authorized member of tenant [%s] via [%s]',
                    $userId,
                    $tenantId,
                    get_class($resolver)
                ));
                \CodeIgniter\Events\Events::trigger('rahpt.tenant.membership_denied', $userId, $tenantId);
                return service('response')->setStatusCode(403, 'Access to requested tenant is unauthorized for current user');
            }
        }

        // 4. Activate TenantContext with resolution source or enforce requirement
        if ($tenantId !== null && $tenantId !== '') {
            $source = $config->detectionMode;
            TenantContext::set($tenantId, $source);
            \CodeIgniter\Events\Events::trigger('rahpt.tenant.resolved', $tenantId, $source);
        } elseif ($config->requireTenant) {
            return service('response')->setStatusCode(403, 'Tenant Required');
        }
    }

    /**
     * Builds the appropriate resolver for the configured detection mode.
     * Custom resolverClass takes precedence over detectionMode.
     */
    protected function makeResolver($config): TenantResolverInterface
    {
        // Custom resolver class takes priority
        if (!empty($config->resolverClass) && class_exists($config->resolverClass)) {
            $resolver = new $config->resolverClass($config);
            if ($resolver instanceof TenantResolverInterface) {
                return $resolver;
            }
        }

        return match ($config->detectionMode) {
            'header'   => new HeaderTenantResolver($config),
            'session'  => new SessionTenantResolver($config),
            default    => new SubdomainTenantResolver($config), // 'subdomain' is default
        };
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
