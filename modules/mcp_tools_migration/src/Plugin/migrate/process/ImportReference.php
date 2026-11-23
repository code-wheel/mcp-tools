<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * References to other rows of the same import, and to what is there.
 *
 * The source is two lists: keys of rows of this import (a term's parent
 * given in the same call) and ids there already. A key is looked up in the
 * import's own id map, so the row it names must have run first: the import
 * orders its rows that way. Core's migration_lookup takes one value, not a
 * list.
 */
#[MigrateProcess(id: 'mcp_import_reference', handle_multiples: TRUE)]
class ImportReference extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$keys, $ids] = array_pad((array) $value, 2, []);
    $targets = array_map('intval', (array) $ids);
    foreach ((array) $keys as $key) {
      $found = NULL;
      foreach ($this->migration->getIdMap()->lookupDestinationIds([(string) $key]) as $destination) {
        $found = reset($destination) ?: NULL;
      }
      if ($found === NULL) {
        throw new MigrateException(sprintf("%s: the row '%s' it refers to was not imported.", $destination_property, $key));
      }
      $targets[] = (int) $found;
    }
    return array_values(array_unique($targets));
  }

}
