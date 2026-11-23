<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_webform\Unit\Service;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for WebformService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_webform\Service\WebformService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_webform')]
class WebformServiceTest extends UnitTestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $webformStorage;
  protected EntityStorageInterface $submissionStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);
    $this->webformStorage = $this->createMock(EntityStorageInterface::class);
    $this->submissionStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['webform', $this->webformStorage],
        ['webform_submission', $this->submissionStorage],
      ]);
  }

  /**
   * Creates a WebformService instance.
   */
  protected function createService(): WebformService {
    return new WebformService(
      $this->entityTypeManager,
      $this->accessManager,
      $this->auditLogger
    );
  }

  public function testListWebformsEmpty(): void {
    $this->webformStorage->method('loadMultiple')->willReturn([]);

    $service = $this->createService();
    $result = $service->listWebforms();

    $this->assertTrue($result['success']);
    $this->assertEmpty($result['data']['webforms']);
  }

  public function testGetWebformNotFound(): void {
    $this->webformStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getWebform('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testGetSubmissionsWebformNotFound(): void {
    $this->webformStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->getSubmissions('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testDeleteSubmissionAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
      'code' => 'INSUFFICIENT_SCOPE',
    ]);

    $service = $this->createService();
    $result = $service->deleteSubmission(1);

    $this->assertFalse($result['success']);
    $this->assertEquals('INSUFFICIENT_SCOPE', $result['code']);
  }

  public function testDeleteSubmissionNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->submissionStorage->method('load')->willReturn(NULL);

    $service = $this->createService();
    $result = $service->deleteSubmission(999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * The elements a create stores, as Drupal reads them back.
   */
  private function storedElements(array $elements): array|string {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->webformStorage->method('load')->willReturn(NULL);
    $stored = NULL;
    $entity = $this->createMock(ConfigEntityInterface::class);
    $this->webformStorage->method('create')->willReturnCallback(function (array $values) use (&$stored, $entity) {
      $stored = $values['elements'] ?? NULL;
      return $entity;
    });
    $result = $this->createService()->createWebform('contact', 'Contact', $elements);
    return $result['success'] ? Yaml::decode((string) $stored) : $result['error'];
  }

  /**
   * Elements with "#" keys, as the tool's description says, are stored.
   *
   * The description says "YAML-style" (#type, #title); the code read only
   * plain keys, so a "#" element was stored empty and the call said success.
   */
  public function testElementsWithHashKeysAreStored(): void {
    $stored = $this->storedElements([
      'email' => ['#type' => 'email', '#title' => 'Email', '#required' => TRUE],
      'topic' => ['type' => 'select', 'title' => 'Topic', 'options' => ['a' => 'A', 'b' => 'B'], 'multiple' => TRUE],
    ]);
    $this->assertSame(['#type' => 'email', '#title' => 'Email', '#required' => TRUE], $stored['email'] ?? NULL, 'An element with # keys was stored as: ' . json_encode($stored));
    $this->assertSame(['#type' => 'select', '#title' => 'Topic', '#options' => ['a' => 'A', 'b' => 'B'], '#multiple' => TRUE], $stored['topic']);
  }

  /**
   * An element inside a container keeps its place.
   */
  public function testAnElementInAContainerIsKept(): void {
    $stored = $this->storedElements([
      'who' => ['#type' => 'fieldset', '#title' => 'Who', 'name' => ['#type' => 'textfield', '#title' => 'Name']],
    ]);
    $this->assertSame(['#type' => 'textfield', '#title' => 'Name'], $stored['who']['name'] ?? NULL, json_encode($stored));
  }

  /**
   * An element with no type is refused: Drupal would store nothing usable.
   */
  public function testAnElementWithNoTypeIsRefused(): void {
    $error = $this->storedElements(['email' => ['title' => 'Email']]);
    $this->assertIsString($error, 'An element with no type was stored.');
    $this->assertStringContainsString('email', $error);
  }

  /**
   * One element is added or removed, and the others are kept.
   *
   * Adding a field meant sending all elements back, and a local model
   * often could not write that call (framework scoring, 2026-11-22).
   */
  public function testOneElementIsAddedOrRemoved(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $webform = new class() {
      public array $set = [];

      public function getElementsDecoded(): array {
        return ['name' => ['#type' => 'textfield', '#title' => 'Name'], 'email' => ['#type' => 'email', '#title' => 'Email']];
      }

      public function set(string $key, $value): void {
        $this->set[$key] = $value;
      }

      public function save(): void {}

      public function id(): string {
        return 'contact';
      }

      public function label(): string {
        return 'Contact';
      }

      public function isOpen(): bool {
        return TRUE;
      }

    };
    $this->webformStorage->method('load')->willReturn($webform);
    $result = $this->createService()->updateWebform('contact', ['set_elements' => ['phone' => ['type' => 'tel', 'title' => 'Phone']], 'remove_elements' => ['email']]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $stored = Yaml::decode($webform->set['elements'] ?? '');
    $this->assertSame(['name', 'phone'], array_keys($stored ?? []), 'The other elements were not kept, or the new one not added.');
    $this->assertSame('tel', $stored['phone']['#type']);

    $missing = $this->createService()->updateWebform('contact', ['remove_elements' => ['nope']]);
    $this->assertFalse($missing['success']);
    $this->assertStringContainsString('name, email', $missing['error']);
  }

  /**
   * Status is webform's own, and a setting it does not have is refused.
   *
   * Any status but exactly "open" closed the form ("Open" too), and an
   * unknown setting (the tool's own example, submission_limit) was stored
   * and did nothing.
   */
  public function testStatusAndSettingsAreWebformsOwn(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $webform = new class() {
      public mixed $status = NULL;
      public array $settings = [];

      public static function getDefaultSettings(): array {
        return ['limit_total' => NULL, 'confirmation_message' => ''];
      }

      public function setStatus($status): void {
        $this->status = $status;
      }

      public function setSetting(string $key, $value): void {
        $this->settings[$key] = $value;
      }

      public function save(): void {}

      public function id(): string {
        return 'contact';
      }

      public function label(): string {
        return 'Contact';
      }

      public function isOpen(): bool {
        return $this->status === 'open';
      }

    };
    $this->webformStorage->method('load')->willReturn($webform);
    $service = $this->createService();

    $this->assertTrue($service->updateWebform('contact', ['status' => 'Open'])['success']);
    $this->assertSame('open', $webform->status, '"Open" closed the form.');
    $this->assertFalse($service->updateWebform('contact', ['status' => 'sometimes'])['success']);

    $unknown = $service->updateWebform('contact', ['settings' => ['submission_limit' => 1]]);
    $this->assertFalse($unknown['success'], 'A setting webform does not have was stored.');
    $this->assertStringContainsString('limit_total', $unknown['error']);
    $this->assertSame([], $webform->settings);
  }

}
