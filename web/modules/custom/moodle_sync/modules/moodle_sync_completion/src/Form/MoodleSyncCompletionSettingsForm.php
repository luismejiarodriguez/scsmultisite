<?php

namespace Drupal\moodle_sync_completion\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MoodleSyncCompletionSettingsForm extends ConfigFormBase {

 /**
  * {@inheritdoc}
  */
  protected function getEditableConfigNames() {
    return [
      'moodle_sync_completion.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'moodle_sync_completion_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('moodle_sync_completion.settings');

    // User reference field sync.
    $form['field'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Moodle activity completion -> Drupal sync'),
      '#description' => $this->t('<h3>How this works</h3><ul>
        <li><a href="/admin/config/services/api_key">Create a token</a> with a user with sufficient permissions</li>
        <li>Install <strong>Completion Push</strong> plugin in Moodle and configure the endpoint and token</li>
        <li><a href="/admin/config/services/rest/resource/moodle_completion_rest_resource/edit">Configure the the endpoint</a> to accept GET and POST requests with json format</li>
        <li>When a completion changes in Moodle, the plugin will send a POST request to this endpoint, including the Node ID and User ID</li>
        <li>This module will search for <strong>Registration entities</strong> for the given Node and User, and update the completion fields</li></ul>')
    ];

    // Field for course completed.
    $form['field']['field_course_completed'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fieldname for course completion status'),
      '#default_value' => $config->get('field_course_completed'),
      '#description' => $this->t('Machine name of a boolean field in ENROLLMENTS for the course completion status in Moodle.'),
    ];

    // Field for course completed.
    $form['field']['field_course_completed_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fieldname for course completion date'),
      '#default_value' => $config->get('field_course_completed_date'),
      '#description' => $this->t('Machine name of a date field in ENROLLMENTS for the course completion date in Moodle.'),
    ];

    // Field for completed activities.
    $form['field']['field_completed'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fieldname for completed activities'),
      '#default_value' => $config->get('field_completed'),
      '#description' => $this->t('Machine name of a text or number field in ENROLLMENTS for the number of completed activities for a user in a course.'),
    ];

    // Field for total activities.
    $form['field']['field_total'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fieldname for total activities'),
      '#default_value' => $config->get('field_total'),
      '#description' => $this->t('Machine name of a text or number field in NODES that holds how many activities exist in total in a Moodle course.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('moodle_sync_completion.settings')
      ->set('field_course_completed', $form_state->getValue('field_course_completed'))
      ->set('field_course_completed_date', $form_state->getValue('field_course_completed_date'))
      ->set('field_completed', $form_state->getValue('field_completed'))
      ->set('field_total', $form_state->getValue('field_total'))
      ->save();
  }

}
