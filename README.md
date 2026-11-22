# MCP Tools

[![CI](https://github.com/code-wheel/mcp-tools/actions/workflows/ci.yml/badge.svg)](https://github.com/code-wheel/mcp-tools/actions/workflows/ci.yml)
[![Security](https://github.com/code-wheel/mcp-tools/actions/workflows/security.yml/badge.svg)](https://github.com/code-wheel/mcp-tools/actions/workflows/security.yml)
[![codecov](https://codecov.io/gh/code-wheel/mcp-tools/branch/master/graph/badge.svg)](https://codecov.io/gh/code-wheel/mcp-tools)

MCP Tools lets an AI assistant work on a Drupal site. It can read the
site, build content types, fields and views, and edit content.

It speaks the [Model Context Protocol](https://modelcontextprotocol.io)
(MCP). So it is not tied to one assistant or one model: any MCP client
can connect, with a model in the cloud or one on your own machine.

```
You: "Create a blog with articles, categories, tags, and an editor role"
AI:  Creates the content type, fields, vocabularies, role, and permissions
```

- **227 tools:** 25 read-only tools in the base module and 202 more in 32
  optional submodules. They are [Tool API](https://www.drupal.org/project/tool)
  plugins.
- **Its own MCP server:** STDIO through Drush for local work, HTTP with
  API keys for a remote site.
- **Rules around every call:** scopes, read-only mode, write limits, an
  audit log. You decide what a connection may do.

## Where to use it

MCP Tools is made for local development and prototyping.

| Environment | Read tools | Write tools | Advice |
|-------------|------------|-------------|--------|
| **Local dev** | ✅ | ✅ | Everything |
| **Staging** | ✅ | ⚠️ | Config-only mode, or limited scopes |
| **Production** | ⚠️ | ❌ | Read-only mode |

Why write tools are a risk on a live site:

- They change configuration in the database, not in your Git repository.
  The next deploy can overwrite it, or conflict with it.
- An assistant can be steered by text it reads (prompt injection).
- Nobody reviews a change before it is made.

A workflow that fits:

1. Build the site locally with MCP Tools.
2. Export the configuration: `drush config:export`
3. Commit it and deploy as usual.
4. Keep production in read-only mode.

## Requirements

- Drupal 10.5+ or 11. Tested on 10.6 and 11.4. Drupal 12 once Tool API
  supports it.
- PHP 8.3+. Tested on 8.3, 8.4 and 8.5.
- [Tool API](https://www.drupal.org/project/tool) 1.0.0-beta12 or later
- `mcp/sdk` 0.6 to 0.7.1, 0.9 or 0.10. Composer picks it. 0.8 is
  skipped: it has a security advisory. From 0.9 the server also speaks
  the 2026-07-28 revision of MCP. Next to MCP Server 2.0.0-beta7 and
  later, Composer picks 0.9, which is what MCP Server requires.

## Install

```bash
composer require drupal/mcp_tools
drush en mcp_tools
```

Settings are at `/admin/config/services/mcp-tools`.

### Local: STDIO (recommended)

```bash
drush en mcp_tools_stdio
drush mcp-tools:serve --uid=1 --scope=read,write
```

Drush often starts as the anonymous user, so name the user with
`--uid`. Use user 1 for local development, and a user of its own with
only the MCP Tools permissions it needs anywhere else.

`--scope` (or the `MCP_SCOPE` environment variable) says what the
connection may do. See "Access control" below.

`drush mcp-tools:client-config > .mcp.json` writes the config for your
client. [docs/CLIENT_INTEGRATIONS.md](docs/CLIENT_INTEGRATIONS.md) has
ready examples.

### Remote: HTTP (experimental)

```bash
drush en mcp_tools_remote
drush mcp-tools:remote-key-create --label="My Key" --scopes=read --ttl=86400
```

Point your client at `/_mcp_tools` and send the key as
`Authorization: Bearer …` or `X-MCP-Api-Key: …`.

Use this on trusted networks only. Choose the user the tools run as at
`/admin/config/services/mcp-tools/remote`: user 1 for development, or
the `mcp_executor` account the form creates. Set IP and Origin/Host
allowlists, and keep keys read-only unless you need more.

### Gateway mode

```bash
drush mcp-tools:serve --uid=1 --gateway
```

The client then sees three tools and not the whole list:
`mcp_tools_discover_tools`, `mcp_tools_get_tool_info` and
`mcp_tools_execute_tool`. A tool that a model calls by its own name runs
too, through the same checks. For HTTP, turn gateway mode on in the
remote settings.

### Other ways to run the tools

Every tool is a plain Tool API plugin, so you do not need our server:

- **[MCP Server](https://www.drupal.org/project/mcp_server) 2.x** with
  its [Tool Bridge](https://www.drupal.org/project/mcp_server_tool_bridge).
  You pick each exposed tool in the bridge's settings. MCP Tools' own
  tools keep their scope and permission checks there. For the tools of
  other modules, the optional `mcp_tools_policy` submodule adds the same
  rules on MCP Server's HTTP endpoint. Tested before each release and by
  a weekly CI job. See [docs/CLIENT_INTEGRATIONS.md](docs/CLIENT_INTEGRATIONS.md).
- **MCP Server 1.x:** `mcp_tools_mcp_server` writes its tool configs
  (`drush mcp-tools:mcp-server-sync --enable-read`). It needs
  `drupal/mcp_server < 2.0` and is not being ported to 2.x.
- **[Drupal AI](https://www.drupal.org/project/ai):** the optional
  `mcp_tools_ai` submodule (experimental) offers the tools to in-site
  agents as function calls. Read-only tools by default. See
  `modules/mcp_tools_ai/README.md`.
- **No transport at all:** enable the base module and the tool
  submodules, and call the tools from ECA or your own code.

## Any client, any model

MCP is an open protocol, and the server does not know or care which
model is on the other end. What we tried ourselves:

- **Clients:** Claude Code, Codex CLI, fast-agent, Goose and OpenCode,
  and the official MCP clients for JavaScript and Python before each
  release.
- **Models on one graphics card,** through Ollama: five models of 9 to
  30 billion parameters did 10 to 12 of twelve site tasks in every run,
  with two of the clients. With the three other clients one of the
  models did 8 to 12.
- **Inside the site,** through Drupal AI and `mcp_tools_ai`.

Use gateway mode for a local model: three tools fit a small context.
The size of the model matters: one of 8 billion parameters did five or
six of the twelve when we tried it with 1.0.0-beta19.
[docs/CLIENT_INTEGRATIONS.md](docs/CLIENT_INTEGRATIONS.md) has the
numbers, per model and per client, and what to know before you judge a
model.

## The tools

### Base module: 25 read-only tools

| Area | Tools |
|------|-------|
| Site health | `mcp_tools_get_site_status`, `mcp_tools_get_system_status`, `mcp_tools_check_security_updates`, `mcp_tools_check_cron_status`, `mcp_tools_analyze_watchdog`, `mcp_tools_get_queue_status`, `mcp_tools_get_file_system_status` |
| Content | `mcp_tools_list_content_types`, `mcp_tools_get_recent_content`, `mcp_tools_search_content`, `mcp_tools_get_vocabularies`, `mcp_tools_get_terms`, `mcp_tools_get_files`, `mcp_tools_find_orphaned_files` |
| Configuration | `mcp_tools_get_config_status`, `mcp_tools_get_config`, `mcp_tools_list_config`, `mcp_list_text_formats`, `mcp_get_text_format` |
| Users | `mcp_tools_get_roles`, `mcp_tools_get_users`, `mcp_tools_get_permissions` |
| Menus | `mcp_tools_get_menus`, `mcp_tools_get_menu_tree` |
| Discovery | `mcp_tools_list_available` |

The base module also offers read-only MCP resources (for example
`drupal://site/status`) and prompts (for example `mcp_tools/site-brief`).

### Submodules: 202 tools in 32 submodules

Enable what you need. Each submodule lists its tools in its own
`README.md`, and [docs/TOOL_EXAMPLES.md](docs/TOOL_EXAMPLES.md) has a
real call for every tool.

| Submodule | Tools | What it does |
|-----------|------:|--------------|
| `mcp_tools_content` | 4 | Create, update, delete and publish content |
| `mcp_tools_translate` | 3 | Read translatable fields, create and update linked translations, also in paragraphs |
| `mcp_tools_structure` | 20 | Content types, fields, vocabularies, roles, permissions |
| `mcp_tools_users` | 5 | User accounts, roles, blocking |
| `mcp_tools_menus` | 5 | Menus and menu links |
| `mcp_tools_views` | 6 | Views |
| `mcp_tools_blocks` | 5 | Block placement and configuration |
| `mcp_tools_media` | 7 | Media types, uploads, media |
| `mcp_tools_webform` | 7 | Webforms and submissions (needs `webform`) |
| `mcp_tools_theme` | 8 | Theme settings, enable and disable |
| `mcp_tools_layout_builder` | 9 | Layout sections, blocks, plugins (needs `layout_builder`) |
| `mcp_tools_recipes` | 6 | Drupal Recipes. Needs the `admin` scope. |
| `mcp_tools_config` | 5 | Configuration diff, export, change tracking, preview |
| `mcp_tools_paragraphs` | 6 | Paragraph types and fields (needs `paragraphs`) |
| `mcp_tools_moderation` | 6 | Content moderation (needs `content_moderation`) |
| `mcp_tools_scheduler` | 5 | Scheduled publishing (needs `scheduler`) |
| `mcp_tools_metatag` | 5 | Meta tags (needs `metatag`) |
| `mcp_tools_image_styles` | 7 | Image styles and effects |
| `mcp_tools_cache` | 6 | Cache clear, invalidate, rebuild |
| `mcp_tools_cron` | 5 | Cron jobs and queues |
| `mcp_tools_ultimate_cron` | 6 | Ultimate Cron jobs (needs `ultimate_cron`) |
| `mcp_tools_pathauto` | 6 | URL alias patterns (needs `pathauto`) |
| `mcp_tools_redirect` | 7 | Redirects (needs `redirect`) |
| `mcp_tools_sitemap` | 7 | XML sitemap (needs `simple_sitemap`) |
| `mcp_tools_search_api` | 9 | Search indexes and servers (needs `search_api`) |
| `mcp_tools_entity_clone` | 4 | Entity cloning (needs `entity_clone`) |
| `mcp_tools_analysis` | 8 | SEO, security, accessibility and performance checks |
| `mcp_tools_batch` | 6 | Bulk content operations (50 items a batch at most) |
| `mcp_tools_templates` | 5 | Site templates (blog, portfolio, business, docs) |
| `mcp_tools_migration` | 7 | CSV and JSON import and export (100 items at most) |
| `mcp_tools_jsonapi` | 6 | Any entity through JSON:API (needs `jsonapi`) |
| `mcp_tools_remote_media` | 1 | Fetch a remote file and make media of it |

Six more submodules have no tools: `mcp_tools_stdio` and
`mcp_tools_remote` (our server), `mcp_tools_ai`, `mcp_tools_policy`,
`mcp_tools_mcp_server` and `mcp_tools_observability`.

Two bundles to start with:

```bash
# Site building
drush en mcp_tools_structure mcp_tools_views mcp_tools_blocks mcp_tools_menus mcp_tools_users mcp_tools_content mcp_tools_media -y

# Operations (use with care)
drush en mcp_tools_cache mcp_tools_cron mcp_tools_batch mcp_tools_analysis -y
```

## Access control

Three layers decide what a connection may do, and each connection is
shown only the tools those layers let it call.

### 1. Submodules

Only enabled submodules offer their tools. Each category of tools has a
Drupal permission of its own.

### 2. Read-only mode

A switch for the whole site that turns every write off:

```php
// In settings.php, or on the settings form
$config['mcp_tools.settings']['access']['read_only_mode'] = TRUE;
```

Config-only mode is the step between: tools that change configuration
run, tools that change content or run operations do not.

### 3. Scopes

Each connection has scopes: `read`, `write`, `admin`.

- `read`: tools that change nothing.
- `write`: tools that change the site.
- `admin`: five tools that reach further (writing and applying
  recipes, applying and exporting site templates, exporting
  configuration), and granting a permission that Drupal marks for
  trusted roles only. Give this scope only to an agent you would let
  change any config of the site.

A new site gives a connection `read` (`access.default_scopes`) and
allows `read` and `write` at most (`access.allowed_scopes`). The allowed
scopes limit every way to ask for scopes: an API key, a server profile,
`--scope` on the command line, the environment. When a session asks for
more, the STDIO server says on stderr which scope it left out.

```bash
# STDIO
drush mcp-tools:serve --uid=1 --scope=read,write
MCP_SCOPE=read,write drush mcp-tools:serve --uid=1
```

Scopes from an HTTP header (`X-MCP-Scope`) or a query parameter
(`mcp_scope`) are ignored unless you switch them on. Do that only behind
a proxy you trust to overwrite what a client sends.

### What a connection is shown

The tool list is what the connection may call. A connection with the
`read` scope is shown the tools that read. Write tools are left out in
read-only mode, and config-only mode leaves out the tools that write
content or run operations. Tools of a category the user has no
permission for are left out too. The server's instructions tell the
model how many tools are not listed, and why. A tool that is not listed
is still refused when it is called by name.

The STDIO server builds its list when it starts. A mode you switch later
changes at once what may run, and the list at the next start.

To list every tool, as before 1.0.0-beta20:

```php
$config['mcp_tools.settings']['access']['list_all_tools'] = TRUE;
```

### Calls that ask first

Three kinds of call are run after the user of the client said yes, when
the client can ask its user (MCP elicitation):

- a tool that declares itself destructive, of MCP Tools or of another
  module,
- a grant of a permission that Drupal marks for trusted roles only,
- a recipe that was written through MCP and that no person has reviewed.

A no runs nothing.

Where nobody can be asked, the call is refused. That is a client
without elicitation, the Drupal AI bridge, and MCP Server when the
submodule `mcp_tools_policy` is enabled (without it, MCP Server runs
the tool). This is the setting "Strong calls need a confirmation", and
a site installed with 1.0.0-beta22 or later has it on. A site from
before keeps what it had, which is off: there such a call runs, and the
audit log has it, the last two kinds as warnings.

30 tools declare themselves destructive: the ones that delete, remove
or revoke, the bulk imports, turning Layout Builder off (it deletes the
layouts), cancelling a schedule, and a few that clear something for
good (a search index, a cron key). The cache tools and the sitemap
rebuild do not, because what they clear is rebuilt, and neither do the
tools that disable a view, a theme or a cron job, which can be switched
on again.

To run these calls where nobody can be asked, untick the setting on the
settings form, or:

```php
$config['mcp_tools.settings']['access']['confirm_destructive_tools'] = FALSE;
```

Two of our tools also take a confirm flag: deleting a piece of content
with all its translations, and exporting configuration.

### Tools of other modules

With `--all-tools` (or `include_all_tools` in a server profile) the
server also runs the Tool API tools of other modules. They are held to
the same rules as MCP Tools' own: the scope their operation needs,
read-only and config-only mode, the write limits when rate limiting is
on, and a line in the audit log for each write that ran.

## Server profiles

Define server profiles in `mcp_tools.servers.yml` and pick one with
`--server` on STDIO or the `server_id` setting of the remote endpoint.
New installs have `development`, `staging` and `production` profiles.

```yaml
default_server: default
servers:
  default:
    name: 'Drupal MCP Tools'
    version: '1.0.0'
    pagination_limit: 50
    include_all_tools: false
    gateway_mode: false
    enable_resources: true
    enable_prompts: true
    transports: ['http', 'stdio']
    scopes: ['read', 'write']
    # permission_callback: 'my_module.server_access:check'
```

A profile's scopes are limited by `access.allowed_scopes`, like every
other way to ask for scopes. `transports` says which entry points may
run a profile. Leave it out to allow both.

## Security

### What is protected

| What | How |
|------|-----|
| User 1 | Cannot be changed or blocked |
| Administrators | An administrator role (by its flag or its name) cannot be assigned or deleted. A user who has one cannot be changed, blocked or activated. |
| A connection's own user | Cannot be given a role that would add a permission |
| System menus | admin, main, footer, tools, account |
| Core views | Cannot be deleted |
| Active themes | The default and the admin theme cannot be disabled |

### Permissions that are not granted

These are never granted through MCP, with any scope:

- Every permission of MCP Tools itself. They say which tools a user may
  call.
- `administer permissions`, `administer users`,
  `administer site configuration`, `administer modules`,
  `administer software updates`, `administer themes`, `administer menu`,
  `administer blocks`, `administer views`, `administer url aliases`,
  `create url aliases`, `bypass node access`, `access all views`,
  `synchronize configuration`, `import configuration`,
  `export configuration`, `cancel account`,
  `select account cancellation method`, `translate interface`.

Every other permission that Drupal marks "restrict access" needs the
`admin` scope. Even then it is not granted to the roles `anonymous` and
`authenticated`, or to a role the connection's own user has.

### What else is built in

- The audit log has every write, with the user and with secrets taken
  out. It is Drupal's log: enable `dblog` or `syslog` to keep it.
- Write limits per minute and per hour, when rate limiting is on. Two
  costly read tools are limited too (broken links, content search).
- The remote endpoint is closed at the routing layer: a client that is
  not allowed gets a 404.
- Arguments a tool does not know are refused, not dropped.
- Batch tools take 50 items at most, and uploads are capped.

### What is left to you

| Risk | What to do |
|------|------------|
| **Prompt injection:** text on the site or the web can steer an assistant | Read-only mode on a live site. Give the `admin` scope sparingly. |
| **What read tools show:** they expose site information | Proper authentication, and a user with only the permissions it needs |
| **Config drift:** changes are made in the database | Export and commit after you used write tools |
| **Content a tool saves** | Goes through Drupal's text formats, as content from a form does |

### On a live site

```php
// settings.php
$config['mcp_tools.settings']['access']['read_only_mode'] = TRUE;
$config['mcp_tools.settings']['access']['default_scopes'] = ['read'];
$config['mcp_tools.settings']['access']['allowed_scopes'] = ['read'];

$config['mcp_tools.settings']['rate_limiting']['enabled'] = TRUE;
$config['mcp_tools.settings']['rate_limiting']['max_writes_per_minute'] = 10;
$config['mcp_tools.settings']['rate_limiting']['max_writes_per_hour'] = 100;
```

Also: allow only known IP addresses at the web server, keep write
submodules disabled, and read the audit log.

## More

- **Docs:** [QUICKSTART](docs/QUICKSTART.md),
  [CLIENT_INTEGRATIONS](docs/CLIENT_INTEGRATIONS.md),
  [USE_CASES](docs/USE_CASES.md), [TOOL_EXAMPLES](docs/TOOL_EXAMPLES.md),
  [TROUBLESHOOTING](docs/TROUBLESHOOTING.md),
  [ARCHITECTURE](docs/ARCHITECTURE.md)
- **Events:** the server dispatches PSR-14 events from
  `code-wheel/mcp-events` when a tool starts, succeeds or fails. The
  optional `mcp_tools_observability` submodule writes them to the log.
- **Commands:**

```bash
drush mcp:servers                       # the server profiles
drush mcp:server-info --server=default --tools --resources --prompts
drush mcp:server-smoke --server=default # does the server start?
drush mcp:dev-profile                   # the development preset (see docs/QUICKSTART.md)
```

- **Tests:** `../vendor/bin/phpunit` from the module folder.
  [TESTING.md](TESTING.md) has the rest.

## Contributing

Issues and merge requests: https://www.drupal.org/project/issues/mcp_tools.
See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later
