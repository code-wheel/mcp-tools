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
 * Tool for applying a site configuration template.
 *
 * This is a potentially significant operation that creates content types,
 * vocabularies, roles, views, and other site components. Requires admin scope.
 *
 * @Tool(
 *   id = "mcp_templates_apply",
 *   label = @Translation("Apply Template"),
 *   description = @Translation("Apply a site configuration template to create content types, vocabularies, roles, and views. WARNING: This can make significant changes. Requires admin scope."),
 *   category = "templates",
 * )
 */
class ApplyTemplate extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'template_id' => [
        'type' => 'string',
        'label' => 'Template ID',
        'description' => 'The template ID to apply (blog, portfolio, business, documentation).',
        'required' => TRUE,
      ],
      'skip_existing' => [
        'type' => 'boolean',
        'label' => 'Skip Existing',
        'description' => 'Skip components that already exist instead of failing. Default: true.',
        'required' => FALSE,
      ],
      'components' => [
        'type' => 'array',
        'label' => 'Components',
        'description' => 'Specific component types to apply (content_types, vocabularies, roles, views, media_types, webforms). Default: all.',
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
        'type' => 'string',
        'label' => 'Template ID',
        'description' => 'The template that was applied.',
      ],
      'created' => [
        'type' => 'array',
        'label' => 'Created Components',
        'description' => 'List of components that were created.',
      ],
      'skipped' => [
        'type' => 'array',
        'label' => 'Skipped Components',
        'description' => 'List of components that were skipped (already exist).',
      ],
      'errors' => [
        'type' => 'array',
        'label' => 'Errors',
        'description' => 'Any errors that occurred during application.',
      ],
      'message' => [
        'type' => 'string',
        'label' => 'Result Message',
        'description' => 'Summary message about the operation.',
      ],
    ];
  }

}
