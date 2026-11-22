<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\ToolNameSuggester;
use Drupal\Tests\UnitTestCase;

/**
 * A tool name that does not exist is answered with the closest real names.
 *
 * The cases are names a local model made up in the evaluation runs.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\ToolNameSuggester::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolNameSuggesterTest extends UnitTestCase {

  private const NAMES = [
    'mcp_tools_list_content_types',
    'mcp_tools_get_config',
    'mcp_tools_list_config',
    'mcp_tools_get_site_status',
    'mcp_tools_get_system_status',
    'mcp_structure_create_content_type',
    'mcp_structure_add_field',
    'mcp_structure_list_field_types',
    'mcp_create_content',
    'mcp_batch_create_content',
    'mcp_cache_clear_all',
  ];

  /**
   * The names a model made up, and the tool it meant.
   *
   * The provider is named twice: Drupal 10 runs PHPUnit 9, which reads only
   * the annotation, and PHPUnit 11 wants the attribute.
   *
   * @dataProvider madeUpNames
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('madeUpNames')]
  public function testTheToolThatWasMeantComesFirst(string $madeUp, string $meant): void {
    $closest = ToolNameSuggester::closest($madeUp, self::NAMES);
    $this->assertNotSame([], $closest, $madeUp);
    $this->assertSame($meant, $closest[0], $madeUp . ' gave ' . implode(', ', $closest));
  }

  /**
   * Data provider.
   */
  public static function madeUpNames(): array {
    return [
      'no module in the name' => ['mcp_list_content_types', 'mcp_tools_list_content_types'],
      'another module in the name' => ['mcp_structure_list_content_types', 'mcp_tools_list_content_types'],
      'the words the other way round' => ['mcp_config_get', 'mcp_tools_get_config'],
      'singular for plural' => ['mcp_tools_list_field_type', 'mcp_structure_list_field_types'],
      'a typing mistake' => ['mcp_tools_get_site_staus', 'mcp_tools_get_site_status'],
      'the plugin ID form' => ['mcp_tools:get_config', 'mcp_tools_get_config'],
    ];
  }

  /**
   * At most three, and none for a name that is close to nothing.
   */
  public function testFewAndOnlyCloseOnes(): void {
    $this->assertSame([], ToolNameSuggester::closest('zebra_crossing', self::NAMES));
    $this->assertSame([], ToolNameSuggester::closest('', self::NAMES));
    $this->assertSame([], ToolNameSuggester::closest('mcp_tools', self::NAMES));

    $closest = ToolNameSuggester::closest('mcp_create_content', ['mcp_a_create_content', 'mcp_b_create_content', 'mcp_c_create_content', 'mcp_d_create_content']);
    $this->assertCount(3, $closest);

    // A name that exists is not a question for this class, but it must not
    // be offered other tools as if it were wrong.
    $this->assertSame('mcp_create_content', ToolNameSuggester::closest('mcp_create_content', self::NAMES)[0]);
  }

}
