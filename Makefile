# MCP Tools - Development Makefile
#
# Usage:
#   make up          # Start Docker environment
#   make setup       # First-time Drupal setup
#   make test        # Run all tests
#   make shell       # Open shell in container
#   make down        # Stop everything

.PHONY: up down setup shell test test-unit test-kernel lint cs help

# Default target
help:
	@echo "MCP Tools Development Commands"
	@echo ""
	@echo "Docker:"
	@echo "  make up        - Start Docker environment"
	@echo "  make down      - Stop Docker environment"
	@echo "  make setup     - First-time Drupal setup"
	@echo "  make shell     - Open bash in Drupal container"
	@echo "  make logs      - Follow container logs"
	@echo ""
	@echo "Testing:"
	@echo "  make test      - Run all tests"
	@echo "  make test-unit - Run unit tests only"
	@echo "  make test-kernel - Run kernel tests only"
	@echo ""
	@echo "Code Quality:"
	@echo "  make lint      - Run PHP linter"
	@echo "  make cs        - Run coding standards check"
	@echo "  make cs-fix    - Fix coding standards issues"
	@echo ""
	@echo "Drupal:"
	@echo "  make drush     - Run drush (use: make drush CMD='status')"
	@echo "  make cr        - Clear Drupal caches"
	@echo "  make en        - Enable submodules (use: make en MOD='mcp_tools_content')"
	@echo ""

# Docker commands
up:
	docker compose up -d
	@echo ""
	@echo "Drupal starting at http://localhost:8080"
	@echo "Run 'make setup' for first-time installation"

down:
	docker compose down

setup:
	./scripts/docker-setup.sh

shell:
	docker compose exec drupal bash

logs:
	docker compose logs -f drupal

# Testing commands
test:
	docker compose exec drupal ./vendor/bin/phpunit \
		-c modules/custom/mcp_tools/phpunit.xml \
		modules/custom/mcp_tools/tests/

test-unit:
	docker compose exec drupal ./vendor/bin/phpunit \
		-c modules/custom/mcp_tools/phpunit.xml \
		modules/custom/mcp_tools/tests/src/Unit/

test-kernel:
	docker compose exec drupal ./vendor/bin/phpunit \
		-c modules/custom/mcp_tools/phpunit.xml \
		modules/custom/mcp_tools/tests/src/Kernel/

# Code quality
lint:
	docker compose exec drupal find modules/custom/mcp_tools -name "*.php" \
		-not -path "*/vendor/*" \
		-exec php -l {} \;

cs:
	docker compose exec drupal ./vendor/bin/phpcs \
		--standard=Drupal,DrupalPractice \
		--extensions=php,module,inc,install,test,profile,theme \
		modules/custom/mcp_tools/src modules/custom/mcp_tools/modules/*/src

cs-fix:
	docker compose exec drupal ./vendor/bin/phpcbf \
		--standard=Drupal,DrupalPractice \
		--extensions=php,module,inc,install,test,profile,theme \
		modules/custom/mcp_tools/src modules/custom/mcp_tools/modules/*/src

# Drupal commands
drush:
	docker compose exec drupal ./vendor/bin/drush $(CMD)

cr:
	docker compose exec drupal ./vendor/bin/drush cr

en:
	docker compose exec drupal ./vendor/bin/drush en $(MOD) -y
