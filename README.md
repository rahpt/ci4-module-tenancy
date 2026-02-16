# CodeIgniter 4 Module Tenancy

[![Version](https://img.shields.io/badge/version-1.1.0-blue.svg)](https://github.com/rahpt/ci4-module-tenancy)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-brightgreen.svg)](https://php.net)

Suporte a Multi-Tenancy para módulos CodeIgniter 4. Permite isolar dados e configurações por cliente (tenant) de forma automática.

---

## 📋 Características

- ✅ **Detecção Automática** - Detecta o tenant via Subdomínio, Header HTTP ou Sessão
- ✅ **Global Context** - Acesso fácil ao tenant atual em qualquer lugar do app
- ✅ **Filtro Nativo** - Integração direta com filtros do CI4 para segurança
- ✅ **Helper Functions** - Funções `tenant()` e `has_tenant()`
- ✅ **Configurável** - Fácil de estender e adaptar ao seu modelo de banco

---

## 🚀 Instalação

```bash
composer require rahpt/ci4-module-tenancy
```

---

## 📖 Uso Básico

### 1. Configuração

Publique o arquivo de configuração para `app/Config/Tenancy.php`:

```php
<?php

namespace Config;

use Rahpt\Ci4ModuleTenancy\Config\Tenancy as BaseTenancy;

class Tenancy extends BaseTenancy
{
    public string $detectionMode = 'subdomain'; // 'subdomain', 'header' ou 'session'
    public string $detectionKey  = '0';         // Índice do subdomínio
    public bool   $requireTenant = true;       // Bloqueia acesso sem tenant
}
```

### 2. Ativar o Filtro

O pacote registra automaticamente o alias `tenant`. Adicione-o ao seu `app/Config/Filters.php`:

```php
public array $globals = [
    'before' => [
        'tenant', // Ativa detecção em todas as rotas
    ],
];
```

### 3. Usando no Código

```php
// Obter o ID do tenant atual
$tenantId = tenant();

if (has_tenant()) {
    echo "Logado na empresa: " . $tenantId;
}

// Exemplo em um Model
public function getItems()
{
    return $this->where('tenant_id', tenant())->findAll();
}
```

---

## 🧪 Métodos de Detecção

### Subdomínio
Se o app estiver em `cliente1.meuapp.com`, o tenant será `cliente1`.

### Header HTTP
Útil para APIs. Envie o header `X-Tenant-ID: cliente1`.

### Sessão
Útil se o tenant for escolhido após o login. Salve `tenant_id` na sessão.

---

## 🕒 Histórico de Versões

### [1.1.0] - 2026-02-16
- **Arquitetura**: Refatoração do `TenantContext` para o namespace raiz do pacote.
- **Testes**: Adição de suíte de testes unitários para validação de contexto.
- **Padronização**: Alinhamento com o ecossistema Rahpt v1.1.0.

### [1.0.1] - 2026-02-15
- Versão inicial com detecção tripla (subdomain, header, session).

---

## 📄 Licença

MIT License

---

## 👏 Créditos

Desenvolvido por **Rahpt**

---

**Versão**: 1.1.0  
**Última Atualização**: 2026-02-16
