<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting entity sitemap inclusion settings.
 *
 * @Tool(
 *   id = "mcp_sitemap_entity_settings",
 *   label = @Translation("Get Entity Sitemap Settings"),
 *   description = @Translation("Get sitemap inclusion settings for an entity type and optionally a specific bundle."),
 *   category = "sitemap",
 * )
 */
class GetEntitySettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected SitemapService $sitemapService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->sitemapService = $container->get('mcp_tools_sitemap.sitemap');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $entityType = $input['entity_type'] ?? '';
    $bundle = $input['bundle'] ?? NULL;

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    return $this->sitemapService->getEntitySettings($entityType, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type ID (e.g., "node", "taxonomy_term", "media").',
        'required' => TRUE,
      ],
      'bundle' => [
        'type' => 'string',
        'label' => 'Bundle',
        'description' => 'Optional: specific bundle to get settings for (e.g., "article", "page").',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'settings' => ['type' => 'object', 'label' => 'Sitemap Settings'],
      'bundles' => ['type' => 'object', 'label' => 'All Bundle Settings'],
    ];
  }

}
