<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_translate\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Handles content translation for MCP tools.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ContentTranslationService {

  /**
   * Translation metadata fields to skip when copying values.
   *
   * @var list<string>
   */
  private const SKIP_META_FIELDS = [
    'langcode', 'default_langcode', 'content_translation_source',
    'content_translation_uid', 'content_translation_created',
    'content_translation_changed', 'content_translation_outdated',
    'revision_translation_affected',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected LanguageManagerInterface $languageManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected ContentTranslationAccess $translationAccess,
  ) {}

  /**
   * Extract all translatable text from a node and its paragraphs.
   *
   * @return array
   *   Legacy MCP result with structured translatable content.
   */
  public function getTranslatableContent(int $nid): array {
    $node = $this->translationAccess->loadNode($nid);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node $nid not found."];
    }
    $denied = $this->translationAccess->checkTranslateAccess($node);
    if ($denied !== NULL) {
      return $denied;
    }

    $sourceLang = $node->language()->getId();
    $existingTranslations = array_keys($node->getTranslationLanguages());

    $nodeFields = $this->extractTranslatableFields($node, 'node', $node->bundle());

    $paragraphs = [];
    $this->collectNodeParagraphs($node, $paragraphs);

    $readingOrder = $this->buildReadingOrder($node, $nodeFields, $paragraphs);

    return [
      'success' => TRUE,
      'data' => [
        'nid' => $nid,
        'title' => $node->getTitle(),
        'type' => $node->bundle(),
        'source_language' => $sourceLang,
        'existing_translations' => $existingTranslations,
        'reading_order' => $readingOrder,
        'fields' => $nodeFields,
        'paragraphs' => $paragraphs,
      ],
    ];
  }

  /**
   * Collect the set of translatable keys a complete translation must cover.
   *
   * Reuses the same extraction routine that getTranslatableContent() exposes,
   * so "what the agent is told to translate" and "what is required" can never
   * diverge.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   *
   * @return array{fields: list<string>, paragraphs: array<string, list<string>>}
   *   'fields'     => non-empty translatable node field names.
   *   'paragraphs' => paragraph id => non-empty translatable field names
   *                   (recursive, including nested child paragraphs).
   */
  public function collectTranslatableKeys(NodeInterface $node): array {
    $nodeFields = $this->extractTranslatableFields($node, 'node', $node->bundle());

    $paragraphs = [];
    $this->collectNodeParagraphs($node, $paragraphs);

    $paragraphKeys = [];
    foreach ($paragraphs as $pid => $info) {
      $paragraphKeys[(string) $pid] = array_values(array_map('strval', array_keys($info['fields'])));
    }

    return [
      'fields' => array_keys($nodeFields),
      'paragraphs' => $paragraphKeys,
    ];
  }

  /**
   * Get translation status for a node.
   *
   * @return array
   *   Legacy MCP result with translation status.
   */
  public function getTranslationStatus(int $nid): array {
    $node = $this->translationAccess->loadNode($nid);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node $nid not found."];
    }
    $denied = $this->translationAccess->checkTranslateAccess($node);
    if ($denied !== NULL) {
      return $denied;
    }

    $allLanguages = $this->languageManager->getLanguages();
    $nodeLanguages = $node->getTranslationLanguages();
    $sourceLang = $node->language()->getId();

    $status = [];
    foreach ($allLanguages as $langcode => $language) {
      $status[$langcode] = [
        'language' => $language->getName(),
        'is_source' => $langcode === $sourceLang,
        'has_translation' => isset($nodeLanguages[$langcode]),
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'nid' => $nid,
        'title' => $node->getTitle(),
        'source_language' => $sourceLang,
        'languages' => $status,
      ],
    ];
  }

  /**
   * Create a linked translation for a node and its paragraphs.
   *
   * Node translation triggers layout_paragraphs to duplicate referenced
   * paragraphs for the target language. After saving the node translation,
   * the duplicate paragraphs are updated with translated field values.
   *
   * @return array
   *   Legacy MCP result.
   */
  public function translateContent(int $nid, string $langcode, array $translations): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      $available = implode(', ', array_keys($this->languageManager->getLanguages()));
      return [
        'success' => FALSE,
        'error' => "Language '$langcode' is not enabled. Available: $available.",
      ];
    }

    $node = $this->translationAccess->loadNode($nid);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node $nid not found."];
    }
    $denied = $this->translationAccess->checkTranslateAccess($node);
    if ($denied !== NULL) {
      return $denied;
    }

    if ($node->hasTranslation($langcode)) {
      return [
        'success' => FALSE,
        'error' => "Node $nid already has a '$langcode' translation. Use mcp_update_content to modify it.",
      ];
    }

    $required = $this->collectTranslatableKeys($node);
    $missing = self::diffCoverage($required, $translations);
    if (!empty($missing['node_fields']) || !empty($missing['paragraphs'])) {
      $missingParagraphFields = array_sum(array_map('count', $missing['paragraphs']));
      $this->auditLogger->logFailure('translate_content', 'node', (string) $nid, [
        'language' => $langcode,
        'reason' => 'incomplete_coverage',
        'missing_node_fields' => count($missing['node_fields']),
        'missing_paragraph_fields' => $missingParagraphFields,
      ]);
      return [
        'success' => FALSE,
        'error' => sprintf(
          'Incomplete translation: %d node field(s) and %d paragraph field(s) were not '
          . 'provided in the target language. Nothing was saved.',
          count($missing['node_fields']),
          $missingParagraphFields,
        ),
        'missing' => $missing,
        'hint' => 'Re-translate the COMPLETE article as one coherent document (all fields '
          . 'together) and resubmit — do not translate only the missing fields in isolation.',
      ];
    }

    try {
      $sourceMap = $this->prepareParagraphSourceMap($node, $langcode);
      $nodeValues = $this->buildNodeTranslationValues(
        $node,
        $langcode,
        $translations,
      );
      $nodeTranslation = $node->addTranslation($langcode, $nodeValues);
      $nodeTranslation->save();

      $translations += ['paragraphs' => []];
      $paragraphTranslations = $translations['paragraphs'];
      $paragraphCount = $this->updateTranslationParagraphs(
        $sourceMap,
        $nodeTranslation,
        $paragraphTranslations,
        $langcode,
      );

      $this->auditLogger->logSuccess('translate_content', 'node', (string) $nid, [
        'language' => $langcode,
        'paragraphs_translated' => $paragraphCount,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'language' => $langcode,
          'language_name' => $language->getName(),
          'title' => $nodeTranslation->getTitle(),
          'url' => $nodeTranslation->toUrl()->toString(),
          'fields_translated' => count($required['fields']),
          'paragraphs_translated' => $paragraphCount,
          'message' => "Translation to {$language->getName()} created for '{$node->getTitle()}'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('translate_content', 'node', (string) $nid, [
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Translation failed: ' . $e->getMessage()];
    }
  }

  /**
   * Update field values on an existing translation of a node.
   *
   * Only the given language version changes. Non-translatable fields are
   * rejected (they are shared across languages) and unknown fields are
   * rejected before anything is saved. A "paragraphs" map keyed by SOURCE
   * paragraph ID updates the translation's paragraphs in place — supporting
   * both duplicated (asymmetric, e.g. Layout Paragraphs) and shared
   * (symmetric) paragraph translation setups.
   *
   * @param int $nid
   *   The node ID.
   * @param string $langcode
   *   The language code of the translation to update.
   * @param array $updates
   *   Field values keyed by field name, plus the special keys "title",
   *   "status" and "paragraphs".
   *
   * @return array
   *   Legacy MCP result shaped for mcp_update_content's outputs.
   */
  public function updateTranslation(int $nid, string $langcode, array $updates): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $node = $this->translationAccess->loadNode($nid);
    if (!$node) {
      return ['success' => FALSE, 'error' => "Node $nid not found."];
    }
    $denied = $this->translationAccess->checkTranslateAccess($node);
    if ($denied !== NULL) {
      return $denied;
    }

    if (!$node->hasTranslation($langcode)) {
      $existing = implode(', ', array_keys($node->getTranslationLanguages()));
      return [
        'success' => FALSE,
        'error' => "Node $nid has no '$langcode' translation to update."
          . " Existing language versions: $existing."
          . ' Use mcp_translate_content to create one.',
      ];
    }

    $translation = $node->getTranslation($langcode);
    $definitions = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle());

    $paragraphUpdates = [];
    if (array_key_exists('paragraphs', $updates)) {
      if (!is_array($updates['paragraphs'])) {
        return [
          'success' => FALSE,
          'error' => "The 'paragraphs' key must map source paragraph IDs to arrays of translated field values.",
        ];
      }
      foreach ($updates['paragraphs'] as $pid => $fieldValues) {
        if (!is_array($fieldValues)) {
          return [
            'success' => FALSE,
            'error' => "Paragraph '$pid' must map field names to translated values. Nothing was updated.",
          ];
        }
      }
      $paragraphUpdates = $updates['paragraphs'];
      unset($updates['paragraphs']);
    }

    // Fail loudly on unknown or shared fields before touching anything.
    $unknown = [];
    $shared = [];
    $resolved = [];
    foreach ($updates as $fieldName => $value) {
      $fieldName = (string) $fieldName;
      if (in_array($fieldName, ['title', 'status'], TRUE)) {
        $resolved[$fieldName] = $value;
        continue;
      }
      $candidate = isset($definitions[$fieldName]) ? $fieldName
        : (isset($definitions["field_$fieldName"]) ? "field_$fieldName" : NULL);
      if ($candidate === NULL) {
        $unknown[] = $fieldName;
        continue;
      }
      if (!$definitions[$candidate]->isTranslatable()) {
        $shared[] = $candidate;
        continue;
      }
      $resolved[$candidate] = $value;
    }
    if ($unknown !== [] || $shared !== []) {
      $parts = [];
      if ($unknown !== []) {
        $parts[] = 'unknown field(s): ' . implode(', ', $unknown);
      }
      if ($shared !== []) {
        $parts[] = 'non-translatable field(s) shared across all languages: ' . implode(', ', $shared);
      }
      return [
        'success' => FALSE,
        'error' => sprintf(
          "Cannot update the '%s' translation of node %d — %s. Nothing was updated.",
          $langcode,
          $nid,
          implode('; ', $parts),
        ),
      ];
    }

    $pidMap = [];
    if ($paragraphUpdates !== []) {
      $pidMap = $this->buildPidTargetMap($node, $translation, $langcode);
      $invalid = array_diff(array_map('strval', array_keys($paragraphUpdates)), array_keys($pidMap));
      if ($invalid !== []) {
        return [
          'success' => FALSE,
          'error' => sprintf(
            'Unknown source paragraph id(s) on node %d: %s. Valid ids: %s. Nothing was updated.',
            $nid,
            implode(', ', $invalid),
            implode(', ', array_keys($pidMap)) ?: '(none)',
          ),
        ];
      }
    }

    try {
      $updatedFields = [];
      foreach ($resolved as $fieldName => $value) {
        if ($fieldName === 'title') {
          $translation->setTitle((string) $value);
        }
        elseif ($fieldName === 'status') {
          $value ? $translation->setPublished() : $translation->setUnpublished();
        }
        else {
          $translation->set($fieldName, $this->normalizeFieldValue($fieldName, $value, $definitions, $translation));
        }
        $updatedFields[] = $fieldName;
      }

      $translation->setNewRevision(TRUE);
      $translation->setRevisionLogMessage('Updated via MCP Tools (translation)');
      $translation->save();

      $paragraphFieldsUpdated = 0;
      foreach ($paragraphUpdates as $pid => $fieldValues) {
        $this->applyParagraphTranslation($pidMap[(string) $pid], $fieldValues);
        $paragraphFieldsUpdated += count($fieldValues);
      }

      $required = $this->collectTranslatableKeys($node);
      $untouched = array_values(array_diff($required['fields'], $updatedFields));

      $this->auditLogger->logSuccess('update_translation', 'node', (string) $nid, [
        'language' => $langcode,
        'updates' => $updatedFields,
        'paragraphs' => count($paragraphUpdates),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'nid' => $nid,
          'title' => $translation->getTitle(),
          'language' => $langcode,
          'fields_updated' => $updatedFields,
          'paragraph_fields_updated' => $paragraphFieldsUpdated,
          'untouched_translatable_fields' => $untouched,
          'revision_id' => $translation->getRevisionId(),
          'message' => sprintf(
            "Updated the '%s' translation of '%s'.",
            $langcode,
            $translation->getTitle(),
          ),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_translation', 'node', (string) $nid, [
        'language' => $langcode,
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Translation update failed: ' . $e->getMessage()];
    }
  }

  /**
   * Map source paragraph IDs to the paragraph objects to edit.
   *
   * Walks the source node's paragraph reference fields and the translation's
   * in parallel, pairing by field and delta and recursing into children.
   * Shared (symmetric) paragraphs resolve to their $langcode translation;
   * duplicated (asymmetric) paragraphs already carry the target language.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param \Drupal\node\NodeInterface $translation
   *   The node translation being updated.
   * @param string $langcode
   *   The target language code.
   *
   * @return array<string, \Drupal\paragraphs\ParagraphInterface>
   *   Source paragraph ID => paragraph object to update.
   */
  protected function buildPidTargetMap(NodeInterface $node, NodeInterface $translation, string $langcode): array {
    $map = [];
    foreach ($this->paragraphReferenceFields($node) as $refField) {
      if (!$translation->hasField($refField)) {
        continue;
      }
      $this->pairParagraphs(
        $node->get($refField)->referencedEntities(),
        $translation->get($refField)->referencedEntities(),
        $langcode,
        $map,
      );
    }
    return $map;
  }

  /**
   * Pair source and target paragraph lists by delta, recursing children.
   *
   * @param array<int, object> $sources
   *   Source-language paragraphs.
   * @param array<int, object> $targets
   *   Target-language paragraphs at the same field.
   * @param string $langcode
   *   The target language code.
   * @param array<string, \Drupal\paragraphs\ParagraphInterface> $map
   *   Accumulated source pid => target paragraph map.
   */
  protected function pairParagraphs(array $sources, array $targets, string $langcode, array &$map): void {
    foreach ($sources as $delta => $source) {
      if (!$source instanceof ParagraphInterface
        || !isset($targets[$delta])
        || !$targets[$delta] instanceof ParagraphInterface) {
        continue;
      }
      $target = $targets[$delta];
      if ($target->hasTranslation($langcode)) {
        $target = $target->getTranslation($langcode);
      }
      $map[(string) $source->id()] = $target;

      $this->pairParagraphs(
        $this->getChildParagraphs($source),
        $this->getChildParagraphs($target),
        $langcode,
        $map,
      );
    }
  }

  /**
   * Compute which required translation keys are missing or empty.
   *
   * Pure function (no Drupal dependencies) so it is unit-testable. A key is
   * "covered" only if present AND non-empty after trimming; an empty value for
   * a non-empty source field is content loss and counts as missing.
   *
   * @param array{fields?: list<string>, paragraphs?: array<string|int, list<string>>} $required
   *   Required keys from collectTranslatableKeys().
   * @param array<string, mixed> $translations
   *   The supplied translations payload.
   *
   * @return array{node_fields: list<string>, paragraphs: array<string, list<string>>}
   *   Missing keys. Both empty => fully covered.
   */
  public static function diffCoverage(array $required, array $translations): array {
    $missing = ['node_fields' => [], 'paragraphs' => []];

    foreach ($required['fields'] ?? [] as $field) {
      if (self::valueIsEmpty($translations[$field] ?? NULL)) {
        $missing['node_fields'][] = $field;
      }
    }

    $providedParagraphs = $translations['paragraphs'] ?? [];
    foreach ($required['paragraphs'] ?? [] as $pid => $fields) {
      foreach ($fields as $field) {
        if (self::valueIsEmpty($providedParagraphs[$pid][$field] ?? NULL)) {
          $missing['paragraphs'][(string) $pid][] = $field;
        }
      }
    }

    return $missing;
  }

  /**
   * Whether a supplied field value carries no translatable text.
   *
   * @param mixed $value
   *   A string, a {value, format} array, or NULL.
   *
   * @return bool
   *   TRUE when the value is missing or blank after trimming.
   */
  private static function valueIsEmpty(mixed $value): bool {
    $scalar = is_array($value) ? ($value['value'] ?? '') : $value;
    return trim((string) ($scalar ?? '')) === '';
  }

  /**
   * Build an ordered, nested outline of the article for the AI agent.
   *
   * Node fields in definition order, then paragraphs in document order with
   * children nested, so the agent translates the article as one coherent
   * document. Only entries with translatable content (or descendants with
   * content) are included.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param array<string, mixed> $nodeFields
   *   Translatable node fields from extractTranslatableFields().
   * @param array<string, array<string, mixed>> $paragraphs
   *   Collected paragraphs keyed by id.
   *
   * @return list<string|array<string, mixed>>
   *   Ordered entries: node field names (string) and paragraph entries
   *   ({pid, type, children?}).
   */
  protected function buildReadingOrder(
    NodeInterface $node,
    array $nodeFields,
    array $paragraphs,
  ): array {
    $order = array_keys($nodeFields);

    foreach ($this->paragraphReferenceFields($node) as $refField) {
      foreach ($node->get($refField)->referencedEntities() as $entity) {
        if (!$entity instanceof ParagraphInterface) {
          continue;
        }
        $entry = $this->buildParagraphOrderEntry($entity, $paragraphs);
        if ($entry !== NULL) {
          $order[] = $entry;
        }
      }
    }

    return $order;
  }

  /**
   * Build a single reading-order entry for a paragraph, recursing children.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   * @param array<string, array<string, mixed>> $paragraphs
   *   Collected paragraphs with translatable fields, keyed by id.
   *
   * @return array<string, mixed>|null
   *   {pid, type, children?}, or NULL when neither this paragraph nor any
   *   descendant has translatable content.
   */
  protected function buildParagraphOrderEntry(
    ParagraphInterface $paragraph,
    array $paragraphs,
  ): ?array {
    $pid = (string) $paragraph->id();

    $children = [];
    foreach ($this->getChildParagraphs($paragraph) as $child) {
      $childEntry = $this->buildParagraphOrderEntry($child, $paragraphs);
      if ($childEntry !== NULL) {
        $children[] = $childEntry;
      }
    }

    if (!isset($paragraphs[$pid]) && $children === []) {
      return NULL;
    }

    $entry = ['pid' => $pid, 'type' => $paragraph->bundle()];
    if ($children !== []) {
      $entry['children'] = $children;
    }
    return $entry;
  }

  /**
   * Collect paragraph fields from all paragraph reference fields on a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to collect paragraphs from.
   * @param array<string, array<string, mixed>> &$collected
   *   Accumulated paragraph data, keyed by paragraph ID.
   */
  protected function collectNodeParagraphs(NodeInterface $node, array &$collected): void {
    foreach ($this->paragraphReferenceFields($node) as $refField) {
      foreach ($node->get($refField)->referencedEntities() as $entity) {
        if ($entity instanceof ParagraphInterface) {
          $this->collectParagraphFields($entity, $collected);
        }
      }
    }
  }

  /**
   * Build source paragraph map and clean stale translations in one pass.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param string $langcode
   *   Target language to clean from paragraphs.
   *
   * @return array<string, array<int, string>>
   *   Map of field_name => [delta => paragraph_id].
   */
  protected function prepareParagraphSourceMap(
    NodeInterface $node,
    string $langcode,
  ): array {
    $map = [];
    foreach ($this->paragraphReferenceFields($node) as $refField) {
      foreach ($node->get($refField)->referencedEntities() as $delta => $entity) {
        if (!$entity instanceof ParagraphInterface) {
          continue;
        }
        $map[$refField][$delta] = (string) $entity->id();
        if ($entity->hasTranslation($langcode)) {
          $entity->removeTranslation($langcode);
          $entity->save();
        }
      }
    }
    return $map;
  }

  /**
   * Update duplicate paragraphs created during node translation.
   *
   * Layout_paragraphs duplicates paragraphs referenced by ERR fields
   * when a node translation is created. The duplicates have the target
   * langcode but the same field values as the source. This method
   * updates those duplicates with translated text.
   *
   * @param array<string, array<int, string>> $sourceMap
   *   Original paragraph position map from buildParagraphPositionMap().
   * @param \Drupal\node\NodeInterface $translatedNode
   *   The saved node translation.
   * @param array<string, array<string, mixed>> $paragraphTranslations
   *   Translated field values keyed by original paragraph ID.
   * @param string $langcode
   *   Target language code for child paragraph langcode correction.
   *
   * @return int
   *   Number of paragraphs updated.
   */
  protected function updateTranslationParagraphs(
    array $sourceMap,
    NodeInterface $translatedNode,
    array $paragraphTranslations,
    string $langcode,
  ): int {
    // The paragraphs module is a soft dependency: without it there are no
    // paragraph reference fields and nothing to update.
    if (!$this->entityTypeManager->hasDefinition('paragraph')) {
      return 0;
    }
    $count = 0;
    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');

    foreach ($this->paragraphReferenceFields($translatedNode) as $refField) {
      $entities = $translatedNode->get($refField)->referencedEntities();
      foreach ($entities as $delta => $duplicate) {
        if (!$duplicate instanceof ParagraphInterface) {
          continue;
        }
        if (!isset($sourceMap[$refField][$delta])) {
          continue;
        }
        $originalPid = $sourceMap[$refField][$delta];

        if (isset($paragraphTranslations[$originalPid])) {
          $this->applyParagraphTranslation(
            $duplicate,
            $paragraphTranslations[$originalPid],
          );
          $count++;
        }

        $source = $paragraphStorage->load((int) $originalPid);
        if ($source instanceof ParagraphInterface) {
          $this->updateChildParagraphs(
            $source,
            $duplicate,
            $paragraphTranslations,
            $count,
            $langcode,
          );
        }
      }
    }

    return $count;
  }

  /**
   * Apply translated field values to a paragraph and save.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to update.
   * @param array<string, mixed> $fieldValues
   *   Translated field values.
   */
  protected function applyParagraphTranslation(
    ParagraphInterface $paragraph,
    array $fieldValues,
  ): void {
    $definitions = $this->entityFieldManager->getFieldDefinitions(
      'paragraph',
      $paragraph->bundle(),
    );
    foreach ($fieldValues as $fieldName => $value) {
      if (!isset($definitions[$fieldName])) {
        continue;
      }
      if (!$definitions[$fieldName]->isTranslatable()) {
        continue;
      }
      $normalized = $this->normalizeFieldValue(
        $fieldName,
        $value,
        $definitions,
        $paragraph,
      );
      $paragraph->set($fieldName, $normalized);
    }
    $paragraph->save();
  }

  /**
   * Recursively update child paragraphs with translated text.
   *
   * Walks ERR fields on both source and duplicate in parallel, matching
   * children by field name and delta position.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $source
   *   The source paragraph.
   * @param \Drupal\paragraphs\ParagraphInterface $duplicate
   *   The duplicated paragraph in the translation.
   * @param array<string, array<string, mixed>> $translations
   *   Translated field values keyed by original paragraph ID.
   * @param int &$count
   *   Running count of updated paragraphs.
   * @param string $langcode
   *   Target language code to set on child paragraphs.
   */
  protected function updateChildParagraphs(
    ParagraphInterface $source,
    ParagraphInterface $duplicate,
    array $translations,
    int &$count,
    string $langcode,
  ): void {
    $definitions = $this->entityFieldManager->getFieldDefinitions(
      'paragraph',
      $source->bundle(),
    );

    foreach ($definitions as $fieldName => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($source->get($fieldName)->isEmpty()) {
        continue;
      }

      $srcKids = $source->get($fieldName)->referencedEntities();
      $dupKids = $duplicate->get($fieldName)->referencedEntities();

      foreach ($srcKids as $delta => $srcKid) {
        if (!isset($dupKids[$delta])) {
          continue;
        }
        if (!$srcKid instanceof ParagraphInterface) {
          continue;
        }
        $dupKid = $dupKids[$delta];
        if (!$dupKid instanceof ParagraphInterface) {
          continue;
        }

        $dupKid->set('langcode', $langcode);

        $pid = (string) $srcKid->id();
        if (isset($translations[$pid])) {
          $this->applyParagraphTranslation($dupKid, $translations[$pid]);
          $count++;
        }
        else {
          $dupKid->save();
        }

        $this->updateChildParagraphs($srcKid, $dupKid, $translations, $count, $langcode);
      }
    }
  }

  /**
   * Extract translatable text/string fields from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to extract fields from.
   * @param string $entityType
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array<string, mixed>
   *   Field name => value pairs for translatable fields.
   */
  protected function extractTranslatableFields(
    ContentEntityInterface $entity,
    string $entityType,
    string $bundle,
  ): array {
    $definitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    $textTypes = ['string', 'string_long', 'text_long', 'text_with_summary', 'text'];
    $allowedBase = ['title', 'meta_title', 'meta_description'];
    $fields = [];

    foreach ($definitions as $name => $definition) {
      if (!$definition->isTranslatable()) {
        continue;
      }
      if (!str_starts_with($name, 'field_') && !in_array($name, $allowedBase, TRUE)) {
        continue;
      }

      $type = $definition->getType();
      if (!in_array($type, $textTypes, TRUE)) {
        continue;
      }

      if ($entity->get($name)->isEmpty()) {
        continue;
      }
      $value = $entity->get($name)->first()->getValue();
      $value += ['value' => '', 'format' => NULL];
      $fields[$name] = [
        'type' => $type,
        'value' => $value['value'],
        'format' => $value['format'],
      ];
    }

    return $fields;
  }

  /**
   * Recursively collect translatable fields from a paragraph and children.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   * @param array<string, array<string, mixed>> &$collected
   *   Accumulated paragraph data, keyed by paragraph ID.
   */
  protected function collectParagraphFields(
    ParagraphInterface $paragraph,
    array &$collected,
  ): void {
    $pid = (string) $paragraph->id();
    $bundle = $paragraph->bundle();
    $fields = $this->extractTranslatableFields($paragraph, 'paragraph', $bundle);

    if ($fields) {
      $collected[$pid] = [
        'type' => $bundle,
        'fields' => $fields,
      ];
    }

    foreach ($this->getChildParagraphs($paragraph) as $child) {
      $this->collectParagraphFields($child, $collected);
    }
  }

  /**
   * Paragraph reference (ERR) field names on an entity, in definition order.
   *
   * Discovered dynamically so any content model works — reference fields
   * must not be hardcoded per site.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to inspect.
   *
   * @return list<string>
   *   Field names of entity_reference_revisions fields targeting paragraphs.
   */
  protected function paragraphReferenceFields(ContentEntityInterface $entity): array {
    $fields = [];
    $definitions = $this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(),
      $entity->bundle(),
    );
    foreach ($definitions as $name => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($definition->getSetting('target_type') !== 'paragraph') {
        continue;
      }
      $fields[] = $name;
    }
    return $fields;
  }

  /**
   * Get all child paragraphs from ERR fields on a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The parent paragraph.
   *
   * @return array<int, \Drupal\paragraphs\ParagraphInterface>
   *   Child paragraphs indexed by a running counter.
   */
  protected function getChildParagraphs(
    ParagraphInterface $paragraph,
  ): array {
    $children = [];
    $definitions = $this->entityFieldManager->getFieldDefinitions(
      'paragraph',
      $paragraph->bundle(),
    );
    foreach ($definitions as $name => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($paragraph->get($name)->isEmpty()) {
        continue;
      }
      foreach ($paragraph->get($name)->referencedEntities() as $child) {
        if ($child instanceof ParagraphInterface) {
          $children[] = $child;
        }
      }
    }
    return $children;
  }

  /**
   * Copy all translatable field values from a source entity.
   *
   * Skips translation metadata fields (langcode, default_langcode, etc.)
   * since addTranslation() handles language setup internally.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity (node or paragraph).
   *
   * @return array<string, mixed>
   *   Values suitable for addTranslation(), with all translatable fields
   *   pre-populated from the source.
   */
  protected function copyTranslatableFieldValues(
    ContentEntityInterface $entity,
  ): array {
    $values = [];
    $definitions = $this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(),
      $entity->bundle(),
    );

    foreach ($definitions as $fieldName => $definition) {
      if (!$definition->isTranslatable()
        || in_array($fieldName, self::SKIP_META_FIELDS, TRUE)) {
        continue;
      }
      $values[$fieldName] = $entity->get($fieldName)->getValue();
    }

    return $values;
  }

  /**
   * Build the values array for a node translation.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source node.
   * @param string $langcode
   *   Target language code.
   * @param array<string, mixed> $translations
   *   Translated field values.
   *
   * @return array<string, mixed>
   *   Values suitable for Node::addTranslation().
   */
  protected function buildNodeTranslationValues(
    NodeInterface $node,
    string $langcode,
    array $translations,
  ): array {
    $values = $this->copyTranslatableFieldValues($node);

    $definitions = $this->entityFieldManager->getFieldDefinitions(
      'node',
      $node->bundle(),
    );

    foreach ($translations as $fieldName => $value) {
      if ($fieldName === 'paragraphs') {
        continue;
      }
      if (!isset($definitions[$fieldName]) || !$definitions[$fieldName]->isTranslatable()) {
        continue;
      }
      $values[$fieldName] = $this->normalizeFieldValue(
        $fieldName,
        $value,
        $definitions,
        $node,
      );
    }

    return $values;
  }

  /**
   * Normalize a field value based on its type.
   *
   * @param string $fieldName
   *   The field machine name.
   * @param mixed $value
   *   The raw field value.
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $definitions
   *   Field definitions for the entity bundle.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $source
   *   Entity whose current field value supplies the text format fallback.
   *
   * @return mixed
   *   Normalized value.
   */
  protected function normalizeFieldValue(
    string $fieldName,
    mixed $value,
    array $definitions,
    ?ContentEntityInterface $source = NULL,
  ): mixed {
    if (!isset($definitions[$fieldName])) {
      return $value;
    }

    $type = $definitions[$fieldName]->getType();

    if ($type === 'string' && is_string($value)) {
      $max = $definitions[$fieldName]->getSetting('max_length') ?: 255;
      return mb_substr($value, 0, $max);
    }

    if (in_array($type, ['text_long', 'text_with_summary', 'text'], TRUE)) {
      if (is_array($value)) {
        return $value;
      }
      // A translation must keep the source field's text format — formats are
      // site-specific and must not be hardcoded here.
      $format = NULL;
      if ($source !== NULL && $source->hasField($fieldName) && !$source->get($fieldName)->isEmpty()) {
        $format = $source->get($fieldName)->first()->getValue()['format'] ?? NULL;
      }
      return [
        'value' => $value,
        'format' => $format ?? 'basic_html',
      ];
    }

    return $value;
  }

}
