<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_moderation\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_moderation\Service\ModerationService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_moderation\Service\ModerationService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_moderation')]
final class ModerationServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): ModerationService {
    return new ModerationService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['moderation_info'] ?? $this->createMock(ModerationInformationInterface::class),
      $overrides['transition_validation'] ?? $this->createMock(StateTransitionValidationInterface::class),
      $overrides['current_user'] ?? $this->createMock(AccountProxyInterface::class),
      $overrides['time'] ?? $this->createMock(TimeInterface::class),
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
    );
  }

  public function testListWorkflowsFiltersToContentModerationType(): void {
    $moderatedWorkflow = new class() {
      public function id(): string { return 'editorial'; }
      public function label(): string { return 'Editorial'; }
      public function getTypePlugin(): object {
        return new class() {
          public function getPluginId(): string { return 'content_moderation'; }
          public function getStates(): array {
            return [
              'draft' => new class() {
                public function label(): string { return 'Draft'; }
                public function isPublishedState(): bool { return FALSE; }
                public function isDefaultRevisionState(): bool { return TRUE; }
              },
            ];
          }
          public function getTransitions(): array {
            return [
              'publish' => new class() {
                public function label(): string { return 'Publish'; }
                public function from(): array { return [new class() { public function id(): string { return 'draft'; } }]; }
                public function to(): object {
                  return new class() {
                    public function id(): string { return 'published'; }
                    public function label(): string { return 'Published'; }
                  };
                }
              },
            ];
          }
          public function getEntityTypes(): array { return ['node']; }
        };
      }
    };

    $otherWorkflow = new class() {
      public function getTypePlugin(): object { return new class() { public function getPluginId(): string { return 'other'; } }; }
    };

    $workflowStorage = $this->createMock(EntityStorageInterface::class);
    $workflowStorage->method('loadMultiple')->willReturn([$moderatedWorkflow, $otherWorkflow]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('workflow')->willReturn($workflowStorage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->listWorkflows();

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total']);
    $this->assertSame('editorial', $result['data']['workflows'][0]['id']);
  }

  public function testGetModerationStateReturnsErrorWhenEntityMissing(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(123)->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->getModerationState('node', 123);
    $this->assertFalse($result['success']);
  }

  public function testGetModerationStateReturnsErrorWhenNotModerated(): void {
    $entity = $this->createMock(ContentEntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(123)->willReturn($entity);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $moderationInfo = $this->createMock(ModerationInformationInterface::class);
    $moderationInfo->method('isModeratedEntity')->with($entity)->willReturn(FALSE);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'moderation_info' => $moderationInfo,
    ]);

    $result = $service->getModerationState('node', 123);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not under content moderation', $result['error']);
  }

  public function testSetModerationStateRespectsWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn(['success' => FALSE, 'code' => 'INSUFFICIENT_SCOPE']);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->setModerationState('node', 1, 'published');
    $this->assertFalse($result['success']);
    $this->assertSame('INSUFFICIENT_SCOPE', $result['code']);
  }

  public function testSetModerationStateReturnsNoopWhenAlreadyInState(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('get')->with('moderation_state')->willReturn((object) ['value' => 'draft']);
    $entity->method('label')->willReturn('Test');
    $entity->method('bundle')->willReturn('page');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($entity);

    $typePlugin = new class() {
      public function hasState(string $state): bool { return TRUE; }
      public function getStates(): array { return ['draft' => []]; }
      public function getState(string $id): object {
        return new class() {
          public function label(): string { return 'Draft'; }
          public function isPublishedState(): bool { return FALSE; }
          public function isDefaultRevisionState(): bool { return TRUE; }
        };
      }
    };

    $workflow = new class($typePlugin) {
      public function __construct(private readonly object $typePlugin) {}
      public function id(): string { return 'editorial'; }
      public function label(): string { return 'Editorial'; }
      public function getTypePlugin(): object { return $this->typePlugin; }
    };

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $moderationInfo = $this->createMock(ModerationInformationInterface::class);
    $moderationInfo->method('isModeratedEntity')->with($entity)->willReturn(TRUE);
    $moderationInfo->method('getWorkflowForEntity')->with($entity)->willReturn($workflow);

    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $transitionValidation = $this->createMock(StateTransitionValidationInterface::class);
    $transitionValidation->method('getValidTransitions')->willReturn([]);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'moderation_info' => $moderationInfo,
      'transition_validation' => $transitionValidation,
      'access_manager' => $accessManager,
    ]);

    $result = $service->setModerationState('node', 1, 'draft');
    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['changed']);
  }

  public function testSetModerationStateValidatesTargetStateExists(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('get')->with('moderation_state')->willReturn((object) ['value' => 'draft']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($entity);

    $typePlugin = new class() {
      public function hasState(string $state): bool { return FALSE; }
      public function getStates(): array { return ['draft' => [], 'published' => []]; }
    };

    $workflow = new class($typePlugin) {
      public function __construct(private readonly object $typePlugin) {}
      public function getTypePlugin(): object { return $this->typePlugin; }
    };

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $moderationInfo = $this->createMock(ModerationInformationInterface::class);
    $moderationInfo->method('isModeratedEntity')->with($entity)->willReturn(TRUE);
    $moderationInfo->method('getWorkflowForEntity')->with($entity)->willReturn($workflow);

    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'moderation_info' => $moderationInfo,
      'access_manager' => $accessManager,
    ]);

    $result = $service->setModerationState('node', 1, 'missing');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Available states', $result['error']);
  }

}
