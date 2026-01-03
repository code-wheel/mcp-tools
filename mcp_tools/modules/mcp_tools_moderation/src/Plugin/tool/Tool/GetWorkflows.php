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
  id: 'mcp_moderation_get_workflows',
  label: new TranslatableMarkup('Get Workflows'),
  description: new TranslatableMarkup('List all content moderation workflows with their states and transitions.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Workflows'),
      description: new TranslatableMarkup(''),
    ),
    'workflows' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Workflows'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetWorkflows extends McpToolsToolBase {

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
    return $this->moderationService->getWorkflows();
  }

  

  

}
