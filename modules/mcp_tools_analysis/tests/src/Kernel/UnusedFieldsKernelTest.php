<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * An empty field on a vocabulary is found.
 *
 * The query filtered terms on "type"; a term's bundle key is "vid". It
 * threw, the field was skipped, and the answer was "all fields are in use".
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class UnusedFieldsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'taxonomy', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_analysis'];

  /**
   * The empty term field is listed.
   */
  public function testAnEmptyTermFieldIsFound(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    FieldStorageConfig::create(['field_name' => 'field_x', 'entity_type' => 'taxonomy_term', 'type' => 'string'])->save();
    FieldConfig::create(['field_name' => 'field_x', 'entity_type' => 'taxonomy_term', 'bundle' => 'tags', 'label' => 'X'])->save();
    $result = $this->container->get('mcp_tools_analysis.field_analyzer')->findUnusedFields();
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertContains('field_x', array_column($result['data']['unused_fields'], 'field_name'), 'An empty field on a vocabulary was not found: ' . json_encode($result['data']['suggestions']));
  }

}
