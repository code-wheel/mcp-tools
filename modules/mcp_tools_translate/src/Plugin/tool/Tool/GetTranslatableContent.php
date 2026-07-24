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
 * Read all translatable text fields from a node and its paragraphs.
 */
#[Tool(
  id: 'mcp_get_translatable_content',
  label: new TranslatableMarkup('Get Translatable Content'),
  description: new TranslatableMarkup('Extract all translatable text fields from a node and its paragraphs. Returns structured content ready for translation. Read ALL returned fields and paragraphs and translate the article as ONE coherent document — consistent terminology, tone and cross-references throughout; never translate fields in isolation. "reading_order" gives the document sequence (node fields, then paragraphs with nested children). Use before TranslateContent to know which fields to translate.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID to extract translatable content from. Get from SearchContent or GetRecentContent.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
    ),
    'source_language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Language Code'),
    ),
    'existing_translations' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Existing Translation Languages'),
    ),
    'reading_order' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Reading Order'),
      description: 'Ordered outline of the article: node field names then paragraphs (with nested children) in document order. Translate following this sequence so the result reads as one coherent document.',
    ),
    'fields' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Translatable Node Fields'),
    ),
    'paragraphs' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Translatable Paragraph Fields'),
    ),
  ],
)]
class GetTranslatableContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'translation';

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
    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }

    return $this->translationService->getTranslatableContent((int) $nid);
  }

}
