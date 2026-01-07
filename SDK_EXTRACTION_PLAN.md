# MCP Security SDK Extraction Plan

> Extract reusable PHP packages from mcp_tools_remote for the broader MCP ecosystem.

## Overview

We have production-ready HTTP security components in `mcp_tools_remote` that don't exist elsewhere in the PHP MCP ecosystem. This plan extracts them as standalone Composer packages.

## Package Architecture

### Package 1: `code-wheel/mcp-http-security`

The main package - secure HTTP transport wrapper for MCP servers.

```
code-wheel/mcp-http-security/
├── src/
│   ├── ApiKey/
│   │   ├── ApiKeyManagerInterface.php    # Core interface
│   │   ├── ApiKeyManager.php             # Implementation
│   │   ├── ApiKey.php                    # Value object
│   │   └── Storage/
│   │       ├── StorageInterface.php      # Storage abstraction
│   │       ├── ArrayStorage.php          # In-memory (testing)
│   │       ├── FileStorage.php           # JSON file storage
│   │       └── PdoStorage.php            # Database storage
│   ├── Validation/
│   │   ├── RequestValidatorInterface.php
│   │   ├── RequestValidator.php          # IP + Origin checks
│   │   ├── IpValidator.php               # IP allowlist
│   │   └── OriginValidator.php           # Hostname allowlist
│   ├── Middleware/
│   │   ├── SecurityMiddleware.php        # PSR-15 middleware
│   │   └── RateLimitMiddleware.php       # Token bucket rate limiting
│   ├── Config/
│   │   └── SecurityConfig.php            # Configuration DTO
│   └── Exception/
│       ├── AuthenticationException.php
│       ├── AuthorizationException.php
│       └── RateLimitException.php
├── composer.json
├── README.md
└── tests/
```

**Dependencies:**
- `psr/http-server-middleware` (PSR-15)
- `psr/http-message` (PSR-7)
- `psr/clock` (PSR-20)
- `psr/log` (PSR-3)

### Package 2: `code-wheel/mcp-drupal-bridge` (Optional)

Bridge between Drupal's Tool API and MCP servers. Lower priority - more niche.

```
code-wheel/mcp-drupal-bridge/
├── src/
│   ├── ToolApiSchemaConverter.php        # Tool API → MCP schema
│   ├── ToolApiCallHandler.php            # MCP calls → Tool API
│   └── DrupalMcpServerFactory.php        # Server factory
```

---

## Extraction Phases

### Phase 1: Core API Key Management

Extract `ApiKeyManager` with storage abstraction.

**Current Drupal dependencies to abstract:**

| Drupal Service | Abstraction | PSR Standard |
|----------------|-------------|--------------|
| `StateInterface` | `StorageInterface` | Custom |
| `PrivateKey` | Config option (pepper) | N/A |
| `TimeInterface` | `ClockInterface` | PSR-20 |

**Tasks:**
1. [ ] Create `StorageInterface` with get/set/delete methods
2. [ ] Create `ArrayStorage` for testing
3. [ ] Create `FileStorage` for simple deployments
4. [ ] Create `PdoStorage` for database storage
5. [ ] Create `ApiKeyManagerInterface`
6. [ ] Port `ApiKeyManager` logic with PSR-20 clock
7. [ ] Write unit tests

### Phase 2: Request Validation

Extract IP and Origin allowlist checking.

**Already framework-agnostic:**
- `IpUtils::checkIp()` - from Symfony HttpFoundation
- `hostnameMatchesAllowlist()` - pure PHP

**Tasks:**
1. [ ] Create `RequestValidatorInterface`
2. [ ] Port `IpValidator` (wrap Symfony IpUtils or implement directly)
3. [ ] Port `OriginValidator` with wildcard support
4. [ ] Create composite `RequestValidator`
5. [ ] Write unit tests

### Phase 3: PSR-15 Middleware

Create middleware that wraps everything together.

```php
$middleware = new SecurityMiddleware(
    apiKeyManager: $apiKeyManager,
    requestValidator: $requestValidator,
    config: new SecurityConfig(
        requireAuth: true,
        allowedScopes: ['read', 'write'],
    ),
);
```

**Tasks:**
1. [ ] Create `SecurityMiddleware` implementing PSR-15
2. [ ] Create `RateLimitMiddleware` with token bucket algorithm
3. [ ] Create `SecurityConfig` DTO
4. [ ] Write integration tests

### Phase 4: Drupal Integration

Update `mcp_tools_remote` to use extracted packages.

**Tasks:**
1. [ ] Add `code-wheel/mcp-http-security` to composer.json
2. [ ] Create `DrupalStateStorage` implementing `StorageInterface`
3. [ ] Refactor `ApiKeyManager` to be thin wrapper
4. [ ] Refactor controller to use middleware pattern
5. [ ] Ensure backward compatibility
6. [ ] Write kernel tests

### Phase 5: Release

1. [ ] Create GitHub repos
2. [ ] Register on Packagist
3. [ ] Write comprehensive README with examples
4. [ ] Tag v1.0.0

---

## Interface Designs

### StorageInterface

