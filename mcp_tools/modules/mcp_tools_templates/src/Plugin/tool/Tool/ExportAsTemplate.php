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
  id: 'mcp_templates_export',
  label: new TranslatableMarkup('Export as Template'),
  description: new TranslatableMarkup('Export current content types, vocabularies, and roles as a custom template definition. Requires admin scope.'),
  operation: ToolOperation::Trigger,
  input_definitions: [
    'name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template Name'),
      description: new TranslatableMarkup('Machine name for the exported template (lowercase letters, numbers, underscores).'),
      required: TRUE,
    ),
    'content_types' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content Types'),
      description: new TranslatableMarkup('Array or comma-separated list of content type machine names to include.'),
      required: FALSE,
    ),
    'vocabularies' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Vocabularies'),
      description: new TranslatableMarkup('Array or comma-separated list of vocabulary machine names to include.'),
      required: FALSE,
    ),
    'roles' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup('Array or comma-separated list of role machine names to include (excludes built-in roles).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'template' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Template Definition'),
      description: new TranslatableMarkup('The exported template definition that can be used to recreate these components.'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Any warnings or errors during export.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary message about the export.'),
    ),
  ],
)]
class ExportAsTemplate extends McpToolsToolBase {

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

    $name = $input['name'] ?? '';

    if (empty($name)) {
      return [
        'success' => FALSE,
        'error' => 'Template name is required.',
      ];
    }

    // Parse content types.
    $contentTypes = [];
    if (!empty($input['content_types'])) {
      $contentTypes = is_array($input['content_types'])
        ? $input['content_types']
        : array_map('trim', explode(',', $input['content_types']));
    }

    // Parse vocabularies.
    $vocabularies = [];
    if (!empty($input['vocabularies'])) {
      $vocabularies = is_array($input['vocabularies'])
        ? $input['vocabularies']
        : array_map('trim', explode(',', $input['vocabularies']));
    }

    // Parse roles.
    $roles = [];
    if (!empty($input['roles'])) {
      $roles = is_array($input['roles'])
        ? $input['roles']
        : array_map('trim', explode(',', $input['roles']));
    }

    if (empty($contentTypes) && empty($vocabularies) && empty($roles)) {
      return [
        'success' => FALSE,
        'error' => 'At least one of content_types, vocabularies, or roles must be specified.',
      ];
    }

    return $this->templateService->exportAsTemplate($name, $contentTypes, $vocabularies, $roles);
  }

  

  

}
