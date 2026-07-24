<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_translate\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for the translation service on plain nodes (no paragraphs).
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_translate')]
class ContentTranslationServiceKernelTest extends KernelTestBase {

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
    'language',
    'content_translation',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_content',
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
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'language', 'mcp_tools']);

    ConfigurableLanguage::createFromLangcode('fr')->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'article', TRUE);

    FieldStorageConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'type' => 'text_long',
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'translatable' => TRUE,
    ])->save();

    $this->setUpCurrentUser([], ['translate article node']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);

    $this->translationService = $this->container->get('mcp_tools_translate.translation');
  }

  /**
   * Creates an en source node with a body value.
   */
  protected function createSourceNode(): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => 'EN title',
      'langcode' => 'en',
      'field_body' => ['value' => '<p>EN body</p>', 'format' => 'plain_text'],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Incomplete payloads are rejected before anything is saved.
   */
  public function testTranslateContentEnforcesCoverage(): void {
    $node = $this->createSourceNode();

    $result = $this->translationService->translateContent((int) $node->id(), 'fr', [
      'title' => 'FR titre',
    ]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Incomplete translation', $result['error']);
    $this->assertFalse($this->reload((int) $node->id())->hasTranslation('fr'));
  }

  /**
   * A complete payload creates the translation, keeping the source format.
   */
  public function testTranslateContentCreatesTranslation(): void {
    $node = $this->createSourceNode();

    $result = $this->translationService->translateContent((int) $node->id(), 'fr', [
      'title' => 'FR titre',
      'field_body' => '<p>FR corps</p>',
    ]);

    $this->assertTrue($result['success'], $result['error'] ?? '');

    $translation = $this->reload((int) $node->id())->getTranslation('fr');
    $this->assertSame('FR titre', $translation->getTitle());
    $this->assertSame('<p>FR corps</p>', $translation->get('field_body')->value);
    $this->assertSame('plain_text', $translation->get('field_body')->format, 'The source text format is preserved.');
  }

  /**
   * Unknown target languages and duplicate translations are rejected.
   */
  public function testTranslateContentRejectsBadTargets(): void {
    $node = $this->createSourceNode();

    $result = $this->translationService->translateContent((int) $node->id(), 'xx', ['title' => 'X']);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString("not enabled", $result['error']);

    $ok = $this->translationService->translateContent((int) $node->id(), 'fr', [
      'title' => 'FR titre',
      'field_body' => 'FR corps',
    ]);
    $this->assertTrue($ok['success']);

    $again = $this->translationService->translateContent((int) $node->id(), 'fr', [
      'title' => 'FR 2',
      'field_body' => 'FR 2',
    ]);
    $this->assertFalse($again['success']);
    $this->assertStringContainsString('already has', $again['error']);
  }

  /**
   * updateTranslation() updates only the target language version.
   */
  public function testUpdateTranslation(): void {
    $node = $this->createSourceNode();
    $this->translationService->translateContent((int) $node->id(), 'fr', [
      'title' => 'FR titre',
      'field_body' => 'FR corps',
    ]);
    $nid = (int) $node->id();

    $result = $this->translationService->updateTranslation($nid, 'fr', [
      'title' => 'FR titre 2',
    ]);

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertSame(['title'], $result['data']['fields_updated']);
    $this->assertContains('field_body', $result['data']['untouched_translatable_fields']);

    $node = $this->reload($nid);
    $this->assertSame('FR titre 2', $node->getTranslation('fr')->getTitle());
    $this->assertSame('EN title', $node->getTitle(), 'The source language is untouched.');
  }

  /**
   * Unknown fields and missing translations fail loudly, changing nothing.
   */
  public function testUpdateTranslationFailsLoudly(): void {
    $node = $this->createSourceNode();
    $nid = (int) $node->id();

    $missing = $this->translationService->updateTranslation($nid, 'fr', ['title' => 'X']);
    $this->assertFalse($missing['success']);
    $this->assertStringContainsString("no 'fr' translation", $missing['error']);

    $this->translationService->translateContent($nid, 'fr', [
      'title' => 'FR titre',
      'field_body' => 'FR corps',
    ]);

    $unknown = $this->translationService->updateTranslation($nid, 'fr', ['bogus_field' => 'X']);
    $this->assertFalse($unknown['success']);
    $this->assertStringContainsString('unknown field(s): bogus_field', $unknown['error']);
    $this->assertSame('FR titre', $this->reload($nid)->getTranslation('fr')->getTitle());
  }

  /**
   * The mcp_update_content plugin routes language updates to this service.
   *
   * End-to-end coverage of the beta15 integration point: UpdateContent
   * resolves mcp_tools_translate.translation when the submodule is enabled.
   */
  public function testUpdateContentPluginRoutesLanguage(): void {
    $node = $this->createSourceNode();
    $nid = (int) $node->id();
    $this->translationService->translateContent($nid, 'fr', [
      'title' => 'FR titre',
      'field_body' => 'FR corps',
    ]);

    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_update_content');
    $tool->setInputValue('nid', $nid);
    $tool->setInputValue('language', 'fr');
    $tool->setInputValue('updates', ['title' => 'FR via plugin']);
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $values = $tool->getOutputValues();
    $this->assertSame('fr', $values['language']);
    $this->assertSame('FR via plugin', $this->reload($nid)->getTranslation('fr')->getTitle());
    $this->assertSame('EN title', $this->reload($nid)->getTitle());
  }

  /**
   * Reloads a node bypassing the static cache.
   */
  protected function reload(int $nid): ?NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $node = $storage->load($nid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
