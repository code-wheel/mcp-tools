<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_translate\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Paragraph handling: dynamic field discovery and per-language updates.
 *
 * Uses shared (symmetric) paragraph translation — the plain paragraphs
 * module setup — to verify buildPidTargetMap() resolves the target-language
 * paragraph translation and updateTranslation() edits only that language.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_translate')]
class TranslateParagraphsKernelTest extends KernelTestBase {

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
    'mcp_tools_translate',
  ];

  /**
   * The translation service under test.
   *
   * @var \Drupal\mcp_tools_translate\Service\ContentTranslationService
   */
  protected $translationService;

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

    // A paragraph reference field with a NON-hardcoded name proves dynamic
    // discovery: julien's original patch only handled field_paragraphs and
    // field_summary_key_facts.
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

    $this->container->get('content_translation.manager')->setEnabled('node', 'article', TRUE);
    $this->container->get('content_translation.manager')->setEnabled('paragraph', 'text_block', TRUE);

    $this->setUpCurrentUser([], ['translate article node']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);

    $this->translationService = $this->container->get('mcp_tools_translate.translation');
  }

  /**
   * Translatable paragraph fields are discovered on any reference field.
   */
  public function testGetTranslatableContentDiscoversParagraphs(): void {
    [$node, $paragraph] = $this->createNodeWithParagraph();

    $result = $this->translationService->getTranslatableContent((int) $node->id());

    $this->assertTrue($result['success']);
    $pid = (string) $paragraph->id();
    $this->assertArrayHasKey($pid, $result['data']['paragraphs']);
    $this->assertArrayHasKey('field_text', $result['data']['paragraphs'][$pid]['fields']);
  }

  /**
   * updateTranslation() edits the fr paragraph translation, not the source.
   */
  public function testUpdateTranslationUpdatesParagraphTranslation(): void {
    [$node, $paragraph] = $this->createNodeWithParagraph();
    $nid = (int) $node->id();
    $pid = (string) $paragraph->id();

    // Create the fr node and paragraph translations through core APIs
    // (shared/symmetric setup).
    $paragraph->addTranslation('fr', [
      'field_text' => ['value' => '<p>FR original</p>', 'format' => 'plain_text'],
    ])->save();
    $node->addTranslation('fr', ['title' => 'FR titre'])->save();

    $result = $this->translationService->updateTranslation($nid, 'fr', [
      'paragraphs' => [
        $pid => ['field_text' => '<p>FR mis à jour</p>'],
      ],
    ]);

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertSame(1, $result['data']['paragraph_fields_updated']);

    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache([(int) $pid]);
    $reloaded = $storage->load((int) $pid);
    $this->assertSame('<p>FR mis à jour</p>', $reloaded->getTranslation('fr')->get('field_text')->value);
    $this->assertSame('plain_text', $reloaded->getTranslation('fr')->get('field_text')->format, 'The existing format is preserved.');
    $this->assertSame('<p>EN text</p>', $reloaded->get('field_text')->value, 'The source language paragraph is untouched.');
  }

  /**
   * Unknown paragraph ids fail loudly before anything is saved.
   */
  public function testUpdateTranslationRejectsUnknownParagraphIds(): void {
    [$node, $paragraph] = $this->createNodeWithParagraph();
    $nid = (int) $node->id();
    $paragraph->addTranslation('fr', ['field_text' => 'FR'])->save();
    $node->addTranslation('fr', ['title' => 'FR titre'])->save();

    $result = $this->translationService->updateTranslation($nid, 'fr', [
      'paragraphs' => ['99999' => ['field_text' => 'X']],
    ]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unknown source paragraph id(s)', $result['error']);
  }

  /**
   * Creates an en node referencing one text paragraph.
   *
   * @return array{0: \Drupal\node\Entity\Node, 1: \Drupal\paragraphs\Entity\Paragraph}
   *   The node and its paragraph.
   */
  protected function createNodeWithParagraph(): array {
    $paragraph = Paragraph::create([
      'type' => 'text_block',
      'langcode' => 'en',
      'field_text' => ['value' => '<p>EN text</p>', 'format' => 'plain_text'],
    ]);
    $paragraph->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'EN title',
      'langcode' => 'en',
      'field_sections' => [$paragraph],
    ]);
    $node->save();

    return [$node, $paragraph];
  }

}
