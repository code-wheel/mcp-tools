<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\mcp_tools\Service\ClientConfigGenerator;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for ClientConfigGenerator service.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\ClientConfigGenerator::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class ClientConfigGeneratorTest extends UnitTestCase {

  protected ClientConfigGenerator $generator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->generator = new ClientConfigGenerator();
  }

  /**
   * Tests DDEV environment config generation.
   */
  public function testDdevConfig(): void {
    $config = $this->generator->buildConfig('/var/www/html/web', TRUE, FALSE);

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('ddev', $server['command']);
    $this->assertSame('/var/www/html', $server['cwd']);
    $this->assertSame([
      'drush',
      'mcp-tools:serve',
      '--quiet',
      '--uid=1',
      '--scope=read,write',
    ], $server['args']);
  }

  /**
   * Tests Lando environment config generation.
   */
  public function testLandoConfig(): void {
    $config = $this->generator->buildConfig('/app/web', FALSE, TRUE);

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('lando', $server['command']);
    $this->assertSame('/app', $server['cwd']);
    $this->assertSame([
      'drush',
      'mcp-tools:serve',
      '--quiet',
      '--uid=1',
      '--scope=read,write',
    ], $server['args']);
  }

  /**
   * Tests bare-metal environment config generation.
   */
  public function testBareMetalConfig(): void {
    $config = $this->generator->buildConfig('/var/www/drupal', FALSE, FALSE);

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('/var/www/drupal/vendor/bin/drush', $server['command']);
    $this->assertSame('/var/www/drupal', $server['cwd']);
    $this->assertSame([
      'mcp-tools:serve',
      '--quiet',
      '--uid=1',
      '--scope=read,write',
    ], $server['args']);
  }

  /**
   * Tests custom scope option.
   */
  public function testCustomScope(): void {
    $config = $this->generator->buildConfig('/var/www/drupal', FALSE, FALSE, 'read');

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('--scope=read', $server['args'][3]);
  }

  /**
   * Tests custom uid option.
   */
  public function testCustomUid(): void {
    $config = $this->generator->buildConfig('/var/www/drupal', FALSE, FALSE, 'read,write', '42');

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('--uid=42', $server['args'][2]);
  }

  /**
   * Tests DDEV takes precedence when both DDEV and Lando are set.
   */
  public function testDdevTakesPrecedenceOverLando(): void {
    $config = $this->generator->buildConfig('/var/www/html/web', TRUE, TRUE);

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('ddev', $server['command']);
  }

  /**
   * Tests DDEV config with custom scope and uid.
   */
  public function testDdevCustomOptions(): void {
    $config = $this->generator->buildConfig('/var/www/html/web', TRUE, FALSE, 'read', '5');

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('ddev', $server['command']);
    $this->assertSame('--uid=5', $server['args'][3]);
    $this->assertSame('--scope=read', $server['args'][4]);
  }

  /**
   * Tests Lando config with custom scope and uid.
   */
  public function testLandoCustomOptions(): void {
    $config = $this->generator->buildConfig('/app/web', FALSE, TRUE, 'admin', '99');

    $server = $config['mcpServers']['drupal'];
    $this->assertSame('lando', $server['command']);
    $this->assertSame('--uid=99', $server['args'][3]);
    $this->assertSame('--scope=admin', $server['args'][4]);
  }

  /**
   * Tests the output always has the mcpServers.drupal structure.
   */
  public function testOutputStructure(): void {
    foreach ([
      ['/var/www/html/web', TRUE, FALSE],
      ['/app/web', FALSE, TRUE],
      ['/var/www/drupal', FALSE, FALSE],
    ] as [$root, $ddev, $lando]) {
      $config = $this->generator->buildConfig($root, $ddev, $lando);

      $this->assertArrayHasKey('mcpServers', $config);
      $this->assertArrayHasKey('drupal', $config['mcpServers']);
      $this->assertArrayHasKey('command', $config['mcpServers']['drupal']);
      $this->assertArrayHasKey('args', $config['mcpServers']['drupal']);
      $this->assertArrayHasKey('cwd', $config['mcpServers']['drupal']);
    }
  }

  /**
   * Tests that bare-metal args do NOT include 'drush' prefix.
   *
   * DDEV/Lando wrap drush via their CLI, so args include 'drush'.
   * Bare metal invokes drush directly, so args should not.
   */
  public function testBareMetalArgsOmitDrushPrefix(): void {
    $config = $this->generator->buildConfig('/var/www/drupal', FALSE, FALSE);

    $args = $config['mcpServers']['drupal']['args'];
    $this->assertSame('mcp-tools:serve', $args[0]);
  }

  /**
   * Tests that DDEV/Lando args include 'drush' prefix.
   */
  public function testContainerArgsIncludeDrushPrefix(): void {
    $ddev = $this->generator->buildConfig('/var/www/html/web', TRUE, FALSE);
    $this->assertSame('drush', $ddev['mcpServers']['drupal']['args'][0]);

    $lando = $this->generator->buildConfig('/app/web', FALSE, TRUE);
    $this->assertSame('drush', $lando['mcpServers']['drupal']['args'][0]);
  }

  /**
   * Tests JSON serializability of the output.
   */
  public function testJsonSerializable(): void {
    $config = $this->generator->buildConfig('/var/www/drupal', FALSE, FALSE);

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $this->assertNotFalse($json);
    $this->assertJson($json);

    $decoded = json_decode($json, TRUE);
    $this->assertSame($config, $decoded);
  }

}
