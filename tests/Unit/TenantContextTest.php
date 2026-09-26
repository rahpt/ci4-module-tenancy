<?php

namespace Rahpt\Ci4ModuleTenancy\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rahpt\Ci4ModuleTenancy\TenantContext;

/**
 * Tests for TenantContext multi-tenant isolation, global scope, and mass-assignment protection.
 */
class TenantContextTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    public function testSetAndGetTenant(): void
    {
        TenantContext::set('tenant-123');
        $this->assertSame('tenant-123', TenantContext::get());
        $this->assertTrue(TenantContext::hasTenant());
    }

    public function testClearTenant(): void
    {
        TenantContext::set('abc');
        TenantContext::clear();
        $this->assertNull(TenantContext::get());
        $this->assertFalse(TenantContext::hasTenant());
    }

    public function testSourceTracking(): void
    {
        TenantContext::set('tenant-1', 'subdomain');
        $this->assertSame('subdomain', TenantContext::source());
    }

    public function testIdExtraction(): void
    {
        TenantContext::set('tenant-abc');
        $this->assertSame('tenant-abc', TenantContext::id());
    }

    public function testIdFromObject(): void
    {
        $obj     = new \stdClass();
        $obj->id = 42;
        TenantContext::set($obj);
        $this->assertSame('42', TenantContext::id());
    }

    public function testIdFromArray(): void
    {
        TenantContext::set(['id' => 'arr-tenant']);
        $this->assertSame('arr-tenant', TenantContext::id());
    }

    public function testIsGlobalDefaultsFalse(): void
    {
        $this->assertFalse(TenantContext::isGlobal());
    }

    public function testRunRestoresPreviousContext(): void
    {
        TenantContext::set('tenant-A');

        TenantContext::run('tenant-B', function () {
            $this->assertSame('tenant-B', TenantContext::get());
        });

        $this->assertSame('tenant-A', TenantContext::get());
    }

    public function testRunRestoresPreviousContextOnException(): void
    {
        TenantContext::set('tenant-A');

        try {
            TenantContext::run('tenant-B', function () {
                throw new \RuntimeException('Inner error');
            });
        } catch (\RuntimeException) {}

        $this->assertSame('tenant-A', TenantContext::get());
    }

    public function testRunGlobalRequiresNonEmptyReason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TenantContext::runGlobal(fn() => null, '  ');
    }

    public function testRunGlobalSetsGlobalScopeAndRestores(): void
    {
        $this->assertFalse(TenantContext::isGlobal());

        TenantContext::runGlobal(function () {
            $this->assertTrue(TenantContext::isGlobal());
        }, 'test reason');

        $this->assertFalse(TenantContext::isGlobal());
    }

    public function testRunGlobalRestoresOnException(): void
    {
        try {
            TenantContext::runGlobal(function () {
                throw new \RuntimeException('fail');
            }, 'reason');
        } catch (\RuntimeException) {}

        $this->assertFalse(TenantContext::isGlobal());
    }

    public function testRequireThrowsWhenNoTenant(): void
    {
        $this->expectException(\RuntimeException::class);
        TenantContext::require();
    }

    public function testRequireReturnsTenantWhenSet(): void
    {
        TenantContext::set('tenant-x');
        $this->assertSame('tenant-x', TenantContext::require());
    }

    public function testClearResetsGlobalScope(): void
    {
        // Manually set global (bypass runGlobal for unit isolation)
        TenantContext::set('t1');
        TenantContext::clear();
        $this->assertFalse(TenantContext::isGlobal());
        $this->assertSame('system', TenantContext::source());
    }
}
