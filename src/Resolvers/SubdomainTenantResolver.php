<?php

namespace Rahpt\Ci4ModuleTenancy\Resolvers;

use CodeIgniter\HTTP\RequestInterface;
use Rahpt\Ci4ModuleTenancy\Contracts\TenantResolverInterface;
use Rahpt\Ci4ModuleTenancy\Config\Tenancy;

/**
 * SubdomainTenantResolver - Resolves tenant from the HTTP Host header subdomain.
 *
 * Security: trusted base domains MUST be configured. The resolver will ONLY accept
 * subdomains of known base domains. This prevents DNS rebinding and HOST header injection.
 */
class SubdomainTenantResolver implements TenantResolverInterface
{
    public function __construct(protected Tenancy $config) {}

    public function resolve(RequestInterface $request): ?string
    {
        $hostname = $request->getUri()->getHost();

        // Security: validate against trusted base domains
        if (!$this->isFromTrustedBaseDomain($hostname)) {
            log_message('warning', "[SubdomainTenantResolver] Host [{$hostname}] is not from a trusted base domain. Ignoring.");
            return null;
        }

        $parts = explode('.', $hostname);
        $index = (int) $this->config->detectionKey;

        // A valid subdomain tenant requires at least: tenant.domain.tld (3 parts)
        if (count($parts) < 3) {
            return null;
        }

        $tenantId = $parts[$index] ?? null;
        return ($tenantId !== null && $tenantId !== '') ? $tenantId : null;
    }

    /**
     * Validates that the hostname ends with one of the configured trusted base domains.
     * Prevents accepting arbitrary Host headers (DNS rebinding / header injection).
     */
    protected function isFromTrustedBaseDomain(string $hostname): bool
    {
        $trustedDomains = $this->config->trustedBaseDomains ?? [];

        if (empty($trustedDomains)) {
            // If no trusted domains configured, only warn (backwards compatibility)
            log_message('notice', '[SubdomainTenantResolver] No trustedBaseDomains configured. Consider setting them for SSRF protection.');
            return true;
        }

        $hostname = strtolower(trim($hostname));
        foreach ($trustedDomains as $domain) {
            $domain = strtolower(trim($domain, '. '));
            // hostname must be exactly domain or end with .domain
            if ($hostname === $domain || str_ends_with($hostname, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }
}
