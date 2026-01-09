<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_jsonapi\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\mcp_tools_jsonapi\Form\JsonApiSettingsForm;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for JsonApiSettingsForm.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_jsonapi\Form\JsonApiSettingsForm::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_jsonapi')]
final class JsonApiSettingsFormTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private ResourceTypeRepositoryInterface $resourceTypeRepository;
  private ConfigFactoryInterface $configFactory;
  private Config $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->resourceTypeRepository = $this->createMock(ResourceTypeRepositoryInterface::class);
    $this->resourceTypeRepository->method('all')->willReturn([]);

    $this->config = $this->createMock(Config::class);
    $this->config->method('get')->willReturnMap([
      ['allowed_entity_types', []],
      ['blocked_entity_types', []],
      ['allow_write_operations', FALSE],
      ['max_items_per_page', 50],
      ['include_relationships', FALSE],
    ]);
    $this->config->method('set')->willReturnSelf();
    $this->config->method('save')->willReturn($this->config);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('getEditable')->with('mcp_tools_jsonapi.settings')->willReturn($this->config);
    $this->configFactory->method('get')->with('mcp_tools_jsonapi.settings')->willReturn($this->config);

    // Set up container for string translation.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  private function createForm(): JsonApiSettingsForm {
    $form = new class(
      $this->entityTypeManager,
      $this->resourceTypeRepository,
    ) extends JsonApiSettingsForm {
      public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        ResourceTypeRepositoryInterface $resourceTypeRepository,
      ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->resourceTypeRepository = $resourceTypeRepository;
      }

      private EntityTypeManagerInterface $entityTypeManager;
      private ResourceTypeRepositoryInterface $resourceTypeRepository;

      public function setConfigFactory(ConfigFactoryInterface $configFactory): void {
        $this->configFactory = $configFactory;
      }
    };
    $form->setConfigFactory($this->configFactory);
    return $form;
  }

  public function testGetFormId(): void {
    $form = $this->createForm();
    $this->assertSame('mcp_tools_jsonapi_settings', $form->getFormId());
  }

  public function testGetEditableConfigNames(): void {
    $form = $this->createForm();

    // Use reflection to call protected method.
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('getEditableConfigNames');
    $method->setAccessible(TRUE);

    $names = $method->invoke($form);
    $this->assertSame(['mcp_tools_jsonapi.settings'], $names);
  }

  public function testBuildFormContainsExpectedElements(): void {
    // Set up mock resource types.
    $nodeResourceType = $this->createMock(ResourceType::class);
    $nodeResourceType->method('getEntityTypeId')->willReturn('node');
    $nodeResourceType->method('isInternal')->willReturn(FALSE);

    $mediaResourceType = $this->createMock(ResourceType::class);
    $mediaResourceType->method('getEntityTypeId')->willReturn('media');
    $mediaResourceType->method('isInternal')->willReturn(FALSE);

    $internalResourceType = $this->createMock(ResourceType::class);
    $internalResourceType->method('getEntityTypeId')->willReturn('internal_entity');
    $internalResourceType->method('isInternal')->willReturn(TRUE);

    $resourceTypeRepository = $this->createMock(ResourceTypeRepositoryInterface::class);
    $resourceTypeRepository->method('all')->willReturn([
      $nodeResourceType,
      $mediaResourceType,
      $internalResourceType,
    ]);

    $nodeEntityType = $this->createMock(EntityTypeInterface::class);
    $nodeEntityType->method('getLabel')->willReturn('Content');

    $mediaEntityType = $this->createMock(EntityTypeInterface::class);
    $mediaEntityType->method('getLabel')->willReturn('Media');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->willReturnMap([
      ['node', TRUE, $nodeEntityType],
      ['media', TRUE, $mediaEntityType],
    ]);

    $form = new class(
      $entityTypeManager,
      $resourceTypeRepository,
    ) extends JsonApiSettingsForm {
      public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        ResourceTypeRepositoryInterface $resourceTypeRepository,
      ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->resourceTypeRepository = $resourceTypeRepository;
      }

      private EntityTypeManagerInterface $entityTypeManager;
      private ResourceTypeRepositoryInterface $resourceTypeRepository;

      public function setConfigFactory(ConfigFactoryInterface $configFactory): void {
        $this->configFactory = $configFactory;
      }
    };
    $form->setConfigFactory($this->configFactory);

    $formState = $this->createMock(FormStateInterface::class);
    $formArray = [];
    $built = $form->buildForm($formArray, $formState);

    // Verify form structure.
    $this->assertArrayHasKey('description', $built);

    $this->assertArrayHasKey('access_control', $built);
    $this->assertSame('details', $built['access_control']['#type']);

    $this->assertArrayHasKey('allowed_entity_types', $built['access_control']);
    $this->assertSame('checkboxes', $built['access_control']['allowed_entity_types']['#type']);

    $this->assertArrayHasKey('blocked_entity_types', $built['access_control']);
    $this->assertSame('checkboxes', $built['access_control']['blocked_entity_types']['#type']);

    $this->assertArrayHasKey('operations', $built);
    $this->assertSame('details', $built['operations']['#type']);

    $this->assertArrayHasKey('allow_write_operations', $built['operations']);
    $this->assertSame('checkbox', $built['operations']['allow_write_operations']['#type']);

    $this->assertArrayHasKey('response', $built);
    $this->assertSame('details', $built['response']['#type']);

    $this->assertArrayHasKey('max_items_per_page', $built['response']);
    $this->assertSame('number', $built['response']['max_items_per_page']['#type']);

    $this->assertArrayHasKey('include_relationships', $built['response']);
    $this->assertSame('checkbox', $built['response']['include_relationships']['#type']);

    // Verify entity type options include node and media but not internal.
    $options = $built['access_control']['allowed_entity_types']['#options'];
    $this->assertArrayHasKey('node', $options);
    $this->assertArrayHasKey('media', $options);
    $this->assertArrayNotHasKey('internal_entity', $options);
  }

  public function testSubmitFormSavesConfiguration(): void {
    $config = $this->createMock(Config::class);

    $setCalls = [];
    $config->method('set')->willReturnCallback(function (string $key, $value) use ($config, &$setCalls): Config {
      $setCalls[$key] = $value;
      return $config;
    });
    $config->expects($this->once())->method('save');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('getEditable')->with('mcp_tools_jsonapi.settings')->willReturn($config);
    $configFactory->method('get')->with('mcp_tools_jsonapi.settings')->willReturn($config);

    $form = $this->createForm();
    $form->setConfigFactory($configFactory);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      ['allowed_entity_types', ['node' => 'node', 'media' => 0, 'taxonomy_term' => 'taxonomy_term']],
      ['blocked_entity_types', ['user' => 'user', 'file' => 0]],
      ['allow_write_operations', TRUE],
      ['max_items_per_page', 100],
      ['include_relationships', TRUE],
    ]);

    $formArray = [];
    $form->submitForm($formArray, $formState);

    // Verify allowed types filter out unchecked values.
    $this->assertSame(['node', 'taxonomy_term'], $setCalls['allowed_entity_types']);

    // Verify blocked types filter out unchecked values.
    $this->assertSame(['user'], $setCalls['blocked_entity_types']);

    $this->assertTrue($setCalls['allow_write_operations']);
    $this->assertSame(100, $setCalls['max_items_per_page']);
    $this->assertTrue($setCalls['include_relationships']);
  }

  public function testSubmitFormHandlesEmptyCheckboxes(): void {
    $config = $this->createMock(Config::class);

    $setCalls = [];
    $config->method('set')->willReturnCallback(function (string $key, $value) use ($config, &$setCalls): Config {
      $setCalls[$key] = $value;
      return $config;
    });
    $config->method('save')->willReturn($config);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('getEditable')->with('mcp_tools_jsonapi.settings')->willReturn($config);
    $configFactory->method('get')->with('mcp_tools_jsonapi.settings')->willReturn($config);

    $form = $this->createForm();
    $form->setConfigFactory($configFactory);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      ['allowed_entity_types', NULL],
      ['blocked_entity_types', []],
      ['allow_write_operations', FALSE],
      ['max_items_per_page', 50],
      ['include_relationships', FALSE],
    ]);

    $formArray = [];
    $form->submitForm($formArray, $formState);

    // Empty arrays when nothing is selected.
    $this->assertSame([], $setCalls['allowed_entity_types']);
    $this->assertSame([], $setCalls['blocked_entity_types']);
    $this->assertFalse($setCalls['allow_write_operations']);
  }

  public function testGetEntityTypeOptionsFiltersInternalTypes(): void {
    $nodeResourceType = $this->createMock(ResourceType::class);
    $nodeResourceType->method('getEntityTypeId')->willReturn('node');
    $nodeResourceType->method('isInternal')->willReturn(FALSE);

    $internalResourceType = $this->createMock(ResourceType::class);
    $internalResourceType->method('getEntityTypeId')->willReturn('internal_entity');
    $internalResourceType->method('isInternal')->willReturn(TRUE);

    $resourceTypeRepository = $this->createMock(ResourceTypeRepositoryInterface::class);
    $resourceTypeRepository->method('all')->willReturn([
      $nodeResourceType,
      $internalResourceType,
    ]);

    $nodeEntityType = $this->createMock(EntityTypeInterface::class);
    $nodeEntityType->method('getLabel')->willReturn('Content');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->willReturnMap([
      ['node', TRUE, $nodeEntityType],
    ]);

    $form = new class(
      $entityTypeManager,
      $resourceTypeRepository,
    ) extends JsonApiSettingsForm {
      public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        ResourceTypeRepositoryInterface $resourceTypeRepository,
      ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->resourceTypeRepository = $resourceTypeRepository;
      }

      private EntityTypeManagerInterface $entityTypeManager;
      private ResourceTypeRepositoryInterface $resourceTypeRepository;

      public function setConfigFactory(ConfigFactoryInterface $configFactory): void {
        $this->configFactory = $configFactory;
      }

      public function getEntityTypeOptionsPublic(): array {
        return $this->getEntityTypeOptions();
      }
    };
    $form->setConfigFactory($this->configFactory);

    $options = $form->getEntityTypeOptionsPublic();

    $this->assertArrayHasKey('node', $options);
    $this->assertArrayNotHasKey('internal_entity', $options);
    $this->assertSame('Content (node)', $options['node']);
  }

  public function testGetEntityTypeOptionsSkipsDuplicates(): void {
    // Create two resource types for the same entity (different bundles).
    $articleResourceType = $this->createMock(ResourceType::class);
    $articleResourceType->method('getEntityTypeId')->willReturn('node');
    $articleResourceType->method('isInternal')->willReturn(FALSE);

    $pageResourceType = $this->createMock(ResourceType::class);
    $pageResourceType->method('getEntityTypeId')->willReturn('node');
    $pageResourceType->method('isInternal')->willReturn(FALSE);

    $resourceTypeRepository = $this->createMock(ResourceTypeRepositoryInterface::class);
    $resourceTypeRepository->method('all')->willReturn([
      $articleResourceType,
      $pageResourceType,
    ]);

    $nodeEntityType = $this->createMock(EntityTypeInterface::class);
    $nodeEntityType->method('getLabel')->willReturn('Content');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->with('node', TRUE)->willReturn($nodeEntityType);

    $form = new class(
      $entityTypeManager,
      $resourceTypeRepository,
    ) extends JsonApiSettingsForm {
      public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        ResourceTypeRepositoryInterface $resourceTypeRepository,
      ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->resourceTypeRepository = $resourceTypeRepository;
      }

      private EntityTypeManagerInterface $entityTypeManager;
      private ResourceTypeRepositoryInterface $resourceTypeRepository;

      public function setConfigFactory(ConfigFactoryInterface $configFactory): void {
        $this->configFactory = $configFactory;
      }

      public function getEntityTypeOptionsPublic(): array {
        return $this->getEntityTypeOptions();
      }
    };
    $form->setConfigFactory($this->configFactory);

    $options = $form->getEntityTypeOptionsPublic();

    // Should only have one entry for 'node'.
    $this->assertCount(1, $options);
    $this->assertArrayHasKey('node', $options);
  }

  public function testGetEntityTypeOptionsHandlesEntityTypeException(): void {
    $nodeResourceType = $this->createMock(ResourceType::class);
    $nodeResourceType->method('getEntityTypeId')->willReturn('missing_entity');
    $nodeResourceType->method('isInternal')->willReturn(FALSE);

    $resourceTypeRepository = $this->createMock(ResourceTypeRepositoryInterface::class);
    $resourceTypeRepository->method('all')->willReturn([$nodeResourceType]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')
      ->with('missing_entity', TRUE)
      ->willThrowException(new \Exception('Entity type not found'));

    $form = new class(
      $entityTypeManager,
      $resourceTypeRepository,
    ) extends JsonApiSettingsForm {
      public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        ResourceTypeRepositoryInterface $resourceTypeRepository,
      ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->resourceTypeRepository = $resourceTypeRepository;
      }

      private EntityTypeManagerInterface $entityTypeManager;
      private ResourceTypeRepositoryInterface $resourceTypeRepository;

      public function setConfigFactory(ConfigFactoryInterface $configFactory): void {
        $this->configFactory = $configFactory;
      }

      public function getEntityTypeOptionsPublic(): array {
        return $this->getEntityTypeOptions();
      }
    };
    $form->setConfigFactory($this->configFactory);

    // Should not throw, should return empty options.
    $options = $form->getEntityTypeOptionsPublic();
    $this->assertSame([], $options);
  }

}
