# DrupalCI (Drupal.org) Notes

Drupal.org uses GitLab pipelines (“DrupalCI”). This project ships a `.gitlab-ci.yml` that includes the Drupal Association’s maintained templates.

## What DrupalCI Covers

- Standard Drupal contrib checks (Composer build, linting/code standards, PHPUnit).
- Multiple Drupal core branches (based on the template variables) when opted-in.

## Common Failure Modes

- **Wrong PHP version**: DrupalCI may test multiple PHP versions; ensure `composer.json` and `mcp_tools.info.yml` match the supported minimums.
- **Composer resolution issues**: dependency constraints can become incompatible as core/contrib releases move.
- **Missing PHP extensions**: some CI images don’t include optional extensions; this repo sets `COMPOSER_IGNORE_PLATFORM_REQS=1` in `.gitlab-ci.yml` to reduce false failures.
- **Deprecations**: newer core versions can surface deprecations; treat as a signal to update APIs even if tests still pass locally.

## How To Triage a DrupalCI Failure

1. **Identify the failing job** (Composer vs PHPUnit vs coding standards).
2. **Compare with GitHub Actions** (`.github/workflows/ci.yml`). If GitHub passes but DrupalCI fails, it’s often an environment/version-matrix mismatch.
3. **Reproduce locally** using the Docker-based workflow in `TESTING.md` (run the same PHPUnit suites against a Drupal project).

## Maintenance Tips

- Keep `drupal/tool` and `mcp/sdk` constraints aligned with what Drupal core and Tool API expect.
- When Drupal core bumps requirements (PHP, Symfony), update `mcp_tools.info.yml` and CI matrices together.

