<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Plugin\tool\Tool;

use Drupal\mcp_tools_templates\Service\TemplateService;
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
  id: 'mcp_templates_list',
  label: new TranslatableMarkup('List Templates'),
  description: new TranslatableMarkup('List all available built-in site configuration templates (blog, portfolio, business, documentation).'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'templates' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Available Templates'),
      description: new TranslatableMarkup('List of templates with id, label, description, category, and component summary.'),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Template Count'),
      description: new TranslatableMarkup('Total number of available templates.'),
    ),
  ],
)]
class ListTemplates extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'templates';


  /**
   * The template service.
   */
  protected TemplateService $templateService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->templateService = $container->get('mcp_tools_templates.template');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return $this->templateService->listTemplates();
  }

  

  

}
