<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_translate\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\mcp_tools\Traits\DeclaredOutputTypesTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Runs a multilingual node with paragraphs through the tool plugins.
 *
 * The other kernel tests call the services. This one executes the tools, so
 * Tool API also checks every result against the declared outputs, which is
 * what an MCP client gets. Both paragraph translation setups are covered:
 * shared paragraphs (the reference field is not translatable and each
 * paragraph holds its own translations) and duplicated paragraphs (the
 * reference field is translatable and each language gets its own copies, as
 * with Layout Paragraphs).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_translate')]
class TranslationToolFlowKernelTest extends KernelTestBase {

  use DeclaredOutputTypesTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'file',
    'language',
    'content_translation',
    'entity_reference_revisions',
    'paragraphs',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_content',
    'mcp_tools_paragraphs',
    'mcp_tools_translate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'language', 'mcp_tools']);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    ParagraphsType::create(['id' => 'text_block', 'label' => 'Text block'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'bundle' => 'article',
      'translatable' => FALSE,
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'text_long',
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'text_block',
      'translatable' => TRUE,
    ])->save();

    $translation = $this->container->get('content_translation.manager');
    $translation->setEnabled('node', 'article', TRUE);
    $translation->setEnabled('paragraph', 'text_block', TRUE);

    $this->setUpCurrentUser([], [], TRUE);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Shared paragraphs: create, read, translate, update, unpublish, delete.
   */
  public function testSharedParagraphFlow(): void {
    $this->assertSame(1, $this->runTool('mcp_paragraphs_list_types', [])['total']);
    $this->assertSame('text_block', $this->runTool('mcp_paragraphs_get_type', ['id' => 'text_block'])['id']);

    [$nid, $pid] = $this->createArticle();

    $source = $this->runTool('mcp_get_translatable_content', ['nid' => $nid]);
    $this->assertSame(['en'], $source['existing_translations']);
    $this->assertArrayHasKey($pid, $source['paragraphs']);

    $status = $this->runTool('mcp_get_translation_status', ['nid' => $nid]);
    $this->assertFalse($status['languages']['fr']['has_translation']);

    // Translate everything the source lists, as the tool asks.
    $translations = [];
    foreach (array_keys($source['fields']) as $field) {
      $translations[$field] = 'FR titre';
    }
    foreach ($source['paragraphs'] as $id => $item) {
      foreach (array_keys($item['fields']) as $field) {
        $translations['paragraphs'][$id][$field] = '<p>FR texte</p>';
      }
    }
    $translated = $this->runTool('mcp_translate_content', [
      'nid' => $nid,
      'language' => 'fr',
      'translations' => $translations,
    ]);
    $this->assertSame('fr', $translated['language']);
    $this->assertSame(1, $translated['paragraphs_translated']);

    $paragraph = $this->reloadParagraph($pid);
    $this->assertSame('<p>EN text</p>', $paragraph->get('field_text')->value, 'Translating left the source text alone.');
    $this->assertTrue($paragraph->hasTranslation('fr'), 'The shared paragraph got its own translation.');
    $this->assertSame('<p>FR texte</p>', $paragraph->getTranslation('fr')->get('field_text')->value);

    $status = $this->runTool('mcp_get_translation_status', ['nid' => $nid]);
    $this->assertTrue($status['languages']['fr']['has_translation']);

    $updated = $this->runTool('mcp_update_content', [
      'nid' => $nid,
      'language' => 'fr',
      'updates' => [
        'title' => 'FR titre 2',
        'paragraphs' => [$pid => ['field_text' => '<p>FR texte 2</p>']],
      ],
    ]);
    $this->assertSame('fr', $updated['language']);
    $this->assertSame(1, $updated['paragraph_fields_updated']);

    $nodes = $this->container->get('entity_type.manager')->getStorage('node');
    $nodes->resetCache([$nid]);
    $node = $nodes->load($nid);
    $this->assertSame('FR titre 2', $node->getTranslation('fr')->getTitle());
    $this->assertSame('EN title', $node->getTitle(), 'The source title is untouched.');
    $paragraph = $this->reloadParagraph($pid);
    $this->assertSame('<p>FR texte 2</p>', $paragraph->getTranslation('fr')->get('field_text')->value);
    $this->assertSame('<p>EN text</p>', $paragraph->get('field_text')->value, 'The source paragraph is untouched.');

    $this->runTool('mcp_publish_content', ['nid' => $nid, 'publish' => FALSE]);

    $deleted = $this->runTool('mcp_delete_content', ['nid' => $nid, 'language' => 'fr']);
    $this->assertSame(['en'], array_values($deleted['remaining_languages']));

    // A bare delete of the whole node is refused; a confirmed one works.
    $this->runTool('mcp_delete_content', ['nid' => $nid], FALSE);
    $this->runTool('mcp_delete_content', ['nid' => $nid, 'confirm_delete_all' => TRUE]);
    $nodes->resetCache([$nid]);
    $this->assertNull($nodes->load($nid));
  }

  /**
   * Duplicated paragraphs: each language edits its own copy.
   */
  public function testDuplicatedParagraphFlow(): void {
    FieldConfig::loadByName('node', 'article', 'field_sections')->setTranslatable(TRUE)->save();
    $this->enableModules(['mcp_tools_translate_test']);
    // Enabling a module rebuilds the container and its access manager.
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);

    [$nid, $pid] = $this->createArticle();

    $translated = $this->runTool('mcp_translate_content', [
      'nid' => $nid,
      'language' => 'fr',
      'translations' => [
        'title' => 'FR titre',
        'paragraphs' => [$pid => ['field_text' => '<p>FR texte</p>']],
      ],
    ]);
    $this->assertSame(1, $translated['paragraphs_translated']);

    $nodes = $this->container->get('entity_type.manager')->getStorage('node');
    $nodes->resetCache([$nid]);
    $copyId = (string) $nodes->load($nid)->getTranslation('fr')->get('field_sections')->target_id;
    $this->assertNotSame($pid, $copyId, 'The translation references its own copy.');
    $copy = $this->reloadParagraph($copyId);
    $this->assertSame('<p>FR texte</p>', $copy->get('field_text')->value);
    $this->assertSame('fr', $copy->language()->getId());
    $source = $this->reloadParagraph($pid);
    $this->assertSame('<p>EN text</p>', $source->get('field_text')->value);
    $this->assertFalse($source->hasTranslation('fr'), 'The source paragraph is not translated in place.');

    // Updates are keyed by the source paragraph ID and land on the copy.
    $this->runTool('mcp_update_content', [
      'nid' => $nid,
      'language' => 'fr',
      'updates' => ['paragraphs' => [$pid => ['field_text' => '<p>FR texte 2</p>']]],
    ]);
    $this->assertSame('<p>FR texte 2</p>', $this->reloadParagraph($copyId)->get('field_text')->value);
    $this->assertSame('<p>EN text</p>', $this->reloadParagraph($pid)->get('field_text')->value);
  }

  /**
   * Paragraph type and field management through the tools.
   */
  public function testParagraphTypeTools(): void {
    $this->assertSame('quote', $this->runTool('mcp_paragraphs_create_type', ['id' => 'quote', 'label' => 'Quote'])['id']);
    $field = $this->runTool('mcp_paragraphs_add_field', [
      'bundle' => 'quote',
      'field_name' => 'source',
      'field_type' => 'string',
      'label' => 'Source',
    ]);
    $this->assertSame('quote', $field['bundle']);
    $this->assertNotEmpty($this->runTool('mcp_paragraphs_get_type', ['id' => 'quote'])['fields']);
    $this->runTool('mcp_paragraphs_delete_field', ['bundle' => 'quote', 'field_name' => $field['field_name']]);
    $this->runTool('mcp_paragraphs_delete_type', ['id' => 'quote']);
    $this->assertNull(ParagraphsType::load('quote'));
  }

  /**
   * Creates an English article with one inline paragraph through the tool.
   *
   * @return array{0: int, 1: string}
   *   The node ID and the paragraph ID.
   */
  private function createArticle(): array {
    $created = $this->runTool('mcp_create_content', [
      'type' => 'article',
      'title' => 'EN title',
      'fields' => [
        'field_sections' => [
          ['type' => 'text_block', 'field_text' => ['value' => '<p>EN text</p>', 'format' => 'plain_text']],
        ],
      ],
    ]);
    $nid = (int) $created['nid'];
    $paragraph = Node::load($nid)->get('field_sections')->entity;
    $this->assertNotNull($paragraph, 'The paragraph was created inline.');
    return [$nid, (string) $paragraph->id()];
  }

  /**
   * Loads a paragraph fresh from storage.
   */
  private function reloadParagraph(string $id): ParagraphInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache([(int) $id]);
    return $storage->load((int) $id);
  }

  /**
   * Executes a tool plugin and returns its validated outputs.
   */
  private function runTool(string $id, array $input, bool $expectSuccess = TRUE): array {
    $tool = $this->container->get('plugin.manager.tool')->createInstance($id);
    foreach ($input as $name => $value) {
      $tool->setInputValue($name, $value);
    }
    $tool->execute();
    $result = $tool->getResult();
    $this->assertSame($expectSuccess, $result->isSuccess(), $id . ': ' . $result->getMessage());
    if ($expectSuccess) {
      $this->assertOutputsHaveDeclaredTypes($tool, $id);
    }
    return $expectSuccess ? $tool->getOutputValues() : [];
  }

}
