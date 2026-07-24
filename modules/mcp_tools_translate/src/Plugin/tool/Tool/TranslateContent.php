<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_translate\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\mcp_tools_translate\Service\ContentTranslationService;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a linked content translation for a node and its paragraphs.
 */
#[Tool(
  id: 'mcp_translate_content',
  label: new TranslatableMarkup('Translate Content'),
  description: new TranslatableMarkup('Create a linked translation for a node and its paragraphs using the Content Translation API. Call GetTranslatableContent first to get the source text. You MUST provide a translated value for EVERY field and paragraph it returns (include a field unchanged if it is identical in the target language, e.g. a proper name or number). Translate the whole article as one coherent document, not field by field. Incomplete payloads are REJECTED and nothing is saved — the error lists the missing keys; on rejection, re-translate the entire article, not just the gaps.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The source node ID to translate.'),
      required: TRUE,
    ),
    'language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Target Language'),
      description: new TranslatableMarkup('Target language code (e.g. "fr"). Must be a language enabled on the site.'),
      required: TRUE,
    ),
    'translations' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Translated Content'),
      description: new TranslatableMarkup('Translated field values. Include "title" for the node title, field names (e.g. "field_description") for node fields, and a "paragraphs" map keyed by paragraph ID with translated field values. Text fields accept a string (the source field\'s text format is kept) or {"value": "...", "format": "..."}.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
    ),
    'language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Translated Title'),
    ),
    'url' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Translated Node URL'),
    ),
    'fields_translated' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node Fields Translated Count'),
    ),
    'paragraphs_translated' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Paragraphs Translated Count'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
    ),
  ],
)]
class TranslateContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'translation';

  protected const MCP_WRITE_KIND = 'content';

  /**
   * The translation service.
   *
   * @var \Drupal\mcp_tools_translate\Service\ContentTranslationService
   */
  protected ContentTranslationService $translationService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->translationService = $container->get('mcp_tools_translate.translation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $nid = $input['nid'] ?? 0;
    $language = $input['language'] ?? '';
    $translations = $input['translations'] ?? [];

    if (empty($nid) || empty($language)) {
      return ['success' => FALSE, 'error' => 'Both nid and language are required.'];
    }

    if (empty($translations)) {
      return [
        'success' => FALSE,
        'error' => 'Translations map is required.'
          . ' Use GetTranslatableContent to see which fields to translate.',
      ];
    }

    return $this->translationService->translateContent((int) $nid, $language, $translations);
  }

}
