<?php

namespace Drupal\moodle_sync_template\Service;

use Drupal\moodle_sync_template\Utility\MoodleTemplate;

class DeleteService {
  /*
   * Deletes a Moodle template.
   *
   * @param $entity Drupal entity.
   *
   * @return string Moodle ID.
   *
   */
  public function deleteTemplate($entity) {

    // Get config.
    $config = \Drupal::config('moodle_sync_template.settings');

    // Check if this entity should be processed.
    if (!MoodleTemplate::process($entity, $config)) {
      return;
    }

    // Check if the field_moodle_id has a non-empty value.
    if (!$moodle_id = $entity->field_moodle_id->value) {
      return;
    }

    // Check if we should delete Moodle templates. Or move the template to the Trashbin
    if(!$config->get('deletion') && $config->get('moodle_template_trashbin_id') !== NULL) {

      $moodle_template_trashbin_id = $config->get('moodle_template_trashbin_id');

      // Build data to update a new Moodle category.
      $function = 'core_course_update_courses';
      $params = [
        'courses[0][id]' => $moodle_id,
        'courses[0][categoryid]' => $moodle_template_trashbin_id,
      ];

    }
    else {
       // Set params.
      $function = 'core_course_delete_courses';
      $params = [
        'courseids[0]' => $moodle_id,
      ];
    }

    // Call Moodle Sync service for API call.
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

    // Analyze response and log results.
    if (property_exists($response, 'exception')) {
      $message = t('Error creating Moodle template for Drupal entity. No template was created. Error: @exception. Params: @params.',
        array('@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';
    }
    elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
      $message = $response->warnings[0]->message;
      $type = 'warning';
    }
    else {
      $type = 'info';
      $message = t('Deleted Moodle template with id @moodle_id.',
        array('@moodle_id' => $moodle_id));
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $entity->id(), $moodle_id);

  }

}
