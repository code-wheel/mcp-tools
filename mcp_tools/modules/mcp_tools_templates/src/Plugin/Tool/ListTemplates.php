<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_templates\Service\TemplateService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing available site configuration templates.
 *
 * @Tool(
 *   id = "mcp_templates_list",
 *   label = @Translation("List Templates"),
 *   description = @Translation("List all available built-in site configuration templates (blog, portfolio, business, documentation)."),
 *   category = "templates",
 * )
 */
class ListTemplates extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->templateService->listTemplates();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    // No inputs required for listing templates.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'templates' => [
        'type' => 'array',
        'label' => 'Available Templates',
        'description' => 'List of templates with id, label, description, category, and component summary.',
      ],
      'count' => [
        'type' => 'integer',
        'label' => 'Template Count',
        'description' => 'Total number of available templates.',
      ],
    ];
  }

}
