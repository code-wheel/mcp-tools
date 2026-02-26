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
  id: 'mcp_templates_preview',
  label: new TranslatableMarkup('Preview Template'),
  description: new TranslatableMarkup('Preview what components would be created by applying a template (dry-run). Shows what will be created vs skipped.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'template_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template ID'),
      description: new TranslatableMarkup('The template ID to preview (blog, portfolio, business, documentation).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'template_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template ID'),
      description: new TranslatableMarkup('The template being previewed.'),
    ),
    'template_label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template Label'),
      description: new TranslatableMarkup('Human-readable template name.'),
    ),
    'will_create' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Will Create'),
      description: new TranslatableMarkup('Components that will be created (do not exist yet).'),
    ),
    'will_skip' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Will Skip'),
      description: new TranslatableMarkup('Components that will be skipped (already exist).'),
    ),
    'conflicts' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Conflicts'),
      description: new TranslatableMarkup('Any conflicts or issues detected (e.g., missing modules).'),
    ),
  ],
)]
class PreviewTemplate extends McpToolsToolBase {

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

    return $this->templateService->previewTemplate($templateId);
  }

}
