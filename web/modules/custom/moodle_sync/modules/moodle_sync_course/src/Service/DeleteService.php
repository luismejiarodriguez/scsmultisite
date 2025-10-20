<?php

namespace Drupal\moodle_sync_course\Service;

use Drupal\moodle_sync_course\Utility\MoodleCourse;

class DeleteService {
  /*
   * Deletes a Moodle course.
   *
   * @param $entity Drupal entity.
   *
   * @return string Moodle ID.
   *
   */
  public function deleteCourse($entity) {

    // Get config.
    $config = \Drupal::config('moodle_sync_course.settings');

    // Check if this entity should be processed.
    if (!MoodleCourse::process($entity, $config)) {
      return;
    }

    // Check if we should delete Moodle courses.
    if (!$config->get('deletion')) {
      return;
    }

    // Set params.
    if (!$moodle_id = $entity->field_moodle_id->value) {
      return;
    }
    $function = 'core_course_delete_courses';
    $params = [
      'courseids[0]' => $moodle_id,
    ];

    // Call Moodle Sync service for API call.
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

    // Analyze response and log results.
    if (property_exists($response, 'exception')) {
      $message = t('Error creating Moodle course for Drupal entity. No course was created. Error: @exception. Params: @params.',
        array('@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';

    } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
      $message = $response->warnings[0]->message;
      $type = 'warning';

    } else {
      $type = 'info';
      $message = t('Deleted Moodle course with id @moodle_id.',
        array('@moodle_id' => $moodle_id));
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $entity->id(), $moodle_id);

  }

  public function deleteRemoteCourse($moodle_id) {

    // Get config.
    $config = \Drupal::config('moodle_sync_course.settings');

    // Check if we should delete Moodle courses.
    if (!$config->get('deletion')) {
      return;
    }

    // Set params.
    if (!$moodle_id) {
      return;
    }
    $function = 'core_course_delete_courses';
    $params = [
      'courseids[0]' => $moodle_id,
    ];

    // Call Moodle Sync service for API call.
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, FALSE);

    // Analyze response and log results.
    if (property_exists($response, 'exception')) {
      $message = t('Error deleting Moodle course. Error: @exception. Params: @params.',
        array('@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';

    } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
      $message = $response->warnings[0]->message;
      $type = 'warning';

    } else {
      $type = 'info';
      $message = t('Deleted Moodle course with id @moodle_id.',
        array('@moodle_id' => $moodle_id));
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $moodle_id);

  }

}
