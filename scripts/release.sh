#!/bin/bash
# =============================================================================
# MCP Tools Release Script
# Syncs module to drupal.org and GitHub mirror
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
MODULE_DIR="$PROJECT_ROOT/mcp_tools"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Flags
DRY_RUN=false

# Drupal.org Git remote
DRUPAL_ORG_REMOTE="git@git.drupal.org:project/mcp_tools.git"
# GitHub mirror remote (update this after creating the repo)
GITHUB_MIRROR_REMOTE="git@github.com:code-wheel/mcp_tools.git"

show_help() {
    echo "MCP Tools Release Script"
    echo ""
    echo "Usage: $0 [--dry-run] <command> [options]"
    echo ""
    echo "Options:"
    echo "  --dry-run   Show what would be done without making changes"
    echo ""
    echo "Commands:"
    echo "  check       Full dry-run check (gitleaks, syntax, structure)"
    echo "  prepare     Run pre-release checks (gitleaks, phpcs, tests)"
    echo "  tag <ver>   Create a release tag (e.g., 1.0.0)"
    echo "  push        Push to drupal.org"
    echo "  mirror      Sync GitHub mirror with drupal.org"
    echo "  release     Full release: prepare + tag + push + mirror"
    echo ""
    echo "Examples:"
    echo "  $0 --dry-run check      # Full validation without changes"
    echo "  $0 prepare              # Run checks before release"
    echo "  $0 --dry-run tag 1.0.0  # Preview tag creation"
    echo "  $0 release 1.0.0        # Full release"
    echo ""
}

# Check if we're in the module directory
check_module_dir() {
    if [ ! -f "$MODULE_DIR/mcp_tools.info.yml" ]; then
        echo -e "${RED}Error: Cannot find mcp_tools module at $MODULE_DIR${NC}"
        exit 1
    fi
}

# Check if we're in a git repo
is_git_repo() {
    git -C "$1" rev-parse --git-dir > /dev/null 2>&1
}

# Run gitleaks with appropriate flags
run_gitleaks() {
    local target_dir=$1
    local config_file=$2

    if ! command -v gitleaks &> /dev/null; then
        echo -e "${YELLOW}Warning: gitleaks not installed, skipping secret scan${NC}"
        return 0
    fi

    local gitleaks_args="detect --source $target_dir --config $config_file --verbose"

    # Use --no-git if not a git repo
    if ! is_git_repo "$target_dir"; then
        gitleaks_args="$gitleaks_args --no-git"
        echo -e "${YELLOW}(running in no-git mode)${NC}"
    fi

    if $DRY_RUN; then
        echo -e "${BLUE}[DRY-RUN] Would run: gitleaks $gitleaks_args${NC}"
        # Still run it for validation
        gitleaks $gitleaks_args
    else
        gitleaks $gitleaks_args
    fi
}

