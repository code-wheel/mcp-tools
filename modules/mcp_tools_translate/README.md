# MCP Tools — Translate

Create linked content translations for Drupal nodes and paragraphs via
the Model Context Protocol (MCP). Designed for AI agents (Claude Code,
Claude Desktop, or any MCP client) that translate articles remotely
through the Drupal HTTP endpoint.

## Quick start — no Drupal needed

You do **not** need Drupal, DDEV, or PHP installed locally. Claude
connects directly to the remote Drupal MCP endpoint over HTTP. Choose
the setup that matches your environment.

### 1. Get an API key

Ask a project admin to create one for you, or run this yourself if you
have Upsun SSH access:

```bash
upsun ssh -e main -- "drush mcp-tools:remote-key-create --label='Your Name' --scopes=read,write"
```

Save the key — it is shown only once.

### Available environments

| Environment | URL | Env var |
|---|---|---|
| Production | `https://tcs-preview.ch/_mcp_tools` | `TCS_MCP_PRODUCTION_KEY` |
| Main | `https://main.tcs-preview.ch/_mcp_tools` | `TCS_MCP_MAIN_KEY` |
| Staging | `https://staging.tcs-preview.ch/_mcp_tools` | `TCS_MCP_STAGING_KEY` |

API keys are per-environment — a key from main will not work on
production.

---

### Option A: Claude Code on Linux / macOS / WSL

**2. Set the env var**

Add the key to your shell profile (`~/.zshrc`, `~/.bashrc`, or
equivalent):

```bash
export TCS_MCP_MAIN_KEY="mcp_tools.xxxxx.xxxxxxxxxxxxxxx"
```

**3. Verify `.mcp.json`**

The repo ships an `.mcp.json` that is already configured. Confirm it
contains the `headers` block:

```json
"tcs-main": {
  "type": "http",
  "url": "https://main.tcs-preview.ch/_mcp_tools",
  "headers": {
    "Authorization": "Bearer ${TCS_MCP_MAIN_KEY}"
  }
}
```

Claude Code expands `${TCS_MCP_MAIN_KEY}` from your environment at
startup.

**4. Restart Claude Code and translate**

```bash
source ~/.zshrc   # pick up the new env var
claude            # launch in the project directory
```

---

### Option B: Claude Code on Windows (no WSL)

**2. Set the env var**

Open PowerShell **as Administrator** and set it permanently:

```powershell
[Environment]::SetEnvironmentVariable("TCS_MCP_MAIN_KEY", "mcp_tools.xxxxx.xxxxxxxxxxxxxxx", "User")
```

Or set it for the current session only:

```powershell
$env:TCS_MCP_MAIN_KEY = "mcp_tools.xxxxx.xxxxxxxxxxxxxxx"
```

**3. Verify `.mcp.json`**

The repo ships an `.mcp.json` that is already configured (same as
Option A). Claude Code on Windows expands `${TCS_MCP_MAIN_KEY}` from
your environment variables the same way.

**4. Restart Claude Code and translate**

Close and reopen your terminal (so it picks up the new env var), then:

```powershell
claude   # launch in the project directory
```

---

### Option C: Claude Desktop on Windows (no terminal needed)

Claude Desktop connects to the MCP endpoint directly — no git clone,
no terminal, no CLI tools required.

**2. Open the Claude Desktop config file**

Press `Win+R`, paste this path, and press Enter:

```
%APPDATA%\Claude\claude_desktop_config.json
```

If the file does not exist, create it.

**3. Add the MCP server**

Add (or merge into) the `mcpServers` block. Replace `YOUR_API_KEY`
with the key from step 1:

```json
{
  "mcpServers": {
    "tcs-main": {
      "type": "http",
      "url": "https://main.tcs-preview.ch/_mcp_tools",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```

For production or staging, use the matching URL from the environments
table above and a key created on that environment.

**4. Restart Claude Desktop and translate**

Fully quit Claude Desktop (system tray → Quit), then reopen it. The
`tcs-main` server should appear in the MCP tools list.

