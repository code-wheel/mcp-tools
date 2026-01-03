<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Manager\SitemapManager;

/**
 * Service for Simple XML Sitemap operations.
 */
class SitemapService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected Generator $generator,
    protected SitemapManager $sitemapManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Get sitemap generation status.
   *
   * @return array
   *   Status information including last generated time and link counts.
   */
  public function getStatus(): array {
    try {
      $sitemaps = $this->sitemapManager->getSitemaps();
      $status = [];

      foreach ($sitemaps as $id => $sitemap) {
        $sitemapStatus = $sitemap->getStatus();
        $linkCount = $sitemap->getLinkCount();
        $chunkCount = $sitemap->getChunkCount();

        $status[$id] = [
          'id' => $id,
          'label' => $sitemap->label(),
          'status' => $sitemapStatus,
          'link_count' => $linkCount,
          'chunk_count' => $chunkCount,
          'is_enabled' => $sitemap->status(),
        ];

        // Get content status for each sitemap type.
        $content = $sitemap->getContent();
        if (!empty($content)) {
          $status[$id]['has_content'] = TRUE;
          $status[$id]['content_size'] = strlen(reset($content));
        }
        else {
          $status[$id]['has_content'] = FALSE;
        }
      }

      // Get overall generator status.
      $generatorStatus = $this->generator->getQueueStatus();

      return [
        'success' => TRUE,
        'data' => [
          'sitemaps' => $status,
          'total_sitemaps' => count($status),
          'generator_queue' => [
            'total' => $generatorStatus['total'] ?? 0,
            'processed' => $generatorStatus['processed'] ?? 0,
            'remaining' => ($generatorStatus['total'] ?? 0) - ($generatorStatus['processed'] ?? 0),
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to get sitemap status: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * List all sitemap variants.
   *
   * @return array
   *   List of sitemap variants with their configuration.
   */
  public function getSitemaps(): array {
    try {
      $sitemaps = $this->sitemapManager->getSitemaps();
      $result = [];

      foreach ($sitemaps as $id => $sitemap) {
        $result[] = [
          'id' => $id,
          'label' => $sitemap->label(),
          'is_default' => $id === 'default',
          'is_enabled' => $sitemap->status(),
          'type' => $sitemap->getType()->id(),
          'type_label' => $sitemap->getType()->label(),
          'link_count' => $sitemap->getLinkCount(),
          'chunk_count' => $sitemap->getChunkCount(),
        ];
      }

      return [
        'success' => TRUE,
        'data' => [
          'total' => count($result),
          'sitemaps' => $result,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to list sitemaps: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get settings for a specific sitemap variant.
   *
   * @param string $variant
   *   The sitemap variant ID.
   *
   * @return array
   *   Sitemap settings.
   */
  public function getSettings(string $variant = 'default'): array {
    try {
      $sitemap = $this->sitemapManager->getSitemap($variant);

      if (!$sitemap) {
        return [
          'success' => FALSE,
          'error' => "Sitemap variant '$variant' not found.",
        ];
      }

      // Get the sitemap configuration.
      $config = $this->configFactory->get('simple_sitemap.settings');

      $settings = [
        'variant' => $variant,
        'label' => $sitemap->label(),
        'type' => $sitemap->getType()->id(),
        'is_enabled' => $sitemap->status(),
        'global_settings' => [
          'max_links' => $config->get('max_links'),
          'cron_generate' => $config->get('cron_generate'),
          'cron_generate_interval' => $config->get('cron_generate_interval'),
          'remove_duplicates' => $config->get('remove_duplicates'),
          'skip_untranslated' => $config->get('skip_untranslated'),
          'base_url' => $config->get('base_url'),
          'xsl' => $config->get('xsl'),
        ],
      ];

      // Get enabled entity types for this variant.
      $bundleSettings = $this->generator->setVariants($variant)->getBundleSettings();
      $settings['entity_types'] = [];

      foreach ($bundleSettings as $entityTypeId => $bundles) {
        $settings['entity_types'][$entityTypeId] = [];
        foreach ($bundles as $bundleId => $bundleConfig) {
          $settings['entity_types'][$entityTypeId][$bundleId] = [
            'index' => $bundleConfig['index'] ?? FALSE,
            'priority' => $bundleConfig['priority'] ?? '0.5',
            'changefreq' => $bundleConfig['changefreq'] ?? '',
            'include_images' => $bundleConfig['include_images'] ?? FALSE,
          ];
        }
      }

      return [
        'success' => TRUE,
        'data' => $settings,
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to get sitemap settings: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Update settings for a sitemap variant.
   *
   * @param string $variant
   *   The sitemap variant ID.
   * @param array $settings
   *   Settings to update.
   *
   * @return array
   *   Result of the operation.
   */
  public function updateSettings(string $variant, array $settings): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      $sitemap = $this->sitemapManager->getSitemap($variant);

      if (!$sitemap) {
        return [
          'success' => FALSE,
          'error' => "Sitemap variant '$variant' not found.",
        ];
      }

      $updatedSettings = [];

      // Update global settings if provided.
      if (!empty($settings['global'])) {
        $config = $this->configFactory->getEditable('simple_sitemap.settings');
        $allowedGlobal = [
          'max_links',
          'cron_generate',
          'cron_generate_interval',
          'remove_duplicates',
          'skip_untranslated',
          'base_url',
          'xsl',
        ];

        foreach ($settings['global'] as $key => $value) {
          if (in_array($key, $allowedGlobal, TRUE)) {
            $config->set($key, $value);
            $updatedSettings['global'][$key] = $value;
          }
        }
        $config->save();
      }

      // Update sitemap enabled status.
      if (isset($settings['enabled'])) {
        $sitemap->setStatus((bool) $settings['enabled']);
        $sitemap->save();
        $updatedSettings['enabled'] = (bool) $settings['enabled'];
      }

      // Update sitemap label.
      if (isset($settings['label'])) {
        $sitemap->set('label', $settings['label']);
        $sitemap->save();
        $updatedSettings['label'] = $settings['label'];
      }

      $this->auditLogger->logSuccess('update_sitemap_settings', 'sitemap', $variant, [
        'updated_settings' => array_keys($updatedSettings),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'variant' => $variant,
          'updated_settings' => $updatedSettings,
          'message' => 'Sitemap settings updated successfully.',
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_sitemap_settings', 'sitemap', $variant, [
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'Failed to update sitemap settings: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Regenerate sitemap(s).
   *
   * @param string|null $variant
   *   Optional variant to regenerate. NULL for all.
   *
   * @return array
   *   Result of the operation.
   */
  public function regenerate(?string $variant = NULL): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      if ($variant) {
        $sitemap = $this->sitemapManager->getSitemap($variant);
        if (!$sitemap) {
          return [
            'success' => FALSE,
            'error' => "Sitemap variant '$variant' not found.",
          ];
        }
        $this->generator->setVariants($variant)->rebuildQueue()->generateSitemap();
        $message = "Sitemap '$variant' regeneration started.";
      }
      else {
        $this->generator->rebuildQueue()->generateSitemap();
        $message = 'All sitemap variants regeneration started.';
      }

      $this->auditLogger->logSuccess('regenerate_sitemap', 'sitemap', $variant ?? 'all', [
        'variant' => $variant ?? 'all',
      ]);

      // Get queue status after starting regeneration.
      $queueStatus = $this->generator->getQueueStatus();

      return [
        'success' => TRUE,
        'data' => [
          'variant' => $variant ?? 'all',
          'queue_status' => [
            'total' => $queueStatus['total'] ?? 0,
            'processed' => $queueStatus['processed'] ?? 0,
          ],
          'message' => $message,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('regenerate_sitemap', 'sitemap', $variant ?? 'all', [
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'Failed to regenerate sitemap: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get entity inclusion settings.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param string|null $bundle
   *   Optional bundle to filter.
   *
   * @return array
   *   Entity sitemap settings.
   */
  public function getEntitySettings(string $entityType, ?string $bundle = NULL): array {
    try {
      $bundleSettings = $this->generator->getBundleSettings();

      if (!isset($bundleSettings[$entityType])) {
        // Get available entity types.
        $availableTypes = array_keys($bundleSettings);
        return [
          'success' => FALSE,
          'error' => "Entity type '$entityType' is not configured for sitemap.",
          'available_entity_types' => $availableTypes,
        ];
      }

      $entitySettings = $bundleSettings[$entityType];

      if ($bundle) {
        if (!isset($entitySettings[$bundle])) {
          $availableBundles = array_keys($entitySettings);
          return [
            'success' => FALSE,
            'error' => "Bundle '$bundle' not found for entity type '$entityType'.",
            'available_bundles' => $availableBundles,
          ];
        }

        return [
          'success' => TRUE,
          'data' => [
            'entity_type' => $entityType,
            'bundle' => $bundle,
            'settings' => $entitySettings[$bundle],
          ],
        ];
      }

      // Return all bundles for this entity type.
      $result = [];
      foreach ($entitySettings as $bundleId => $bundleConfig) {
        $result[$bundleId] = [
          'bundle' => $bundleId,
          'index' => $bundleConfig['index'] ?? FALSE,
          'priority' => $bundleConfig['priority'] ?? '0.5',
          'changefreq' => $bundleConfig['changefreq'] ?? '',
          'include_images' => $bundleConfig['include_images'] ?? FALSE,
        ];
      }

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'total_bundles' => count($result),
          'bundles' => $result,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to get entity settings: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Set entity inclusion settings.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   * @param array $settings
   *   Settings to apply.
   *
   * @return array
   *   Result of the operation.
   */
  public function setEntitySettings(string $entityType, string $bundle, array $settings): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      // Validate entity type exists.
      $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType, FALSE);
      if (!$entityTypeDefinition) {
        return [
          'success' => FALSE,
          'error' => "Entity type '$entityType' does not exist.",
        ];
      }

      // Build settings array with defaults.
      $bundleSettings = [
        'index' => $settings['index'] ?? TRUE,
        'priority' => $settings['priority'] ?? '0.5',
        'changefreq' => $settings['changefreq'] ?? '',
        'include_images' => $settings['include_images'] ?? FALSE,
      ];

      // Validate priority.
      $validPriorities = ['0.0', '0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0'];
      if (!in_array($bundleSettings['priority'], $validPriorities, TRUE)) {
        return [
          'success' => FALSE,
          'error' => 'Invalid priority. Must be between 0.0 and 1.0.',
          'valid_priorities' => $validPriorities,
        ];
      }

      // Validate changefreq.
      $validChangefreq = ['', 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
      if (!in_array($bundleSettings['changefreq'], $validChangefreq, TRUE)) {
        return [
          'success' => FALSE,
          'error' => 'Invalid changefreq value.',
          'valid_changefreq' => $validChangefreq,
        ];
      }

      // Apply settings using the generator.
      $this->generator->setBundleSettings($entityType, $bundle, $bundleSettings);

      $this->auditLogger->logSuccess('set_entity_sitemap_settings', $entityType, $bundle, [
        'settings' => $bundleSettings,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'settings' => $bundleSettings,
          'message' => "Sitemap settings for $entityType:$bundle updated successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('set_entity_sitemap_settings', $entityType, $bundle, [
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'Failed to set entity settings: ' . $e->getMessage(),
      ];
    }
  }

}
