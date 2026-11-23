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
   * A name without "tools_" gets it, and no other name is changed.
   *
   * In 161 runs of a local model, 64 of 78 made-up names were a real name
   * with only "tools_" left out: mcp_search_content, mcp_list_content_types,
   * mcp_execute_tool. That one is looked up and run.
   */
  public function testANameWithoutToolsGetsThem(): void {
    $this->assertSame('mcp_tools_search_content', ToolNameSuggester::withTools('mcp_search_content'));
    $this->assertSame('mcp_tools_execute_tool', ToolNameSuggester::withTools('mcp_execute_tool'));
    // It has them, or it is not one of these names at all.
    $this->assertNull(ToolNameSuggester::withTools('mcp_tools_search_content'));
    $this->assertNull(ToolNameSuggester::withTools('search_content'));
    $this->assertNull(ToolNameSuggester::withTools('other_search_content'));
    $this->assertNull(ToolNameSuggester::withTools('mcp_'));
    $this->assertNull(ToolNameSuggester::withTools(''));
  }

  /**
   * A name with "tools_" put in loses it, and meant() tries both ways.
   */
  public function testANameWithToolsTooManyLosesThem(): void {
    $this->assertSame('mcp_redirect_create', ToolNameSuggester::withoutTools('mcp_tools_redirect_create'));
    $this->assertNull(ToolNameSuggester::withoutTools('mcp_redirect_create'));
    $this->assertNull(ToolNameSuggester::withoutTools('mcp_tools_'));
    $this->assertSame('mcp_redirect_create', ToolNameSuggester::meant('mcp_tools_redirect_create'));
    $this->assertSame('mcp_tools_search_content', ToolNameSuggester::meant('mcp_search_content'));
    $this->assertNull(ToolNameSuggester::meant('zebra_crossing'));
  }

  /**
   * The one real name that ends the same, when the module part is wrong.
   */
  public function testTheModulePartOfANameIsWrong(): void {
    $names = ['mcp_structure_list_field_types', 'mcp_structure_get_role_permissions', 'mcp_create_content', 'mcp_batch_create_content', 'mcp_tools_search_content', 'mcp_cache_get_status'];
    $this->assertSame('mcp_structure_list_field_types', ToolNameSuggester::meantAmong('mcp_tools_list_field_types', $names));
    $this->assertSame('mcp_structure_get_role_permissions', ToolNameSuggester::meantAmong('mcp_tools_get_role_permissions', $names));
    // "tools_" too few still comes first.
    $this->assertSame('mcp_tools_search_content', ToolNameSuggester::meantAmong('mcp_search_content', $names));
    // "tools_" too many is the rule before: mcp_create_content is there.
    $this->assertSame('mcp_create_content', ToolNameSuggester::meantAmong('mcp_tools_create_content', $names));
    // Two real names end the same: nothing is meant.
    $this->assertNull(ToolNameSuggester::meantAmong('mcp_structure_create_content', $names));
    // One word after the module is too little to go by.
    $this->assertNull(ToolNameSuggester::meantAmong('mcp_tools_status', $names));
    $this->assertNull(ToolNameSuggester::meantAmong('zebra_get_status', $names));
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