---

### Translate

Once connected (any option above), just ask:

> "Translate article 3487 to French"

Claude will call the three MCP tools automatically (status check,
content extraction, translation push) and return the URL of the new
translation.

---

## Requirements (full Drupal development)

- Drupal 11 with `content_translation` and `paragraphs`
- `mcp_tools` module (1.0-beta7+) with `mcp_tools_remote` enabled
- An API key with `read` and `write` scopes

## Setup (Drupal developers)

### 1. Enable the module

```bash
ddev drush en mcp_tools_translate -y
ddev drush cr
```

### 2. Verify permissions

The `mcp_operator` role needs the **"Use MCP translation tools"**
permission. Check at `/admin/people/permissions#module-mcp_tools_translate`.

### 3. Create an API key

```bash
ddev drush mcp-tools:remote-key-create --label="Editor Name" --scopes=read,write
```

Save the key — it is shown only once. Keys are managed at
`/admin/config/services/mcp-tools/api-keys`.

### 4. Configure the MCP connection

Add the server to your MCP client configuration.

**Claude Code** (`.mcp.json` or `~/.claude.json`):

```json
{
  "mcpServers": {
    "tcs-drupal": {
      "type": "http",
      "url": "https://tcs-ch-drupal.ddev.site:8443/_mcp_tools"
    }
  }
}
```

