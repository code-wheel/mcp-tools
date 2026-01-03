<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_templates\Service\TemplateService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting detailed information about a specific template.
 *
 * @Tool(
 *   id = "mcp_templates_get",
 *   label = @Translation("Get Template"),
 *   description = @Translation("Get detailed information about a specific template including all components it will create."),
 *   category = "templates",
 * )
 */
class GetTemplate extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->templateService->getTemplate($templateId);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'template_id' => [
        'type' => 'string',
        'label' => 'Template ID',
        'description' => 'The template ID (blog, portfolio, business, documentation).',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Template ID',
        'description' => 'The template identifier.',
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Label',
        'description' => 'Human-readable template name.',
      ],
      'description' => [
        'type' => 'string',
        'label' => 'Description',
        'description' => 'What this template provides.',
      ],
      'category' => [
        'type' => 'string',
        'label' => 'Category',
        'description' => 'Template category (Content, Corporate, Technical).',
      ],
      'components' => [
        'type' => 'object',
        'label' => 'Components',
        'description' => 'Detailed component definitions (content_types, vocabularies, roles, views, etc.).',
      ],
      'component_summary' => [
        'type' => 'object',
        'label' => 'Component Summary',
        'description' => 'Summary counts of components by type.',
      ],
    ];
  }

}
