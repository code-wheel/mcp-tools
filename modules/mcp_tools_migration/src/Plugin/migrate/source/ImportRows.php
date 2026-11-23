<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\migrate\source;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_migration\Service\MigrateImport;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rows of an MCP Tools import, kept apart from its configuration.
 *
 * Core's embedded_data keeps the rows in the source configuration, and
 * Migrate hashes each row with that configuration: one changed row changed
 * the hash of every row, and every row ran again. Here the configuration
 * names the import only, and the rows are kept in key-value storage.
 *
 * @MigrateSource(
 *   id = "mcp_import_rows",
 *   source_module = "mcp_tools_migration"
 * )
 */
class ImportRows extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The rows, keyed by their key value.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $rows;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, KeyValueFactoryInterface $keyValue) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->rows = (array) $keyValue->get(MigrateImport::ROWS)->get((string) ($configuration['import'] ?? ''), []);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static($configuration, $plugin_id, $plugin_definition, $migration, $container->get('keyvalue'));
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $first = reset($this->rows);
    $names = $first ? array_keys($first) : [];
    return array_combine($names, $names);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    return new \ArrayIterator(array_values($this->rows));
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return $this->configuration['ids'];
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE): int {
    return count($this->rows);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'MCP Tools import rows';
  }

}
