#!/bin/bash
# MCP Tools - Docker Development Setup
#
# This script sets up a fresh Drupal installation with MCP Tools.
# Run this after `docker compose up -d` for first-time setup.

set -e

echo "=== MCP Tools Docker Setup ==="
echo ""

# Check if we're in the container or on host
if [ -f /var/www/html/index.php ]; then
    # We're inside the container
    cd /var/www/html
    DRUSH="./vendor/bin/drush"
    COMPOSER="composer"
else
    # We're on the host - use docker compose exec
    DRUSH="docker compose exec drupal ./vendor/bin/drush"
    COMPOSER="docker compose exec drupal composer"

    # Check if containers are running
    if ! docker compose ps | grep -q "mcp_tools_drupal"; then
        echo "Error: Docker containers not running. Run 'docker compose up -d' first."
        exit 1
    fi
fi

echo "Step 1: Installing dependencies (Drush, Tool API)..."
$COMPOSER require drush/drush drupal/tool:^1.0@alpha --no-interaction

echo ""
echo "Step 2: Installing Drupal (minimal profile)..."
$DRUSH site:install minimal \
    --db-url=mysql://drupal:drupal@database:3306/drupal \
    --account-name=admin \
    --account-pass=admin \
    --site-name="MCP Tools Dev" \
    -y

echo ""
echo "Step 3: Enabling MCP Tools..."
$DRUSH en mcp_tools mcp_tools_stdio -y

echo ""
echo "Step 4: Clearing caches..."
$DRUSH cr

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Access your site at: http://localhost:8080"
echo "Admin login: admin / admin"
echo ""
echo "To enable more submodules:"
echo "  $DRUSH en mcp_tools_content mcp_tools_structure mcp_tools_users -y"
echo ""
echo "To run tests:"
echo "  docker compose exec drupal ./vendor/bin/phpunit modules/custom/mcp_tools/tests/src/Unit/"
echo ""
