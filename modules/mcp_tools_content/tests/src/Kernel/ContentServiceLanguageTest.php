<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the delete safety guard and per-language delete of ContentService.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ContentService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
class ContentServiceLanguageTest extends KernelTestBase {

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
  ];

  /**
   * The content service under test.
   */
  protected ContentService $contentService;

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

    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'article', TRUE);

    $this->setUpCurrentUser();
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);

    $this->contentService = $this->container->get('mcp_tools_content.content');
  }

  /**
   * Creates an en source node with an it translation.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createTranslatedNode(): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => 'EN title',
      'langcode' => 'en',
    ]);
    $node->addTranslation('it', ['title' => 'IT titolo']);
    $node->save();
    return $node;
  }

  /**
   * Reloads a node bypassing the static cache.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The freshly loaded node, or NULL when it no longer exists.
   */
  protected function reload(int $nid): ?NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $node = $storage->load($nid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * A bare nid delete is refused and lists the language versions.
   */
  public function testBareDeleteIsRefused(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->deleteContent($nid);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Refused', $result['error']);
    $this->assertStringContainsString('en, it', $result['error']);
    $this->assertStringContainsString('confirm_delete_all', $result['error']);
    $this->assertNotNull($this->reload($nid), 'Nothing was deleted.');
  }

  /**
   * Passing language and confirm_delete_all together is ambiguous.
   */
  public function testLanguageAndConfirmTogetherRejected(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->deleteContent($nid, 'it', TRUE);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Ambiguous', $result['error']);
    $this->assertNotNull($this->reload($nid));
    $this->assertTrue($this->reload($nid)->hasTranslation('it'));
  }

  /**
   * Deleting a language the node does not have is an error.
   */
  public function testDeleteUnknownTranslationRejected(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->deleteContent($nid, 'fr');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString("no 'fr' translation", $result['error']);
    $this->assertStringContainsString('en, it', $result['error']);
  }

  /**
   * The source language cannot be deleted while translations exist.
   */
  public function testDeleteSourceLanguageRejected(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->deleteContent($nid, 'en');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('source language', $result['error']);
    $this->assertNotNull($this->reload($nid));
  }

  /**
   * Deleting the only language equals a full delete and is redirected.
   */
  public function testDeleteOnlyLanguageRejected(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Single', 'langcode' => 'en']);
    $node->save();

    $result = $this->contentService->deleteContent((int) $node->id(), 'en');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('only language version', $result['error']);
    $this->assertStringContainsString('confirm_delete_all', $result['error']);
  }

  /**
   * A translation delete keeps all other languages and earlier revisions.
   */
  public function testDeleteTranslationKeepsOtherLanguagesAndRevisions(): void {
    $node = $this->createTranslatedNode();
    $nid = (int) $node->id();
    $firstRevisionId = (int) $node->getRevisionId();

    $result = $this->contentService->deleteContent($nid, 'it');

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertSame('it', $result['data']['language']);
    $this->assertSame(['it'], $result['data']['languages_deleted']);
    $this->assertSame(['en'], $result['data']['remaining_languages']);
    $this->assertStringContainsString("Deleted the 'it' translation", $result['data']['message']);

    $reloaded = $this->reload($nid);
    $this->assertNotNull($reloaded);
    $this->assertSame(['en'], array_keys($reloaded->getTranslationLanguages()));
    $this->assertSame('EN title', $reloaded->getTitle());

    // The removal created a new revision; the previous one still carries the
    // removed translation as a recovery path.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertGreaterThan($firstRevisionId, (int) $reloaded->getRevisionId());
    $previous = $storage->loadRevision($firstRevisionId);
    $this->assertInstanceOf(NodeInterface::class, $previous);
    $this->assertTrue($previous->hasTranslation('it'));
    $this->assertSame('IT titolo', $previous->getTranslation('it')->getTitle());
  }

  /**
   * A confirmed full delete removes the node (permanently without trash).
   */
  public function testConfirmDeleteAllRemovesNode(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->deleteContent($nid, NULL, TRUE);

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertSame(['en', 'it'], $result['data']['languages_deleted']);
    $this->assertSame([], $result['data']['remaining_languages']);
    $this->assertStringContainsString('permanent', $result['data']['message']);
    $this->assertNull($this->reload($nid));
  }

  /**
   * Unknown fields in updates are rejected before anything is saved.
   */
  public function testUpdateUnknownFieldRejected(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->updateContent($nid, [
      'title' => 'should not be saved',
      'field_bogus' => 'x',
    ]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('field_bogus', $result['error']);
    $this->assertStringContainsString('Nothing was updated', $result['error']);
    $this->assertSame('EN title', $this->reload($nid)->getTitle());
  }

  /**
   * The paragraphs map is redirected to the per-language update path.
   */
  public function testUpdateParagraphsMapRejectedOnSourcePath(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->updateContent($nid, [
      'paragraphs' => ['1' => ['field_x' => 'y']],
    ]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('per-language updates', $result['error']);
  }

  /**
   * A successful source update names the affected language.
   */
  public function testUpdateResponseNamesSourceLanguage(): void {
    $nid = (int) $this->createTranslatedNode()->id();

    $result = $this->contentService->updateContent($nid, ['title' => 'EN updated']);

    $this->assertTrue($result['success'], $result['error'] ?? '');
    $this->assertSame('en', $result['data']['language']);
    $this->assertSame(['title'], $result['data']['fields_updated']);
    $this->assertStringContainsString("'en'", $result['data']['message']);
    $this->assertSame('EN updated', $this->reload($nid)->getTitle());
    $this->assertSame('IT titolo', $this->reload($nid)->getTranslation('it')->getTitle(), 'Translation untouched.');
  }

}
