<?php

namespace Drupal\moodle_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class MoodleSyncSettingsForm extends ConfigFormBase {
  /**
 * {@inheritdoc}
 */
  protected function getEditableConfigNames() {
    return [
      'moodle_sync.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'moodle_sync_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('moodle_sync.settings');

    // Set drupalpath to current site if setting is empty.
    if (!$drupalpath = $config->get('drupalpath')) {
      $drupalpath = \Drupal::request()->getSchemeAndHttpHost();
    }

    // Basic settings.
    $form['basic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic settings'),
    ];
    // Drupalpath.
    $form['basic']['drupalpath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal path'),
      '#default_value' => $drupalpath,
      '#description' => $this->t('Current drupal site, will auto-fill if empty. This is a safety measure, so that the module will automatically stop writing to Moodle when the Drupal site is copied to another adress (eg when moving the prod DB to a dev server).'),
    ];
    // Moodlepath.
    $form['basic']['moodlepath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moodle site'),
      '#default_value' => $config->get('moodlepath'),
    ];
    // Webservices token.
    $form['basic']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moodle webservice token'),
      '#default_value' => $config->get('token'),
    ];

    // Logging.
    $form['logging'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Logging'),
      '#description' => $this->t('Where to log/display all actions that lead to data changing in Moodle.'),
    ];
    $form['logging']['log_onscreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Onscreen messages'),
      '#default_value' => $config->get('log_onscreen'),
      '#description' => $this->t('Directly show alerts on page.'),
    ];
    $form['logging']['log_drupal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Drupal logging'),
      '#default_value' => $config->get('log_drupal'),
      '#description' => $this->t('Use Drupal logging.'),
    ];
    $form['logging']['log_file'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('File logging'),
      '#default_value' => $config->get('log_file'),
      '#description' => $this->t('Detailled logging into file in private://social_moodle_webservice.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    if (!$drupalpath = $form_state->getValue('drupalpath')) {
      $drupalpath = \Drupal::request()->getSchemeAndHttpHost();
    }

    $this->config('moodle_sync.settings')
      ->set('drupalpath', $drupalpath)
      ->set('moodlepath', $form_state->getValue('moodlepath'))
      ->set('token', $form_state->getValue('token'))
      ->set('managerrole', $form_state->getValue('managerrole'))
      ->set('teacherrole', $form_state->getValue('teacherrole'))
      ->set('categories', $form_state->getValue('categories'))
      ->set('category_iteration', $form_state->getValue('category_iteration'))
      ->set('category_language_version', $form_state->getValue('category_language_version'))
      ->set('log_onscreen', $form_state->getValue('log_onscreen'))
      ->set('log_drupal', $form_state->getValue('log_drupal'))
      ->set('log_file', $form_state->getValue('log_file'))

      ->save();
  }

}
