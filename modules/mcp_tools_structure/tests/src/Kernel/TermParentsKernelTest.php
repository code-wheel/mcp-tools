<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * The tools that make several terms take a parent by name.
 *
 * They took an id only. Given names, mcp_structure_create_terms answered
 * "Tool execution failed." and nothing else (its errors were in its data,
 * which a failed tool does not pass on), and mcp_batch_create_terms stored
 * a parent that was no term.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class TermParentsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'dblog', 'update',
    'tool', 'mcp_tools', 'mcp_tools_structure', 'mcp_tools_batch',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'taxonomy', 'mcp_tools']);
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    Vocabulary::create(['vid' => 'other', 'name' => 'Other'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Each term's parent, by name: name => parent name.
   */
  private function tree(): array {
    $tree = [];
    foreach (Term::loadMultiple() as $term) {
      if ($term->bundle() !== 'tags') {
        continue;
      }
      $parent = (int) ($term->get('parent')->target_id ?? 0);
      $tree[$term->label()] = $parent ? Term::load($parent)->label() : '';
    }
    ksort($tree);
    return $tree;
  }

  /**
   * Both tools: a tree by names, an existing parent by name, one by id.
   */
  public function testATreeByNames(): void {
    $existing = Term::create(['vid' => 'tags', 'name' => 'Food']);
    $existing->save();
    $tools = [
      'structure' => fn(array $terms) => $this->container->get('mcp_tools_structure.taxonomy')->createTerms('tags', $terms),
      'batch' => fn(array $terms) => $this->container->get('mcp_tools_batch.batch')->createMultipleTerms('tags', $terms),
    ];
    foreach ($tools as $tool => $create) {
      $result = $create([
        ['name' => "Fruit $tool", 'parent' => 'Food'],
        ['name' => "Apple $tool", 'parent' => "Fruit $tool"],
        ['name' => "Pear $tool", 'parent' => (string) $existing->id()],
      ]);
      $this->assertTrue($result['success'], $tool . ': ' . json_encode($result));
    }
    $this->assertSame([
      'Apple batch' => 'Fruit batch',
      'Apple structure' => 'Fruit structure',
      'Food' => '',
      'Fruit batch' => 'Food',
      'Fruit structure' => 'Food',
      'Pear batch' => 'Food',
      'Pear structure' => 'Food',
    ], $this->tree());
  }

  /**
   * The answer says where the term went: under its parent, or at the top.
   *
   * It said "created" only. A model that named the parent in its thinking
   * and left it out of the call never saw that the term was at the top
   * (7 of 24 task passes of one kind of request, framework 2026-11-23).
   */
  public function testTheAnswerSaysWhereTheTermWent(): void {
    $taxonomy = $this->container->get('mcp_tools_structure.taxonomy');
    $top = $taxonomy->createTerm('tags', 'Food');
    $this->assertStringContainsString('at the top level (no parent given)', $top['data']['message']);
    $under = $taxonomy->createTerm('tags', 'Fruit', ['parent' => 'Food']);
    $this->assertStringContainsString("under 'Food' (" . $top['data']['tid'] . ')', $under['data']['message']);
  }

  /**
   * A parent that is no term is refused, and the answer says why.
   */
  public function testAParentThatIsNoTermIsSaid(): void {
    Term::create(['vid' => 'other', 'name' => 'Elsewhere'])->save();

    $result = $this->container->get('mcp_tools_structure.taxonomy')->createTerms('tags', [
      ['name' => 'Kept'],
      ['name' => 'Lost', 'parent' => 'Elsewhere'],
    ]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString("'Lost': There is no term 'Elsewhere' in vocabulary 'tags'", $result['error'] ?? '', 'The failure says nothing a model can read.');
    $this->assertStringContainsString('1 of 2 terms were not made (the others were)', $result['error']);

    $result = $this->container->get('mcp_tools_batch.batch')->createMultipleTerms('tags', [['name' => 'Also lost', 'parent' => 'Nowhere']]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString("There is no term 'Nowhere'", $result['error']);
    $this->assertSame(['Kept' => ''], $this->tree());
  }

  /**
   * The tool that makes one term takes a parent by name, or by id as a number.
   *
   * Its input was an integer, so "parent": "Food" was refused by the input
   * check, though the tools that make several terms take names.
   */
  public function testTheOneTermToolTakesAParentByName(): void {
    $food = Term::create(['vid' => 'tags', 'name' => 'Food']);
    $food->save();
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
    $session = $this->createMock(SessionInterface::class);
    foreach (['Fruit' => 'Food', 'Bread' => (int) $food->id()] as $n => $parent) {
      $response = $handler->handle((new CallToolRequest('mcp_structure_create_term', ['vocabulary' => 'tags', 'name' => $n, 'parent' => $parent]))->withId($n), $session);
      $this->assertInstanceOf(Response::class, $response);
      $this->assertFalse($response->result->isError, var_export($parent, TRUE) . ': ' . $response->result->content[0]->text);
    }
    $this->assertSame(['Bread' => 'Food', 'Food' => '', 'Fruit' => 'Food'], $this->tree());
  }

}
