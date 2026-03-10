# MCP Tools - Remote Media

Fetch remote files by URL and create managed Drupal media entities.

Currently supports **images** (JPEG, PNG, GIF, WebP, SVG). The architecture is
designed to be extended toward documents, audio, and video in future sub-tools.

## Tools (1)

| Tool | Description |
|------|-------------|
| `mcp_fetch_remote_image` | Download an image from a remote URL, save it as a managed file, and optionally create a media entity |

## Requirements

- `mcp_tools` (base module)
- `mcp_tools_media` (media management submodule)
- `drupal:media`
- `drupal:file`

## Installation

```bash
drush en mcp_tools_remote_media
drush cr
```

> `drush cr` is required after enabling so that the Tool API plugin manager
> discovers the new plugin.

## Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `url` | string | yes | — | http/https URL of the remote image |
| `name` | string | yes | — | Human-readable name for the media entity |
| `bundle` | string | no | `image` | Media type machine name. Use `mcp_list_media_types` to list available types |
| `directory` | string | no | `public://mcp-uploads` | Drupal stream wrapper destination path |
| `create_media` | boolean | no | `true` | If false, saves only the managed file (no media entity) |

## Supported Image Formats

`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`

Maximum file size: 10 MiB.

## Example Usage

### Fetch an image and create a media entity

```
mcp_fetch_remote_image(
  url: "https://picsum.photos/800/600?random=1",
  name: "My image",
  bundle: "image"
)
```

Returns `fid` (managed file ID) and `mid` (media entity ID).
Use `mid` to reference the media from a content field:

```
mcp_create_content(
  type: "article",
  title: "My article",
  fields: {
    "field_article_image": {"target_id": <mid>}
  }
)
```

### Save file only (no media entity)

```
mcp_fetch_remote_image(
  url: "https://example.com/photo.jpg",
  name: "Photo",
  create_media: false
)
```

## Architecture

```
src/
├── Plugin/tool/Tool/
│   └── FetchRemoteImage.php          ← Tool API plugin (id: mcp_fetch_remote_image)
└── Service/
    ├── AbstractRemoteFileService.php  ← Abstract base: HTTP fetch, validation,
    │                                     file & media entity creation
    └── RemoteImageService.php         ← Concrete: image MIME types + orchestration
```

**`AbstractRemoteFileService`** holds all reusable logic:
- URL and directory validation
- Guzzle HTTP fetch with timeout/redirect limits
- MIME type validation (against the subclass-provided list)
- File body size and content checks (including `finfo` content sniffing)
- Filename sanitisation
- `File` entity creation
- `Media` entity creation

**`RemoteImageService`** extends the base and:
- Declares the allowed MIME types (`image/*`) and extension map
- Owns the `fetchRemoteImage()` orchestration method

To add support for a new file type in the future, create a new service that
extends `AbstractRemoteFileService` and implement the three abstract methods:
`getAllowedMimeTypes()`, `getMimeToExtMap()`, and `getOperationName()`. Then
create a corresponding Tool plugin.

Discovery is fully automatic: the Tool API plugin manager scans all active
modules' `src/Plugin/tool/Tool/` directories. No registration or hook is
needed beyond having the module enabled.

## Safety

- Only `http` and `https` URL schemes are accepted
- Only allowed MIME types are accepted (validated from the `Content-Type`
  response header **and** from the actual file content via `finfo`)
- Maximum 10 MiB per file
- Only `public://` and `private://` stream wrappers are allowed;
  path traversal is rejected
- All operations are logged via `mcp_tools.audit_logger`
- SSRF protection: private/internal IP ranges are blocked by default
- Extension blocklist: dangerous file extensions (e.g. `.php`, `.phar`,
  `.html`) are rejected regardless of MIME type
- SVG sanitization: `<script>`, event handlers (`onload`, etc.),
  `<foreignObject>`, and remote references are stripped via
  [enshrined/svg-sanitize](https://packagist.org/packages/enshrined/svg-sanitize)

## Gotchas

**Module not visible on the MCP Tools status page**
(`/admin/config/services/mcp-tools/status`) — this is expected. That page
maintains a hardcoded list of known sub-modules in `StatusController.php`.
Custom modules do not appear there; it is cosmetic only and has no effect on
whether the tool is discovered or exposed.

**Tool not visible in Claude Desktop after enabling the module** — two
possible causes:

1. Cache not cleared: run `drush cr` after enabling.
2. Pagination: if the server exposes more than `pagination_limit` tools
   (default 50), Claude Desktop only loads the first page. Increase
   `pagination_limit` in `mcp_tools_servers.settings` for the active server
   profile (e.g. set to 200 for the `development` profile) so all tools fit
   on a single page. Then restart Claude Desktop to reload the tool list.
