<?php

namespace Drupal\moodle_sync_template\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MoodleSyncTemplateSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;
  protected $entity_fields;
  protected $reference_fields;
  protected $template_custom_fields;

  /**
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {

    // Get Moodle-side options for template fields.
    define("TEMPLATE_FIELDS", array('fullname' => 'fullname',
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
    define("REQUIRED_FIELDS", array('fullname'));

    $this->entityFieldManager = $entity_field_manager;

    $config = $this->config('moodle_sync_template.settings');

    // Get all fields from chosen entity.
    $entity_type = 'taxonomy_term';
    $entity_name = $config->get('entity_name');
    $entity_fields = array();
    $this->entity_fields = array();
    $this->reference_fields = array();

    if ($entity_type && $entity_name) {

      // Get all fields of entity.
      $entity_fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity_name);

      // Display warning if no entity fields can be fetched.
      if (!$entity_fields) {
        \Drupal::messenger()->addMessage($this->t('Entity not found! Check entity settings and save this form to get available Drupal fields for mappings.'), 'warning');
      }

      // Filter out fields that do not start with 'field_'.
      $entity_fields = array_filter($entity_fields, function($key) {
        return (strpos($key, 'field_') === 0);
      }, ARRAY_FILTER_USE_KEY);

      // Write fieldnames into array.
      foreach ($entity_fields as $entity_field) {

        // Skip our own ID field, since this is always mapped hardcoded to Moodle ID.
        if ($entity_field->getName() == 'field_moodle_id') {
          continue;
        }

        // Write the rest of the fields into arrays for respective options.
        if ($entity_field->getType() == 'entity_reference') {
          $this->reference_fields[$entity_field->getName()] = $entity_field->getName();
        }
        else {
          $this->entity_fields[$entity_field->getName()] = $entity_field->getName();
        }

        // Add title field to start of field array.
        $standard_fields = array();
        $standard_fields['id'] = 'id';
        $standard_fields['description'] = 'description';
        if ($entity_type == 'group') {
          $standard_fields['label'] = 'title';
        }
        elseif ($entity_type == 'taxonomy_term') {
          $standard_fields['name'] = 'title';
        }
        else {
          $standard_fields['title'] = 'title';
        }

        $this->entity_fields = array_merge($standard_fields, $this->entity_fields);
      }

      array_unshift($this->entity_fields, $this->t(' - Select drupal entity field - '));

    }
    else {
      // Display warning.
      \Drupal::messenger()->addMessage($this->t('Fill out entity settings and save this form to get available Drupal fields for mappings.'), 'warning');
    }

    // Fetch template custom fields.
    $this->template_custom_fields = array();
    $function = 'core_course_get_courses_by_field';
    $params = [
      'field' => 'id',
      'value' => 1,
    ];

    if ($response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, FALSE)) {
      if (property_exists($response, 'courses')) {
        if (property_exists($response->courses[0], 'customfields')) {
          $this->template_custom_fields = array();
          foreach ($response->courses[0]->customfields as $customfield) {
            $this->template_custom_fields["$customfield->shortname"] = "Custom field: $customfield->shortname";
          }
        }
      }
    }
    else {
      // Display warning.
      \Drupal::messenger()->addMessage($this->t('No response from Moodle - cannot fetch template custom fields. Check Moodle Sync settings.'), 'warning');
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

  protected function getEditableConfigNames() {
    return [
      'moodle_sync_template.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'moodle_sync_template_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('moodle_sync_template.settings');

    // Entity settings.
    $form['entity'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Entity settings'),
      '#description' => $this->t('Type and name of entity that will map onto a Moodle template. <br>
                                  The entity must have a <b>field_moodle_id</b> to hold the Moodle ID.
                                  <p>When changing these settings, save and reload the form to update the options in the field selectors.</p>'),
                                ];

    // Entity type.
    $options = [
      NULL => t('-- Select entity type --'),
      'taxonomy_term' => 'Taxonomy Term',
    ];

    $form['entity']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $options,
      '#default_value' => 'taxonomy_term',
      '#required' => TRUE,
      '#disabled' => TRUE,
    ];

    // Entity machine name.
    $form['entity']['entity_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine name'),
      '#default_value' => $config->get('entity_name'),
      '#required' => TRUE,
    ];

    // Category settings.
    $form['category'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Category settings'),
      '#description' => $this->t('Use a fixed category for templates.')
    ];

    // Fixed category id.
    $form['category']['category_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moodle category id for templates'),
      '#default_value' => $config->get('category_id'),
      '#required' => TRUE,
    ];

    // Delete Settings Form
    $form['delete_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Delete Settings'),
    ];

    // Trashbin ID.
    $form['delete_settings']['moodle_template_trashbin_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moodle Template Trashbin ID'),
      '#default_value' => $config->get('moodle_template_trashbin_id'),
      '#suffix' => '<div class="fieldset__description">' . $this->t('ID of a Moodle course category where deleted templates will be moved into.') . "</div>",
    ];

    // Deletion settings.
    $form['delete_settings']['deletion'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete Moodle template when Drupal entity gets deleted. To uncheck it add a Template trashbin id'),
      '#default_value' => $config->get('deletion'),
      '#states' => [
        'disabled' => [
          ':input[name="moodle_trashbin_id"]' => ['value' => ''],
        ],
        'checked' => [
          ':input[name="moodle_trashbin_id"]' => ['value' => ''],
        ],
      ],
    ];

    // Field mappings for base fields.
    $form['mappings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Moodle template field mappings'),
      '#markup' => $this->t('<ul><li>Select Drupal entity fields for all basic Moodle template fields that should be filled by Drupal</li>
                            <li><b>shortname must be unique or the template will not be created in Moodle.</b></li>
                            <li>Drupal entity ID will always be synced to Moodle IDNUMBER.</li>')
    ];

    // Create fields for each moodle template field.
    foreach (TEMPLATE_FIELDS as $field) {
      if (in_array($field, REQUIRED_FIELDS)) {
        $required = TRUE;
      }
      else {
        $required = FALSE;
      }
      $default = NULL;
      if ($map_fields = $config->get('map_fields')) {
        if (array_key_exists($field, $map_fields)) {
          $default = $map_fields[$field];
        }
      }

      $form['mappings']['map_' . $field] = [
        '#type' => 'select',
        '#options' => $this->entity_fields,
        '#title' => $field,
        '#default_value' => $default,
        //'#required' => $required,
      ];
    }

    if (count($this->template_custom_fields) > 0) {

      // Field mappings for custom fields.
      $form['customfield_mappings'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Moodle template custom field mappings'),
        '#markup' => $this->t('<p>Select Drupal entity fields for all Moodle template custom fields that should be filled by Drupal.'),
      ];

    }

    // Create fields for each moodle template field.
    foreach ($this->template_custom_fields as $field => $name) {
      $default = NULL;
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

    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Get editable config.
    $config = $this->configFactory->getEditable('moodle_sync_template.settings');

    // Write field mappings into array.
    $map_fields = array('idnumber' => 'id');
    foreach (TEMPLATE_FIELDS as $field) {
      $map_fields[$field] = $form_state->getValue('map_' . $field);
    }

    // Write custom field mappings into array.
    $map_customfields = array();
    foreach ($this->template_custom_fields as $field => $name) {
      $map_customfields[$field] = $form_state->getValue("custom_$field");
    }

    // Save all config settings.
    $config
      ->set('entity_type', $form_state->getValue('entity_type'))
      ->set('entity_name', $form_state->getValue('entity_name'))
      ->set('moodle_template_trashbin_id', $form_state->getValue('moodle_template_trashbin_id'))
      ->set('deletion', $form_state->getValue('deletion'))
      ->set('category_id', $form_state->getValue('category_id'))
      ->set('map_fields', $map_fields)
      ->set('map_customfields', $map_customfields)
      ->save();
  }

}
