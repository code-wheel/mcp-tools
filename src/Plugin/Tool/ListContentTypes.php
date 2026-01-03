<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\ContentAnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing content types.
 *
 * @Tool(
 *   id = "mcp_tools_list_content_types",
 *   label = @Translation("List Content Types"),
 *   description = @Translation("List all content types on the Drupal site with their fields and configuration."),
 *   category = "content",
 * )
 */
class ListContentTypes extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The content analysis service.
   */
  protected ContentAnalysisService $contentAnalysis;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->contentAnalysis = $container->get('mcp_tools.content_analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->contentAnalysis->getContentTypes(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_types' => [
        'type' => 'integer',
        'label' => 'Total Content Types',
      ],
      'types' => [
        'type' => 'list',
        'label' => 'Content Types',
      ],
    ];
  }

}
