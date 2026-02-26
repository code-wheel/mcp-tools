<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Generates MCP client configuration JSON for AI editors.
 *
 * Detects DDEV, Lando, or bare-metal environments and builds the
 * appropriate mcpServers config array.
 */
class ClientConfigGenerator {

  /**
   * Build MCP client configuration for the detected environment.
   *
   * @param string $drupalRoot
   *   Absolute path to the Drupal web root.
   * @param bool $isDdev
   *   Whether the environment is DDEV.
   * @param bool $isLando
   *   Whether the environment is Lando.
   * @param string $scope
   *   Comma-separated scopes (e.g. "read,write").
   * @param string $uid
   *   Drupal user ID for tool execution.
   *
   * @return array<string, mixed>
   *   The mcpServers configuration array.
   */
  public function buildConfig(string $drupalRoot, bool $isDdev, bool $isLando, string $scope = 'read,write', string $uid = '1'): array {
    if ($isDdev) {
      return $this->buildDdevConfig($drupalRoot, $scope, $uid);
    }

    if ($isLando) {
      return $this->buildLandoConfig($drupalRoot, $scope, $uid);
    }

    return $this->buildBareMetalConfig($drupalRoot, $scope, $uid);
  }

  /**
   * Build config for a DDEV environment.
   */
  private function buildDdevConfig(string $drupalRoot, string $scope, string $uid): array {
    return [
      'mcpServers' => [
        'drupal' => [
          'command' => 'ddev',
          'args' => $this->buildArgs($scope, $uid),
          'cwd' => $this->resolveProjectRoot($drupalRoot),
        ],
      ],
    ];
  }

  /**
   * Build config for a Lando environment.
   */
  private function buildLandoConfig(string $drupalRoot, string $scope, string $uid): array {
    return [
      'mcpServers' => [
        'drupal' => [
          'command' => 'lando',
          'args' => $this->buildArgs($scope, $uid),
          'cwd' => $this->resolveProjectRoot($drupalRoot),
        ],
      ],
    ];
  }

  /**
   * Build config for a bare-metal / native environment.
   */
  private function buildBareMetalConfig(string $drupalRoot, string $scope, string $uid): array {
    $drushPath = $drupalRoot . '/vendor/bin/drush';
    return [
      'mcpServers' => [
        'drupal' => [
          'command' => $drushPath,
          'args' => [
            'mcp-tools:serve',
            '--quiet',
            "--uid={$uid}",
            "--scope={$scope}",
          ],
          'cwd' => $drupalRoot,
        ],
      ],
    ];
  }

  /**
   * Build the args array for DDEV/Lando wrappers.
   *
   * @return string[]
   */
  private function buildArgs(string $scope, string $uid): array {
    return [
      'drush',
      'mcp-tools:serve',
      '--quiet',
      "--uid={$uid}",
      "--scope={$scope}",
    ];
  }

  /**
   * Resolve the project root from the Drupal web root.
   *
   * For DDEV/Lando the project root is typically one level above the
   * Drupal web root (e.g. web/ or docroot/). If the Drupal root itself
   * is the project root, return it directly.
   */
  private function resolveProjectRoot(string $drupalRoot): string {
    $projectRoot = dirname($drupalRoot);
    if (basename($drupalRoot) === $drupalRoot) {
      $projectRoot = $drupalRoot;
    }
    return $projectRoot;
  }

}
