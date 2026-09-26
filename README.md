# CodeIgniter 4 Module Tenancy

[![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)](https://github.com/rahpt/ci4-module-tenancy)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-brightgreen.svg)](https://php.net)

Isolamento de dados corporativo e suporte a Multi-Tenancy para aplicações e módulos CodeIgniter 4. Oferece `TenantModel` com scoping automático em SELECT/INSERT/UPDATE/DELETE, fail-safe contra vazamento de dados, execução auditada em escopo global (`TenantContext::runGlobal`), rastreabilidade de proveniência e verificação de membros Zero-Trust (`TenantMembershipInterface`).

---

## 📋 Índice

- [Características](#-características)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [TenantModel: Isolamento Automático de Banco](#-tenantmodel-isolamento-automático-de-banco)
- [TenantContext e Execução com Escopo](#-tenantcontext-e-execução-com-escopo)
- [Contrato de Associação (TenantMembershipInterface)](#-contrato-de-associação-tenantmembershipinterface)
- [Detecção e Filtro Nativo (TenantFilter)](#-detecção-e-filtro-nativo-tenantfilter)
- [Helpers](#-helpers)
- [Histórico de Versões](#-histórico-de-versões)
- [Licença](#-licença)

---

## ✨ Características

### Isolamento de Dados & Segurança
- ✅ **TenantModel Nativo** - Intercepta automaticamente queries de SELECT, INSERT, UPDATE e DELETE injetando a cláusula `tenant_id`.
- ✅ **Fail-Safe contra Data Leakage** - Se nenhuma empresa estiver no contexto ativo, o `TenantModel` bloqueia a query com `RuntimeException` em vez de expor dados de todos os clientes.
- ✅ **Bypass Seguro e Auditado** - `withoutTenantScope()` e `TenantContext::runGlobal()` exigem justificativa e permissão Shield obrigatórias registradas no log de auditoria.
- ✅ **Zero-Trust Membership** - Validação automática se o usuário logado realmente pertence ao tenant identificado via subdomínio ou cabeçalho.
- ✅ **Validação Estrita de Identificador** - Rejeição automática de slugs maliciosos ou tentativas de Path Traversal (`strictTenantValidation`).

### Rastreabilidade & Execução
- ✅ **Scoped Execution (`TenantContext::run`)** - Troca de contexto transacional temporária com retorno garantido ao contexto anterior via bloco `finally`.
- ✅ **Execução de Sistema (`runSystem`)** - Auditoria imutável para rotinas CLI, crons e background jobs.
- ✅ **Rastreamento de Proveniência (`source`)** - Identifica a origem da resolução do tenant (`subdomain`, `header`, `session`, `cli`, `system`).

---

## 🚀 Instalação

```bash
composer require rahpt/ci4-module-tenancy
```

---

## ⚙️ Configuração

Publique e personalize o arquivo `app/Config/Tenancy.php`:

```php
<?php

namespace Config;

use Rahpt\Ci4ModuleTenancy\Config\Tenancy as BaseTenancy;

class Tenancy extends BaseTenancy
{
    /**
     * Modo de detecção: 'subdomain', 'header' ou 'session'
     */
    public string $detectionMode = 'subdomain';

    /**
     * Índice do subdomínio (ex: 0 para empresa.meuapp.com)
     */
    public string $detectionKey = '0';

    /**
     * Nome do cabeçalho HTTP se detectionMode for 'header' (útil para APIs)
     */
    public string $headerName = 'X-Tenant-ID';

    /**
     * Se true, bloqueia requisições sem tenant com HTTP 403 Forbidden
     */
    public bool $requireTenant = true;

    /**
     * Zero-Trust: Valida se o usuário autenticado é membro ativo da empresa
     */
    public bool $validateMembership = true;

    /**
     * Classe que implementa TenantMembershipInterface
     */
    public ?string $membershipHandler = \App\Services\TenancyMembershipService::class;

    /**
     * Allowlist de identificadores permitidos (vazio = todos válidos)
     */
    public array $allowedTenants = [];

    /**
     * Validação estrita de formato (apenas alfanuméricos, hífens e underscores)
     */
    public bool $strictTenantValidation = true;
}
```

---

## 🗄️ TenantModel: Isolamento Automático de Banco

Ao criar modelos que armazenam registros de clientes, estenda `TenantModel` em vez de `CodeIgniter\Model`:

```php
<?php

namespace App\Modules\Faturas\Models;

use Rahpt\Ci4ModuleTenancy\Models\TenantModel;

class FaturaModel extends TenantModel
{
    protected $table = 'faturas';
    protected $primaryKey = 'id';
    protected $allowedFields = ['tenant_id', 'cliente_nome', 'valor', 'status'];

    // Nome da coluna que isola os dados (padrão: 'tenant_id')
    protected string $tenantColumn = 'tenant_id';

    // Bloqueia execução caso nenhum tenant esteja selecionado
    protected bool $requireTenant = true;
}
```

### Operações Transparentes
```php
$model = new FaturaModel();

// 1. SELECT automático: WHERE tenant_id = 'empresa_atual'
$faturas = $model->findAll();

// 2. INSERT automático: injeta 'tenant_id' => 'empresa_atual' no payload
$model->insert([
    'cliente_nome' => 'Acme Corp',
    'valor' => 1500.00
]);

// 3. UPDATE/DELETE protegidos: impede alteração de registros de outros tenants
$model->update($id, ['status' => 'pago']);
```

### Bypass Auditado de Escopo
Para rotinas de suporte técnico ou relatórios consolidados da plataforma:

```php
// Requer motivo obrigatório e opcionalmente valida permissão Shield ('admin.super')
$todasFaturas = $model->withoutTenantScope('Relatório anual consolidado da matriz', 'admin.super')
                      ->findAll();
```

---

## 🧭 TenantContext e Execução com Escopo

A classe `TenantContext` é o núcleo de gestão de contexto:

```php
use Rahpt\Ci4ModuleTenancy\TenantContext;

// 1. Identificador do tenant ativo
$id = TenantContext::id(); // 'empresa_alpha' ou null

// 2. Origem da resolução
$source = TenantContext::source(); // 'subdomain', 'header', 'session', 'cli'

// 3. Obter tenant ou disparar exceção se ausente
$tenant = TenantContext::require();

// 4. Executar bloco de código no contexto de outro tenant com rollback garantido
TenantContext::run('empresa_beta', function() {
    $model = new FaturaModel();
    // Executa no contexto isolado da empresa_beta
    return $model->findAll();
}, 'background_job');

// 5. Execução global auditada (para administradores da plataforma)
TenantContext::runGlobal(function() {
    // Escopo global ativo (isGlobal() === true)
    // TenantModel não filtrará por tenant neste escopo
}, 'Manutenção programada de faturamento', 'admin.maintenance');

// 6. Execução por rotinas CLI / Cron do sistema
TenantContext::runSystem(function() {
    // Rotinas do sistema
}, 'Sincronização diária de estoque', 'cron_daemon');
```

---

## 🤝 Contrato de Associação (`TenantMembershipInterface`)

Para arquiteturas corporativas onde um mesmo usuário (Shield) pode pertencer a múltiplas organizações com perfis diferentes:

```php
<?php

namespace App\Services;

use Rahpt\Ci4ModuleTenancy\Contracts\TenantMembershipInterface;

class TenancyMembershipService implements TenantMembershipInterface
{
    public function userBelongsToTenant(object|string|int $user, string $tenantId): bool
    {
        $userId = is_object($user) ? $user->id : $user;
        // Consulta tabela pivô tenant_users
        return (bool) db_connect()->table('tenant_users')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->countAllResults();
    }

    public function getUserTenantRole(object|string|int $user, string $tenantId): ?string
    {
        $record = db_connect()->table('tenant_users')
            ->where('user_id', is_object($user) ? $user->id : $user)
            ->where('tenant_id', $tenantId)
            ->get()->getRow();

        return $record ? $record->role : null;
    }
}
```

---

## 🛡️ Detecção e Filtro Nativo (`TenantFilter`)

O pacote registra automaticamente o alias de filtro `tenant`. Para ativá-lo em toda a aplicação ou em rotas específicas:

**`app/Config/Filters.php`**:
```php
public array $globals = [
    'before' => [
        'tenant', // Ativa detecção e Zero-Trust em todas as rotas
    ],
];
```

---

## 🛠️ Helpers

Funções de acesso rápido carregadas automaticamente:

```php
// Obter ID do tenant atual
$tenantId = tenant();

// Verificar se existe um tenant ativo
if (has_tenant()) {
    echo "Operando sob a organização: " . tenant();
}
```

---

## 🕒 Histórico de Versões

### [1.2.0] - 2026-09-26
- **Novo**: `TenantModel` com scoping automático em SELECT, INSERT, UPDATE e DELETE.
- **Novo**: Mecanismo Fail-Safe com `RuntimeException` para prevenir vazamentos de dados sem tenant ativo.
- **Novo**: Métodos `TenantContext::runGlobal()` e `TenantContext::runSystem()` com auditoria obrigatória.
- **Novo**: Execução transacional com escopo garantido via `TenantContext::run()`.
- **Novo**: Contrato `TenantMembershipInterface` para verificação de associação Zero-Trust com Shield.
- **Novo**: Validação estrita de formato de identificador (`strictTenantValidation`).
- **Novo**: Rastreamento de proveniência de detecção (`TenantContext::source()`).

### [1.1.0] - 2026-02-16
- **Arquitetura**: Refatoração do `TenantContext` para o namespace raiz do pacote.
- **Testes**: Suíte de testes unitários para contexto.

### [1.0.1] - 2026-02-15
- Versão inicial com detecção tripla (subdomain, header, session).

---

## 📄 Licença

MIT License. Desenvolvido por **Rahpt**.
