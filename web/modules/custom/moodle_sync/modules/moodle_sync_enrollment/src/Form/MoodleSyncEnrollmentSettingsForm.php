<?php

namespace Drupal\moodle_sync_enrollment\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MoodleSyncEnrollmentSettingsForm extends ConfigFormBase {

  protected $registration_types;

 /**
  * {@inheritdoc}
  */
  protected function getEditableConfigNames() {
    return [
      'moodle_sync_enrollment.settings',
    ];
  }

  /**
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {

    // Get available registration types.
    $registration_types = \Drupal::service('entity_type.bundle.info')->getBundleInfo('registration');
    foreach ($registration_types as $key => $value) {
      $this->registration_types[$key] = $value['label'];
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'moodle_sync_enrollment_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('moodle_sync_enrollment.settings');

    // User reference field sync.
    $form['field'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('User Reference Field in Node -> Moodle Enrollment Sync'),
      '#description' => $this->t('Syncs all users from a user reference field in a node to Moodle enrollments, if the nodes have a <strong>field_moodle_id</strong> set to an existing Moodle course.
        <p>Users removed from reference field will result in Moodle enrollments to be <strong>deleted</strong>, if they have no other role assignments than the ones specified here. Otherwise, they will be <strong>suspended</strong>.</p>'),
    ];

    // Field for user reference.
    $form['field']['fieldname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fieldname'),
      '#default_value' => $config->get('fieldname'),
      '#description' => $this->t('Machine name of the field that references the users in the node.'),
    ];

    // Field for user reference.
    $form['field']['role'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Role'),
      '#default_value' => $config->get('role'),
      '#description' => $this->t('Moodle role ID to grant to linked users.'),
    ];

    // Registration sync.
    $form['registration'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Registration -> Moodle Enrollment Sync'),
      '#description' => $this->t('Syncs Registration entities to Moodle enrollments, if the registrations are in a node that has a <strong>field_moodle_id</strong> set to an existing Moodle course.
        <p>Deleted Registrations will result in Moodle enrollments to be <strong>suspended</strong>.</p>'),
    ];

    // Registration type.
    $form['registration']['registration_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Registration type'),
      '#options' => $this->registration_types,
      '#default_value' => $config->get('registration_types'),
      '#description' => $this->t('Registration types to sync to Moodle enrollments.'),
      '#multiple' => true,
    ];

    // Field for Moodle role.
    $form['registration']['role_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field for Moodle role'),
      '#default_value' => $config->get('role_field'),
      '#description' => $this->t('Select the field that contains the Moodle role.
        <ul><li>Must be a taxonomy reference field</li>
        <li>Taxonomy must contain a <b>field_moodle_id</b> for the Moodle role ID</li>
        <li>The field needs to allow multiple values</li>
        <li>If no field is given or no value is found, will default to Moodle role ID 5 (student)</li>'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('moodle_sync_enrollment.settings')
      ->set('fieldname', $form_state->getValue('fieldname'))
      ->set('role', $form_state->getValue('role'))
      ->set('registration_types', $form_state->getValue('registration_types'))
      ->set('role_field', $form_state->getValue('role_field'))
      ->save();
  }

}
