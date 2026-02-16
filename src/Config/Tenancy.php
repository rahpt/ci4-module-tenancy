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
     * Whether to throw an exception if no tenant is detected.
     */
    public bool $requireTenant = false;
}
