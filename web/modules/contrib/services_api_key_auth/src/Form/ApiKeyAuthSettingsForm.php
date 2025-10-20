<?php

declare(strict_types=1);

namespace Drupal\services_api_key_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Request API Key Authentication settings for this site.
 */
final class ApiKeyAuthSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'services_api_key_auth_api_key_auth_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['services_api_key_auth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('services_api_key_auth.settings');

    $form['api_key_request_header_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key Request header name'),
      '#default_value' => $config->get('api_key_request_header_name'),
      '#description' => $this->t('The name of the API key in the request header parameters ($_SERVER). Leave empty, to disable server API key authentication. NOTE, that the value of this key will be checked on EVERY request for this site.'),
    ];

    $form['api_key_post_parameter_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key POST parameter name'),
      '#default_value' => $config->get('api_key_post_parameter_name'),
      '#description' => $this->t('The name of the API key in the request body parameters ($_POST). Leave empty, to disable request post API key authentication. NOTE, that the value of this key will be checked on EVERY request for this site.'),
    ];

    $form['api_key_get_parameter_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key GET parameter name'),
      '#default_value' => $config->get('api_key_get_parameter_name'),
      '#description' => $this->t('The name of the API key in the request query parameters ($_GET). Leave empty, to disable request query API key authentication. NOTE, that the value of this key will be checked on EVERY request for this site.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('services_api_key_auth.settings')
      ->set('api_key_request_header_name', $form_state->getValue('api_key_request_header_name'))
      ->set('api_key_post_parameter_name', $form_state->getValue('api_key_post_parameter_name'))
      ->set('api_key_get_parameter_name', $form_state->getValue('api_key_get_parameter_name'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
