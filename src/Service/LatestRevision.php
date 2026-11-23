<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;

/**
 * The newest revision of an entity: the one an editor works on.
 *
 * The tools loaded the published (default) revision. With a newer draft
 * waiting, "publish" answered "already published" and left the draft, and
 * an edit was saved on top of the published text and buried the draft.
 * Drupal's moderation form and its "Latest version" tab use the newest.
 */
final class LatestRevision {

  /**
   * The newest revision, or the entity where it has no revisions, or NULL.
   */
  public static function load(EntityTypeManagerInterface $entityTypeManager, string $entityTypeId, int|string $id): ?EntityInterface {
    $storage = $entityTypeManager->getStorage($entityTypeId);
    if ($storage instanceof RevisionableStorageInterface) {
      $revisionId = $storage->getLatestRevisionId($id);
      if ($revisionId !== NULL) {
        return $storage->loadRevision($revisionId);
      }
    }
    return $storage->load($id);
  }

}