# Full check (dry-run friendly)
check() {
    echo -e "${BLUE}=== MCP Tools Pre-Release Check ===${NC}"
    echo ""

    local errors=0
    local warnings=0

    # Use arithmetic that doesn't fail on 0
    increment_errors() { errors=$((errors + 1)); }
    increment_warnings() { warnings=$((warnings + 1)); }

    # 1. Check for secrets
    echo -e "${YELLOW}[1/6] Checking for secrets with gitleaks...${NC}"
    if run_gitleaks "$MODULE_DIR" "$PROJECT_ROOT/.gitleaks.toml"; then
        echo -e "${GREEN}✓ No secrets detected${NC}"
    else
        echo -e "${RED}✗ Secrets detected!${NC}"
        increment_errors
    fi
    echo ""

    # 2. Check PHP syntax (if PHP available)
    echo -e "${YELLOW}[2/6] Checking PHP syntax...${NC}"
    if command -v php &> /dev/null; then
        local php_errors=$(find "$MODULE_DIR" -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" | grep -c "Parse error" || true)
        if [ "$php_errors" -eq 0 ]; then
            echo -e "${GREEN}✓ PHP syntax OK${NC}"
        else
            echo -e "${RED}✗ PHP syntax errors found${NC}"
            increment_errors
        fi
    else
        echo -e "${YELLOW}⚠ PHP not installed, skipping syntax check (CI will verify)${NC}"
        increment_warnings
    fi
    echo ""

    # 3. Check for TODO/FIXME
    echo -e "${YELLOW}[3/6] Checking for TODO/FIXME comments...${NC}"
    local todos=$(grep -r "TODO\|FIXME" "$MODULE_DIR/src" --include="*.php" 2>/dev/null || true)
    if [ -z "$todos" ]; then
        echo -e "${GREEN}✓ No TODO/FIXME comments${NC}"
    else
        echo -e "${YELLOW}⚠ Found TODO/FIXME comments:${NC}"
        echo "$todos" | head -10
        increment_warnings
    fi
    echo ""

    # 4. Check required files
    echo -e "${YELLOW}[4/6] Checking required files...${NC}"
    local required_files=(
        "mcp_tools.info.yml"
        "mcp_tools.module"
        "README.md"
        "CHANGELOG.md"
        "composer.json"
        ".gitattributes"
    )
    local missing=0
    for file in "${required_files[@]}"; do
        if [ -f "$MODULE_DIR/$file" ]; then
            echo -e "  ${GREEN}✓${NC} $file"
        else
            echo -e "  ${RED}✗${NC} $file (missing)"
            missing=$((missing + 1))
        fi
    done
    if [ $missing -gt 0 ]; then
        increment_errors
    fi
    echo ""

    # 5. Check file counts
    echo -e "${YELLOW}[5/6] Checking module structure...${NC}"
    local php_count=$(find "$MODULE_DIR" -name "*.php" | wc -l)
    local submodule_count=$(find "$MODULE_DIR/modules" -maxdepth 1 -type d 2>/dev/null | wc -l)
    submodule_count=$((submodule_count - 1)) # Subtract the modules dir itself
    echo "  PHP files: $php_count"
    echo "  Submodules: $submodule_count"
    if [ $php_count -lt 10 ]; then
        echo -e "${YELLOW}⚠ Fewer PHP files than expected${NC}"
        increment_warnings
    else
        echo -e "${GREEN}✓ Module structure looks good${NC}"
    fi
    echo ""

    # 6. Check .gitattributes export-ignore
    echo -e "${YELLOW}[6/6] Checking .gitattributes export-ignore rules...${NC}"
    if [ -f "$MODULE_DIR/.gitattributes" ]; then
        local export_ignores=$(grep "export-ignore" "$MODULE_DIR/.gitattributes" | wc -l)
        echo "  Export-ignore rules: $export_ignores"
        if grep -q "/tests" "$MODULE_DIR/.gitattributes" && grep -q "/docs" "$MODULE_DIR/.gitattributes"; then
            echo -e "${GREEN}✓ Dev files excluded from releases${NC}"
        else
            echo -e "${YELLOW}⚠ Some dev files may be included in releases${NC}"
            increment_warnings
        fi
    else
        echo -e "${RED}✗ .gitattributes not found${NC}"
        increment_errors
    fi
    echo ""

    # Summary
    echo -e "${BLUE}=== Summary ===${NC}"
    if [ $errors -eq 0 ] && [ $warnings -eq 0 ]; then
        echo -e "${GREEN}All checks passed! Ready for release.${NC}"
    elif [ $errors -eq 0 ]; then
        echo -e "${YELLOW}$warnings warning(s), but no errors. Review before release.${NC}"
    else
        echo -e "${RED}$errors error(s) and $warnings warning(s). Fix before release.${NC}"
        return 1
    fi
}

# Run pre-release checks (original prepare function)
prepare() {
    echo -e "${BLUE}=== Running Pre-Release Checks ===${NC}"
    echo ""

    # Check for secrets
    echo -e "${YELLOW}Checking for secrets with gitleaks...${NC}"
    run_gitleaks "$MODULE_DIR" "$PROJECT_ROOT/.gitleaks.toml"
    echo -e "${GREEN}No secrets detected.${NC}"

    # Check PHP syntax
    echo ""
    echo -e "${YELLOW}Checking PHP syntax...${NC}"
    if command -v php &> /dev/null; then
        find "$MODULE_DIR" -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" || true
        echo -e "${GREEN}PHP syntax OK.${NC}"
    else
        echo -e "${YELLOW}Warning: PHP not installed, skipping syntax check${NC}"
    fi

    # Check for TODO/FIXME
    echo ""
    echo -e "${YELLOW}Checking for TODO/FIXME comments...${NC}"
    TODOS=$(grep -r "TODO\|FIXME" "$MODULE_DIR/src" --include="*.php" 2>/dev/null || true)
    if [ -n "$TODOS" ]; then
        echo -e "${YELLOW}Warning: Found TODO/FIXME comments:${NC}"
        echo "$TODOS"
    else
        echo -e "${GREEN}No TODO/FIXME found.${NC}"
    fi

    echo ""
    echo -e "${GREEN}=== Pre-Release Checks Complete ===${NC}"
}

# Create a release tag
tag_release() {
    VERSION=$1
    if [ -z "$VERSION" ]; then
        echo -e "${RED}Error: Version required. Usage: $0 tag <version>${NC}"
        exit 1
    fi

    echo -e "${BLUE}Creating tag $VERSION...${NC}"

    cd "$MODULE_DIR"

    # Validate version format
    if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9]+)?$ ]]; then
        echo -e "${RED}Error: Invalid version format. Use semver (e.g., 1.0.0, 1.0.0-beta1)${NC}"
        exit 1
    fi

    if $DRY_RUN; then
        echo -e "${BLUE}[DRY-RUN] Would update version to '$VERSION' in mcp_tools.info.yml${NC}"
        echo -e "${BLUE}[DRY-RUN] Would commit: 'Prepare release $VERSION'${NC}"
        echo -e "${BLUE}[DRY-RUN] Would create tag: $VERSION${NC}"
        return 0
    fi

    # Check if we're in a git repo
    if ! is_git_repo "."; then
        echo -e "${RED}Error: Not a git repository. Run 'git init' first.${NC}"
        exit 1
    fi

    # Check if tag already exists
    if git tag -l "$VERSION" | grep -q "$VERSION"; then
        echo -e "${RED}Error: Tag $VERSION already exists${NC}"
        exit 1
    fi

    # Update version in info.yml
    sed -i "s/^version:.*/version: '$VERSION'/" mcp_tools.info.yml

    # Commit version update
    git add mcp_tools.info.yml
    git commit -m "Prepare release $VERSION"

    # Create annotated tag
    git tag -a "$VERSION" -m "Release $VERSION"

    echo -e "${GREEN}Tag $VERSION created.${NC}"
    echo ""
    echo "To push: git push origin $VERSION"
}

