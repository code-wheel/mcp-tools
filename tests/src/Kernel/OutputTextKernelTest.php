<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * The keys an output's text names are the keys of the answer.
 *
 * The texts named filemime and filesize for mime and size, item_count for
 * items, free_space for size: a model looked for a key that was not there.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class OutputTextKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'dblog', 'update', 'tool', 'mcp_tools'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    file_put_contents('public://probe.txt', 'probe');
    File::create(['uri' => 'public://probe.txt', 'filename' => 'probe.txt', 'status' => 1])->save();
    $this->container->get('queue')->get('mcp_probe')->createItem(['x' => 1]);
  }

  /**
   * The keys named in a text, each in the first item of the output.
   *
   * @dataProvider outputs
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('outputs')]
  public function testTheKeysTheTextNamesAreThere(string $tool, string $output, array $keys): void {
    $instance = $this->container->get('plugin.manager.tool')->createInstance($tool);
    $instance->execute();
    $this->assertTrue($instance->getResult()->isSuccess(), (string) $instance->getResult()->getMessage());
    $text = (string) $this->container->get('plugin.manager.tool')->getDefinition($tool)->getOutputDefinition($output)->getDescription();
    $items = $instance->getOutputValues()[$output];
    $first = (array) reset($items);
    foreach ($keys as $key) {
      // As a word: "filesize" does not name size.
      $this->assertMatchesRegularExpression('/(?<![a-z_])' . $key . '(?![a-z_])/', $text, "$tool.$output does not name $key.");
      $this->assertArrayHasKey($key, $first, "$tool.$output names $key, and the answer has no such key.");
    }
  }

  /**
   * @return array<string, array{0: string, 1: string, 2: string[]}>
   *   Tool, output, keys its text names.
   */
  public static function outputs(): array {
    return [
      'files' => ['mcp_tools_get_files', 'files', ['fid', 'filename', 'uri', 'mime', 'size', 'status', 'created']],
      'orphaned files' => ['mcp_tools_find_orphaned_files', 'files', ['fid', 'filename', 'uri', 'size', 'created']],
      'queues' => ['mcp_tools_get_queue_status', 'queues', ['name', 'title', 'items']],
      'directories' => ['mcp_tools_get_file_system_status', 'directories', ['scheme', 'path', 'exists', 'writable', 'size']],
    ];
  }

}
