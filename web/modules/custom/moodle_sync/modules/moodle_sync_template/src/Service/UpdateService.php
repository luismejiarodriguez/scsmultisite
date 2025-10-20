<?php

namespace Drupal\moodle_sync_template\Service;

use Drupal\moodle_sync_template\Utility\MoodleTemplate;

class UpdateService {
  /*
   * Updates a Moodle template.
   *
   * @param $entity Drupal entity.
   * @param $moodle_id the moodle ID - given from create function to prevent infinite loop.
   *
   */
  public function updateTemplate($entity, $moodle_id = NULL) {

    // Get config.
    $config = \Drupal::config('moodle_sync_template.settings');

    // Check if this entity should be processed.
    if (!MoodleTemplate::process($entity, $config)) {
      return;
    }

    // If entity does not have a Moodle ID yet, try creating a Moodle template.
    if (!$moodle_id) {
      if (!$moodle_id = $entity->field_moodle_id->value) {
        \Drupal::service('moodle_sync_template.create')->createTemplate($entity);
        return;
      }
    }

    // Get entity id and fields to map.
    $id = $entity->id();
    $map_fields = $config->get('map_fields');
    $map_customfields = $config->get('map_customfields');
    $params = array();

    // Set basic parameters.
    $function = 'core_course_update_courses';
    $params['courses[0][id]'] = $moodle_id;

    // We will only call the API if one of the synced fields really did change.
    $needsupdate = FALSE;

    // Get template category.
    $categoryid = MoodleTemplate::getTemplateCategory($entity, $config);
    if ($entity->original) {
      $categoryid_orig = MoodleTemplate::getTemplateCategory($entity, $config);
      if ($categoryid_orig !== $categoryid) {
        $needsupdate = TRUE;
      }
    }

    $params['courses[0][categoryid]'] = $categoryid;

    // Add template base fields for all fields that changed.
    $service = \Drupal::service('moodle_sync.sync');

    foreach ($map_fields as $moodlefield => $drupalfield) {

      $value = NULL;

      if ($drupalfield) {
        // Add changed fields to params.
        if ($entity->original && $entity->$drupalfield && $entity->original->$drupalfield) {
         if ($entity->$drupalfield->getString() !== $entity->original->$drupalfield->getString()) {
            $needsupdate = TRUE;
            $value = $service->getValue($entity, $drupalfield);
          }
        } 
        // Add all fields to params if no original entity exists (being run from insert hook).
        else {
          $needsupdate = TRUE;
          $value = $service->getValue($entity, $drupalfield);
        }
      }

      // Add value to query string.
      if ($value) {
        $params["courses[0][$moodlefield]"] = $value;
      }
    }

    // Add template custom fields for all fields that changed.
    foreach ($map_customfields as $moodlefield => $drupalfield) {

      $value = NULL;

      if ($drupalfield) {
        // Add changed fields to params.
        if ($entity->original) {
          if ($entity->$drupalfield->value !== $entity->original->$drupalfield->value) {
            $needsupdate = TRUE;
            $value = $service->getValue($entity, $drupalfield);
          }
        } 
        // Add all fields to params if no original entity exists (being run from insert hook).
        else {
          $value = $service->getValue($entity, $drupalfield);
        }
      }

      // Add value to query string.
      if ($value) {
        $params["courses[0][customfields][0][shortname]"] = $moodlefield;
        $params["courses[0][customfields][0][value]"] = $entity->$drupalfield->value;
      }
    }

    // Call Moodle Sync service for API call.
    if ($needsupdate) {

      $response = $service->apiCall($function, $params);

      // Analyze response and log results.
      if (property_exists($response, 'exception')) {
        $message = t('Error updating Moodle template @moodle_id with settings from Drupal node @nid. <p>Error: @exception</p><p>Params: @params</p>',
          array('@moodle_id' => $moodle_id, '@nid' => $id, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';
      } 
      elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
        $message = t('Error updating Moodle template @moodle_id with settings from Drupal node @nid. <p>Warning: @exception</p><p>Params: @params</p>',
          array('@moodle_id' => $moodle_id, '@nid' => $id, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'warning';
      } 
      else {
        $message = t('Updated Moodle template @moodle_id with settings from Drupal node @nid.',
          array('@moodle_id' => $moodle_id, '@nid' => $id));
        $type = 'info';
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $id, $moodle_id);
    }
  }

}
