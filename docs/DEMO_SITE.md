# MCP Tools Demo Site Playbook

This guide outlines a repeatable local demo setup for MCP Tools using DDEV.

## Prereqs

- DDEV + Composer installed
- A Drupal codebase with this module checked out

## 1) Start a local site

```bash
ddev start
ddev composer install
ddev drush si minimal -y
ddev drush en mcp_tools mcp_tools_stdio -y
```

## 2) Enable a demo bundle

```bash
# Core site builder
ddev drush en mcp_tools_structure mcp_tools_views mcp_tools_blocks mcp_tools_menus mcp_tools_users mcp_tools_content mcp_tools_media -y

# Optional: templates
ddev drush en mcp_tools_templates -y
```

## 3) Apply a template

```bash
# Apply the blog template
ddev drush php:eval "\Drupal::service('mcp_tools_templates.template')->applyTemplate('blog');"
```

## 4) Seed a little content (optional)

```bash
ddev drush php:eval "\Drupal::service('tool.manager')->createInstance('mcp_create_content')->execute(['type' => 'article', 'title' => 'Hello MCP']);"
```

## 5) Run MCP over STDIO

```bash
# Start MCP server over STDIO
ddev drush mcp-tools:serve --uid=1
```

## 6) Optional: HTTP endpoint (remote clients)

```bash
ddev drush en mcp_tools_remote -y
ddev drush mcp-tools:remote-setup
ddev drush mcp-tools:remote-key-create --label="Demo" --scopes=read
```

## 7) Demo prompts

- "Apply the blog template and show me what was created."
- "Create a blog post titled 'Hello Drupal' and publish it."
- "Audit content for duplicates or stale pages."

## 8) Reset the demo (if needed)

```bash
ddev drush sql-drop -y
ddev drush si minimal -y
```

---

For production demos, use a locked-down user and read-only scopes.
