<?php

namespace Drupal\augmentor_eca\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Augmentor related actions.
 */
abstract class AugmentorBase extends ConfigurableActionBase {

  /**
   * The augmentor plugin manager.
   *
   * @var \Drupal\augmentor\AugmentorManager
   */
  protected $augmentorManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ActionBase {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->augmentorManager = $container->get('plugin.manager.augmentor.augmentors');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'augmentor' => '',
      'response_key' => 'default',
      'token_input' => '',
      'token_result' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['augmentor'] = [
      '#type' => 'select',
      '#title' => $this->t('Augmentor'),
      '#options' => $this->getAugmentorOptions(),
      '#default_value' => $this->configuration['augmentor'],
      '#weight' => -12,
      '#required' => TRUE,
    ];

    $form['response_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response Key'),
      '#default_value' => $this->configuration['response_key'],
      '#description' => $this->t('The key used for multiple responses, typically "default" for the first response, may vary between augmentors.'),
      '#weight' => -11,
      '#eca_token_reference' => TRUE,
    ];

    $form['token_input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token input'),
      '#default_value' => $this->configuration['token_input'],
      '#required' => TRUE,
      '#description' => $this->t('The data input for Augmentor.'),
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];

    $form['token_result'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token result'),
      '#default_value' => $this->configuration['token_result'],
      '#required' => TRUE,
      '#description' => $this->t('The response from Augmentor will be stored into the token result field to be used in future steps.'),
      '#weight' => -9,
      '#eca_token_reference' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['augmentor'] = $form_state->getValue('augmentor');
    $this->configuration['response_key'] = $form_state->getValue('response_key');
    $this->configuration['token_input'] = $form_state->getValue('token_input');
    $this->configuration['token_result'] = $form_state->getValue('token_result');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Returns the list of available augmentors.
   *
   * @return array
   *   The list of available augmentors.
   */
  private function getAugmentorOptions(): array {
    $options = [];
    foreach ($this->augmentorManager->getAugmentors() as $uuid => $augmentor) {
      $options[$uuid] = $augmentor['label'];
    }
    return $options;
  }

}
