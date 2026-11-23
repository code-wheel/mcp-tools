<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_sitemap\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Queue\QueueWorker;

/**
 * Service for Simple XML Sitemap operations.
 */
class SitemapService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected Generator $generator,
    protected QueueWorker $queueWorker,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected ?LockBackendInterface $lock = NULL,
  ) {}

  /**
   * Get sitemap generation status.
   *
   * @return array
   *   Status information including last generated time and link counts.
   */
  public function getStatus(): array {
    try {
      $sitemaps = $this->loadSitemaps();
      $status = [];

      foreach ($sitemaps as $id => $sitemap) {
        $sitemapStatus = $sitemap->contentStatus();
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

        // Content presence: generated chunks or an index exist.
        $status[$id]['has_content'] = $chunkCount > 0 || $sitemap->hasIndex();
      }

      // Overall queue status from the queue worker.
      $queueTotal = (int) ($this->queueWorker->getInitialElementCount() ?? 0);
      $queueRemaining = (int) ($this->queueWorker->getQueuedElementCount() ?? 0);

      return [
        'success' => TRUE,
        'data' => [
          'sitemaps' => $status,
          'total_sitemaps' => count($status),
          'generator_queue' => [
            'total' => $queueTotal,
            'processed' => max(0, $queueTotal - $queueRemaining),
            'remaining' => $queueRemaining,
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
      $sitemaps = $this->loadSitemaps();
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
      $sitemap = $this->loadSitemap($variant);

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
      $allSettings = $this->generator->entityManager()->setSitemaps($sitemap)->getAllBundleSettings();
      $bundleSettings = $allSettings[$variant] ?? [];
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
      $sitemap = $this->loadSitemap($variant);

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
   * @param bool $continue
   *   Go on with what is queued, instead of queueing all again.
   *
   * @return array
   *   Result of the operation.
   */
  public function regenerate(?string $variant = NULL, bool $continue = FALSE): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }
    // Simple Sitemap skips a generation it cannot lock, without a word.
    if ($this->lock !== NULL && !$this->lock->lockMayBeAvailable(QueueWorker::LOCK_ID)) {
      return [
        'success' => FALSE,
        'error' => 'Another sitemap generation is running. Call again in a minute with continue: true.',
      ];
    }

    try {
      $sitemap = NULL;
      if ($variant) {
        $sitemap = $this->loadSitemap($variant);
        if (!$sitemap) {
          return [
            'success' => FALSE,
            'error' => "Sitemap variant '$variant' not found.",
          ];
        }
      }
      // Go on with what is queued, or start over.
      $queued = (int) ($this->queueWorker->getQueuedElementCount(TRUE) ?? 0);
      $sitemap ? $this->generator->setSitemaps($sitemap) : $this->generator->setSitemaps();
      if (!$continue || $queued === 0) {
        $this->generator->rebuildQueue();
      }
      // The backend mode generates for a bounded time in this request. The
      // form mode, used before, only set up a batch nothing ran: the tool
      // said "started" and no sitemap changed.
      $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

      $this->auditLogger->logSuccess('regenerate_sitemap', 'sitemap', $variant ?? 'all', [
        'variant' => $variant ?? 'all',
      ]);

      $total = (int) ($this->queueWorker->getInitialElementCount() ?? 0);
      $remaining = (int) ($this->queueWorker->getQueuedElementCount(TRUE) ?? 0);
      $done = $remaining === 0;

      return [
        'success' => TRUE,
        'data' => [
          'variant' => $variant ?? 'all',
          'queue_status' => [
            'total' => $total,
            'processed' => max(0, $total - $remaining),
          ],
          'remaining' => $remaining,
          'done' => $done,
          'message' => $done
            ? sprintf('Sitemap %s generated: %d elements.', $variant ? "'$variant'" : 'variants', $total)
            : sprintf('Part generated: %d of %d elements are left. Call again with continue: true until done is true.', $remaining, $total),
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
      $allSettings = $this->generator->entityManager()->setSitemaps()->getAllBundleSettings();
      $bundleSettings = $allSettings ? (array) reset($allSettings) : [];

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

      // Only what was given: the rest stays as it is. Defaults filled in for
      // what was not given turned indexing on and reset the priority.
      $bundleSettings = array_intersect_key($settings, array_flip(['index', 'priority', 'changefreq', 'include_images']));
      if ($bundleSettings === []) {
        return ['success' => FALSE, 'error' => 'Give at least one of index, priority, changefreq, include_images.'];
      }

      // Validate priority.
      $validPriorities = ['0.0', '0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0'];
      if (isset($bundleSettings['priority'])) {
        $bundleSettings['priority'] = number_format((float) $bundleSettings['priority'], 1, '.', '');
        if (!in_array($bundleSettings['priority'], $validPriorities, TRUE)) {
          return [
            'success' => FALSE,
            'error' => 'Invalid priority. Must be between 0.0 and 1.0.',
            'valid_priorities' => $validPriorities,
          ];
        }
      }

      // Validate changefreq.
      $validChangefreq = ['', 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
      if (isset($bundleSettings['changefreq']) && !in_array($bundleSettings['changefreq'], $validChangefreq, TRUE)) {
        return [
          'success' => FALSE,
          'error' => 'Invalid changefreq value.',
          'valid_changefreq' => $validChangefreq,
        ];
      }

      // Simple Sitemap does nothing, without a word, for a type it does not
      // index, and does not check the bundle.
      $entityManager = $this->generator->entityManager();
      if ($entityManager->setSitemaps()->getBundleSettings($entityType, $bundle) === []) {
        return ['success' => FALSE, 'error' => "Simple Sitemap does not index $entityType."];
      }
      $bundleEntityType = $entityTypeDefinition->getBundleEntityType();
      if ($bundleEntityType !== NULL && !$this->entityTypeManager->getStorage($bundleEntityType)->load($bundle)) {
        return ['success' => FALSE, 'error' => "$entityType has no bundle '$bundle'."];
      }

      // Every variant: Simple Sitemap writes only the first it is given.
      $applied = [];
      foreach ($this->loadSitemaps() as $variant => $sitemap) {
        $entityManager->setSitemaps($sitemap)->setBundleSettings($entityType, $bundle, $bundleSettings);
        $applied[$variant] = $entityManager->getBundleSettings($entityType, $bundle)[$variant] ?? NULL;
      }
      $entityManager->setSitemaps();

      $this->auditLogger->logSuccess('set_entity_sitemap_settings', $entityType, $bundle, [
        'settings' => $bundleSettings,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'settings' => $bundleSettings,
          'variants' => $applied,
          'message' => sprintf('Sitemap settings for %s:%s updated in %d variant(s): %s.', $entityType, $bundle, count($applied), implode(', ', array_keys($applied))),
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

  /**
   * Loads all sitemap variant entities.
   *
   * Simple_sitemap >= 4.2 removed the SitemapManager service; variants are
   * SimpleSitemap config entities.
   *
   * @return array<string, \Drupal\simple_sitemap\Entity\SimpleSitemapInterface>
   *   Sitemap entities keyed by id.
   */
  protected function loadSitemaps(): array {
    return $this->entityTypeManager->getStorage('simple_sitemap')->loadMultiple();
  }

  /**
   * Loads a single sitemap variant entity.
   *
   * @param string $variant
   *   The variant id.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapInterface|null
   *   The sitemap entity, or NULL.
   */
  protected function loadSitemap(string $variant): ?object {
    $sitemap = $this->entityTypeManager->getStorage('simple_sitemap')->load($variant);
    return $sitemap ?: NULL;
  }

}
