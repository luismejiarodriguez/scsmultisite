<?php

namespace Drupal\moodle_sync_cohort\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MoodleSyncCohortSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'moodle_sync_cohort.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'moodle_sync_cohort_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('moodle_sync_cohort.settings');

    // Help.
    $form['help'] = [
      '#type' => 'markup',
      '#markup' => '<ul><li>' . $this->t('This module synchronizes Drupal <b>taxonomy terms</b> to <b>Moodle cohorts</b>.') . '</li>' .
                    '<li>' . $this->t('The taxonomy needs a <b>field_moodle_id</b> to save the corresponding Moodle cohort id') . '</li>' .
                    '<li>' . $this->t('Taxonomies can be synced into cohorts, and new terms will automatically create a Moodle cohort') . '</li>' .
                    '<li>' . $this->t('User Cohort assignments can be saved in a entity reference field in the <b>User</b> or <b>Profile</b> entity') . '</li></ul>',
    ];

    // Vocabulary.
    $options = [];
    $vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      $options[$vocabulary->id()] = $vocabulary->label();
    }
    $form['vocabulary'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Vocabulary to sync with cohorts'),
      '#default_value' => $config->get('vocabulary'),
      '#required' => TRUE,
    ];

    // Button to sync all existing cohorts terms.
    $form['sync']['sync_all'] = [
      '#type' => 'link',
      '#title' => $this->t('Sync all Taxonomy Terms to Moodle cohorts'),
      '#url' => Url::fromRoute('moodle_sync_cohort.sync_all'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // Reference entity.
    $options = ['user' => $this->t('User'),
                'profile' => $this->t('Profile')];
    $form['reference_entity'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Entity that holds the reference field - User or Profile'),
      '#default_value' => $config->get('reference_entity'),
      '#required' => TRUE,
    ];

    // Profile type.
    $form['profile_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine name of profile type'),
      '#default_value' => $config->get('profile_type'),
    ];

    // Only make profile type visible when profile is selected.
    $form['profile_type']['#states'] = [
      'visible' => [
        ':input[name="reference_entity"]' => ['value' => 'profile'],
      ],
      'required' => [
        ':input[name="reference_entity"]' => ['value' => 'profile'],
      ],
    ];

    // Reference field.
    $form['reference_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine name of cohort reference field'),
      '#default_value' => $config->get('reference_field'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Get editable config.
    $config = $this->configFactory->getEditable('moodle_sync_cohort.settings');

    // Save all config settings.
    $config
      ->set('vocabulary', $form_state->getValue('vocabulary'))
      ->set('reference_entity', $form_state->getValue('reference_entity'))
      ->set('profile_type', $form_state->getValue('profile_type'))
      ->set('reference_field', $form_state->getValue('reference_field'))
      ->save();
  }

}
