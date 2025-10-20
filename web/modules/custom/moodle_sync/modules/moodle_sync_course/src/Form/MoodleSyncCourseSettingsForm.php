<?php

namespace Drupal\moodle_sync_course\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MoodleSyncCourseSettingsForm extends ConfigFormBase {

  protected $entityFieldManager;
  protected $entity_fields;
  protected $reference_fields;
  protected $taxonomy_reference_fields;
  protected $course_custom_fields;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'moodle_sync_course.settings',
    ];
  }

  /**
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {

    // Get Moodle-side options for course fields.
    define("COURSE_FIELDS", array('fullname' => 'fullname',
                                  'shortname' => 'shortname',
                                  'summary' => 'summary',
                                  'format' => 'format',
                                  'showgrades' => 'showgrades',
                                  'newsitems' => 'newsitems',
                                  'startdate' => 'startdate',
                                  'enddate' => 'enddate',
                                  'visible' => 'visible',
                                  'groupmode' => 'groupmode',
                                  'groupmodeforce' => 'groupmodeforce',
                                  'lang' => 'lang',
                                  'theme' => 'theme'));
    define("REQUIRED_FIELDS", array('fullname', 'shortname'));

    $this->entityFieldManager = $entity_field_manager;

    $config = $this->config('moodle_sync_course.settings');

    // Get all fields from chosen entity.
    $entity_type = 'node';
    $entity_name = $config->get('entity_name');
    $entity_fields = array();
    $this->entity_fields = array();
    $this->reference_fields = array();
    $this->taxonomy_reference_fields = array();

    if ($entity_type && $entity_name) {

      // Get all fields of entity.
      $entity_fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity_name);

      // Display warning if no entity fields can be fetched.
      if (!$entity_fields) {
        \Drupal::messenger()->addMessage($this->t('Node type not found! Check entity settings and save this form to get available Drupal fields for mappings.'), 'warning');
      }

      // Write fieldnames into array.
      foreach ($entity_fields as $fieldName => $fieldDefinition) {

        // Skip our own ID field, since this is always mapped hardcoded to Moodle ID.
        if ($fieldName == 'field_moodle_id') {
          continue;
        }

        // Skip non-field properties.
        if (!str_contains($fieldName, 'field_')) {
          continue;
        }

        $fieldStorage = $fieldDefinition->getFieldStorageDefinition();
        $schema = $fieldStorage->getSchema();
        $columns = array_keys($schema['columns']);

        // Do not use columns for simple fields for backwards-compatibility reasons.
        if (count($columns) == 1) {
          $this->entity_fields[$fieldName] = $fieldName;
        }
        else {
          foreach($columns as $key) {
            $this->entity_fields["$fieldName:$key"] = "$fieldName:$key";
          }
        }

        // Write reference fields into arrays for respective options.
        if ($fieldDefinition->getType() == 'entity_reference') {
          $this->reference_fields[$fieldName] = $fieldName;
          if ($fieldDefinition->getSetting('target_type') == 'taxonomy_term') {
            $this->taxonomy_reference_fields[$fieldName] = $fieldName;
          }
        }

      }

      // Add title field to start of field array.
      $standard_fields = array();
      $standard_fields['id'] = 'id';
      $standard_fields['title'] = 'title';

      $this->entity_fields = array_merge($standard_fields, $this->entity_fields);

      array_unshift($this->entity_fields, $this->t(' - Select drupal entity field - '));

    // Display warning.
    } else {
      \Drupal::messenger()->addMessage($this->t('Fill out entity settings and save this form to get available Drupal fields for mappings.'), 'warning');
    }

    // Fetch course custom fields.
    $this->course_custom_fields = array();
    $function = 'core_course_get_courses_by_field';
    $params = [
      'field' => 'id',
      'value' => 1,
    ];

    if ($response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, false)) {
      if (property_exists($response, 'courses')) {
        if (property_exists($response->courses[0], 'customfields')) {
          $this->course_custom_fields = array();
          foreach ($response->courses[0]->customfields as $customfield) {
            $this->course_custom_fields["$customfield->shortname"] = "Custom field: $customfield->shortname";
          }
        }
      }

    // Display warning.
    } else {
      \Drupal::messenger()->addMessage($this->t('No response from Moodle - cannot fetch course custom fields. Check Moodle Sync settings.'), 'warning');
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
    return 'moodle_sync_course_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('moodle_sync_course.settings');

    // Sync/async settings.
    $form['async'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Asynchronous settings'),
      '#description' => $this->t('Settings whether entities gets synchronous or asynchronous created, updated or deleted.'),
    ];
    $form['async']['async_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create courses asynchronously'),
      '#default_value' => $config->get('async_create'),
    ];
    $form['async']['async_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update courses asynchronously'),
      '#default_value' => $config->get('async_update'),
    ];
    $form['async']['async_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete courses asynchronously'),
      '#default_value' => $config->get('async_delete'),
    ];

    // Entity settings.
    $form['entity'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity settings'),
      '#description' => $this->t('Type and name of entity that will map onto a Moodle course. <br>
                                  The entity must have a <b>field_moodle_id</b> to hold the Moodle ID.
                                  <p>When changing these settings, save and reload the form to update the options in the field selectors.</p>'),
    ];

    // Entity type.
    $options = [
      null => t('Select entity type'),
      'node' => 'Node',
    ];
    $form['entity']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $options,
      '#disabled' => TRUE,
      '#default_value' => 'node'
    ];

    // Entity machine name.
    $form['entity']['entity_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine name'),
      '#default_value' => $config->get('entity_name'),
    ];

    // Deletion settings.
    $form['entity']['deletion'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete Moodle course when Drupal entity gets deleted'),
      '#default_value' => $config->get('deletion'),
    ];

    // Category settings.
    $form['category'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Category settings'),
      '#description' => $this->t('Either use a fixed category for courses, or use a field in the entity to determine the category. If using a field, the following conditions must be met:
                                  <ul>
                                    <li>Must be a field that contains a reference to a taxonomy term</li>
                                    <li>The term must have a field_moodle_id that contains the Moodle category id</li>
                                  </ul>')
    ];

    // Category method.
    $form['category']['categories'] = [
      '#type' => 'select',
      '#options' => array('entitity_categories' => $this->t('Get category from a field in the entity itself'),
                          'fixed_categories' => $this->t('Predefined category id')),
      '#title' => $this->t('Course category for created Moodle courses'),
      '#default_value' => $config->get('categories'),
    ];

    // Fixed category id.
    $form['category']['category_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moodle category id for courses'),
      '#default_value' => $config->get('category_id'),
    ];

    // Field category id.
    $form['category']['category_field'] = [
      '#type' => 'select',
      '#options' => $this->taxonomy_reference_fields,
      '#title' => $this->t('Taxonomy reference field in entity'),
      '#default_value' => $config->get('category_field'),
    ];

    // Fallback value for course category
    $form['category']['category_fallback'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fallback value in case course category is empty'),
      '#default_value' => $config->get('category_fallback'),
      '#description' => $this->t('Moodle category ID for courses that do not have a category specified in Drupal'),
    ];

    // Course template settings.
    $form['template'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Course template settings'),
      '#description' => $this->t('This must be a reference field that links to other Drupal entities that represent a Moodle course. The Moodle course will be looked up using the field_moodle_id in the entity.'),
    ];

    // Entity field name.
    $form['template']['template_field'] = [
      '#type' => 'select',
      '#options' => $this->reference_fields,
      '#title' => $this->t('Reference field in entity'),
      '#default_value' => $config->get('template_field'),
    ];

    // Hide template field.
    $form['template']['template_hide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide template field in edit forms'),
      '#description' => $this->t('Will hide fields in ALL edit forms, and only show them in create forms.'),
      '#default_value' => $config->get('template_hide'),
    ];

    // Field mappings for base fields.
    $form['mappings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Moodle course field mappings'),
      '#markup' => $this->t('<ul><li>Select Drupal entity fields for all basic Moodle course fields that should be filled by Drupal</li>
                            <li><b>shortname must be unique or the course will not be created in Moodle.</b></li>
                            <li>Drupal entity ID will always be synced to Moodle IDNUMBER.</li>')
    ];

    // Create fields for each moodle course field.
    foreach (COURSE_FIELDS as $field) {
      if (in_array($field, REQUIRED_FIELDS)) {
        $title = $field . ' (' . $this->t('required') . ')';
      } else {
        $title = $field;
      }
      $default = null;
      if ($map_fields = $config->get('map_fields')) {
        if (array_key_exists($field, $map_fields)) {
          $default = $map_fields[$field];
        }
      }

      $form['mappings']['map_' . $field] = [
        '#type' => 'select',
        '#options' => $this->entity_fields,
        '#title' => $title,
        '#default_value' => $default,
      ];
    }

    if (count($this->course_custom_fields) > 0) {

      // Field mappings for custom fields.
      $form['customfield_mappings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Moodle course custom field mappings'),
        '#markup' => $this->t('<p>Select Drupal entity fields for all Moodle course custom fields that should be filled by Drupal.'),
      ];

    }

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
        '#options' => $this->entity_fields,
        '#title' => $field,
        '#default_value' => $default,
      ];
    }

    // Attach JS library to conditionally hide fields.
    $form['#attached']['library'][] = 'moodle_sync_course/settings-form';

    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Get editable config.
    $config = $this->configFactory->getEditable('moodle_sync_course.settings');

    // Write field mappings into array.
    $map_fields = array('idnumber' => 'id');
    foreach (COURSE_FIELDS as $field) {
      $map_fields[$field] = $form_state->getValue('map_' . $field);
    }

    // Write custom field mappings into array.
    $map_customfields = array();
    foreach ($this->course_custom_fields as $field => $name) {
      $map_customfields[$field] = $form_state->getValue("custom_$field");
    }

    // Save all config settings.
    $config
      ->set('async_create', $form_state->getValue('async_create'))
      ->set('async_update', $form_state->getValue('async_update'))
      ->set('async_delete', $form_state->getValue('async_delete'))
      ->set('entity_type', $form_state->getValue('entity_type'))
      ->set('entity_name', $form_state->getValue('entity_name'))
      ->set('deletion', $form_state->getValue('deletion'))
      ->set('categories', $form_state->getValue('categories'))
      ->set('category_id', $form_state->getValue('category_id'))
      ->set('category_field', $form_state->getValue('category_field'))
      ->set('category_fallback', $form_state->getValue('category_fallback'))
      ->set('template_field', $form_state->getValue('template_field'))
      ->set('template_hide', $form_state->getValue('template_hide'))
      ->set('map_fields', $map_fields)
      ->set('map_customfields', $map_customfields)
      ->save();
  }

  // TODO: Form validation
  // - check if selected entity contains a field_moodle_id

}
