<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\mcp_tools_remote\Form\RemoteSettingsForm;
use Drupal\mcp_tools_remote\Service\ApiKeyManager;
use Drupal\Tests\UnitTestCase;
use Drupal\user\PermissionHandlerInterface;

/**
 * Tests for RemoteSettingsForm.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_remote\Form\RemoteSettingsForm::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote')]
final class RemoteSettingsFormTest extends UnitTestCase {

  private ApiKeyManager $apiKeyManager;
  private EntityTypeManagerInterface $entityTypeManager;
  private PermissionHandlerInterface $permissionHandler;
  private PasswordGeneratorInterface $passwordGenerator;
  private ConfigFactoryInterface $configFactory;
  private Config $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->apiKeyManager = $this->createMock(ApiKeyManager::class);
    $this->apiKeyManager->method('listKeys')->willReturn([]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadByProperties')->willReturn([]);
    $userStorage->method('load')->willReturn(NULL);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $this->permissionHandler = $this->createMock(PermissionHandlerInterface::class);
    $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);

    $this->config = $this->createMock(Config::class);
    $this->config->method('get')->willReturnMap([
      ['enabled', FALSE],
      ['uid', 0],
      ['allow_uid1', FALSE],
      ['allowed_ips', []],
      ['allowed_origins', []],
      ['server_name', 'Drupal MCP Tools'],
      ['server_version', '1.0.0'],
      ['pagination_limit', 50],
      ['include_all_tools', FALSE],
      ['gateway_mode', FALSE],
    ]);
    $this->config->method('set')->willReturnSelf();
    $this->config->method('save')->willReturn($this->config);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('getEditable')->with('mcp_tools_remote.settings')->willReturn($this->config);
    $this->configFactory->method('get')->with('mcp_tools_remote.settings')->willReturn($this->config);

    // Set up container for string translation and messenger.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $messenger = $this->createMock(MessengerInterface::class);
    $container->set('messenger', $messenger);
    \Drupal::setContainer($container);
  }

  private function createForm(): RemoteSettingsForm {
    $form = new RemoteSettingsForm(
      $this->apiKeyManager,
      $this->entityTypeManager,
      $this->permissionHandler,
      $this->passwordGenerator,
    );
    $form->setConfigFactory($this->configFactory);
    return $form;
  }

  public function testGetFormId(): void {
    $form = $this->createForm();
    $this->assertSame('mcp_tools_remote_settings', $form->getFormId());
  }

  public function testGetEditableConfigNames(): void {
    $form = $this->createForm();

    // Use reflection to call protected method.
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('getEditableConfigNames');
    $method->setAccessible(TRUE);

    $names = $method->invoke($form);
    $this->assertSame(['mcp_tools_remote.settings'], $names);
  }

  public function testValidateFormRequiresUserWhenEnabled(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnCallback(function ($key) {
      if ($key === 'enabled') {
        return TRUE;
      }
      if ($key === ['execution_user_wrapper', 'use_uid1']) {
        return FALSE;
      }
      if ($key === ['execution_user_wrapper', 'execution_user']) {
        return 0;
      }
      return NULL;
    });

    $formState->expects($this->once())
      ->method('setErrorByName')
      ->with(
        'execution_user_wrapper][execution_user',
        $this->anything(),
      );

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  public function testValidateFormAllowsDisabledWithoutUser(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnCallback(function ($key) {
      if ($key === ['execution_user_wrapper', 'use_uid1']) {
        return FALSE;
      }
      if ($key === ['execution_user_wrapper', 'execution_user']) {
        return 0;
      }
      if ($key === 'enabled') {
        return FALSE;
      }
      return NULL;
    });

    // Should NOT call setErrorByName when disabled.
    $formState->expects($this->never())
      ->method('setErrorByName');

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  public function testValidateFormAcceptsUid1Override(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnCallback(function ($key) {
      if ($key === ['execution_user_wrapper', 'use_uid1']) {
        return TRUE;
      }
      if ($key === ['execution_user_wrapper', 'execution_user']) {
        return 0;
      }
      if ($key === 'enabled') {
        return TRUE;
      }
      return NULL;
    });

    // Should NOT call setErrorByName when uid 1 override is checked.
    $formState->expects($this->never())
      ->method('setErrorByName');

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  public function testValidateFormAcceptsSelectedUser(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnCallback(function ($key) {
      if ($key === ['execution_user_wrapper', 'use_uid1']) {
        return FALSE;
      }
      if ($key === ['execution_user_wrapper', 'execution_user']) {
        return 5;
      }
      if ($key === 'enabled') {
        return TRUE;
      }
      return NULL;
    });

    // Should NOT call setErrorByName when a user is selected.
    $formState->expects($this->never())
      ->method('setErrorByName');

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  public function testSubmitFormSavesConfiguration(): void {
    $config = $this->createMock(Config::class);

    // Track calls to set().
    $setCalls = [];
    $config->method('set')->willReturnCallback(function (string $key, $value) use ($config, &$setCalls): Config {
      $setCalls[$key] = $value;
      return $config;
    });
    $config->expects($this->once())->method('save');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('getEditable')->with('mcp_tools_remote.settings')->willReturn($config);
    $configFactory->method('get')->with('mcp_tools_remote.settings')->willReturn($config);

    $form = new RemoteSettingsForm(
      $this->apiKeyManager,
      $this->entityTypeManager,
      $this->permissionHandler,
      $this->passwordGenerator,
    );
    $form->setConfigFactory($configFactory);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnCallback(function ($key) {
      if ($key === ['execution_user_wrapper', 'use_uid1']) {
        return FALSE;
      }
      if ($key === ['execution_user_wrapper', 'execution_user']) {
        return 123;
      }
      return match ($key) {
        'enabled' => TRUE,
        'allowed_ips' => "127.0.0.1\n10.0.0.0/8",
        'allowed_origins' => "localhost\nexample.com",
        'server_name' => 'Test Server',
        'server_version' => '2.0.0',
        'pagination_limit' => 100,
        'include_all_tools' => TRUE,
        'gateway_mode' => TRUE,
        default => NULL,
      };
    });

    $formArray = [];
    $form->submitForm($formArray, $formState);

    $this->assertTrue($setCalls['enabled']);
    $this->assertSame(123, $setCalls['uid']);
    $this->assertFalse($setCalls['allow_uid1']);
    $this->assertSame(['127.0.0.1', '10.0.0.0/8'], $setCalls['allowed_ips']);
    $this->assertSame(['localhost', 'example.com'], $setCalls['allowed_origins']);
    $this->assertSame('Test Server', $setCalls['server_name']);
    $this->assertSame('2.0.0', $setCalls['server_version']);
    $this->assertSame(100, $setCalls['pagination_limit']);
    $this->assertTrue($setCalls['include_all_tools']);
    $this->assertTrue($setCalls['gateway_mode']);
  }

  public function testSubmitFormSetsUid1WhenOverrideChecked(): void {
    $config = $this->createMock(Config::class);

    $setCalls = [];
    $config->method('set')->willReturnCallback(function (string $key, $value) use ($config, &$setCalls): Config {
      $setCalls[$key] = $value;
      return $config;
    });
    $config->method('save')->willReturn($config);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('getEditable')->with('mcp_tools_remote.settings')->willReturn($config);
    $configFactory->method('get')->with('mcp_tools_remote.settings')->willReturn($config);

    $form = new RemoteSettingsForm(
      $this->apiKeyManager,
      $this->entityTypeManager,
      $this->permissionHandler,
      $this->passwordGenerator,
    );
    $form->setConfigFactory($configFactory);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnCallback(function ($key) {
      if ($key === ['execution_user_wrapper', 'use_uid1']) {
        return TRUE;
      }
      if ($key === ['execution_user_wrapper', 'execution_user']) {
        return 0;
      }
      return match ($key) {
        'enabled' => TRUE,
        'allowed_ips' => '',
        'allowed_origins' => '',
        'server_name' => 'Test',
        'server_version' => '1.0.0',
        'pagination_limit' => 50,
        'include_all_tools' => FALSE,
        'gateway_mode' => FALSE,
        default => NULL,
      };
    });

    $formArray = [];
    $form->submitForm($formArray, $formState);

    $this->assertSame(1, $setCalls['uid']);
    $this->assertTrue($setCalls['allow_uid1']);
  }

  public function testBuildFormContainsExpectedElements(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnMap([
      ['enabled', TRUE],
      ['uid', 5],
      ['allow_uid1', FALSE],
      ['allowed_ips', ['127.0.0.1']],
      ['allowed_origins', ['localhost']],
      ['server_name', 'Test Server'],
      ['server_version', '1.0.0'],
      ['pagination_limit', 50],
      ['include_all_tools', FALSE],
      ['gateway_mode', FALSE],
    ]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadByProperties')->willReturn([]);
    $userStorage->method('load')->with(5)->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mcp_tools_remote.settings')->willReturn($config);
    $configFactory->method('getEditable')->with('mcp_tools_remote.settings')->willReturn($config);

    $apiKeyManager = $this->createMock(ApiKeyManager::class);
    $apiKeyManager->method('listKeys')->willReturn([
      'key123' => [
        'label' => 'Test Key',
        'scopes' => ['read'],
        'created' => '2025-01-01',
        'last_used' => '2025-01-08',
        'expires' => '2025-12-31',
      ],
    ]);

    $form = new RemoteSettingsForm(
      $apiKeyManager,
      $entityTypeManager,
      $this->permissionHandler,
      $this->passwordGenerator,
    );
    $form->setConfigFactory($configFactory);

    $formState = $this->createMock(FormStateInterface::class);
    $formArray = [];
    $built = $form->buildForm($formArray, $formState);

    // Verify key form elements exist.
    $this->assertArrayHasKey('enabled', $built);
    $this->assertSame('checkbox', $built['enabled']['#type']);

    $this->assertArrayHasKey('allowed_ips', $built);
    $this->assertSame('textarea', $built['allowed_ips']['#type']);

    $this->assertArrayHasKey('allowed_origins', $built);
    $this->assertSame('textarea', $built['allowed_origins']['#type']);

    $this->assertArrayHasKey('server_name', $built);
    $this->assertSame('textfield', $built['server_name']['#type']);

    $this->assertArrayHasKey('server_version', $built);
    $this->assertSame('textfield', $built['server_version']['#type']);

    $this->assertArrayHasKey('pagination_limit', $built);
    $this->assertSame('number', $built['pagination_limit']['#type']);

    $this->assertArrayHasKey('include_all_tools', $built);
    $this->assertSame('checkbox', $built['include_all_tools']['#type']);

    $this->assertArrayHasKey('gateway_mode', $built);
    $this->assertSame('checkbox', $built['gateway_mode']['#type']);

    $this->assertArrayHasKey('execution_user_wrapper', $built);
    $this->assertSame('fieldset', $built['execution_user_wrapper']['#type']);

    $this->assertArrayHasKey('keys', $built);
    $this->assertSame('details', $built['keys']['#type']);

    // Verify keys table exists and has the key.
    $this->assertArrayHasKey('table', $built['keys']);
    $this->assertCount(1, $built['keys']['table']['#rows']);
  }

}
