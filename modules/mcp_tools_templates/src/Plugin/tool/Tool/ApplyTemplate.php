<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Trait\WriteAccessTrait;
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
  id: 'mcp_templates_apply',
  label: new TranslatableMarkup('Apply Template'),
  description: new TranslatableMarkup('Apply a site configuration template to create content types, vocabularies, roles, and views. WARNING: This can make significant changes. Requires admin scope.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'template_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template ID'),
      description: new TranslatableMarkup('The template ID to apply (blog, portfolio, business, documentation).'),
      required: TRUE,
    ),
    'skip_existing' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Skip Existing'),
      description: new TranslatableMarkup('Skip components that already exist instead of failing. Default: true.'),
      required: FALSE,
    ),
    'components' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Components'),
      description: new TranslatableMarkup('Specific component types to apply (content_types, vocabularies, roles, views, media_types, webforms). Default: all.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'template' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template ID'),
      description: new TranslatableMarkup('The template that was applied.'),
    ),
    'created' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Created Components'),
      description: new TranslatableMarkup('List of components that were created.'),
    ),
    'skipped' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Skipped Components'),
      description: new TranslatableMarkup('List of components that were skipped (already exist).'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Any errors that occurred during application.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary message about the operation.'),
    ),
  ],
)]
class ApplyTemplate extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'templates';


  use WriteAccessTrait;

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
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    // Require admin scope for this operation.
    $accessDenied = $this->checkAdminAccess();
    if ($accessDenied) {
      return $accessDenied;
    }

    $templateId = $input['template_id'] ?? '';

    if (empty($templateId)) {
      return [
        'success' => FALSE,
        'error' => 'Template ID is required.',
      ];
    }

    // Build options array.
    $options = [];

    if (isset($input['skip_existing'])) {
      $options['skip_existing'] = (bool) $input['skip_existing'];
    }

    if (!empty($input['components'])) {
      // Handle both array and comma-separated string.
      $options['components'] = is_array($input['components'])
        ? $input['components']
        : array_map('trim', explode(',', $input['components']));
    }

    return $this->templateService->applyTemplate($templateId, $options);
  }

  

  

}
