# CodeIgniter 4 Module Tenancy

[![Version](https://img.shields.io/badge/version-1.3.0-blue.svg)](https://github.com/rahpt/ci4-module-tenancy)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-brightgreen.svg)](https://php.net)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-%3E%3D4.5-orange.svg)](https://codeigniter.com)

Isolamento de dados corporativo, estratégia desacoplada de resolução de tenants (`TenantResolverInterface`), proteção contra manipulação de `tenant_id` (anti-mass-assignment e IDOR), validação Zero-Trust de associação de membros (`TenantMembershipInterface`), execução global auditada e scoping transparente de banco via `TenantModel`.

---

## 🏛️ Fluxo de Segurança e Resolução

```text
HTTP Request
     │
     ▼
[TenantFilter] ───► [TenantResolverInterface] (Subdomain, Header, Session)
     │                     │ (Verifica trustedBaseDomains)
     │                     ▼
     │              [TenantContext] (Ativa ID do Tenant)
     │                     │
     │                     ▼
     ├────────────► [TenantMembershipInterface] (Usuário pertence a este Tenant?)
     │                     │ (Zero-Trust Check)
     ▼                     ▼
[Shield Auth & Policies] ──► [Controller & Service]
                                   │
                                   ▼
                            [TenantModel]
                                   ├── SELECT: WHERE tenant_id = :current:
                                   ├── INSERT: tenant_id forçado do TenantContext
                                   ├── UPDATE: tentativa de alterar tenant_id bloqueada
                                   └── DELETE: restrito ao tenant ativo
```

---

## ⚠️ Regras Centrais de Segurança

> **1. Tenant não é autorização.** O fato de uma requisição possuir um `tenant_id` válido não concede permissão de acesso a recursos. Autenticação (Shield) e autorização granular (Policies/Permissões) são obrigatórias em todas as etapas.
>
> **2. Header nunca é autorização.** Cabeçalhos como `X-Tenant-ID` apenas **solicitam** contexto operacional. O acesso exige usuário autenticado, vínculo ativo de membro no tenant (`TenantMembershipInterface`) e permissões adequadas.
>
> **3. `tenant_id` é imutável.** O identificador do tenant jamais pode ser aceito a partir de inputs fornecidos pelo usuário em formulários ou payloads JSON.

---

## 📋 Índice

- [Características](#-características)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [Estratégias de Resolução (TenantResolverInterface)](#-estratégias-de-resolução-tenantresolverinterface)
- [TenantModel: Isolamento e Proteção de Dados](#-tenantmodel-isolamento-e-proteção-de-dados)
- [TenantContext e Execução com Escopo](#-tenantcontext-e-execução-com-escopo)
- [Zero-Trust Membership (TenantMembershipInterface)](#-zero-trust-membership-tenantmembershipinterface)
- [Detecção e Filtro Nativo (TenantFilter)](#-detecção-e-filtro-nativo-tenantfilter)
- [Helpers](#-helpers)
- [Histórico de Versões](#-histórico-de-versões)
- [Licença](#-licença)

---

## ✨ Características

### Isolamento de Dados & Anti-Tampering
- ✅ **TenantModel com Scoping Automático** - Injeta transparentemente cláusulas `WHERE tenant_id = :id` em operações de SELECT, UPDATE e DELETE.
- ✅ **Proteção Anti-Mass Assignment no INSERT** - Qualquer `tenant_id` enviado pelo usuário no payload é ignorado e substituído pelo ID seguro mantido no `TenantContext`.
- ✅ **Bloqueio de Mutação no UPDATE** - Tentativas de alterar a coluna `tenant_id` em registros existentes são sumariamente bloqueadas e registradas como alerta crítico de segurança.
- ✅ **Fail-Safe contra Vazamento de Dados** - Se nenhum tenant estiver no contexto ativo e a consulta exigir escopo, o `TenantModel` interrompe a operação com `RuntimeException`.

### Resolução Desacoplada & Domínios Confiáveis
- ✅ **Contrato `TenantResolverInterface`** - Estratégias modulares de extração de identificadores desacopladas da validação de regras de negócio.
- ✅ **Resolvers Nativos** - `SubdomainTenantResolver`, `HeaderTenantResolver` e `SessionTenantResolver`.
- ✅ **Domínios Confiáveis (`trustedBaseDomains`)** - Validação de sufixo no Host header para mitigar DNS Rebinding e Host Header Injection em resoluções por subdomínio.
- ✅ **Validação Estrita de Identificador** - Rejeição de caracteres especiais, tentativas de SQLi ou Path Traversal em slugs de tenants.

### Auditoria & Execução Scoped
- ✅ **Scoped Execution (`TenantContext::run`)** - Troca temporária de contexto com restauração garantida do tenant original através de bloco `finally`.
- ✅ **Execução Global Segura (`TenantContext::runGlobal`)** - Bypass auditado para operações da matriz/plataforma exigindo justificativa obrigatória e verificação explícita de capability.
- ✅ **Rastreamento de Origem (`TenantContext::source`)** - Identifica a procedência da resolução (`subdomain`, `header`, `session`, `cli`, `system`).

---

## 🚀 Instalação

```bash
composer require rahpt/ci4-module-tenancy
```

---

## ⚙️ Configuração

Copie ou personalize o arquivo `app/Config/Tenancy.php`:

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
     * Índice do segmento de subdomínio (ex: 0 para empresa.meuapp.com)
     */
    public string $detectionKey = '0';

    /**
     * Cabeçalho HTTP para APIs e integrações (apenas contexto, nunca autorização!)
     */
    public string $headerName = 'X-Tenant-ID';

    /**
     * Domínios base autorizados para resolução de subdomínio (Anti Host Injection)
     */
    public array $trustedBaseDomains = [
        'meuapp.com',
        'staging.meuapp.com',
        'localhost',
    ];

    /**
     * Resolver customizado opcional implementando TenantResolverInterface
     */
    public ?string $resolverClass = null;

    /**
     * Se true, bloqueia requisições sem tenant com HTTP 403 Forbidden
     */
    public bool $requireTenant = true;

    /**
     * Zero-Trust: Valida se o usuário autenticado pertence ao tenant detectado
     */
    public bool $validateMembership = true;

    /**
     * Classe responsável por verificar a associação do usuário com a empresa
     */
    public ?string $membershipHandler = \App\Services\TenancyMembershipService::class;

    /**
     * Validação estrita de formato de slug (apenas alfanuméricos, hífens e underscores)
     */
    public bool $strictTenantValidation = true;
}
```

---

## 🔌 Estratégias de Resolução (`TenantResolverInterface`)

O pacote separa a **resolução** do identificador do tenant da sua **autorização**.

### O Contrato

```php
namespace Rahpt\Ci4ModuleTenancy\Contracts;

use CodeIgniter\HTTP\RequestInterface;

interface TenantResolverInterface
{
    public function resolve(RequestInterface $request): ?string;
}
```

### Resolvers Embutidos

1. **`SubdomainTenantResolver`**: Extrai o slug a partir do subdomínio do Host, validando obrigatoriamente se pertence a `trustedBaseDomains`.
2. **`HeaderTenantResolver`**: Extrai o valor do cabeçalho HTTP configurado (padrão `X-Tenant-ID`).
3. **`SessionTenantResolver`**: Extrai o tenant armazenado na sessão segura do usuário logado.

---

## 🗄️ `TenantModel`: Isolamento e Proteção de Dados

Estenda `TenantModel` nos Models de tabelas compartilhadas que contenham dados de clientes:

```php
<?php

namespace App\Modules\Faturas\Models;

use Rahpt\Ci4ModuleTenancy\Models\TenantModel;

class FaturaModel extends TenantModel
{
    protected $table = 'faturas';
    protected $primaryKey = 'id';
    protected $allowedFields = ['cliente_nome', 'valor', 'status']; // tenant_id não precisa ser permitido em inputs externos!

    protected string $tenantColumn = 'tenant_id';
    protected bool $requireTenant = true;
}
```

### Comportamento em Execução

```php
$model = new FaturaModel();

// 1. SELECT automático: injeta WHERE faturas.tenant_id = 'org_atual'
$faturas = $model->findAll();

// 2. INSERT protegido: mesmo que o payload contenha 'tenant_id' => 'hacker',
// o modelo substitui o valor por TenantContext::id() ativo
$model->insert([
    'cliente_nome' => 'Cliente ABC',
    'valor'        => 2500.00
]);

// 3. UPDATE protegido: tentativas de alterar 'tenant_id' são bloqueadas
$model->update($id, [
    'tenant_id' => 'outra_empresa', // Ignorado e logado como incidente
    'status'    => 'liquidado'
]);

// 4. Bypass auditado de escopo para relatórios globais da plataforma
$todas = $model->withoutTenantScope('Consolidação financeira matriz', 'platform.reports.global')
               ->findAll();
```

---

## 🧭 TenantContext e Execução com Escopo

```php
use Rahpt\Ci4ModuleTenancy\TenantContext;

// 1. Obter identificador ativo ou disparar exceção se ausente
$tenantId = TenantContext::require();

// 2. Executar rotina em escopo temporário de outro tenant com restauração garantida
TenantContext::run('empresa_beta', function() {
    $model = new FaturaModel();
    // Consultas aqui operam estritamente sob 'empresa_beta'
    return $model->findAll();
}, 'background_worker');

// 3. Execução global privilegiada (requer capability explícita)
TenantContext::runGlobal(function() {
    // Escopo global ativo (TenantContext::isGlobal() === true)
    // Modelos não aplicarão filtro de tenant neste bloco
}, 'Manutenção de índices da plataforma', 'platform.tenants.global');

// 4. Execução de rotinas do sistema (CLI / Cron)
TenantContext::runSystem(function() {
    // Execução sob identidade do sistema
}, 'Sincronização agendada de faturas', 'cron_sync');
```

---

## 🤝 Zero-Trust Membership (`TenantMembershipInterface`)

Para validar se o usuário do CodeIgniter Shield tem permissão para operar no tenant selecionado:

```php
<?php

namespace App\Services;

use Rahpt\Ci4ModuleTenancy\Contracts\TenantMembershipInterface;

class TenancyMembershipService implements TenantMembershipInterface
{
    public function userBelongsToTenant(object|string|int $user, string $tenantId): bool
    {
        $userId = is_object($user) ? $user->id : $user;

        return (bool) db_connect()->table('tenant_members')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->countAllResults();
    }

    public function getUserTenantRole(object|string|int $user, string $tenantId): ?string
    {
        $userId = is_object($user) ? $user->id : $user;

        $record = db_connect()->table('tenant_members')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->get()->getRow();

        return $record?->role;
    }
}
```

---

## 🛡️ Detecção e Filtro Nativo (`TenantFilter`)

Em `app/Config/Filters.php`:

```php
public array $globals = [
    'before' => [
        'tenant', // Executa resolução e verificação Zero-Trust em todas as requisições
    ],
];
```

---

## 🛠️ Helpers

```php
// Retorna o identificador do tenant atual ou null
$tenantId = tenant();

// Verifica se há um tenant ativo no contexto
if (has_tenant()) {
    echo "Tenant ativo: " . tenant();
}
```

---

## 🕒 Histórico de Versões

### [1.3.0] - 2026-09-26
- **Novo**: Contrato `TenantResolverInterface` desacoplando resolução da autorização.
- **Novo**: Resolvers dedicados: `SubdomainTenantResolver`, `HeaderTenantResolver` e `SessionTenantResolver`.
- **Segurança**: Validação de domínios base autorizados (`trustedBaseDomains`) para mitigação de Host Header Injection.
- **Segurança**: Blindagem no `TenantModel` contra mutação de `tenant_id` em UPDATE e stripping de `tenant_id` externo em INSERT.
- **Novo**: Exigência de capability explícita para `TenantContext::runGlobal()` com registro de auditoria.
- **Testes**: Suíte abrangente de testes unitários para `TenantContextTest`.

### [1.2.0] - 2026-09-26
- **Novo**: `TenantModel` nativo com scoping automático e fail-safe de isolamento.
- **Novo**: Suporte a execução em escopo temporário via `TenantContext::run()`.
- **Novo**: Contrato `TenantMembershipInterface` para verificação de associação Zero-Trust.

### [1.0.1] - 2026-02-15
- Versão inicial com detecção de tenant.

---

## 📄 Licença

Distribuído sob a licença MIT. Veja `LICENSE` para mais detalhes.

Desenvolvido por **Rahpt**  
Mantido pela equipe Rahpt / CodeIgniter 4 Modular Platform.
