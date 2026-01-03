<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_sitemap\Service\SitemapService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for setting entity sitemap inclusion settings.
 *
 * @Tool(
 *   id = "mcp_sitemap_set_entity",
 *   label = @Translation("Set Entity Sitemap Settings"),
 *   description = @Translation("Set sitemap inclusion settings for an entity type bundle. This is a write operation."),
 *   category = "sitemap",
 * )
 */
class SetEntitySettings extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $bundle = $input['bundle'] ?? '';
    $settings = $input['settings'] ?? [];

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    if (empty($bundle)) {
      return ['success' => FALSE, 'error' => 'Bundle is required.'];
    }

    if (empty($settings)) {
      return ['success' => FALSE, 'error' => 'Settings object is required.'];
    }

    return $this->sitemapService->setEntitySettings($entityType, $bundle, $settings);
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
        'description' => 'The bundle ID (e.g., "article", "page", "tags").',
        'required' => TRUE,
      ],
      'settings' => [
        'type' => 'object',
        'label' => 'Settings',
        'description' => 'Sitemap settings: index (bool), priority (0.0-1.0), changefreq (always/hourly/daily/weekly/monthly/yearly/never), include_images (bool).',
        'required' => TRUE,
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
      'settings' => ['type' => 'object', 'label' => 'Applied Settings'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
