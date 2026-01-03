# MCP Tools Release Checklist

## Pre-Release Setup (Completed)

- [x] `.gitignore` - Private repo file exclusions
- [x] `.gitleaks.toml` - Secret scanning configuration
- [x] `scripts/setup-hooks.sh` - Git hooks installation
- [x] `scripts/release.sh` - Release automation script
- [x] `.github/workflows/security.yml` - Gitleaks + security scanning
- [x] `.github/workflows/ci.yml` - PHP linting, PHPCS, PHPUnit

## Module Preparation (Completed)

- [x] `mcp_tools/.gitattributes` - Export-ignore for release archives
- [x] `mcp_tools/composer.json` - Drupal.org metadata
- [x] `mcp_tools/mcp_tools.info.yml` - Version and lifecycle fields
- [x] `mcp_tools/README.md` - Public documentation
- [x] `mcp_tools/CHANGELOG.md` - Release notes
- [x] `mcp_tools/ROADMAP.md` - Public roadmap (cleaned)
- [x] `mcp_tools/CONTRIBUTING.md` - Contribution guidelines

## Security Hardening (Completed)

- [x] SSRF protection in WebhookNotifier
- [x] Role escalation prevention with pattern matching
- [x] Protected field lists for imports
- [x] Menu URI validation (XSS prevention)
- [x] Admin rate limiting
- [x] Secure session identification
- [x] accessCheck(FALSE) documentation

## Files Excluded from Public Release

These files are in the private monorepo but excluded from drupal.org releases:

- `INTERNAL_PLANNING.md` - Internal week-based planning
- `.github/` - Private repo CI workflows
- `scripts/` - Release and development scripts
- `.gitleaks.toml` - Security config (not needed for users)
- `.gitleaks.toml.local` - Local overrides

## Release Commands

```bash
# 1. Install git hooks (one-time setup)
./scripts/setup-hooks.sh

# 2. Run pre-release checks
./scripts/release.sh prepare

# 3. Create release tag
./scripts/release.sh tag 1.0.0

# 4. Full release (prepare + tag + push + mirror)
./scripts/release.sh release 1.0.0
```

## Repository Structure

```
Private GitHub Repo (code-wheel/mcp_tools-dev)
├── mcp_tools/          → drupal.org module
├── scripts/            → Release automation
├── .github/            → CI/CD workflows
├── .gitleaks.toml      → Secret scanning
└── INTERNAL_PLANNING.md

↓ (git subtree or manual copy)

drupal.org (project/mcp_tools)
└── mcp_tools/          → Public module only

↓ (mirror)

GitHub Mirror (code-wheel/mcp_tools)
└── mcp_tools/          → Same as drupal.org
```

## First Release Steps

1. **Create drupal.org project page**
   - Go to https://www.drupal.org/node/add/project-module
   - Use description from README.md
   - Set maintenance status to "Actively maintained"

2. **Initialize drupal.org git**
   ```bash
   cd mcp_tools
   git init
   git remote add origin git@git.drupal.org:project/mcp_tools.git
   git add .
   git commit -m "Initial release"
   git push -u origin main
   ```

3. **Create GitHub mirror**
   - Create repo at github.com/code-wheel/mcp_tools
   - Push same content

4. **Create first release**
   - Use release.sh script
   - Create release on drupal.org
