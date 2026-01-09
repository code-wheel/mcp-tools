# MCP Tools - Repository Guidelines

## Repository Structure

This project uses TWO repositories:

### 1. Private Dev Repo: `code-wheel/mcp-tools-dev`
- **Purpose**: Development, internal docs, CI configs, testing infrastructure
- **Contains**: Everything including internal/private files
- **Branch**: `master`

### 2. Public Repo: `code-wheel/mcp-tools`
- **Purpose**: Public release for Drupal.org and open source community
- **Contains**: Only files needed for end users
- **Branch**: `master`
- **Also synced to**: `git.drupal.org:project/mcp_tools`

## Files that should ONLY be in Dev Repo (NEVER in public)

### Internal Configuration
- `.gitleaks.toml` - Security scanning config
- `.gitlab-ci.yml` - GitLab CI (we use GitHub Actions)
- `.ddev/` - Local development environment
- `codecov.yml` - Code coverage service config

### Internal Documentation
- `DRUPALCI.md` - Drupal.org CI documentation
- `DRUPAL_ORG_DESCRIPTION.html` - Drupal.org project page content
- `DRUPAL_ORG_DESCRIPTION.md` - Drupal.org project page source
- `INTERNAL_PLANNING.md` - Internal planning notes
- `docs/DRUPALCON_TALK.md` - Presentation materials
- `docs/TESTIMONIALS.md` - Marketing testimonials
- `docs/DEMO_SITE.md` - Demo site setup

### Internal Scripts
- `scripts/` - All test/dev scripts (mcp_http_e2e.py, etc.)

### SDK Packages (separate repos now)
- `packages/` - Extracted SDK packages

## Files that SHOULD be in Public Repo

### Standard OSS Files
- `README.md` - Project documentation
- `CHANGELOG.md` - Version history
- `LICENSE` - GPL-2.0-or-later
- `CONTRIBUTING.md` - Contribution guidelines
- `TESTING.md` - Testing instructions
- `ROADMAP.md` - Project roadmap

### User Documentation
- `docs/ARCHITECTURE.md`
- `docs/QUICKSTART.md`
- `docs/TROUBLESHOOTING.md`
- `docs/USE_CASES.md`
- `docs/CLIENT_INTEGRATIONS.md`

### Module Files
- All `*.yml`, `*.php`, `*.module` files
- `config/`, `src/`, `modules/`, `tests/`, `templates/`
- `mcp.json.example`
- `phpunit.xml`

### CI (GitHub only)
- `.github/workflows/` - GitHub Actions
- `.gitignore`, `.gitattributes`

## Workflow for Changes

### Making Changes
1. Always work in the dev repo (`mcp-tools-dev`)
2. Test locally with `.ddev` or your preferred setup
3. Push to dev repo, verify CI passes

### Releasing to Public
1. Ensure dev repo CI is green
2. Push to public repo (exclude internal files)
3. Create release tag
4. Sync to Drupal.org if needed

## SDK Packages (Separate Repos)

These are now separate repos on Packagist:
- `code-wheel/mcp-http-security` - HTTP security middleware
- `code-wheel/mcp-error-codes` - MCP error code constants
- `code-wheel/mcp-events` - Tool execution events

## Remotes Setup

```bash
# In the working directory:
origin  -> code-wheel/mcp-tools-dev (dev)
public  -> code-wheel/mcp-tools (public)
drupal  -> git.drupal.org:project/mcp_tools
```

## IMPORTANT: Before Pushing to Public

Always verify you're not including internal files:
```bash
git diff --name-only origin/master public/master
```

Check for files that shouldn't be public:
- No `.gitleaks.toml`
- No `.gitlab-ci.yml`
- No `.ddev/`
- No `codecov.yml`
- No `DRUPAL*` files (except in dev)
- No `scripts/`
- No `packages/`
