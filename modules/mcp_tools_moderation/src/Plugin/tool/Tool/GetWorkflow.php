<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Plugin\tool\Tool;

use Drupal\mcp_tools_moderation\Service\ModerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_moderation_get_workflow',
  label: new TranslatableMarkup('Get Workflow'),
  description: new TranslatableMarkup('Get details of a specific content moderation workflow including states and transitions.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow ID'),
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow ID'),
      description: new TranslatableMarkup(''),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflow Label'),
      description: new TranslatableMarkup(''),
    ),
    'states' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('States'),
      description: new TranslatableMarkup(''),
    ),
    'transitions' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Transitions'),
      description: new TranslatableMarkup(''),
    ),
    'entity_types' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Entity Types'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetWorkflow extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'moderation';


  protected ModerationService $moderationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moderationService = $container->get('mcp_tools_moderation.moderation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Workflow ID is required.'];
    }

    return $this->moderationService->getWorkflow($id);
  }

  

  

}
