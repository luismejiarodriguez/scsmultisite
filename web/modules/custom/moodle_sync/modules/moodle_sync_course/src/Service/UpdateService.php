<?php

namespace Drupal\moodle_sync_course\Service;

use Drupal\moodle_sync_course\Utility\MoodleCourse;

class UpdateService {
  /*
   * Updates a Moodle course.
   *
   * @param $entity Drupal entity.
   * @param $moodle_id the moodle ID - given from create function to prevent infinite loop.
   * @param $forceupdate force update (including ID), called from course duplication.
   *
   */
  public function updateCourse($entity, $moodle_id = null, $forceupdate = FALSE) {

    // Get config.
    $config = \Drupal::config('moodle_sync_course.settings');

    // Check if this entity should be processed.
    if (!MoodleCourse::process($entity, $config)) {
      return;
    }

    // If entity does not have a Moodle ID yet, try creating a Moodle course.
    if (!$moodle_id) {
      if (!$moodle_id = $entity->field_moodle_id->value) {
        \Drupal::service('moodle_sync_course.create')->createCourse($entity);
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

    // When updating an existing node, we will only call the API if one of the synced fields really did change.
    if ($entity->original && !$forceupdate) {
      $needsupdate = false;
    } else {
      $needsupdate = true;
      $params['courses[0][idnumber]'] = $id;
    }

    // Get course category.
    $categoryid = MoodleCourse::getCourseCategory($entity, $config);
    if ($entity->original) {
      $categoryid_orig = MoodleCourse::getCourseCategory($entity, $config);
      if ($categoryid_orig !== $categoryid) {
        $needsupdate = true;
      }
    }

    $params['courses[0][categoryid]'] = $categoryid;

    // Add course base fields for all fields that changed.
    $service = \Drupal::service('moodle_sync.sync');

    foreach ($map_fields as $moodlefield => $drupalfield) {
      $value = null;
      if ($drupalfield) {

        // Add all fields to params.
        if ($forceupdate) {
          $value = $service->getValue($entity, $drupalfield);
        }

        // Add changed fields to params.
        else if ($entity->original) {
          $newvalue = $service->getValue($entity, $drupalfield);
          if ($newvalue !== $service->getValue($entity->original, $drupalfield)) {
            $needsupdate = true;
            $value = $newvalue;
          }
        }
      }

      // Add value to query string.
      if ($value) {
        $params["courses[0][$moodlefield]"] = $value;
      }
    }

    // Add course custom fields for all fields that changed.
    $i = 0;
    foreach ($map_customfields as $moodlefield => $drupalfield) {
      $value = null;
      if ($drupalfield) {

        // Add changed fields to params.
        if ($entity->original) {
          $newvalue = $service->getValue($entity, $drupalfield);
          if ($newvalue !== $service->getValue($entity->original, $drupalfield)) {
            $needsupdate = true;
            $value = $newvalue;
          }
        }
      }

      // Add value to query string.
      if ($value) {
        $params["courses[0][customfields][$i][shortname]"] = $moodlefield;
        $params["courses[0][customfields][$i][value]"] = $service->getValue($entity, $drupalfield);
        $i++;
      }
    }

    // Call Moodle Sync service for API call.
    if ($needsupdate) {
      $response = $service->apiCall($function, $params);

      // Analyze response and log results.
      if (property_exists($response, 'exception')) {
        $message = t('Error updating Moodle course @moodle_id with settings from Drupal node @nid. <p>Error: @exception</p><p>Params: @params</p>',
          array('@moodle_id' => $moodle_id, '@nid' => $id, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';

      } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
        $message = t('Error updating Moodle course @moodle_id with settings from Drupal node @nid. <p>Warning: @exception</p><p>Params: @params</p>',
          array('@moodle_id' => $moodle_id, '@nid' => $id, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'warning';

      } else {
        $message = t('Updated Moodle course @moodle_id with settings from Drupal node @nid.',
          array('@moodle_id' => $moodle_id, '@nid' => $id));
        $type = 'info';
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $id, $moodle_id);
    }

  }

  public function updateRemoteCourse($entity, $entity_original, $moodle_id = null, $forceupdate = FALSE) {

    // Get config.
    $config = \Drupal::config('moodle_sync_course.settings');

    // Check if this entity should be processed.
    if (!MoodleCourse::process($entity, $config)) {
      return;
    }

    // If entity does not have a Moodle ID yet, try creating a Moodle course.
    if (!$moodle_id) {
      if (!$moodle_id = $entity->field_moodle_id->value) {
        \Drupal::service('moodle_sync_course.create')->createCourse($entity);
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

    // When updating an existing node, we will only call the API if one of the synced fields really did change.
    if ($entity_original && !$forceupdate) {
      $needsupdate = false;
    } else {
      $needsupdate = true;
      $params['courses[0][idnumber]'] = $id;
    }

    // Get course category.
    $categoryid = MoodleCourse::getCourseCategory($entity, $config);
    if ($entity_original) {
      $categoryid_orig = MoodleCourse::getCourseCategory($entity, $config);
      if ($categoryid_orig !== $categoryid) {
        $needsupdate = true;
      }
    }
    $params['courses[0][categoryid]'] = $categoryid;

    // Add course base fields for all fields that changed.
    $service = \Drupal::service('moodle_sync.sync');

    foreach ($map_fields as $moodlefield => $drupalfield) {
      $value = null;
      if ($drupalfield) {

        // Add all fields to params.
        if ($forceupdate) {
          $value = $service->getValue($entity, $drupalfield);
        }

        // Add changed fields to params.
        else if ($entity_original) {
          $newvalue = $service->getValue($entity, $drupalfield);
          if ($newvalue !== $service->getValue($entity_original, $drupalfield)) {
            $needsupdate = true;
            $value = $newvalue;
          }
        }
      }

      // Add value to query string.
      if ($value) {
        $params["courses[0][$moodlefield]"] = $value;
      }
    }

    // Add course custom fields for all fields that changed.
    $i = 0;
    foreach ($map_customfields as $moodlefield => $drupalfield) {
      $value = null;
      if ($drupalfield) {

        // Add changed fields to params.
        if ($entity_original) {
          $newvalue = $service->getValue($entity, $drupalfield);
          if ($newvalue !== $service->getValue($entity_original, $drupalfield)) {
            $needsupdate = true;
            $value = $newvalue;
          }
        }
      }

      // Add value to query string.
      if ($value) {
        $params["courses[0][customfields][$i][shortname]"] = $moodlefield;
        $params["courses[0][customfields][$i][value]"] = $service->getValue($entity, $drupalfield);
        $i++;
      }
    }
    // Call Moodle Sync service for API call.
    if ($needsupdate) {
      $response = $service->apiCall($function, $params);

      // Analyze response and log results.
      if (property_exists($response, 'exception')) {
        $message = t('Error updating Moodle course @moodle_id with settings from Drupal node @nid. <p>Error: @exception</p><p>Params: @params</p>',
          array('@moodle_id' => $moodle_id, '@nid' => $id, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';

      } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
        $message = t('Error updating Moodle course @moodle_id with settings from Drupal node @nid. <p>Warning: @exception</p><p>Params: @params</p>',
          array('@moodle_id' => $moodle_id, '@nid' => $id, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'warning';

      } else {
        $message = t('Updated Moodle course @moodle_id with settings from Drupal node @nid.',
          array('@moodle_id' => $moodle_id, '@nid' => $id));
        $type = 'info';
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $id, $moodle_id);
    }

  }

}
