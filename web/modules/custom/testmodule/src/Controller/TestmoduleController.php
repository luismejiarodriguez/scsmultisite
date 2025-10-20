<?php

declare(strict_types=1);

namespace Drupal\testmodule\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Testmodule routes.
 */
final class TestmoduleController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
