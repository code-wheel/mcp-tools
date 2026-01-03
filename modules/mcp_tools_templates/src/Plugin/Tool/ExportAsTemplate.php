<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Trait\WriteAccessTrait;
use Drupal\mcp_tools_templates\Service\TemplateService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for exporting current site configuration as a custom template.
 *
 * @Tool(
 *   id = "mcp_templates_export",
 *   label = @Translation("Export as Template"),
 *   description = @Translation("Export current content types, vocabularies, and roles as a custom template definition. Requires admin scope."),
 *   category = "templates",
 * )
 */
class ExportAsTemplate extends ToolPluginBase implements ContainerFactoryPluginInterface {

  use WriteAccessTrait;

  /**
   * The template service.
   */
  protected TemplateService $templateService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->templateService = $container->get('mcp_tools_templates.template');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'name' => [
        'type' => 'string',
        'label' => 'Template Name',
        'description' => 'Machine name for the exported template (lowercase letters, numbers, underscores).',
        'required' => TRUE,
      ],
      'content_types' => [
        'type' => 'array',
        'label' => 'Content Types',
        'description' => 'Array or comma-separated list of content type machine names to include.',
        'required' => FALSE,
      ],
      'vocabularies' => [
        'type' => 'array',
        'label' => 'Vocabularies',
        'description' => 'Array or comma-separated list of vocabulary machine names to include.',
        'required' => FALSE,
      ],
      'roles' => [
        'type' => 'array',
        'label' => 'Roles',
        'description' => 'Array or comma-separated list of role machine names to include (excludes built-in roles).',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'template' => [
        'type' => 'object',
        'label' => 'Template Definition',
        'description' => 'The exported template definition that can be used to recreate these components.',
      ],
      'errors' => [
        'type' => 'array',
        'label' => 'Errors',
        'description' => 'Any warnings or errors during export.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Result Message',
        'description' => 'Summary message about the export.',
      ],
    ];
  }

}
