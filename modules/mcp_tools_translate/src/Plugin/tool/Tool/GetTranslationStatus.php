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
 * Check which translations exist for a node.
 */
#[Tool(
  id: 'mcp_get_translation_status',
  label: new TranslatableMarkup('Get Translation Status'),
  description: new TranslatableMarkup('Check which languages a node is translated into and which are missing. Use to identify untranslated content before calling TranslateContent.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID to check translation status for.'),
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
      label: new TranslatableMarkup('Source Language'),
    ),
    'languages' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Per-Language Translation Status'),
    ),
  ],
)]
class GetTranslationStatus extends McpToolsToolBase {

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

    return $this->translationService->getTranslationStatus((int) $nid);
  }

}
