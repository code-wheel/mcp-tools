<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * A media type made the way Drupal's media type form makes one.
 *
 * The type, its source field, the source configuration, and the form and
 * view displays the source prepares. The template tool made the type alone
 * (no source field: nobody could add media of it), and the media tool
 * skipped the displays (the add form had no file widget).
 */
final class MediaTypeBuilder {

  /**
   * Makes and saves the media type.
   *
   * @throws \Exception
   *   When a step fails; the type is deleted again, so a retry can make it.
   */
  public static function create(EntityTypeManagerInterface $entityTypeManager, ?EntityDisplayRepositoryInterface $displayRepository, string $id, string $label, string $source, string $description = ''): EntityInterface {
    $mediaType = $entityTypeManager->getStorage('media_type')->create([
      'id' => $id,
      'label' => $label,
      'description' => $description !== '' ? $description : NULL,
      'source' => $source,
    ]);
    $mediaType->save();
    try {
      $plugin = $mediaType->getSource();
      $sourceField = $plugin->createSourceField($mediaType);
      $sourceField->getFieldStorageDefinition()->save();
      $sourceField->save();
      $mediaType->set('source_configuration', ['source_field' => $sourceField->getName()]);
      $mediaType->save();
      if ($displayRepository !== NULL) {
        $form = $displayRepository->getFormDisplay('media', $id);
        $plugin->prepareFormDisplay($mediaType, $form);
        $form->save();
        $view = $displayRepository->getViewDisplay('media', $id);
        $plugin->prepareViewDisplay($mediaType, $view);
        $view->save();
      }
    }
    catch (\Exception $e) {
      // Half a type was left behind, and a retry was "already exists".
      $mediaType->delete();
      throw $e;
    }
    return $mediaType;
  }

}
