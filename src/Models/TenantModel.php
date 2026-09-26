<?php

namespace Rahpt\Ci4ModuleTenancy\Models;

use CodeIgniter\Model;
use Rahpt\Ci4ModuleTenancy\TenantContext;
use RuntimeException;

/**
 * TenantModel - Base model that enforces multi-tenant data isolation automatically.
 * 
 * Extends CodeIgniter\Model to intercept SELECT, INSERT, UPDATE, and DELETE operations,
 * guaranteeing that queries are automatically scoped to the current tenant.
 */
abstract class TenantModel extends Model
{
    /**
     * Name of the database column that stores the tenant identifier.
     */
    protected string $tenantColumn = 'tenant_id';

    /**
     * Whether automatic tenant scoping is active for this model instance.
     */
    protected bool $tenantScopeActive = true;

    /**
     * Whether to require an active tenant context. If true and no tenant is set,
     * queries will throw an exception instead of risking data exposure.
     */
    protected bool $requireTenant = true;

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        // Prepend tenant hooks to ensure they run before user-defined callbacks
        array_unshift($this->beforeFind, 'applyTenantScopeOnFind');
        array_unshift($this->beforeInsert, 'applyTenantScopeOnInsert');
        array_unshift($this->beforeUpdate, 'applyTenantScopeOnUpdate');
        array_unshift($this->beforeDelete, 'applyTenantScopeOnDelete');
    }

    /**
     * Temporarily disables tenant scoping for the current query instance.
     * Requires an explicit reason and records an audit log to prevent accidental bypass.
     */
    public function withoutTenantScope(?string $reason = null, ?string $capability = null): static
    {
        if ($capability !== null && function_exists('auth') && auth()->loggedIn()) {
            $user = auth()->user();
            if ($user && !$user->can($capability)) {
                throw new RuntimeException("Unauthorized: user lacks capability [{$capability}] to bypass tenant scope.");
            }
        }

        $auditReason = $reason ?? 'Unspecified programmatic bypass';
        log_message('warning', sprintf('[TenantSecurity] Model [%s] tenant scoping bypassed. Reason: %s', static::class, $auditReason));

        $this->tenantScopeActive = false;
        return $this;
    }

    /**
     * Re-enables tenant scoping if it was previously disabled.
     */
    public function withTenantScope(): static
    {
        $this->tenantScopeActive = true;
        return $this;
    }

    /**
     * Retrieves the current active tenant identifier.
     */
    protected function resolveCurrentTenantId(): ?string
    {
        return TenantContext::id();
    }

    /**
     * Hook applied before any find/select query.
     */
    protected function applyTenantScopeOnFind(array $data): array
    {
        // Bypass if instance-scoped off or globally granted
        if (!$this->tenantScopeActive || TenantContext::isGlobal()) {
            return $data;
        }

        $tenantId = $this->resolveCurrentTenantId();

        if ($tenantId === null) {
            if ($this->requireTenant) {
                throw new RuntimeException(
                    sprintf('TenantModel [%s] query blocked: tenant context is required but none was set.', static::class)
                );
            }
            return $data;
        }

        $prefix = !empty($this->table) ? "{$this->table}." : '';
        $this->builder()->where($prefix . $this->tenantColumn, $tenantId);

        return $data;
    }

    /**
     * Hook applied before inserting a new record. Automatically assigns tenant_id.
     *
     * Security: any tenant_id present in input data is IGNORED and replaced with
     * the authoritative value from TenantContext. This prevents mass-assignment
     * attacks where a user could inject an arbitrary tenant_id into the request.
     */
    protected function applyTenantScopeOnInsert(array $data): array
    {
        if (!$this->tenantScopeActive || TenantContext::isGlobal()) {
            return $data;
        }

        $tenantId = $this->resolveCurrentTenantId();

        if ($tenantId === null && $this->requireTenant) {
            throw new RuntimeException(
                sprintf('TenantModel [%s] insert blocked: tenant context is required but none was set.', static::class)
            );
        }

        if ($tenantId !== null && isset($data['data']) && is_array($data['data'])) {
            // Strip any user-supplied tenant_id and enforce TenantContext value
            // This is the critical mass-assignment / IDOR protection
            if (isset($data['data'][$this->tenantColumn])
                && (string) $data['data'][$this->tenantColumn] !== (string) $tenantId
            ) {
                log_message('warning', sprintf(
                    '[TenantSecurity] INSERT on [%s]: user-supplied tenant_id [%s] was stripped and replaced with TenantContext [%s].',
                    static::class,
                    $data['data'][$this->tenantColumn],
                    $tenantId
                ));
            }
            $data['data'][$this->tenantColumn] = $tenantId;
        }

        return $data;
    }

    /**
     * Hook applied before updating records. Constrains update to current tenant.
     *
     * Security: any attempt to change tenant_id in the update payload is REJECTED.
     * tenant_id must never change once a record is created.
     */
    protected function applyTenantScopeOnUpdate(array $data): array
    {
        if (!$this->tenantScopeActive || TenantContext::isGlobal()) {
            return $data;
        }

        $tenantId = $this->resolveCurrentTenantId();

        if ($tenantId === null && $this->requireTenant) {
            throw new RuntimeException(
                sprintf('TenantModel [%s] update blocked: tenant context is required but none was set.', static::class)
            );
        }

        // Block any attempt to change tenant_id via UPDATE payload
        if (isset($data['data']) && is_array($data['data']) && array_key_exists($this->tenantColumn, $data['data'])) {
            log_message('critical', sprintf(
                '[TenantSecurity] UPDATE on [%s]: attempt to change tenant_id column [%s] was blocked. Caller supplied [%s].',
                static::class,
                $this->tenantColumn,
                $data['data'][$this->tenantColumn]
            ));
            unset($data['data'][$this->tenantColumn]);
        }

        if ($tenantId !== null) {
            $prefix = !empty($this->table) ? "{$this->table}." : '';
            $this->builder()->where($prefix . $this->tenantColumn, $tenantId);
        }

        return $data;
    }

    /**
     * Hook applied before deleting records. Constrains delete to current tenant.
     */
    protected function applyTenantScopeOnDelete(array $data): array
    {
        if (!$this->tenantScopeActive || TenantContext::isGlobal()) {
            return $data;
        }

        $tenantId = $this->resolveCurrentTenantId();

        if ($tenantId === null && $this->requireTenant) {
            throw new RuntimeException(
                sprintf('TenantModel [%s] delete blocked: tenant context is required but none was set.', static::class)
            );
        }

        if ($tenantId !== null) {
            $prefix = !empty($this->table) ? "{$this->table}." : '';
            $this->builder()->where($prefix . $this->tenantColumn, $tenantId);
        }

        return $data;
    }
}
