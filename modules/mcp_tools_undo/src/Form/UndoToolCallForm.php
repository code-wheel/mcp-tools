<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A person undoes one tool call, after seeing what it changed.
 */
class UndoToolCallForm extends ContentEntityConfirmFormBase {

  /**
   * The undo service.
   *
   * @var \Drupal\mcp_tools_undo\Service\UndoService
   */
  protected $undoService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->undoService = $container->get('mcp_tools_undo.undo');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Undo what this tool call changed?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('It changed: @summary. What it made is deleted, and what it changed or deleted is put back. If anything was changed again since, nothing is undone.', ['@summary' => (string) $this->entity->get('summary')->value]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Undo');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.mcp_tool_call.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->undoService->undo((string) $this->entity->get('call_id')->value, TRUE);
    if (!empty($result['success'])) {
      $this->messenger()->addStatus($result['data']['message']);
    }
    else {
      $this->messenger()->addError($result['error']);
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
