<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinitionInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\ToolApiSchemaConverter::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolApiSchemaConverterTest extends UnitTestCase {

  public function testToolDefinitionToAnnotationsReadOnlyHintIsDerived(): void {
    $converter = new ToolApiSchemaConverter();

    $read = new ToolDefinition([
      'id' => 'mcp_tools:test',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Read Tool'),
      'description' => $this->markup('Read tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
    ]);

    $readAnnotations = $converter->toolDefinitionToAnnotations($read);
    $this->assertSame('Read Tool', $readAnnotations['title']);
    $this->assertTrue($readAnnotations['readOnlyHint']);
    $this->assertFalse($readAnnotations['openWorldHint']);
    $this->assertNull($readAnnotations['destructiveHint']);

    $write = new ToolDefinition([
      'id' => 'mcp_tools:test_write',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Write Tool'),
      'description' => $this->markup('Write tool'),
      'operation' => ToolOperation::Transform,
      'destructive' => TRUE,
    ]);

    $writeAnnotations = $converter->toolDefinitionToAnnotations($write);
    $this->assertFalse($writeAnnotations['readOnlyHint']);
    $this->assertTrue($writeAnnotations['destructiveHint']);
  }

  public function testToolDefinitionToInputSchemaMapsTypesAndConstraints(): void {
    $converter = new ToolApiSchemaConverter();

    $name = $this->mockInputDefinition(
      dataType: 'string',
      required: TRUE,
      description: 'Name',
      constraints: ['Length' => ['min' => 2, 'max' => 10]],
    );

    $count = $this->mockInputDefinition(
      dataType: 'integer',
      required: FALSE,
      description: 'Count',
      constraints: ['Range' => ['min' => 1, 'max' => 3]],
    );

    $pattern = $this->mockInputDefinition(
      dataType: 'string',
      required: FALSE,
      description: NULL,
      constraints: ['Regex' => ['pattern' => '^[a-z]+$']],
    );

    $choice = $this->mockInputDefinition(
      dataType: 'string',
      required: FALSE,
      description: NULL,
      constraints: ['AllowedValues' => ['choices' => ['a', 'b']]],
    );

    $entityRef = $this->mockInputDefinition(
      dataType: 'entity:node',
      required: FALSE,
      description: NULL,
      constraints: [],
    );

    $tags = $this->mockInputDefinition(
      dataType: 'list',
      required: FALSE,
      description: 'Tags',
      constraints: [],
      multiple: TRUE,
    );

    $meta = $this->mockInputDefinition(
      dataType: 'map',
      required: FALSE,
      description: NULL,
      constraints: [],
    );

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:test',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Test'),
      'description' => $this->markup('Test tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => [
        'name' => $name,
        'count' => $count,
        'pattern' => $pattern,
        'choice' => $choice,
        'ref' => $entityRef,
        'tags' => $tags,
        'meta' => $meta,
      ],
    ]);

    $schema = $converter->toolDefinitionToInputSchema($definition);

    $this->assertSame('object', $schema['type']);
    $this->assertContains('name', $schema['required']);

    $properties = $schema['properties'];
    $this->assertSame('string', $properties['name']['type']);
    $this->assertSame(2, $properties['name']['minLength']);
    $this->assertSame(10, $properties['name']['maxLength']);

    $this->assertSame('integer', $properties['count']['type']);
    $this->assertSame(1, $properties['count']['minimum']);
    $this->assertSame(3, $properties['count']['maximum']);

    $this->assertSame('^[a-z]+$', $properties['pattern']['pattern']);
    $this->assertSame(['a', 'b'], $properties['choice']['enum']);

    $this->assertSame('string', $properties['ref']['type']);
    $this->assertStringContainsString('Entity objects should be passed', $properties['ref']['description']);

    $this->assertSame('array', $properties['tags']['type']);
    $this->assertInstanceOf(\stdClass::class, $properties['tags']['items']);

    $this->assertSame('object', $properties['meta']['type']);
  }

  public function testToolDefinitionToInputSchemaEncodesEmptyPropertiesAsObject(): void {
    $converter = new ToolApiSchemaConverter();

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:test_empty',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Empty'),
      'description' => $this->markup('No inputs'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => [],
    ]);

    $schema = $converter->toolDefinitionToInputSchema($definition);

    $this->assertSame('object', $schema['type']);
    $this->assertInstanceOf(\stdClass::class, $schema['properties']);
    $this->assertSame('{}', json_encode($schema['properties']));
  }

  public function testListWithoutItemDefinitionProducesUnconstrainedItemsSchema(): void {
    $converter = new ToolApiSchemaConverter();

    $items = $this->mockInputDefinition(
      dataType: 'list',
      required: TRUE,
      description: 'Array of content items',
      constraints: [],
    );

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:batch_test',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Batch Test'),
      'description' => $this->markup('Test batch tool'),
      'operation' => ToolOperation::Write,
      'destructive' => FALSE,
      'input_definitions' => [
        'items' => $items,
      ],
    ]);

    $schema = $converter->toolDefinitionToInputSchema($definition);
    $properties = $schema['properties'];

    // items must be typed as array.
    $this->assertSame('array', $properties['items']['type']);

    // Without a ListInputDefinition, items schema must be unconstrained ({})
    // so complex objects like {"title": "...", "fields": {...}} are accepted.
    $this->assertInstanceOf(\stdClass::class, $properties['items']['items']);
    $this->assertSame('{}', json_encode($properties['items']['items']));
  }

  public function testInputSchemaRejectsAdditionalProperties(): void {
    $converter = new ToolApiSchemaConverter();

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:delete_test',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Delete'),
      'description' => $this->markup('Delete tool'),
      'operation' => ToolOperation::Write,
      'destructive' => TRUE,
      'input_definitions' => [
        'nid' => $this->mockInputDefinition(dataType: 'integer', required: TRUE, description: 'Node ID', constraints: []),
        'language' => $this->mockInputDefinition(dataType: 'string', required: FALSE, description: 'Language', constraints: []),
      ],
    ]);

    $schema = $converter->toolDefinitionToInputSchema($definition);

    // An undeclared key (e.g. "language" on a tool without translation
    // support) must fail validation instead of being silently dropped and
    // falling through to a destructive default.
    $this->assertFalse($schema['additionalProperties']);
    $this->assertSame(['nid', 'language'], array_keys($schema['properties']));
    $this->assertSame(['nid'], $schema['required']);
  }

  public function testEmptyInputSchemaRejectsAdditionalProperties(): void {
    $converter = new ToolApiSchemaConverter();

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:test_empty_strict',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Empty'),
      'description' => $this->markup('No inputs'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => [],
    ]);

    $schema = $converter->toolDefinitionToInputSchema($definition);

    $this->assertFalse($schema['additionalProperties']);
    $this->assertInstanceOf(\stdClass::class, $schema['properties']);
  }

  public function testToolDefinitionToOutputSchemaDescribesEnvelope(): void {
    $converter = new ToolApiSchemaConverter();

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:out_test',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Out'),
      'description' => $this->markup('Out tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'output_definitions' => [
        'nid' => new ContextDefinition(data_type: 'integer', label: 'Node ID'),
        'language' => new ContextDefinition(data_type: 'string', label: 'Language', required: FALSE),
        'items' => new ContextDefinition(data_type: 'list', label: 'Items'),
      ],
    ]);

    $schema = $converter->toolDefinitionToOutputSchema($definition);

    // The schema describes the structured envelope the handler returns;
    // every data property is nullable because conditional outputs are
    // backfilled with NULL.
    $this->assertSame(['success', 'message'], $schema['required']);
    $this->assertSame('boolean', $schema['properties']['success']['type']);
    $data = $schema['properties']['data'];
    $this->assertSame(['integer', 'null'], $data['properties']['nid']['type']);
    $this->assertSame(['string', 'null'], $data['properties']['language']['type']);
    $this->assertSame(['array', 'null'], $data['properties']['items']['type']);
  }

  public function testToolDefinitionToOutputSchemaNullWithoutOutputs(): void {
    $definition = new ToolDefinition([
      'id' => 'mcp_tools:no_out',
      'provider' => 'mcp_tools',
      'label' => $this->markup('None'),
      'description' => $this->markup('No outputs'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
    ]);

    $this->assertNull((new ToolApiSchemaConverter())->toolDefinitionToOutputSchema($definition));
  }

  private function mockInputDefinition(
    string $dataType,
    bool $required,
    ?string $description,
    array $constraints,
    bool $multiple = FALSE,
  ): InputDefinitionInterface {
    $definition = $this->createMock(InputDefinitionInterface::class);
    $definition->method('getDataType')->willReturn($dataType);
    $definition->method('isRequired')->willReturn($required);
    $definition->method('getDescription')->willReturn($description ? $this->markup($description) : NULL);
    $definition->method('getConstraints')->willReturn($constraints);
    $definition->method('isMultiple')->willReturn($multiple);
    return $definition;
  }

  private function markup(string $string): TranslatableMarkup {
    $translation = new class implements TranslationInterface {

      public function translate($string, array $args = [], array $options = []): TranslatableMarkup {
        return new TranslatableMarkup((string) $string, $args, $options, $this);
      }

      public function translateString(TranslatableMarkup $translated_string): string {
        return $translated_string->getUntranslatedString();
      }

      public function formatPlural($count, $singular, $plural, array $args = [], array $options = []): TranslatableMarkup {
        $useSingular = (int) $count === 1;
        $string = $useSingular ? (string) $singular : str_replace('@count', (string) $count, (string) $plural);
        return new TranslatableMarkup($string, $args, $options, $this);
      }

    };

    return new TranslatableMarkup($string, [], [], $translation);
  }

}
