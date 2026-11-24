<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\AsksFirstInterface;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_undo\Service\UndoService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Undoes what one tool call changed.
 */
#[Tool(
  id: 'mcp_undo_call',
  label: new TranslatableMarkup('Undo a tool call'),
  description: new TranslatableMarkup('Undo what one tool call changed: content it made is deleted, content it changed gets its revision or values before, content it deleted is made again, and config gets its data before. Refused, with nothing undone, when anything the call changed was changed again since. mcp_undo_recent lists the recent calls.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'call_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Call ID'),
      description: new TranslatableMarkup('The call to undo, as mcp_undo_recent lists it.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'call_id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Call ID'),
      description: new TranslatableMarkup('The call undone.'),
    ),
    'undone_count' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Undone'),
      description: new TranslatableMarkup('How many changes were undone.'),
    ),
    'undone' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Changes undone'),
      description: new TranslatableMarkup('Each change: what it was (made, changed, deleted) and of what.'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('What was done.'),
    ),
  ],
)]
class UndoToolCall extends McpToolsToolBase implements AsksFirstInterface {

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
    $callId = trim((string) ($input['call_id'] ?? ''));
    if ($callId === '') {
      return ['success' => FALSE, 'error' => 'call_id is required. mcp_undo_recent lists the calls.'];
    }
    return $this->undoService->undo($callId);
  }

  /**
   * {@inheritdoc}
   */
  public function reasonToAsk(array $arguments): ?string {
    return sprintf("This undoes every change of the call '%s': content it made is deleted, and what it changed or deleted is put back.", (string) ($arguments['call_id'] ?? ''));
  }

}
