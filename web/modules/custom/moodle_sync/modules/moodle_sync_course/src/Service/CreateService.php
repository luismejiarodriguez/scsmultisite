<?php

namespace Drupal\moodle_sync_course\Service;

use Drupal\moodle_sync_course\Utility\MoodleCourse;

class CreateService {
  /*
   * Creates a new Moodle course.
   *
   * @param $entity Drupal entity.
   *
   * @return string Moodle ID.
   *
   */
  public function createCourse($entity) {

    // Get config.
    $config = \Drupal::config('moodle_sync_course.settings');

    // Check if this entity should be processed.
    if (!MoodleCourse::process($entity, $config)) {
      return;
    }

    // Get Service Moodle Sync
    $service = \Drupal::service('moodle_sync.sync');

    // Get entity id and fields to map.
    $id = $entity->id();
    $map_fields = $config->get('map_fields');
    $map_customfields = $config->get('map_customfields');
    $params = array();

    // Get course category.
    $categoryid = MoodleCourse::getCourseCategory($entity, $config);

    // Get template and duplicate course if found.
    $template = null;
    $template_field = $config->get('template_field');

    if ($template_entity = $entity->$template_field->entity) {
      $template = $template_entity->field_moodle_id->value;
    }

    // Copy course from template.
    if ($template) {

      // We need to update course fields after duplicating, because the duplicate_courses function
      // does not support most fields..
      $updatelater = true;

      $function = 'core_course_duplicate_course';
      $params = [
        'courseid' => $template,
        'categoryid' => $categoryid,
      ];
      $fullnamefield = $map_fields['fullname'];
      $params['fullname'] = $service->getValue($entity, $fullnamefield);
      $shortnamefield = $map_fields['shortname'];
      $params['shortname'] = $service->getValue($entity, $shortnamefield);

      // Abort if required parameters are not set.
      if (!$params['fullname'] || !$params['shortname'] || !$params['shortname']) {
        $message = t('No API call was made to Moodle. Required fields are missing. <p>Params: @params</p>',
          array('@params' => json_encode($params)));
        \Drupal::service('moodle_sync.logger')->log($message, 'warning', $function, json_encode($params), $id, null);
        return;
      }

    // Create fresh course.
    } else {

      $updatelater = false;
      $function = 'core_course_create_courses';
      $params["courses[0][categoryid]"] = $categoryid;
      $params["courses[0][fullname]"] = null;
      $params["courses[0][shortname]"] = null;

      // Add course base fields.
      foreach ($map_fields as $moodlefield => $drupalfield) {

        $value = null;
        if ($drupalfield && $moodlefield) {
          $value = $service->getValue($entity, $drupalfield);
        }
        if ($value) {
          $params["courses[0][$moodlefield]"] = $value;
        }
      }

      // Abort if required parameters are not set.
      if (!$params["courses[0][fullname]"] || !$params["courses[0][shortname]"] || !$params["courses[0][categoryid]"]) {
        $message = t('No API call was made to Moodle. Required fields are missing. <p>Params: @params</p>',
          array('@params' => json_encode($params)));
        \Drupal::service('moodle_sync.logger')->log($message, 'warning', $function, json_encode($params), $id, null);
        return;
      }

      // Add course custom fields.
      $i = 0;
      foreach ($map_customfields as $moodlefield => $drupalfield) {
        if ($drupalfield && $moodlefield) {
          $value = $service->getValue($entity, $drupalfield);
        }
        if ($value) {
          $params["courses[0][customfields][$i][shortname]"] = $moodlefield;
          $params["courses[0][customfields][$i][value]"] = $value;
        }
        $i++;
      }
    }

    // Call Moodle Sync service for API call.
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

    // Analyze response and log results.
    $moodle_id = null;
    if (property_exists($response, 'exception')) {
      $message = t('Error creating Moodle course for Drupal entity. No course was created. <p>Error: @exception</p><p>Params: @params</p>',
        array('@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';

    } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
      $message = $response->warnings[0]->message;
      $type = 'warning';

    } else {
      $type = 'info';
      $moodle_id = $response->id;
      if ($template) {
        $message = t('Duplicated Moodle course with id @templateid into new course with id @moodle_id in category @categoryid.',
          array('@templateid' => $template, '@moodle_id' => $moodle_id, '@categoryid' => $categoryid));
      } else {
        $message = t('Created Moodle course with id @moodle_id in category @categoryid.',
          array('@templateid' => $template, '@moodle_id' => $moodle_id, '@categoryid' => $categoryid));
      }
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $id, $moodle_id);

    // Write back moodle id to Drupal entity.
    if ($moodle_id) {
      $entity->field_moodle_id->value = $moodle_id;
      if (method_exists($entity, 'setNewRevision')) {
        $entity->setNewRevision(TRUE);
      }
      $entity->save();

      // Update course fields if we duplicated a course.
      if ($updatelater) {
        \Drupal::service('moodle_sync_course.update')->updateCourse($entity, null, TRUE);
      }
    }

    // Return Moodle course id because why not.
    return $moodle_id;

  }

}
