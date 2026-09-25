<?php

namespace Rahpt\Ci4ModuleTenancy\Config;

use CodeIgniter\Config\BaseConfig;

class Tenancy extends BaseConfig
{
    /**
     * How to detect the tenant.
     * Supported: 'subdomain', 'header', 'session'
     */
    public string $detectionMode = 'subdomain';

    /**
     * The key used for detection (subdomain segment index, header name, or session key).
     */
    public string $detectionKey = '0'; // Subdomain index 0 (e.g., tenant.domain.com)

    /**
     * Header name to look for if detectionMode is 'header'.
     */
    public string $headerName = 'X-Tenant-ID';

    /**
     * Whether to throw an HTTP 403 response if no tenant is detected.
     */
    public bool $requireTenant = false;

    /**
     * When true, verifies that an authenticated user actually belongs to the resolved tenant
     * before activating the TenantContext. Highly recommended for multi-tenant SaaS.
     */
    public bool $validateMembership = false;

    /**
     * Optional custom callback or class string implementing membership check.
     * Signature: fn(\CodeIgniter\Shield\Entities\User|object $user, string $tenantId): bool
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
