<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_undo\Service\UndoService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the recent tool calls that changed something.
 */
#[Tool(
  id: 'mcp_undo_recent',
  label: new TranslatableMarkup('Recent changes by tool calls'),
  description: new TranslatableMarkup('The recent tool calls that changed something (kept 30 days by default), newest first, each with what it made, changed or deleted. mcp_undo_call undoes one.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('How many calls, at most (default 20).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'calls' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Calls'),
      description: new TranslatableMarkup('Each call: call_id, time, uid, undone, and its changes.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('A summary.'),
    ),
  ],
)]
class ListRecentChanges extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'undo';

  /**
   * The undo service.
   */
  protected UndoService $undoService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->undoService = $container->get('mcp_tools_undo.undo');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->undoService->recent(max(1, min(100, (int) ($input['limit'] ?? 20))));
  }

}
