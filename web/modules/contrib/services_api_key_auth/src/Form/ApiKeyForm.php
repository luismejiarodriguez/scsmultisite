<?php

namespace Drupal\services_api_key_auth\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * The api key form.
 *
 * @package Drupal\services_api_key_auth\Form
 */
class ApiKeyForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $api_key = $this->entity;
    $hex = $api_key->key ?? substr(hash('sha256', random_bytes(16)), 0, 32);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine Name'),
      '#maxlength' => 255,
      '#default_value' => $api_key->label(),
      '#description' => $this->t("Machine Name for the API Key."),
      '#required' => TRUE,
    ];

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#maxlength' => 42,
      '#default_value' => $hex,
      '#description' => $this->t("The generated API Key for an user."),
      '#required' => TRUE,
    ];

    $form['user_uuid'] = [
      '#title' => ('Select User'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_settings' => [
        'include_anonymous' => TRUE,
      ],
      '#description' => $this->t("Please select the user who gets authenticated with that API Key."),
      '#default_value' => $api_key->user_uuid ? self::getUserEntity($api_key->user_uuid) : '',
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $api_key->id(),
      '#machine_name' => [
        'exists' => '\Drupal\services_api_key_auth\Entity\ApiKey::load',
      ],
      '#disabled' => !$api_key->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * Retrieves the user by uuid.
   */
  public function getUserEntity($uuid) {
    if (empty($uuid)) {
      return;
    }
    $account = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['uuid' => $uuid]);
    $account = current($account);
    return is_object($account) ? $account : '';
  }

  /**
   * Checks whether the user with the given uid exists.
   */
  public function getUuid($uid) {
    if (empty($uid)) {
      return;
    }
    $account = $this->entityTypeManager
      ->getStorage('user')
      ->load($uid);
    $uuid = $account->uuid->value;
    return $uuid ? $uuid : '';
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $api_key = $this->entity;
    $uid = $api_key->user_uuid;
    $api_key->user_uuid = self::getUuid($uid);
    $status = $api_key->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label API Key.', [
          '%label' => $api_key->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label API Key.', [
          '%label' => $api_key->label(),
        ]));
    }

    $form_state->setRedirectUrl($api_key->toUrl('collection'));
    return parent::save($form, $form_state);
  }

  /**
   * Helper function to get user entity options for select widget.
   *
   * @parameter String $machine_name
   *   user name
   *
   * @return array
   *   Select options for form
   */
  public function getUser() {
    $options = [];

    $options_source = $this->entityTypeManager->getStorage('user')->loadMultiple();

    foreach ($options_source as $item) {
      $key = $item->uuid->value;
      $value = $item->name->value;
      $options[$key] = $value;
    }
    return $options;
  }

}
