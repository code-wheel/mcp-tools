# Testing mcp_tools_remote_media

Tests are discovered automatically by the root `mcp_tools` PHPUnit configuration.

## Running the Tests

From the root `mcp_tools` module directory:

```bash
# All sub-module unit tests (including mcp_tools_remote_media)
vendor/bin/phpunit --group mcp_tools_remote_media --testdox

# All mcp_tools unit tests
vendor/bin/phpunit --testsuite unit
```

## Current Test Coverage

### AbstractRemoteFileServiceTest (Unit)

Tests shared validation logic in `AbstractRemoteFileService` via an anonymous
stub class:

- URL validation (invalid URLs, non-HTTP schemes, valid http/https)
- Directory validation (path traversal protection, allowed stream wrappers)
- File body validation (empty body, size limit enforcement)
- SSRF protection (private/reserved IP blocking)
- Extension blocklist (dangerous extensions rejected)
- Filename building (URL extension, fallback, special chars)

**22 tests**

### RemoteImageServiceTest (Unit)

Tests `RemoteImageService` image-specific logic and orchestration:

- Allowed MIME type list (5 image types including SVG)
- MIME-to-extension map completeness
- Operation name for audit logging
- Access control (write permission checks)
- Unsupported MIME type rejection
- HTTP request failure handling
- SSRF blocking (private IPs)
- SVG sanitization (script stripping, event handler removal,
  foreignObject removal, invalid XML rejection, non-SVG passthrough)

**14 tests**

---

**Total: 36 tests** — no database or Drupal installation required.
