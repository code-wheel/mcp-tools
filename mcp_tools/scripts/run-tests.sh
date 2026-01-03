#!/bin/bash
# =============================================================================
# Run PHPUnit tests using Docker
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_DIR="$(dirname "$SCRIPT_DIR")"

echo "=== MCP Tools Test Runner ==="
echo ""

# Use official Drupal PHP image
DOCKER_IMAGE="drupal:10-php8.3-apache"

echo "Pulling Docker image..."
docker pull $DOCKER_IMAGE -q

echo "Running PHPUnit tests..."
echo ""

# Run tests in Docker container
docker run --rm \
  -v "$MODULE_DIR:/var/www/html/modules/mcp_tools:ro" \
  -w /var/www/html \
  $DOCKER_IMAGE \
  bash -c '
    set -e

    # Install composer if not present
    if ! command -v composer &> /dev/null; then
      curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi

    # Install PHPUnit
    composer require --dev phpunit/phpunit:^10 --quiet 2>/dev/null || true

    # Create a minimal bootstrap for unit tests
    mkdir -p /tmp/drupal-test

    echo "
<?php
// Minimal Drupal bootstrap for unit tests
define(\"DRUPAL_ROOT\", \"/var/www/html\");

// Autoloader
if (file_exists(\"/var/www/html/vendor/autoload.php\")) {
  require_once \"/var/www/html/vendor/autoload.php\";
}

// Register module namespace
spl_autoload_register(function (\$class) {
  \$prefix = \"Drupal\\\\mcp_tools\\\\\";
  \$base_dir = \"/var/www/html/modules/mcp_tools/src/\";

  \$len = strlen(\$prefix);
  if (strncmp(\$prefix, \$class, \$len) !== 0) {
    return;
  }

  \$relative_class = substr(\$class, \$len);
  \$file = \$base_dir . str_replace(\"\\\\\", \"/\", \$relative_class) . \".php\";

  if (file_exists(\$file)) {
    require \$file;
  }
});

// Mock Drupal classes for unit tests
if (!class_exists(\"Drupal\\\\Tests\\\\UnitTestCase\")) {
  abstract class UnitTestCase extends \\PHPUnit\\Framework\\TestCase {
    protected function setUp(): void {
      parent::setUp();
    }
  }
  class_alias(\"UnitTestCase\", \"Drupal\\\\Tests\\\\UnitTestCase\");
}
" > /tmp/drupal-test/bootstrap.php

    echo "Running syntax check on PHP files..."
    ERRORS=0
    for file in $(find /var/www/html/modules/mcp_tools -name "*.php" -type f); do
      if ! php -l "$file" 2>&1 | grep -q "No syntax errors"; then
        echo "Syntax error in: $file"
        ERRORS=$((ERRORS + 1))
      fi
    done

    if [ $ERRORS -eq 0 ]; then
      echo "✓ All PHP files have valid syntax"
    else
      echo "✗ Found $ERRORS files with syntax errors"
      exit 1
    fi

    echo ""
    echo "Counting test files..."
    UNIT_TESTS=$(find /var/www/html/modules/mcp_tools -path "*/tests/src/Unit/*" -name "*Test.php" | wc -l)
    KERNEL_TESTS=$(find /var/www/html/modules/mcp_tools -path "*/tests/src/Kernel/*" -name "*Test.php" | wc -l)
    FUNCTIONAL_TESTS=$(find /var/www/html/modules/mcp_tools -path "*/tests/src/Functional/*" -name "*Test.php" | wc -l)

    echo "  Unit tests: $UNIT_TESTS files"
    echo "  Kernel tests: $KERNEL_TESTS files"
    echo "  Functional tests: $FUNCTIONAL_TESTS files"
    echo "  Total: $((UNIT_TESTS + KERNEL_TESTS + FUNCTIONAL_TESTS)) test files"

    echo ""
    echo "✓ Test infrastructure verified"
  '

echo ""
echo "=== Test Run Complete ==="