```php
<?php

namespace CodeWheel\McpSecurity\ApiKey\Storage;

interface StorageInterface
{
    /**
     * Get all stored keys.
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array;

    /**
     * Store all keys (replaces existing).
     * @param array<string, array<string, mixed>> $keys
     */
    public function setAll(array $keys): void;

    /**
     * Get a single key by ID.
     * @return array<string, mixed>|null
     */
    public function get(string $keyId): ?array;

    /**
     * Store a single key.
     * @param array<string, mixed> $data
     */
    public function set(string $keyId, array $data): void;

    /**
     * Delete a key.
     */
    public function delete(string $keyId): bool;
}
```

### ApiKeyManagerInterface

```php
<?php

namespace CodeWheel\McpSecurity\ApiKey;

interface ApiKeyManagerInterface
{
    /**
     * Create a new API key.
     *
     * @param string $label Human-readable label
     * @param string[] $scopes Allowed scopes
     * @param int|null $ttlSeconds Time-to-live (null = no expiry)
     * @return array{key_id: string, api_key: string}
     */
    public function createKey(string $label, array $scopes, ?int $ttlSeconds = null): array;

    /**
     * List all keys (without secrets).
     * @return array<string, array{label: string, scopes: string[], created: int, last_used: ?int, expires: ?int}>
     */
    public function listKeys(): array;

    /**
     * Revoke a key by ID.
     */
    public function revokeKey(string $keyId): bool;

    /**
     * Validate an API key.
     * @return array{key_id: string, label: string, scopes: string[]}|null
     */
    public function validate(string $apiKey): ?array;
}
```

### RequestValidatorInterface

```php
<?php

namespace CodeWheel\McpSecurity\Validation;

use Psr\Http\Message\ServerRequestInterface;

interface RequestValidatorInterface
{
    /**
     * Validate request against security rules.
     *
     * @throws \CodeWheel\McpSecurity\Exception\ValidationException
     */
    public function validate(ServerRequestInterface $request): void;

    /**
     * Check if request passes validation (no exception).
     */
    public function isValid(ServerRequestInterface $request): bool;
}
```

### SecurityMiddleware Usage

```php
<?php

use CodeWheel\McpSecurity\ApiKey\ApiKeyManager;
use CodeWheel\McpSecurity\ApiKey\Storage\FileStorage;
use CodeWheel\McpSecurity\Validation\RequestValidator;
use CodeWheel\McpSecurity\Middleware\SecurityMiddleware;
use CodeWheel\McpSecurity\Config\SecurityConfig;

// Setup
$storage = new FileStorage('/var/data/api-keys.json');
$apiKeyManager = new ApiKeyManager($storage, pepper: getenv('API_KEY_PEPPER'));

$validator = new RequestValidator(
    allowedIps: ['127.0.0.1', '10.0.0.0/8'],
    allowedOrigins: ['localhost', '*.example.com'],
);

$middleware = new SecurityMiddleware(
    apiKeyManager: $apiKeyManager,
    requestValidator: $validator,
    config: new SecurityConfig(
        requireAuth: true,
        authHeader: 'Authorization',
        scopeAttribute: 'mcp.scopes',
    ),
);

// Use with any PSR-15 compatible framework
$app->pipe($middleware);
```

---

## Comparison with Existing Solutions

| Feature | mcp-http-security | Laravel MCP Server | php-mcp/server |
|---------|-------------------|-------------------|----------------|
| API Key Auth | Yes | No | No |
| Key Hashing | SHA-256 + pepper | N/A | N/A |
| Key TTL/Expiry | Yes | N/A | N/A |
| IP Allowlist | Yes | No | No |
| Origin Allowlist | Yes | No | No |
| Wildcard Origins | Yes | N/A | N/A |
| Rate Limiting | Yes | No | No |
| PSR-15 Middleware | Yes | No | No |
| Framework Agnostic | Yes | Laravel only | Yes |

---

## Timeline

| Phase | Effort | Dependencies |
|-------|--------|--------------|
| Phase 1: API Key Management | 4-6 hours | None |
| Phase 2: Request Validation | 2-3 hours | Phase 1 |
| Phase 3: PSR-15 Middleware | 3-4 hours | Phase 1, 2 |
| Phase 4: Drupal Integration | 2-3 hours | Phase 1-3 |
| Phase 5: Release | 1-2 hours | Phase 1-4 |

**Total: ~15-20 hours**

---

## Success Criteria

1. `code-wheel/mcp-http-security` published on Packagist
2. 100% test coverage on core components
3. `mcp_tools_remote` refactored to use extracted package
4. README with clear examples
5. No breaking changes to existing mcp_tools users

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Low adoption | Focus on quality, let mcp_tools be the showcase |
| Maintenance burden | Keep scope minimal, stable interfaces |
| Breaking mcp_tools | Thorough kernel tests before/after |
| Namespace conflicts | Use unique `CodeWheel\McpSecurity` namespace |

---

## Decision: Proceed?

**Pros:**
- First-mover in PHP MCP security space
- Forces cleaner architecture in mcp_tools
- Marketing/visibility for CodeWheel
- Helps PHP MCP ecosystem mature

**Cons:**
- 15-20 hours of work
- Ongoing maintenance
- Unknown demand

**Recommendation:** Proceed with Phase 1-3 only. Keep Drupal integration (Phase 4) optional until the standalone package is validated.
