<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\AsksFirstInterface;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_migration\Service\MigrationService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Undoes one import.
 */
#[Tool(
  id: 'mcp_migration_rollback',
  label: new TranslatableMarkup('Undo an import'),
  description: new TranslatableMarkup('Undo one import: delete the nodes it made. Nodes it only updated are kept as they are now. Other content is not touched.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'import_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Import ID'),
      description: new TranslatableMarkup('The import_id an import answered with. mcp_migration_import_status lists them.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'import_id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Import ID'),
      description: new TranslatableMarkup('The import undone.'),
    ),
    'deleted_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted'),
      description: new TranslatableMarkup('Nodes deleted.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('What was done.'),
    ),
  ],
)]
class RemoveImport extends McpToolsToolBase implements AsksFirstInterface {

  protected const MCP_CATEGORY = 'migration';

  /**
   * The migration service.
   */
  protected MigrationService $migrationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->migrationService = $container->get('mcp_tools_migration.migration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $importId = (string) ($input['import_id'] ?? '');
    if ($importId === '') {
      return ['success' => FALSE, 'error' => 'import_id is required.'];
    }
    return $this->migrationService->rollbackImport($importId);
  }

  /**
   * {@inheritdoc}
   */
  public function reasonToAsk(array $arguments): ?string {
    return sprintf("This deletes every node the import '%s' made.", (string) ($arguments['import_id'] ?? ''));
  }

}
