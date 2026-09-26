<?php

namespace Rahpt\Ci4ModuleTenancy\Config;

use CodeIgniter\Config\BaseConfig;

class Tenancy extends BaseConfig
{
    /**
     * How to detect the tenant.
     * Supported: 'subdomain', 'header', 'session'
     * Or set resolverClass to use a custom TenantResolverInterface implementation.
     */
    public string $detectionMode = 'subdomain';

    /**
     * The key used for detection (subdomain segment index, header name, or session key).
     */
    public string $detectionKey = '0'; // Subdomain index 0 (e.g., tenant.domain.com)

    /**
     * Header name to look for if detectionMode is 'header'.
     *
     * IMPORTANT: X-Tenant-ID (or any header) is CONTEXT only — it is NEVER authorization.
     * The resolved tenant still requires: authenticated user + valid membership + permission check.
     */
    public string $headerName = 'X-Tenant-ID';

    /**
     * Trusted base domains for subdomain detection.
     * REQUIRED when detectionMode = 'subdomain' to prevent DNS rebinding and Host header injection.
     *
     * Example: ['myapp.com', 'staging.myapp.com']
     * Only subdomains of these domains will be accepted as tenant identifiers.
     *
     * @var array<string>
     */
    public array $trustedBaseDomains = [];

    /**
     * Optional: Custom resolver class implementing TenantResolverInterface.
     * When set, overrides detectionMode-based resolver selection.
     * Must implement \Rahpt\Ci4ModuleTenancy\Contracts\TenantResolverInterface.
     */
    public ?string $resolverClass = null;

    /**
     * Whether to throw an HTTP 403 response if no tenant is detected.
     * Default true for SaaS multi-tenant isolation.
     */
    public bool $requireTenant = true;

    /**
     * When true, verifies that an authenticated user actually belongs to the resolved tenant
     * before activating the TenantContext (Zero-Trust header/subdomain verification).
     * Enabled by default for multi-tenant SaaS security.
     */
    public bool $validateMembership = true;

    /**
     * Optional custom callback or class implementing \Rahpt\Ci4ModuleTenancy\Contracts\TenantMembershipInterface.
     * Signature: fn(\CodeIgniter\Shield\Entities\User|object $user, string $tenantId): bool
     * Or class string implementing TenantMembershipInterface.
     */
    public ?string $membershipHandler = null;

    /**
     * Optional allowlist of permitted tenant IDs/slugs. Empty array allows all validated tenants.
     * @var array<string>
     */
    public array $allowedTenants = [];

    /**
     * Strict format validation for tenant IDs (alphanumeric, dashes, underscores only).
     */
    public bool $strictTenantValidation = true;
}
