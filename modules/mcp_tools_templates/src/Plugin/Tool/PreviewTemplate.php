<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_templates\Service\TemplateService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for previewing what a template would create (dry-run).
 *
 * @Tool(
 *   id = "mcp_templates_preview",
 *   label = @Translation("Preview Template"),
 *   description = @Translation("Preview what components would be created by applying a template (dry-run). Shows what will be created vs skipped."),
 *   category = "templates",
 * )
 */
class PreviewTemplate extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $templateId = $input['template_id'] ?? '';

    if (empty($templateId)) {
      return [
        'success' => FALSE,
        'error' => 'Template ID is required.',
      ];
    }

    return $this->templateService->previewTemplate($templateId);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'template_id' => [
        'type' => 'string',
        'label' => 'Template ID',
        'description' => 'The template ID to preview (blog, portfolio, business, documentation).',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'template_id' => [
        'type' => 'string',
        'label' => 'Template ID',
        'description' => 'The template being previewed.',
      ],
      'template_label' => [
        'type' => 'string',
        'label' => 'Template Label',
        'description' => 'Human-readable template name.',
      ],
      'will_create' => [
        'type' => 'array',
        'label' => 'Will Create',
        'description' => 'Components that will be created (do not exist yet).',
      ],
      'will_skip' => [
        'type' => 'array',
        'label' => 'Will Skip',
        'description' => 'Components that will be skipped (already exist).',
      ],
      'conflicts' => [
        'type' => 'array',
        'label' => 'Conflicts',
        'description' => 'Any conflicts or issues detected (e.g., missing modules).',
      ],
    ];
  }

}
