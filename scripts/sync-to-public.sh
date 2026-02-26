#!/usr/bin/env bash
#
# Sync dev repo to public repos (GitHub + Drupal.org).
#
# Copies all tracked files, then removes internal-only files that should
# never appear in the public repo. This preserves tests, docs, CI, and
# other community files while stripping dev-only configuration.
#
# Usage:
#   scripts/sync-to-public.sh              # dry-run (default)
#   scripts/sync-to-public.sh --push       # actually push
#   scripts/sync-to-public.sh --push --skip-drupal  # push to GitHub only

set -euo pipefail

PUSH=false
SKIP_DRUPAL=false
for arg in "$@"; do
  case "$arg" in
    --push) PUSH=true ;;
    --skip-drupal) SKIP_DRUPAL=true ;;
    *) echo "Unknown argument: $arg"; exit 1 ;;
  esac
done

# Ensure we're in the repo root.
cd "$(git rev-parse --show-toplevel)"

DEV_SHA=$(git rev-parse --short HEAD)
DEV_MSG=$(git log -1 --format='%s')
WORKTREE_DIR=".git/sync-worktree"
BRANCH="public/master"

# Files/dirs that exist only in the dev repo.
# Keep this in sync with CLAUDE.md "Files that should ONLY be in Dev Repo".
INTERNAL_FILES=(
  # Project instructions
  CLAUDE.md

  # Internal configuration
  .gitleaks.toml
  .gitlab-ci.yml
  .ddev
  codecov.yml

  # Internal documentation
  DRUPALCI.md
  DRUPAL_ORG_DESCRIPTION.html
  DRUPAL_ORG_DESCRIPTION.md
  INTERNAL_PLANNING.md
  RELEASE_NOTES.md
  docs/DRUPALCON_TALK.md
  docs/TESTIMONIALS.md
  docs/DEMO_SITE.md

  # Internal scripts & packages
  scripts
  packages
)

echo "==> Syncing dev HEAD ($DEV_SHA: $DEV_MSG) to public/master"

# Fetch latest public/master.
echo "==> Fetching public/master..."
git fetch public master

# Create a temporary worktree for public/master.
echo "==> Creating worktree at $WORKTREE_DIR..."
git worktree add "$WORKTREE_DIR" "$BRANCH" 2>/dev/null || {
  # If worktree already exists, remove and recreate.
  git worktree remove --force "$WORKTREE_DIR" 2>/dev/null || true
  git worktree add "$WORKTREE_DIR" "$BRANCH"
}

cleanup() {
  echo "==> Cleaning up worktree..."
  git worktree remove --force "$WORKTREE_DIR" 2>/dev/null || true
}
trap cleanup EXIT

# Remove all tracked content from worktree (keep .git).
echo "==> Clearing worktree..."
find "$WORKTREE_DIR" -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +

# Copy ALL tracked files into worktree (git archive applies export-ignore,
# so we use checkout-index which copies everything).
echo "==> Copying tracked files..."
GIT_WORK_TREE="$WORKTREE_DIR" git checkout-index -a -f

# Remove internal-only files.
echo "==> Removing internal files..."
for f in "${INTERNAL_FILES[@]}"; do
  if [ -e "$WORKTREE_DIR/$f" ]; then
    rm -rf "$WORKTREE_DIR/$f"
    echo "    removed: $f"
  fi
done

# Stage everything and check for changes.
cd "$WORKTREE_DIR"
git add -A

if git diff --cached --quiet; then
  echo "==> No changes to sync. Public is up to date."
  exit 0
fi

echo ""
echo "==> Changes detected:"
git diff --cached --stat

# Commit.
git commit -m "Sync from dev $DEV_SHA: $DEV_MSG"

echo ""
if [ "$PUSH" = true ]; then
  echo "==> Pushing to public (GitHub)..."
  git push public HEAD:master

  if [ "$SKIP_DRUPAL" = true ]; then
    echo "==> Skipping drupal push (--skip-drupal)"
  else
    echo "==> Pushing to drupal (Drupal.org)..."
    git push drupal HEAD:master
  fi

  echo "==> Done! Public repos updated."
else
  echo "==> DRY RUN — not pushing. Use --push to push."
  echo "    Commit ready at: $(git rev-parse --short HEAD)"
  echo "    Run: scripts/sync-to-public.sh --push"
fi
