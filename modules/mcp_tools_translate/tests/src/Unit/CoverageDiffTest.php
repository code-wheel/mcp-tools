<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_translate\Unit;

use Drupal\mcp_tools_translate\Service\ContentTranslationService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the translation coverage guarantee (diffCoverage()).
 */
#[Group('mcp_tools_translate')]
final class CoverageDiffTest extends UnitTestCase {

  /**
   * A fully-covered payload reports nothing missing.
   */
  public function testFullCoverageReturnsNoMissing(): void {
    $required = [
      'fields' => ['title', 'field_introduction'],
      'paragraphs' => ['1234' => ['field_text'], '1235' => ['field_button_text']],
    ];
    $translations = [
      'title' => 'Titre',
      'field_introduction' => '<p>Intro</p>',
      'paragraphs' => [
        '1234' => ['field_text' => '<p>Texte</p>'],
        '1235' => ['field_button_text' => 'En savoir plus'],
      ],
    ];

    $missing = ContentTranslationService::diffCoverage($required, $translations);

    $this->assertSame([], $missing['node_fields']);
    $this->assertSame([], $missing['paragraphs']);
  }

  /**
   * An omitted node field is reported.
   */
  public function testMissingNodeFieldIsReported(): void {
    $required = ['fields' => ['title', 'field_meta_description'], 'paragraphs' => []];
    $translations = ['title' => 'Titre'];

    $missing = ContentTranslationService::diffCoverage($required, $translations);

    $this->assertSame(['field_meta_description'], $missing['node_fields']);
    $this->assertSame([], $missing['paragraphs']);
  }

  /**
   * An omitted paragraph field is reported under its paragraph id.
   */
  public function testMissingParagraphFieldIsReported(): void {
    $required = ['fields' => ['title'], 'paragraphs' => ['1235' => ['field_button_text']]];
    $translations = ['title' => 'Titre', 'paragraphs' => []];

    $missing = ContentTranslationService::diffCoverage($required, $translations);

    $this->assertSame([], $missing['node_fields']);
    $this->assertSame(['1235' => ['field_button_text']], $missing['paragraphs']);
  }

  /**
   * A blank/whitespace value counts as missing (prevents silent content loss).
   */
  public function testEmptyStringCountsAsMissing(): void {
    $required = ['fields' => ['title', 'field_introduction'], 'paragraphs' => []];
    $translations = ['title' => 'Titre', 'field_introduction' => '   '];

    $missing = ContentTranslationService::diffCoverage($required, $translations);

    $this->assertSame(['field_introduction'], $missing['node_fields']);
  }

  /**
   * A {value, format} array with content counts as covered.
   */
  public function testTextFieldValueArrayIsCovered(): void {
    $required = ['fields' => ['field_introduction'], 'paragraphs' => []];
    $translations = [
      'field_introduction' => ['value' => '<p>Intro</p>', 'format' => 'content_format'],
    ];

    $missing = ContentTranslationService::diffCoverage($required, $translations);

    $this->assertSame([], $missing['node_fields']);
  }

  /**
   * A {value, format} array with an empty value counts as missing.
   */
  public function testEmptyValueArrayCountsAsMissing(): void {
    $required = ['fields' => ['field_introduction'], 'paragraphs' => []];
    $translations = [
      'field_introduction' => ['value' => '', 'format' => 'content_format'],
    ];

    $missing = ContentTranslationService::diffCoverage($required, $translations);

    $this->assertSame(['field_introduction'], $missing['node_fields']);
  }

}
