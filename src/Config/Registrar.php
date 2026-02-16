<?php

namespace Rahpt\Ci4ModuleTenancy\Config;

/**
 * Registrar - Autoconfiguração de componentes do pacote ci4-module-tenancy no CodeIgniter 4.
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
