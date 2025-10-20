<?php

declare(strict_types=1);

namespace Drupal\moodle_sync_course\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Moodle Sync Course routes.
 */
final class MoodleCourseController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    //Get ID from the URL '/moodle-course/{id}'
    $id = \Drupal::routeMatch()->getParameter('id');

    // Load the entity
    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($id);

    // Check if entity has moodle id
    if ($entity->hasField('field_moodle_id')) {
      $moodle_id = $entity->get('field_moodle_id')->value;
    }

    if ($moodle_id) {
      // Get Moodle URL
      $moodle_url = \Drupal::config('moodle_sync.settings')->get('moodlepath');
      $moodle_url .= '/course/view.php?id=' . $moodle_id;

      // Reroute user to moodle url
      $response = new RedirectResponse($moodle_url);
      $response->send();
    }
    else {
      $build = [
        '#markup' => $this->t('Course currently being created in Moodle.'),
      ];
    }

    return $build;
  }

}
