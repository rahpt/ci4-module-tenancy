<?php

namespace Rahpt\Ci4ModuleTenancy\Tests;

use PHPUnit\Framework\TestCase;
use Rahpt\Ci4ModuleTenancy\TenantContext;

class TenantContextTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    public function testSetAndGetTenant(): void
    {
        $tenantId = 'tenant-123';

        TenantContext::set($tenantId);

        $this->assertSame($tenantId, TenantContext::get());
        $this->assertTrue(TenantContext::hasTenant());
    }

    public function testClearTenant(): void
    {
        TenantContext::set('abc');
        TenantContext::clear();

        $this->assertNull(TenantContext::get());
        $this->assertFalse(TenantContext::hasTenant());
    }
}