# Push to drupal.org
push_drupal() {
    echo -e "${BLUE}Pushing to drupal.org...${NC}"

    cd "$MODULE_DIR"

    if $DRY_RUN; then
        echo -e "${BLUE}[DRY-RUN] Would add remote: drupal -> $DRUPAL_ORG_REMOTE${NC}"
        echo -e "${BLUE}[DRY-RUN] Would push: git push drupal master --tags${NC}"
        return 0
    fi

    # Check if drupal remote exists
    if ! git remote | grep -q "drupal"; then
        echo "Adding drupal.org remote..."
        git remote add drupal "$DRUPAL_ORG_REMOTE"
    fi

    # Push main branch and tags
    git push drupal master --tags

    echo -e "${GREEN}Pushed to drupal.org successfully.${NC}"
}

# Sync GitHub mirror
sync_mirror() {
    echo -e "${BLUE}Syncing GitHub mirror...${NC}"

    cd "$MODULE_DIR"

    if $DRY_RUN; then
        echo -e "${BLUE}[DRY-RUN] Would add remote: github -> $GITHUB_MIRROR_REMOTE${NC}"
        echo -e "${BLUE}[DRY-RUN] Would push: git push github master --tags${NC}"
        return 0
    fi

    # Check if github remote exists
    if ! git remote | grep -q "github"; then
        echo "Adding GitHub remote..."
        git remote add github "$GITHUB_MIRROR_REMOTE"
    fi

    # Push to GitHub
    git push github master --tags

    echo -e "${GREEN}GitHub mirror synced.${NC}"
}

# Full release
full_release() {
    VERSION=$1
    if [ -z "$VERSION" ]; then
        echo -e "${RED}Error: Version required. Usage: $0 release <version>${NC}"
        exit 1
    fi

    echo -e "${BLUE}=== Starting Full Release $VERSION ===${NC}"
    if $DRY_RUN; then
        echo -e "${YELLOW}(DRY-RUN MODE - no changes will be made)${NC}"
    fi
    echo ""

    prepare
    echo ""

    if $DRY_RUN; then
        echo -e "${BLUE}[DRY-RUN] Would prompt: Continue with tagging?${NC}"
        tag_release "$VERSION"
        echo ""
        echo -e "${BLUE}[DRY-RUN] Would prompt: Push to drupal.org?${NC}"
        push_drupal
        echo ""
        echo -e "${BLUE}[DRY-RUN] Would prompt: Sync GitHub mirror?${NC}"
        sync_mirror
    else
        read -p "Pre-release checks passed. Continue with tagging? (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            tag_release "$VERSION"

            read -p "Push to drupal.org? (y/n) " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                push_drupal
            fi

            read -p "Sync GitHub mirror? (y/n) " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                sync_mirror
            fi
        fi
    fi

    echo ""
    echo -e "${GREEN}=== Release Complete ===${NC}"
}

# Main
check_module_dir

# Parse --dry-run flag
if [ "$1" = "--dry-run" ]; then
    DRY_RUN=true
    shift
fi

case "$1" in
    check)
        check
        ;;
    prepare)
        prepare
        ;;
    tag)
        tag_release "$2"
        ;;
    push)
        push_drupal
        ;;
    mirror)
        sync_mirror
        ;;
    release)
        full_release "$2"
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        show_help
        exit 1
        ;;
esac
