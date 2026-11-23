<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\LatestRevision;
use Drupal\mcp_tools\Service\EntityAccess;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\EntityReferences;
use Drupal\mcp_tools\Service\TextFormat;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Service for content CRUD operations.
 */
class ContentService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AccountProxyInterface $currentUser,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected TimeInterface $time,
    protected ModuleHandlerInterface $moduleHandler,
    protected ?object $moderationInformation = NULL,
  ) {}

  /**
   * Create new content.
   */
  public function createContent(string $type, string $title, array $fields = [], array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($type);
    if ($nodeType && ($denied = EntityAccess::createDenied('node', $type)) !== NULL) {
      return $denied;
    }
    if (!$nodeType) {
      return [
        'success' => FALSE,
        'error' => "Content type '$type' not found."
          . " Use mcp_structure_list_content_types to see available types.",
      ];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $type);

    // Fail loudly on unknown keys, as updateContent() does: a node drops a
    // field it does not have without a word, and the call would report
    // success for a value that was never stored.
    $unknown = [];
    foreach (array_keys($fields) as $fieldName) {
      if (!isset($fieldDefinitions[$this->resolveFieldName((string) $fieldName, $fieldDefinitions)])) {
        $unknown[] = (string) $fieldName;
      }
    }
    if (!empty($unknown)) {
      return [
        'success' => FALSE,
        'error' => sprintf("Unknown field(s) on content type '%s': %s. Nothing was created.", $type, implode(', ', $unknown)),
      ];
    }

    try {
      $nodeData = [
        'type' => $type,
        'title' => $title,
        'uid' => $options['uid'] ?? $this->currentUser->id(),
        'status' => $options['status'] ?? 0,
      ];

      foreach ($fields as $fieldName => $value) {
        if (!str_starts_with($fieldName, 'field_') && !in_array($fieldName, ['body'])) {
          $checkName = 'field_' . $fieldName;
          if (isset($fieldDefinitions[$checkName])) {
            $fieldName = $checkName;
          }
        }
        $nodeData[$fieldName] = $this->normalizeFieldValue($fieldName, $value, $fieldDefinitions);
      }

      $node = $this->entityTypeManager->getStorage('node')->create($nodeData);
      // Use getCurrentTime() to avoid frozen REQUEST_TIME in server mode.
      $now = $this->time->getCurrentTime();
      $node->setCreatedTime($now);
      $node->setChangedTime($now);
      if (($problem = EntityReferences::problem($node)) !== NULL) {
        return ['success' => FALSE, 'error' => $problem];
      }
      $node->save();

      $this->auditLogger->logSuccess('create_content', 'node', (string) $node->id(), [
        'title' => $title,
        'type' => $type,
      ]);

      // Say which it is: "created successfully" read as "published" to a
      // model, and without a status the item is a draft.
      $message = $node->isPublished()
        ? "Content '$title' created and published."
        : "Content '$title' created as an unpublished draft. Publish it with mcp_publish_content.";
      // And say when nothing but the title was given. A model that was
      // asked for a body text sent none and reported it as set.
      if ($fields === []) {
        $unset = [];
        foreach ($fieldDefinitions as $fieldName => $definition) {
          if (!$definition->getFieldStorageDefinition()->isBaseField() && $node->get($fieldName)->isEmpty()) {
            $unset[] = $fieldName;
          }
        }
        if ($unset !== []) {
          $message .= ' Only the title was set. This content type also has: ' . implode(', ', array_slice($unset, 0, 10))
            . (count($unset) > 10 ? ' and ' . (count($unset) - 10) . ' more' : '') . '. Set them with mcp_update_content.';
        }
      }

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $node->id(),
          'uuid' => $node->uuid(),
          'title' => $title,
          'type' => $type,
          'status' => $node->isPublished() ? 'published' : 'draft',
          'url' => $node->toUrl()->toString(),
          'message' => $message,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_content', 'node', 'new', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create content: ' . $e->getMessage()];
    }
  }

  /**
   * Update existing content in its source language.
   *
   * Per-language updates of translations are handled by the
   * mcp_tools_translate submodule; this method always operates on the
   * node's default (source) translation.
   *
   * @param int $nid
   *   The node ID.
   * @param array $updates
   *   Field values keyed by field name, plus the special keys "title" and
   *   "status". Unknown field names are rejected before anything is saved.
   *
   * @return array
   *   Legacy MCP result.
   */
  public function updateContent(int $nid, array $updates): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $node = LatestRevision::load($this->entityTypeManager, 'node', $nid);
    if ($node && ($denied = EntityAccess::denied($node, 'update')) !== NULL) {
      return $denied;
    }
    if (!$node instanceof NodeInterface) {
      return [
        'success' => FALSE,
        'error' => "Content with ID $nid not found."
          . " Use mcp_tools_search_content to find content by title or mcp_tools_get_recent_content to browse.",
      ];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle());

    // Fail loudly on unknown keys before touching the node: a silently
    // skipped field would report success without applying the change.
    if (array_key_exists('paragraphs', $updates)) {
      return [
        'success' => FALSE,
        'error' => "The 'paragraphs' map is only supported for per-language updates."
          . " Pass language (a language code such as 'fr') to update a translation's paragraphs, or set the"
          . ' paragraph reference field itself (e.g. field_paragraphs) to replace the'
          . ' composition in the source language.',
      ];
    }
    if (array_key_exists('status', $updates) && $this->moderationInformation !== NULL && $this->moderationInformation->isModeratedEntity($node)) {
      return [
        'success' => FALSE,
        'error' => "Content $nid is under a moderation workflow: its state decides whether it is published. Leave status out, and use mcp_moderation_set_state for that. Nothing was saved.",
        'code' => 'MODERATED',
      ];
    }
    $unknown = [];
    foreach (array_keys($updates) as $fieldName) {
      if (in_array($fieldName, ['title', 'status'], TRUE)) {
        continue;
      }
      if (!$node->hasField($this->resolveFieldName((string) $fieldName, $fieldDefinitions))) {
        $unknown[] = (string) $fieldName;
      }
    }
    if (!empty($unknown)) {
      return [
        'success' => FALSE,
        'error' => sprintf(
          "Unknown field(s) on node %d (%s): %s. Nothing was updated.",
          $nid,
          $node->bundle(),
          implode(', ', $unknown),
        ),
      ];
    }

    $sourceLangcode = $node->language()->getId();

    try {
      $updatedFields = [];
      foreach ($updates as $fieldName => $value) {
        if ($fieldName === 'title') {
          $node->setTitle($value);
          $updatedFields[] = 'title';
          continue;
        }
        if ($fieldName === 'status') {
          $value ? $node->setPublished() : $node->setUnpublished();
          $updatedFields[] = 'status';
          continue;
        }

        $fieldName = $this->resolveFieldName((string) $fieldName, $fieldDefinitions);
        $node->set($fieldName, $this->normalizeFieldValue($fieldName, $value, $fieldDefinitions));
        $updatedFields[] = $fieldName;
      }

      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage('Updated via MCP Tools');
      if (($problem = EntityReferences::problem($node)) !== NULL) {
        return ['success' => FALSE, 'error' => $problem];
      }
      $node->save();

      $this->auditLogger->logSuccess('update_content', 'node', (string) $nid, [
        'updates' => array_keys($updates),
        'language' => $sourceLangcode,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'title' => $node->getTitle(),
          'language' => $sourceLangcode,
          'fields_updated' => $updatedFields,
          'revision_id' => $node->getRevisionId(),
          'message' => sprintf(
            "Content updated successfully in '%s' (the source language).",
            $sourceLangcode,
          ),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_content', 'node', (string) $nid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to update content: ' . $e->getMessage()];
    }
  }

  /**
   * Resolves a caller-supplied field name against the field definitions.
   *
   * Accepts both "description" and "field_description" for a field stored
   * as "field_description".
   *
   * @param string $fieldName
   *   The caller-supplied field name.
   * @param array $fieldDefinitions
   *   Field definitions for the bundle.
   *
   * @return string
   *   The resolved field name (unchanged when no prefixed variant exists).
   */
  protected function resolveFieldName(string $fieldName, array $fieldDefinitions): string {
    if (!str_starts_with($fieldName, 'field_') && !in_array($fieldName, ['body'], TRUE)) {
      $checkName = 'field_' . $fieldName;
      if (isset($fieldDefinitions[$checkName])) {
        return $checkName;
      }
    }
    return $fieldName;
  }

  /**
   * Delete a node, or a single translation of a node.
   *
   * The full-node delete removes every translation at once and therefore
   * requires the explicit $confirmDeleteAll flag; a bare nid is rejected so
   * that no ambiguous or mistyped call can fall through to the destructive
   * default.
   *
   * @param int $nid
   *   The node ID.
   * @param string|null $langcode
   *   When given, delete only this translation and keep all other language
   *   versions. Mutually exclusive with $confirmDeleteAll.
   * @param bool $confirmDeleteAll
   *   Explicit confirmation to delete the entire node with all translations.
   *
   * @return array
   *   Legacy MCP result naming the affected language version(s).
   */
  public function deleteContent(int $nid, ?string $langcode = NULL, bool $confirmDeleteAll = FALSE): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if ($node && ($denied = EntityAccess::denied($node, 'delete')) !== NULL) {
      return $denied;
    }
    if (!$node instanceof NodeInterface) {
      return [
        'success' => FALSE,
        'error' => "Content with ID $nid not found."
          . " Use mcp_tools_search_content to find content by title or mcp_tools_get_recent_content to browse.",
      ];
    }

    if ($langcode !== NULL && $confirmDeleteAll) {
      return [
        'success' => FALSE,
        'error' => 'Ambiguous request: pass either language (delete a single translation)'
          . ' or confirm_delete_all (delete the whole node), never both.',
      ];
    }

    $languages = array_keys($node->getTranslationLanguages());
    $sourceLangcode = $node->getUntranslated()->language()->getId();

    if ($langcode !== NULL) {
      return $this->deleteTranslation($node, $langcode, $languages, $sourceLangcode);
    }

    if (!$confirmDeleteAll) {
      return [
        'success' => FALSE,
        'error' => sprintf(
          "Refused: node %d ('%s') has %d language version(s): %s. Deleting the node removes ALL of them."
          . " Pass confirm_delete_all: true to delete everything, or language with one of those codes to delete a single translation.",
          $nid,
          $node->getTitle(),
          count($languages),
          implode(', ', $languages),
        ),
      ];
    }

    try {
      $title = $node->getTitle();
      $type = $node->bundle();
      $node->delete();

      $restorable = $this->moduleHandler->moduleExists('trash');
      $this->auditLogger->logSuccess('delete_content', 'node', (string) $nid, [
        'title' => $title,
        'type' => $type,
        'languages' => $languages,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'title' => $title,
          'type' => $type,
          'languages_deleted' => $languages,
          'remaining_languages' => [],
          'message' => sprintf(
            "Content '%s' deleted, including all language versions (%s).",
            $title,
            implode(', ', $languages),
          ) . ($restorable
            ? ' It was moved to the trash and can be restored.'
            : ' This is permanent and cannot be undone.'),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_content', 'node', (string) $nid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete content: ' . $e->getMessage()];
    }
  }

  /**
   * Deletes a single translation of a node, keeping all other languages.
   *
   * The save creates a new revision, so earlier revisions retain the removed
   * translation as a recovery path (translation removal does not go through
   * the trash).
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node (default translation object).
   * @param string $langcode
   *   The language code of the translation to remove.
   * @param list<string> $languages
   *   All language codes currently present on the node.
   * @param string $sourceLangcode
   *   The node's default (source) language code.
   *
   * @return array
   *   Legacy MCP result.
   */
  protected function deleteTranslation(NodeInterface $node, string $langcode, array $languages, string $sourceLangcode): array {
    $nid = (int) $node->id();

    if (!in_array($langcode, $languages, TRUE)) {
      return [
        'success' => FALSE,
        'error' => sprintf(
          "Node %d has no '%s' translation. Existing language versions: %s.",
          $nid,
          $langcode,
          implode(', ', $languages),
        ),
      ];
    }

    if ($langcode === $sourceLangcode) {
      if (count($languages) > 1) {
        return [
          'success' => FALSE,
          'error' => sprintf(
            "'%s' is the source language of node %d and cannot be deleted while translations exist (%s)."
            . ' Delete the whole node with confirm_delete_all: true, or delete the other translations first.',
            $langcode,
            $nid,
            implode(', ', array_diff($languages, [$langcode])),
          ),
        ];
      }
      return [
        'success' => FALSE,
        'error' => sprintf(
          "'%s' is the only language version of node %d, so this equals deleting the whole node."
          . ' Pass confirm_delete_all: true (without language) to do that explicitly.',
          $langcode,
          $nid,
        ),
      ];
    }

    try {
      $title = $node->getTranslation($langcode)->getTitle();
      $node->removeTranslation($langcode);
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage(sprintf('Removed %s translation via MCP Tools.', $langcode));
      $node->save();

      $remaining = array_values(array_diff($languages, [$langcode]));
      $this->auditLogger->logSuccess('delete_translation', 'node', (string) $nid, [
        'language' => $langcode,
        'title' => $title,
        'remaining_languages' => $remaining,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'title' => $title,
          'language' => $langcode,
          'languages_deleted' => [$langcode],
          'remaining_languages' => $remaining,
          'message' => sprintf(
            "Deleted the '%s' translation of node %d ('%s'). Remaining language versions: %s."
            . ' Earlier revisions retain the removed translation.',
            $langcode,
            $nid,
            $title,
            implode(', ', $remaining),
          ),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_translation', 'node', (string) $nid, [
        'language' => $langcode,
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Failed to delete translation: ' . $e->getMessage()];
    }
  }

  /**
   * Publish or unpublish content.
   */
  public function setPublishStatus(int $nid, bool $publish, ?string $langcode = NULL): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $node = LatestRevision::load($this->entityTypeManager, 'node', $nid);
    if ($node && ($denied = EntityAccess::denied($node, 'update')) !== NULL) {
      return $denied;
    }
    if (!$node) {
      return [
        'success' => FALSE,
        'error' => "Content with ID $nid not found."
          . " Use mcp_tools_search_content to find content by title or mcp_tools_get_recent_content to browse.",
      ];
    }
    // Content Moderation sets the status from the moderation state on save:
    // the tool said "published" and the node stayed a draft.
    if ($this->moderationInformation !== NULL && $this->moderationInformation->isModeratedEntity($node)) {
      return [
        'success' => FALSE,
        'error' => sprintf("Content %d is under the moderation workflow '%s': its state decides whether it is published. Use mcp_moderation_set_state with the state to go to (mcp_moderation_get_workflow lists them).", $nid, $this->moderationInformation->getWorkflowForEntity($node)?->id() ?? ''),
        'code' => 'MODERATED',
      ];
    }

    // Each language version of a node is published on its own. Without a
    // language this changes the source language, as it always did. It used
    // to say "unpublished" and no more, while the other languages stayed
    // public.
    $node = $node->getUntranslated();
    $languages = array_keys($node->getTranslationLanguages());
    $langcode = $langcode !== NULL && $langcode !== '' ? $langcode : $node->language()->getId();
    if (!in_array($langcode, $languages, TRUE)) {
      return [
        'success' => FALSE,
        'error' => sprintf("Node %d has no '%s' version. Its language versions: %s.", $nid, $langcode, implode(', ', $languages)),
      ];
    }
    $target = $node->getTranslation($langcode);
    $word = $publish ? 'published' : 'unpublished';

    $changed = $publish !== $target->isPublished();
    if ($changed) {
      try {
        $publish ? $target->setPublished() : $target->setUnpublished();
        $target->setNewRevision(TRUE);
        $target->setRevisionLogMessage(($publish ? 'Published' : 'Unpublished') . ' via MCP Tools');
        $target->save();
        $this->auditLogger->logSuccess($publish ? 'publish_content' : 'unpublish_content', 'node', (string) $nid, count($languages) > 1 ? ['language' => $langcode] : []);
      }
      catch (\Exception $e) {
        return ['success' => FALSE, 'error' => 'Failed to change publish status: ' . $e->getMessage()];
      }
    }

    $states = [];
    foreach ($languages as $code) {
      $states[$code] = $node->getTranslation($code)->isPublished() ? 'published' : 'unpublished';
    }
    $message = $changed ? "Content '" . $target->getTitle() . "' $word." : "Content was already $word.";
    if (count($languages) > 1) {
      $message = $changed
        ? sprintf("Content '%s' %s in '%s'%s.", $target->getTitle(), $word, $langcode, $target->isDefaultTranslation() ? ' (the source language)' : '')
        : sprintf("The '%s' version was already %s.", $langcode, $word);
      $others = array_keys(array_filter($states, static fn(string $state, string $code): bool => $code !== $langcode && $state !== $word, ARRAY_FILTER_USE_BOTH));
      if ($others !== []) {
        $message .= sprintf(' Other language versions are still %s: %s. Pass language to change one of them.', $publish ? 'unpublished' : 'published', implode(', ', $others));
      }
    }

    return [
      'success' => TRUE,
      'data' => [
        'nid' => $nid,
        'title' => $target->getTitle(),
        'status' => $word,
        'language' => $langcode,
        'languages' => $states,
        'message' => $message,
        'changed' => $changed,
      ],
    ];
  }

  /**
   * Normalize field value based on field type.
   */
  protected function normalizeFieldValue(string $fieldName, mixed $value, array $fieldDefinitions): mixed {
    if (!isset($fieldDefinitions[$fieldName])) {
      return $value;
    }

    $fieldType = $fieldDefinitions[$fieldName]->getType();

    return match ($fieldType) {
      'text_long', 'text_with_summary' => TextFormat::fieldValue($this->entityTypeManager, $value),
      'entity_reference' => is_array($value) ? $value : ['target_id' => $value],
      'entity_reference_revisions' => $this->createReferencedEntities($value, $fieldDefinitions[$fieldName]),
      'image', 'file' => is_array($value) ? $value : ['target_id' => $value],
      'link' => is_array($value) ? $value : ['uri' => $value],
      'datetime' => is_array($value) ? $value : ['value' => $value],
      default => $value,
    };
  }

  /**
   * Create referenced entities from inline data for entity_reference_revisions.
   *
   * Items containing a "type" key are created inline; anything else (e.g.
   * ['target_id' => ...] references to existing entities) passes through
   * unchanged so the field item list can resolve it as before.
   *
   * Supports Layout Paragraphs: items may include a "_ref" key (local alias)
   * and reference it via "parent_ref" in behavior_settings.layout_paragraphs.
   * The alias is resolved to the real entity UUID after all entities are
   * created.
   *
   * @return mixed
   *   The field value with inline definitions replaced by unsaved entity
   *   objects. They will be cascade-saved when the parent entity is saved.
   */
  protected function createReferencedEntities(mixed $value, mixed $fieldDefinition): mixed {
    if (!is_array($value)) {
      return $value;
    }

    $targetType = $fieldDefinition->getSetting('target_type') ?? 'paragraph';
    if (!$this->entityTypeManager->hasDefinition($targetType)) {
      return $value;
    }

    $items = array_is_list($value) ? $value : [$value];

    $refMap = [];
    $created = [];
    $result = [];
    foreach ($items as $item) {
      if (!is_array($item) || empty($item['type'])) {
        $result[] = $item;
        continue;
      }

      $ref = $item['_ref'] ?? NULL;
      unset($item['_ref']);

      $entity = $this->createReferencedEntity($targetType, $item);
      if ($entity) {
        $result[] = $entity;
        $created[] = $entity;
        if ($ref !== NULL) {
          $refMap[$ref] = $entity->uuid();
        }
      }
    }

    $this->resolveParentRefs($created, $refMap);

    return $result;
  }

  /**
   * Resolve "parent_ref" aliases to real UUIDs in behavior_settings.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The created paragraph entities.
   * @param array<string, string> $refMap
   *   Map of local ref aliases to entity UUIDs.
   */
  protected function resolveParentRefs(array $entities, array $refMap): void {
    if (empty($refMap)) {
      return;
    }

    foreach ($entities as $entity) {
      if (!$entity instanceof ParagraphInterface) {
        continue;
      }
      $settings = $entity->getAllBehaviorSettings();
      $parentRef = $settings['layout_paragraphs']['parent_ref'] ?? NULL;
      if ($parentRef !== NULL && isset($refMap[$parentRef])) {
        $settings['layout_paragraphs']['parent_uuid'] = $refMap[$parentRef];
        unset($settings['layout_paragraphs']['parent_ref']);
        $entity->setAllBehaviorSettings($settings);
      }
    }
  }

  /**
   * Create a single referenced entity from inline data.
   */
  protected function createReferencedEntity(string $entityTypeId, array $data): ?EntityInterface {
    $bundle = $data['type'];
    unset($data['type']);

    $behaviorSettings = $data['behavior_settings'] ?? NULL;
    unset($data['behavior_settings']);

    $storage = $this->entityTypeManager->getStorage($entityTypeId);

    $bundleKey = $this->entityTypeManager->getDefinition($entityTypeId)->getKey('bundle');
    $entityData = [$bundleKey => $bundle];

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);

    foreach ($data as $fieldName => $fieldValue) {
      if (isset($fieldDefinitions[$fieldName])) {
        $entityData[$fieldName] = $this->normalizeFieldValue($fieldName, $fieldValue, $fieldDefinitions);
      }
    }

    $entity = $storage->create($entityData);

    if (is_array($behaviorSettings) && $entity instanceof ParagraphInterface) {
      $entity->setAllBehaviorSettings($behaviorSettings);
    }

    return $entity;
  }

}