**Claude Desktop** (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "tcs-drupal": {
      "url": "https://tcs-ch-drupal.ddev.site:8443/_mcp_tools",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```

**Hosted** endpoints:

| Environment | URL |
|---|---|
| Production | `https://tcs-preview.ch/_mcp_tools` |
| Main | `https://main.tcs-preview.ch/_mcp_tools` |
| Staging | `https://staging.tcs-preview.ch/_mcp_tools` |
| Local DDEV | `https://tcs-ch-drupal.ddev.site:8443/_mcp_tools` |

## Available tools

### `mcp_get_translation_status`

Check which languages a node has translations for.

**Input:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `nid` | integer | yes | Node ID |

**Output:**

```json
{
  "nid": 504,
  "title": "Roadside Assistance in Switzerland",
  "source_language": "en",
  "languages": {
    "en": { "language": "English", "is_source": true, "has_translation": true },
    "fr": { "language": "French", "is_source": false, "has_translation": false },
    "de": { "language": "German", "is_source": false, "has_translation": true },
    "it": { "language": "Italian", "is_source": false, "has_translation": false }
  }
}
```

### `mcp_get_translatable_content`

Extract all translatable text fields from a node and its paragraphs.
Call this before `mcp_translate_content` to know what to translate.

The output also includes `reading_order`: an ordered, nested outline (node
fields, then paragraphs with child paragraphs nested) so the article is
translated as one coherent document rather than field by field.

**Input:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `nid` | integer | yes | Node ID |

**Output:**

```json
{
  "nid": 504,
  "title": "Roadside Assistance in Switzerland",
  "type": "article",
  "source_language": "en",
  "existing_translations": ["en", "de"],
  "fields": {
    "title": { "type": "string", "value": "Roadside Assistance in Switzerland", "format": null },
    "field_description": { "type": "text_long", "value": "<p>Article description...</p>", "format": "content_format" },
    "field_meta_title": { "type": "string", "value": "Roadside Assistance | TCS", "format": null }
  },
  "paragraphs": {
    "1234": {
      "type": "text",
      "fields": {
        "field_text": { "type": "text_long", "value": "<p>Paragraph content...</p>", "format": "content_format" },
        "field_title": { "type": "string", "value": "Section Heading", "format": null }
      }
    },
    "1235": {
      "type": "button",
      "fields": {
        "field_button_text": { "type": "string", "value": "Learn more", "format": null }
      }
    }
  }
}
```

### `mcp_translate_content`

Create a linked translation for a node and all its paragraphs.
Requires `write` scope on the API key.

**Input:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `nid` | integer | yes | Source node ID |
| `language` | string | yes | Target language code: `fr`, `de`, or `it` |
| `translations` | map | yes | Translated field values (see below) |

**Translations map structure:**

```json
{
  "title": "Assistance routière en Suisse",
  "field_description": "<p>Description traduite...</p>",
  "field_meta_title": "Assistance routière | TCS",
  "paragraphs": {
    "1234": {
      "field_text": "<p>Contenu du paragraphe traduit...</p>",
      "field_title": "Titre de la section"
    },
    "1235": {
      "field_button_text": "En savoir plus"
    }
  }
}
```

**Field value formats:**

- **Text fields** (`text_long`, `text_with_summary`, `text`): pass a string.
  The service auto-wraps with `{"value": "...", "format": "content_format"}`.
  Or pass the full object if you need a different format.
- **String fields** (`string`): pass a string. Auto-truncated to the field's
  `max_length` setting (e.g. 100 chars for `field_button_text`).
- **Paragraph IDs**: use the IDs returned by `mcp_get_translatable_content`.

**Output:**

```json
{
  "nid": 504,
  "language": "fr",
  "language_name": "French",
  "title": "Assistance routière en Suisse",
  "url": "/fr/node/504",
  "paragraphs_translated": 9,
  "message": "Translation to French created for 'Roadside Assistance in Switzerland'."
}
```

**Error cases:**

- Translation already exists: returns error with suggestion to use
  `mcp_update_content` instead.
- Invalid language code: returns error with available languages.
- Write access denied: API key needs `write` scope.
- Incomplete translation: if any translatable node field or paragraph field
  is missing or empty in the `translations` map, the tool **rejects the request
  and writes nothing**. The error includes a `missing` object
  (`node_fields` + `paragraphs` keyed by paragraph id) and a `hint` to
  re-translate the whole article. Provide a value for *every* field returned by
  `mcp_get_translatable_content` (repeat a value unchanged if it is identical in
  the target language). On success the result reports `fields_translated` and
  `paragraphs_translated` counts covering the whole article.

## Translation workflow

A typical AI-assisted translation follows three steps:

```
1. Check status     →  mcp_get_translation_status(nid=504)
                        "French and Italian are missing"

2. Extract content  →  mcp_get_translatable_content(nid=504)
                        Returns all fields + paragraph fields

3. Translate & push →  mcp_translate_content(nid=504, language="fr", translations={...})
                        Creates linked Drupal translation
```

### Example: natural language prompt

An editor using Claude Code or Claude Desktop can simply say:

> "Translate article 504 to French"

The AI agent will:
1. Call `mcp_get_translation_status` to verify French is missing
2. Call `mcp_get_translatable_content` to get all source text
3. Translate every field and paragraph
4. Call `mcp_translate_content` with the translated content
5. Return the URL of the new French version

### Example: curl (raw MCP protocol)

```bash
# 1. Initialize session
SESSION=$(curl -s -D- -X POST "$MCP_URL" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{
    "protocolVersion":"2025-03-26",
    "capabilities":{},
    "clientInfo":{"name":"test","version":"1.0"}
  }}' 2>/dev/null | grep -i "mcp-session-id" | cut -d' ' -f2 | tr -d '\r')

# 2. Check translation status
curl -s -X POST "$MCP_URL" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Mcp-Session-Id: $SESSION" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{
    "name":"mcp_get_translation_status",
    "arguments":{"nid":504}
  }}'

# 3. Get translatable content
curl -s -X POST "$MCP_URL" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Mcp-Session-Id: $SESSION" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{
    "name":"mcp_get_translatable_content",
    "arguments":{"nid":504}
  }}'

# 4. Create translation (after translating the content)
curl -s -X POST "$MCP_URL" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Mcp-Session-Id: $SESSION" \
  -d '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{
    "name":"mcp_translate_content",
    "arguments":{
      "nid":504,
      "language":"fr",
      "translations":{
        "title":"Titre traduit",
        "paragraphs":{}
      }
    }
  }}'
```

## Architecture

```
MCP Client (Claude Code / Claude Desktop / curl)
    │
    │  HTTP POST with JSON-RPC 2.0
    │  Authorization: Bearer <API_KEY>
    │  Mcp-Session-Id: <session>
    │
    ▼
Drupal /_mcp_tools  (mcp_tools_remote module)
    │
    │  API key → scopes (read/write)
    │  Scope check → AccessManager
    │
    ▼
mcp_tools_translate tools
    ├── GetTranslationStatus   (read scope)
    ├── GetTranslatableContent (read scope)
    └── TranslateContent       (write scope)
            │
            ▼
      ContentTranslationService
            │
            ├── Paragraphs translated first (recursive, depth-first)
            ├── Node translation created last (links to paragraph translations)
            ├── String fields auto-truncated to max_length
            └── Text fields auto-wrapped with content_format
```

## Supported content

- All node types with translatable text fields
- Nested paragraphs (recursive — child paragraphs are translated before parents)
- Paragraph reference fields: `field_paragraphs`, `field_summary_key_facts`
- Field types: `string`, `string_long`, `text_long`, `text_with_summary`, `text`

## Deployment (Upsun / production)

The module works on all Upsun environments (main, staging, production).
The deploy hook in `drush/platformsh_deploy_drupal.sh` calls
`drush/mcp_bootstrap.php` automatically on every deploy to:

1. Create the `mcp_service` user (if it doesn't exist)
2. Assign the `mcp_operator` role
3. Set the `mcp_tools_remote` UID to the service user
4. Create a default API key (first deploy only — save the output)

### Per-environment config (settings.upsun.php)

On Upsun, `settings.upsun.php` overrides the config for production safety:

| Setting | Local (DDEV) | Upsun |
|---|---|---|
| `mode` | `development` | `production` |
| `trust_scopes_via_env` | `true` (STDIO needs it) | `false` (HTTP uses API keys) |
| `trust_scopes_via_header` | `false` | `false` |
| `trust_scopes_via_query` | `false` | `false` |
| `audit_logging` | `true` | `true` |
| `server_name` | `TCS MCP Tools (Local)` | `TCS MCP Tools (Main/Staging/Production)` |

### Endpoints

| Environment | URL |
|---|---|
| Production | `https://tcs-preview.ch/_mcp_tools` |
| Main | `https://main.tcs-preview.ch/_mcp_tools` |
| Staging | `https://staging.tcs-preview.ch/_mcp_tools` |
| Local DDEV | `https://tcs-ch-drupal.ddev.site:8443/_mcp_tools` |

### First deploy checklist

After the first deploy to a new environment:

1. Check the deploy log for the generated API key
2. Store the key securely — it is shown only once
3. Create additional keys as needed:
   `upsun ssh -- drush mcp-tools:remote-key-create --label="Editor" --scopes=read,write`
4. Verify the endpoint responds:
   `curl -s https://main.tcs-preview.ch/_mcp_tools -H "Authorization: Bearer <KEY>" -d '...'`

## Security

- API keys are scoped (`read` / `write`) — translation requires `write`
- The `mcp_operator` role must have `mcp_tools use translation` permission
- All write operations are audit-logged (`mcp_tools.audit_logger`)
- `AccessManager` intersects API key scopes with module-level `allowed_scopes`
- `uid` is set per key — translations are attributed to the API key owner
- On Upsun, scope trust vectors (env/header/query) are disabled — only API keys
  determine scopes

## Troubleshooting

**"Access denied" on translate:**
Check that the API key has `write` scope and `mcp_tools.settings.yml` includes
`write` in `access.allowed_scopes`.

**"Node already has a translation":**
The tool creates new translations only. Use `mcp_update_content` (from
`mcp_tools_content`) to modify an existing translation.

**String field truncated:**
String fields are auto-truncated to their configured `max_length`. The
tool does not error — it silently truncates. Keep translations concise.

**Tool not visible in tools/list:**
The endpoint paginates at 50 tools. Use the `cursor` parameter to page
through all tools, or search by name in your MCP client.
