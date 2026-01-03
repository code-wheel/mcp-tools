#!/bin/bash
# =============================================================================
# Setup Git Hooks for MCP Tools Development
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
HOOKS_DIR="$PROJECT_ROOT/.git/hooks"

echo "Setting up git hooks for MCP Tools..."

# Check if we're in a git repo
if [ ! -d "$PROJECT_ROOT/.git" ]; then
    echo "Error: Not a git repository. Run 'git init' first."
    exit 1
fi

# Check if gitleaks is installed
if ! command -v gitleaks &> /dev/null; then
    echo ""
    echo "Gitleaks is not installed. Install it with:"
    echo ""
    echo "  # macOS"
    echo "  brew install gitleaks"
    echo ""
    echo "  # Linux (download from GitHub releases)"
    echo "  wget https://github.com/gitleaks/gitleaks/releases/latest/download/gitleaks_8.18.0_linux_x64.tar.gz"
    echo "  tar -xzf gitleaks_8.18.0_linux_x64.tar.gz"
    echo "  sudo mv gitleaks /usr/local/bin/"
    echo ""
    echo "  # Or use Go"
    echo "  go install github.com/gitleaks/gitleaks/v8@latest"
    echo ""
    exit 1
fi

# Create hooks directory if it doesn't exist
mkdir -p "$HOOKS_DIR"

# Create pre-commit hook
cat > "$HOOKS_DIR/pre-commit" << 'EOF'
#!/bin/bash
# =============================================================================
# Pre-commit hook: Gitleaks secret scanning
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Running gitleaks secret scan...${NC}"

# Get the repo root
REPO_ROOT="$(git rev-parse --show-toplevel)"

# Run gitleaks on staged files
if command -v gitleaks &> /dev/null; then
    # Check staged changes
    gitleaks protect --staged --config="$REPO_ROOT/.gitleaks.toml" --redact --verbose

    if [ $? -ne 0 ]; then
        echo -e "${RED}==============================================================================${NC}"
        echo -e "${RED}Gitleaks detected potential secrets in your commit!${NC}"
        echo -e "${RED}==============================================================================${NC}"
        echo ""
        echo "Please review the findings above and either:"
        echo "  1. Remove the secret from your code"
        echo "  2. Add a legitimate allowlist entry to .gitleaks.toml"
        echo ""
        echo "To bypass this check (NOT RECOMMENDED), use:"
        echo "  git commit --no-verify"
        echo ""
        exit 1
    fi

    echo -e "${GREEN}No secrets detected. Proceeding with commit.${NC}"
else
    echo -e "${YELLOW}Warning: gitleaks not installed. Skipping secret scan.${NC}"
    echo "Install with: brew install gitleaks (macOS) or see docs"
fi

exit 0
EOF

chmod +x "$HOOKS_DIR/pre-commit"

echo ""
echo "Git hooks installed successfully!"
echo ""
echo "Installed hooks:"
echo "  - pre-commit: Gitleaks secret scanning"
echo ""
echo "To test gitleaks manually, run:"
echo "  gitleaks detect --source . --config .gitleaks.toml --verbose"
echo ""
