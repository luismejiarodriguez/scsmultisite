<?php declare(strict_types=1);

namespace Drupal\moodle_sync_category\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\moodle_sync_category\Service\SettingsFormOptionsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a settings form for Moodle Sync Category.
 */
final class MoodleSyncCategorySettingsForm extends ConfigFormBase {

  /**
   * The service for retrieving taxonomy options.
   */
  protected SettingsFormOptionsService $settingsFormOptionsService;

  /**
   * Constructs the settings form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config,
    SettingsFormOptionsService $settings_form_options_service
  ) {
    parent::__construct($config_factory, $typed_config);
    $this->settingsFormOptionsService = $settings_form_options_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new MoodleSyncCategorySettingsForm(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('settings_form_options')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['moodle_sync_category.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'moodle_sync_category_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('moodle_sync_category.settings');

    // Taxonomy Settings.
    $form['taxonomy_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Taxonomy Settings'),
      '#open' => TRUE,
    ];

    $form['taxonomy_settings']['taxonomy_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a Taxonomy'),
      '#options' => $this->settingsFormOptionsService->getTaxonomyOptions(),
      '#default_value' => $config->get('selected_taxonomy_term'),
      '#description' => $this->t('Select the vocabulary that will be synced into Moodle course categories. The vocabulary must have a <b>field_moodle_id</b> to hold the Moodle ID.'),
      '#required' => TRUE,
    ];

    // Delete Settings.
    $form['delete_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Delete Settings'),
      '#open' => TRUE,
    ];

    $form['delete_settings']['moodle_trashbin_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Moodle Trashbin ID'),
      '#default_value' => $config->get('moodle_trashbin_id'),
      '#description' => $this->t('ID of a Moodle course category where deleted categories will be moved.'),
    ];

    // Manual Sync Section.
    $form['sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Manual sync'),
      '#description' => $this->t('Sync all existing Categories to Moodle.'),
      '#open' => TRUE,
    ];

    $form['sync']['update_all_categories'] = [
      '#type' => 'link',
      '#title' => $this->t('Sync all Categories to Moodle'),
      '#url' => Url::fromRoute('moodle_sync_category.sync_all_categories'),
      '#attributes' => ['class' => ['button']],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('moodle_sync_category.settings')
      ->set('selected_taxonomy_term', $form_state->getValue('taxonomy_select'))
      ->set('moodle_trashbin_id', $form_state->getValue('moodle_trashbin_id'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
