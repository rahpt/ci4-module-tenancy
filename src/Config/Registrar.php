<?php

namespace Rahpt\Ci4ModuleTenancy\Config;

/**
 * Registrar for Tenancy Package
 */
class Registrar
{
    /**
     * Registers filters with CodeIgniter 4
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'tenant' => \Rahpt\Ci4ModuleTenancy\Filters\TenantFilter::class,
            ],
        ];
    }
}
