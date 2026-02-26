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
  id: 'mcp_templates_get',
  label: new TranslatableMarkup('Get Template'),
  description: new TranslatableMarkup('Get detailed information about a specific template including all components it will create.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'template_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template ID'),
      description: new TranslatableMarkup('The template ID (blog, portfolio, business, documentation).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template ID'),
      description: new TranslatableMarkup('The template identifier.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable template name.'),
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('What this template provides.'),
    ),
    'category' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Category'),
      description: new TranslatableMarkup('Template category (Content, Corporate, Technical).'),
    ),
    'components' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Components'),
      description: new TranslatableMarkup('Detailed component definitions (content_types, vocabularies, roles, views, etc.).'),
    ),
    'component_summary' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Component Summary'),
      description: new TranslatableMarkup('Summary counts of components by type.'),
    ),
  ],
)]
class GetTemplate extends McpToolsToolBase {

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
    $templateId = $input['template_id'] ?? '';

    if (empty($templateId)) {
      return [
        'success' => FALSE,
        'error' => 'Template ID is required.',
      ];
    }

    return $this->templateService->getTemplate($templateId);
  }

}
