<?php

namespace Drupal\moodle_sync_user\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\moodle_sync_user\Form;
use Drupal\user\Entity\User;

class MoodleSyncUserSettings extends ConfigFormBase {

  /**
 * {@inheritdoc}
 */
  protected function getEditableConfigNames() {
    return [
      'moodle_sync_user.settings',
    ];
  }

  protected $moodleUserVars = [
    'username', 'firstname', 'lastname', 'middlename', 'alternatename', 'email',
    'city', 'country', 'timezone', 'description', 'url', 'icq', 'skype', 'aim', 'yahoo', 'msn',
    'institution', 'department', 'phone1', 'phone2', 'address', 'lang', 'theme',
  ];

  protected $moodleUserVarsRequired = [
    'firstname', 'lastname', 'email', 'username',
  ];

  public $course_custom_fields = array();

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'moodle_sync_user_settings_form';
  }

  public function getCustomFields() {

    // Fetch user custom fields.
    $this->course_custom_fields = array();
    $function = 'core_user_get_users_by_field';
    $params = [
      'field' => 'id',
      'values[]' => '2'
    ];

    if (!$response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, false)) {
      return null;
    }

    if (property_exists($response, 'customfields')) {
      $this->course_custom_fields = array();
      foreach ($response->customfields as $customfield) {
        $this->course_custom_fields["$customfield->shortname"] = "Custom field: $customfield->shortname";
      }
    }

    return $this->course_custom_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->configFactory->getEditable('moodle_sync_user.settings');

    // Get Moodle user custom fields.
    $this->getCustomFields();

    // Get possible Drupal field mappings.
    $drupalFields = array();

    //Drupal User fields.
    $drupalFieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user');
    foreach ($drupalFieldDefinitions as $fieldName => $fieldDefinition) {

      $fieldStorage = $fieldDefinition->getFieldStorageDefinition();
      $schema = $fieldStorage->getSchema();
      $columns = array_keys($schema['columns']);

      // Do not use columns for simple fields for backwards-compatibility reasons.
      if (count($columns) == 1) {
        $drupalFields[$fieldName] = "USER: $fieldName";
      }
      else {
        foreach($columns as $key) {
          $drupalFields["$fieldName:$key"] = "USER: $fieldName:$key";
        }
      }
    }

    // Drupal Profile fields.
    $profileTypes = [];
    if (\Drupal::moduleHandler()->moduleExists('profile')) {
      $profileTypes = \Drupal::entityTypeManager()->getStorage('profile_type')->loadMultiple();
    }

    foreach ($profileTypes as $profileType) {

      // Get the profile type ID.
      $profileTypeId = $profileType->id();

      // Get the fields attached to this profile type.
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('profile', $profileTypeId);

      // Loop through and print the field names for this profile type.
      foreach ($fields as $fieldName => $fieldDefinition) {

        // Skip non-field properties.
        if (!str_contains($fieldName, 'field_')) {
          continue;
        }

        $fieldStorage = $fieldDefinition->getFieldStorageDefinition();
        $schema = $fieldStorage->getSchema();
        $columns = array_keys($schema['columns']);

        // Do not use columns for simple fields for backwards-compatibility reasons.
        if (count($columns) == 1) {
          $drupalFields["profile_" . $profileTypeId . "_" . $fieldName] = "PROFILE $profileTypeId: $fieldName";
        }
        else {
          foreach($columns as $key) {
            $drupalFields["profile_" . $profileTypeId . "_" . "$fieldName:$key"] = "PROFILE $profileTypeId: $fieldName:$key";
          }
        }
      }
    }

    // Add null option.
    array_unshift($drupalFields, $this->t(' - Select drupal entity field - '));

    // Basic settings.
    $form['basic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic settings'),
    ];

    // Auth
    $default = null;
    if (!$default = $config->get('auth')) {
      $default = 'oauth2';
    }
    $form['basic']['auth'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moodle auth plugin'),
      '#default_value' => $default,
      '#description' => $this->t('Select moodle auth plugin to use (normally "oauth2" for SSO setups, or "manual" for standard Moodle users).')
    ];

    // Deletion settings.
    $form['basic']['deletion'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete Moodle user when Drupal user gets deleted'),
      '#default_value' => $config->get('deletion'),
    ];

    // Roles.
    $roles = $config->get('roles') == null ? \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple() : $config->get('roles');
    unset($roles['anonymous']); // We don't need guests.
    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles to leave out of user sync'),
      '#description' => $this->t('Select roles for users to ignore in Moodle sync.')
    ];

    $roleConfig = $config->get('roles');
    foreach($roles as $roleLabel => $roleValue) {
      if ($roleConfig && array_key_exists($roleLabel, $roleConfig)) {
        $default = $roleConfig[$roleLabel];
      } else {
        $default = false;
      }
      $form['roles'][$roleLabel] = [
        '#type' => 'checkbox',
        '#title' => 'Do not sync ' . $roleLabel,
        '#default_value' => $default,
      ];
    }

    // Field mappings.
    $form['mappings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field mappings'),
      '#description' => $this->t('Select all fields to be synced to Moodle. <br>
                                  Reload the form after changing the number of field mappings.'),
      '#markup' => $this->t('<ul><li>Select Drupal entity fields for all basic Moodle user fields that should be filled by Drupal</li>
                              <li>Drupal user ID will always be synced to Moodle IDNUMBER.</li></ul>')
    ];

    foreach ($this->moodleUserVars as $field) {
      if (in_array($field, $this->moodleUserVarsRequired)) {
        $required = true;
      } else {
        $required = false;
      }

      if ($map_fields = $config->get('map_fields')) {
        if (array_key_exists($field, $map_fields)) {
          $default = $map_fields[$field];
        }
      }

      $form['mappings']['map_' . $field] = [
        '#type' => 'select',
        '#options' => $drupalFields,
        '#title' => $field,
        '#default_value' => $default,
        '#required' => $required,
      ];
    }

    // Field mappings for custom fields.
    $form['customfield_mappings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Moodle user profile field mappings'),
      '#markup' => $this->t('<p>Select Drupal entity fields for all Moodle course custom fields that should be filled by Drupal.
                            <p>IMPORTANT: Moodle custom fields must be created <b>and a value filled for the user with user id 2</b> before they can be mapped here.'),
    ];

    // Create fields for each moodle course field.
    foreach ($this->course_custom_fields as $field => $name) {
      $default = null;
      if ($map_customfields = $config->get('map_customfields')) {
        if (array_key_exists($field, $map_customfields)) {
          $default = $map_customfields[$field];
        }
      }
      $form['customfield_mappings']["custom_$field"] = [
        '#type' => 'select',
        '#options' => $drupalFields,
        '#title' => $field,
        '#default_value' => $default,
      ];
    }

    $form['update_all_users'] = [
      '#type' => 'link',
      '#title' => $this->t('Sync all Users to Moodle'),
      '#url' => \Drupal\Core\Url::fromRoute('moodle_sync_user.sync_all_users'),
      '#attributes' => [
        'class' => ['button'],
      ],
      '#prefix' => '<div>' . $this->t('Sync all existing Users to Moodle.') . '<div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $drupalFields = \Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user');

    // Write field mappings into array.
    $map_fields = array('idnumber' => 'uid');
    foreach ($this->moodleUserVars as $field) {
      $map_fields[$field] = $form_state->getValue('map_' . $field);
    }

    // Write custom field mappings into array.
    $map_customfields = array();
    foreach ($this->course_custom_fields as $field => $name) {
      $map_customfields[$field] = $form_state->getValue("custom_$field");
    }
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();

    foreach ($roles as $role) {
      $roles[$role->id()] = $form_state->getValue($role->id());
    }

    $this->config('moodle_sync_user.settings')
      ->set('map_fields', $map_fields)
      ->set('deletion', $form_state->getValue('deletion'))
      ->set('map_customfields', $map_customfields)
      ->set('auth', $form_state->getValue('auth'))
      ->set('roles', $roles)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
