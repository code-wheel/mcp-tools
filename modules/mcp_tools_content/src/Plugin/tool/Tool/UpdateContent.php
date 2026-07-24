<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\tool\Tool;

use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\mcp_tools_translate\Service\ContentTranslationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_update_content',
  label: new TranslatableMarkup('Update Content'),
  description: new TranslatableMarkup('Update an existing node. Creates a new revision. Without language, updates the source-language version. With language, updates ONLY that translation: translatable node fields, plus a "paragraphs" map (keyed by source paragraph ID, same keying as GetTranslatableContent/TranslateContent) for the translation\'s paragraph content.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID to update. Get from SearchContent, GetRecentContent, or CreateContent.'),
      required: TRUE,
    ),
    'language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('Language code of the translation to update (e.g. "it"). Only that language version is changed; other languages stay untouched. Omit to update the source-language version.'),
      required: FALSE,
    ),
    'updates' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Updates'),
      description: new TranslatableMarkup('Fields to update. Include "title" for title changes. Field format same as CreateContent. Only specified fields are changed; unknown field names are rejected. With language, non-translatable fields are rejected (they are shared across languages) and a "paragraphs" map keyed by source paragraph ID updates the translation\'s paragraphs in place.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The updated node ID.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('The title of the updated language version after the update.'),
    ),
    'language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Updated language'),
      description: new TranslatableMarkup('Language code of the language version that was updated.'),
    ),
    'fields_updated' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Updated fields'),
      description: new TranslatableMarkup('Node fields changed by this call.'),
    ),
    'paragraph_fields_updated' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Updated paragraph fields'),
      description: new TranslatableMarkup('Number of paragraph field values changed by this call.'),
      required: FALSE,
    ),
    'untouched_translatable_fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Untouched translatable fields'),
      description: new TranslatableMarkup('Translatable node fields NOT covered by this update (per-language updates only) — review them for language consistency.'),
      required: FALSE,
    ),
    'revision_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('New Revision ID'),
      description: new TranslatableMarkup('ID of the new revision created by this update.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable success or error message naming the affected language.'),
    ),
  ],
)]
class UpdateContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  /**
   * The content service.
   *
   * @var \Drupal\mcp_tools_content\Service\ContentService
   */
  protected ContentService $contentService;

  /**
   * The translation service, when mcp_tools_translate is installed.
   *
   * Optional soft dependency that provides the per-language update path. The
   * nullable type is safe without the submodule — the class is only
   * autoloaded when the container actually provides the service.
   *
   * @var \Drupal\mcp_tools_translate\Service\ContentTranslationService|null
   */
  protected ?ContentTranslationService $translationService = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    if ($container->has('mcp_tools_translate.translation')) {
      $instance->translationService = $container->get('mcp_tools_translate.translation');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $nid = $input['nid'] ?? 0;
    $language = trim((string) ($input['language'] ?? ''));
    $updates = $input['updates'] ?? [];

    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }
    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required.'];
    }

    if ($language !== '') {
      if ($this->translationService === NULL) {
        return [
          'success' => FALSE,
          'error' => 'Per-language updates require the mcp_tools_translate module.'
            . ' Omit the language parameter to update the source-language version.',
        ];
      }
      return $this->translationService->updateTranslation((int) $nid, $language, $updates);
    }

    return $this->contentService->updateContent((int) $nid, $updates);
  }

}
