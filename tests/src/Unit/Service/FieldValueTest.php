<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\mcp_tools\Service\FieldValue;
use Drupal\Tests\UnitTestCase;

/**
 * One way from a model's value to a stored one, for every tool.
 *
 * The content, batch and import tools each had a copy, and they drifted:
 * only the import took a link as "/about", and the import turned a CSV
 * "false" into TRUE and "abc" into 0.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class FieldValueTest extends UnitTestCase {

  /**
   * The value for a field of the type.
   */
  private function normalize(string $type, mixed $value): mixed {
    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getType')->willReturn($type);
    return FieldValue::normalize($this->createMock(EntityTypeManagerInterface::class), $definition, $value);
  }

  /**
   * @dataProvider values
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('values')]
  public function testAValueWithAPlainMeaningIsStored(string $type, mixed $given, mixed $stored): void {
    $this->assertSame($stored, $this->normalize($type, $given));
  }

  /**
   * Type, given, stored.
   */
  public static function values(): array {
    return [
      'a path link' => ['link', '/about', ['uri' => 'internal:/about']],
      'a path link with no slash' => ['link', 'about', ['uri' => 'internal:/about']],
      'a web link' => ['link', 'https://example.com', ['uri' => 'https://example.com']],
      'an entity link' => ['link', 'entity:node/1', ['uri' => 'entity:node/1']],
      'a reference by number' => ['entity_reference', 5, ['target_id' => 5]],
      'a reference by number as text' => ['entity_reference', '5', ['target_id' => 5]],
      'a reference given whole' => ['entity_reference', ['target_id' => 5], ['target_id' => 5]],
      'a date' => ['datetime', '2024-01-15', ['value' => '2024-01-15']],
      'no as text' => ['boolean', 'false', FALSE],
      'no as a word' => ['boolean', 'no', FALSE],
      'yes as one' => ['boolean', '1', TRUE],
      'a whole number as text' => ['integer', '42', 42],
      'a decimal as text' => ['decimal', '4.5', 4.5],
      'a plain string' => ['string', 'plain value', 'plain value'],
    ];
  }

  /**
   * @dataProvider unclear
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('unclear')]
  public function testAValueWithNoPlainMeaningIsLeftForValidation(string $type, mixed $given): void {
    $this->assertSame($given, $this->normalize($type, $given), 'A value was changed into one nobody gave.');
  }

  /**
   * Type, given: each left as it is, so Drupal's validation says why.
   */
  public static function unclear(): array {
    return [
      'a yes or no that is neither' => ['boolean', 'maybe'],
      'a number that is a word' => ['integer', 'abc'],
      'a number with a fraction' => ['integer', '1.9'],
      'a decimal that is a word' => ['decimal', 'lots'],
    ];
  }

  /**
   * A paragraphs field and the managers to read it.
   *
   * @return array{0: \Drupal\Core\Field\FieldDefinitionInterface, 1: \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject, 2: \Drupal\Core\Entity\EntityFieldManagerInterface&\PHPUnit\Framework\MockObject\MockObject}
   *   The field, the entity type manager, the entity field manager.
   */
  private function paragraphsField(): array {
    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getType')->willReturn('entity_reference_revisions');
    $field->method('getSetting')->with('target_type')->willReturn('paragraph');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')->with('paragraph')->willReturn(TRUE);
    $type = $this->createMock(EntityTypeInterface::class);
    $type->method('getKey')->with('bundle')->willReturn('type');
    $entityTypeManager->method('getDefinition')->with('paragraph')->willReturn($type);
    return [$field, $entityTypeManager, $this->createMock(EntityFieldManagerInterface::class)];
  }

  /**
   * Plain references pass through; a value that is no list is as given.
   *
   * Moved from ContentServiceTest with the code.
   */
  public function testPlainReferencesToParagraphsPassThrough(): void {
    [$field, $entityTypeManager, $entityFieldManager] = $this->paragraphsField();
    $this->assertSame([['target_id' => 5]], FieldValue::normalize($entityTypeManager, $field, ['target_id' => 5], $entityFieldManager));
    $value = [['target_id' => 5], ['target_id' => 7, 'target_revision_id' => 9]];
    $this->assertSame($value, FieldValue::normalize($entityTypeManager, $field, $value, $entityFieldManager));
    $this->assertSame(3, FieldValue::normalize($entityTypeManager, $field, 3, $entityFieldManager));
    // Without the field manager the value is as given, as for the import.
    $this->assertSame([['type' => 'text']], FieldValue::normalize($entityTypeManager, $field, [['type' => 'text']]));
  }

  /**
   * An item with a "type" is made, its fields found with or without field_.
   *
   * Moved from ContentServiceTest, and "body" for "field_body" added.
   */
  public function testAnItemWithATypeIsMade(): void {
    [$field, $entityTypeManager, $entityFieldManager] = $this->paragraphsField();
    $text = $this->createMock(FieldDefinitionInterface::class);
    $text->method('getType')->willReturn('string');
    $entityFieldManager->method('getFieldDefinitions')->with('paragraph', 'text_block')->willReturn(['field_body' => $text]);
    $made = $this->createMock(EntityInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('create')->with(['type' => 'text_block', 'field_body' => 'Hello'])->willReturn($made);
    $entityTypeManager->method('getStorage')->with('paragraph')->willReturn($storage);

    $result = FieldValue::normalize($entityTypeManager, $field, [['type' => 'text_block', 'body' => 'Hello'], ['target_id' => 5]], $entityFieldManager);
    $this->assertSame([$made, ['target_id' => 5]], $result);
  }

  /**
   * A field the item's type does not have is refused, with the ones it has.
   *
   * It was dropped without a word, and the call said success.
   */
  public function testAnUnknownFieldInAnItemIsRefused(): void {
    [$field, $entityTypeManager, $entityFieldManager] = $this->paragraphsField();
    $storageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storageDefinition->method('isBaseField')->willReturn(FALSE);
    $text = $this->createMock(FieldDefinitionInterface::class);
    $text->method('getFieldStorageDefinition')->willReturn($storageDefinition);
    $entityFieldManager->method('getFieldDefinitions')->willReturn(['field_body' => $text]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("A paragraph of type 'text_block' has no field field_titel. Its fields: field_body.");
    FieldValue::normalize($entityTypeManager, $field, [['type' => 'text_block', 'field_titel' => 'x']], $entityFieldManager);
  }

}
