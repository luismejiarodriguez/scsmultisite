<?php

namespace Drupal\augmentor_eca\Plugin\Action;

/**
 * Describes the Augmentor augmentor_eca_basic action.
 *
 * @Action(
 *   id = "augmentator_eca_basic",
 *   label = @Translation("Basic Augment Action"),
 *   description = @Translation("Run text through Augmentor.")
 * )
 */
class AugmentorBasic extends AugmentorBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $augmentor = $this->augmentorManager->getAugmentor($this->configuration['augmentor']);
    $tokenValue = $this->tokenServices->getTokenData($this->configuration['token_input'])?->getValue() ?? '';

    // If the augmentor or token value is empty, return early.
    if (!$augmentor || !$tokenValue) {
      return;
    }

    // Execute the augmentor and store the result.
    $result = $augmentor->execute($tokenValue);
    $response_key = $this->configuration['response_key'] ?? NULL;
    $this->tokenServices->addTokenData(
      $this->configuration['token_result'],
      $response_key ? $result[$response_key] : $result
    );
  }

}
