<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Tool;

use Drupal\mcp_tools_batch\Plugin\tool\Tool\CreateMultipleContent;
use Drupal\mcp_tools_content\Plugin\tool\Tool\CreateContent;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\Attribute\Tool;

/**
 * The tools that make content one at a time name the import.
 *
 * Neither said there is one, so asked to import 110 rows, or a list again
 * with one row changed, a model made the nodes one by one: an import tool
 * was called in 11 of 120 such task passes (framework, 2026-11-23).
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ImportPointerTest extends UnitTestCase {

  public function testTheContentToolsNameTheImport(): void {
    foreach ([CreateContent::class, CreateMultipleContent::class] as $class) {
      $description = (new \ReflectionClass($class))->getAttributes(Tool::class)[0]->getArguments()['description']->getUntranslatedString();
      $this->assertStringContainsString('mcp_migration_import_json', $description, $class);
      $this->assertStringContainsString('a second run updates', $description, $class);
    }
  }

}
