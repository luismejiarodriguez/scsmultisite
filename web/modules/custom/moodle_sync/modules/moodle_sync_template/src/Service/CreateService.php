<?php

namespace Drupal\moodle_sync_template\Service;

use Drupal\moodle_sync_template\Utility\MoodleTemplate;

class CreateService {
  /*
   * Creates a new Moodle template.
   *
   * @param $entity Drupal entity.
   *
   * @return string Moodle ID.
   *
   */
  public function createTemplate($entity) {

    // Get config.
    $config = \Drupal::config('moodle_sync_template.settings');

    // Check if this entity should be processed.
    if (!MoodleTemplate::process($entity, $config)) {
      return;
    }

    // Get Service Moodle Sync
    $service = \Drupal::service('moodle_sync.sync');

    // Get entity id and fields to map.
    $id = $entity->id();
    $map_fields = $config->get('map_fields');
    $map_customfields = $config->get('map_customfields');
    $params = array();

    // Get template category.
    $categoryid = MoodleTemplate::getTemplateCategory($entity, $config);

    if (!isset($categoryid) || empty($categoryid)) {
      return;
    }

    // Create template course.
    $updatelater = FALSE;
    $function = 'core_course_create_courses';
    $params["courses[0][categoryid]"] = $categoryid;

    // Add template base fields.
    foreach ($map_fields as $moodlefield => $drupalfield) {

      $value = NULL;
      if ($drupalfield) {
        $value = $service->getValue($entity, $drupalfield);
      }
      if ($value) {
        $params["courses[0][$moodlefield]"] = $value;
      }

      // If field_moodle_shortname does not exist, fill with template_[termid] as fallback
      if(!$entity->hasField('field_moodle_shortname')) {
        $params["courses[0][shortname]"] = "template_[$id]";
      }

      // If field_moodle_shortname is not set, fill with template_[termid] as fallback
      if(!isset($entity->field_moodle_shortname->value) || empty($entity->field_moodle_shortname->value)) {
        $entity->field_moodle_shortname->value = "template_[$id]";
      }

      // Add template custom fields.
      foreach ($map_customfields as $moodlefield => $drupalfield) {
        if ($drupalfield) {
          if ($entity->$drupalfield->value && $moodlefield) {
            $value = $service->getValue($entity, $drupalfield);
          }
        }
        if ($value) {
          $params["courses[0][customfields][0][shortname]"] = $moodlefield;
          $params["courses[0][customfields][0][value]"] = $value;
        }
      }
    }

    // Call Moodle Sync service for API call.
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

    // Analyze response and log results.
    $moodle_id = NULL;
    if (property_exists($response, 'exception')) {
      $message = t('Error creating Moodle template for Drupal entity. No template was created. <p>Error: @exception</p><p>Params: @params</p>',
        array('@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';
    }
    elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
      $message = $response->warnings[0]->message;
      $type = 'warning';
    }
    else {
      $type = 'info';
      $moodle_id = $response->id;
      $message = t('Created Moodle template with id @moodle_id in category @categoryid.',
        array('@moodle_id' => $moodle_id, '@categoryid' => $categoryid));
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $id, $moodle_id);

    // Write back moodle id to Drupal entity.
    if ($moodle_id) {
      $entity->field_moodle_id->value = $moodle_id;
      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(TRUE);
      }
      $entity->save();

      // Update template fields if we duplicated a template.
      if ($updatelater) {
        \Drupal::service('moodle_sync_template.update')->updateTemplate($entity);
      }
    }

    // Return Moodle template id because why not.
    return $moodle_id;

  }

}
