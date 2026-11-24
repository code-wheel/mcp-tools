<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * Counting content and finding the content types that have a field.
 *
 * Two questions a local model could not answer on a site of 24 content
 * types and 33 items (the hard task set of 2026-11-22): how many articles
 * are published (it took the items returned for the count, and could not
 * filter by status), and which types have a price field (it read the whole
 * list four times, then asked for each type on its own).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ContentListingKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'dblog', 'update', 'tool', 'mcp_tools'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    foreach (['article', 'page', 'product', 'service'] as $type) {
      NodeType::create(['type' => $type, 'name' => ucfirst($type)])->save();
    }
    FieldStorageConfig::create(['field_name' => 'field_price', 'entity_type' => 'node', 'type' => 'decimal'])->save();
    foreach (['product', 'service'] as $type) {
      FieldConfig::create(['field_name' => 'field_price', 'entity_type' => 'node', 'bundle' => $type, 'label' => 'Price'])->save();
    }
    for ($i = 1; $i <= 30; $i++) {
      Node::create(['type' => 'article', 'title' => 'Sale ' . $i, 'status' => $i <= 20 ? 1 : 0])->save();
    }
    Node::create(['type' => 'page', 'title' => 'About', 'status' => 1])->save();
  }

  /**
   * Runs a tool the way the server does, and gives back its outputs.
   */
  private function callTool(string $tool, array $values): array {
    $instance = $this->container->get('plugin.manager.tool')->createInstance($tool);
    foreach ($values as $name => $value) {
      $instance->setInputValue($name, $value);
    }
    $instance->execute();
    $this->assertTrue($instance->getResult()->isSuccess(), (string) $instance->getResult()->getMessage());
    return $instance->getOutputValues();
  }

  /**
   * The listing counts what matches, and filters by status.
   */
  public function testPublishedContentCanBeCounted(): void {
    $published = $this->callTool('mcp_tools_get_recent_content', ['content_type' => 'article', 'status' => 'published', 'limit' => 5]);
    $this->assertSame(20, $published['matching']);
    $this->assertSame(5, $published['total'], 'total is what came back, at most the limit.');
    $this->assertSame(['published'], array_values(array_unique(array_column($published['content'], 'status'))));

    $this->assertSame(10, $this->callTool('mcp_tools_get_recent_content', ['content_type' => 'article', 'status' => 'unpublished'])['matching']);
    $this->assertSame(30, $this->callTool('mcp_tools_get_recent_content', ['content_type' => 'article'])['matching']);
    $this->assertSame(31, $this->callTool('mcp_tools_get_recent_content', [])['matching']);
  }

  /**
   * The type list can be asked for the types that have one field.
   */
  public function testTheTypesWithAField(): void {
    foreach (['field_price', 'price'] as $field) {
      $result = $this->callTool('mcp_tools_list_content_types', ['field' => $field]);
      $this->assertSame(['product', 'service'], array_column($result['types'], 'id'), $field);
      $this->assertSame(2, $result['total_types']);
    }
    $this->assertSame(4, $this->callTool('mcp_tools_list_content_types', [])['total_types']);
  }

  /**
   * A number or a yes/no for the status, through the server's call handler.
   *
   * A model sent status: 1, and the input check refused a number for a
   * text input before the tool could read it.
   */
  public function testTheStatusAsANumberOrAYesNo(): void {
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
    $session = $this->createMock(SessionInterface::class);
    foreach ([[1, 20], ['1', 20], [TRUE, 20], [0, 10], [FALSE, 10], ['unpublished', 10]] as $n => [$status, $matching]) {
      $response = $handler->handle((new CallToolRequest('mcp_tools_get_recent_content', ['content_type' => 'article', 'status' => $status]))->withId($n), $session);
      $this->assertInstanceOf(Response::class, $response);
      $this->assertFalse($response->result->isError, var_export($status, TRUE) . ': ' . $response->result->content[0]->text);
      $this->assertSame($matching, $response->result->structuredContent['data']['matching'], var_export($status, TRUE));
    }
  }

  /**
   * A choice that is none of the tool's is refused, not taken as the default.
   *
   * The watchdog tool gave its summary for an unknown mode and named the
   * mode asked for ("error" read as errors); the listing sorted by
   * "changed" for any sort.
   */
  public function testAChoiceThatIsNoneIsRefused(): void {
    foreach ([['mcp_tools_analyze_watchdog', 'mode', 'error', 'mode is summary, errors or recent'], ['mcp_tools_get_recent_content', 'sort', 'title', 'sort is created or changed']] as [$tool, $input, $given, $said]) {
      $instance = $this->container->get('plugin.manager.tool')->createInstance($tool);
      $instance->setInputValue($input, $given);
      $instance->execute();
      $this->assertFalse($instance->getResult()->isSuccess(), "$tool took $input '$given' as its default.");
      $this->assertStringContainsString($said, (string) $instance->getResult()->getMessage());
    }
    $this->assertSame('created', $this->callTool('mcp_tools_get_recent_content', ['sort' => 'created'])['sorted_by'] ?? NULL);
  }

  /**
   * A total counts all that match, not the ones a limit lets through.
   *
   * Search said "total": 5 when 12 matched, and terms the same, so a model
   * took the first page for all there was.
   */
  public function testATotalCountsAllThatMatch(): void {
    for ($n = 1; $n <= 12; $n++) {
      Node::create(['type' => 'article', 'title' => "Harvest note $n", 'status' => 1])->save();
    }
    $found = $this->callTool('mcp_tools_search_content', ['query' => 'Harvest', 'limit' => 5]);
    $this->assertSame(12, $found['total']);
    $this->assertCount(5, $found['results']);

    $this->installEntitySchema('taxonomy_term');
    \Drupal\taxonomy\Entity\Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    for ($n = 1; $n <= 7; $n++) {
      \Drupal\taxonomy\Entity\Term::create(['vid' => 'tags', 'name' => "Tag $n"])->save();
    }
    $terms = $this->callTool('mcp_tools_get_terms', ['vocabulary' => 'tags', 'limit' => 3]);
    $this->assertSame(7, $terms['total']);
    $this->assertCount(3, $terms['terms']);
  }

  /**
   * The watchdog's lists say how many there are, besides the ones shown.
   *
   * They gave total_returned only: 100 entries read as all.
   */
  public function testTheWatchdogListsHaveATotal(): void {
    $logger = $this->container->get('logger.factory')->get('mcp_probe');
    for ($n = 1; $n <= 6; $n++) {
      $logger->error('Probe failure @n', ['@n' => $n]);
    }
    $errors = $this->callTool('mcp_tools_analyze_watchdog', ['mode' => 'errors', 'limit' => 4])['data'];
    $this->assertSame(4, $errors['total_returned']);
    $this->assertGreaterThanOrEqual(6, $errors['total'], 'The total counted only the entries shown.');
    $this->assertStringContainsString('Probe failure 6', $errors['entries'][0]['message'], 'The newest entry is not first.');
  }

}
