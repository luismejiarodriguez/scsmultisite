<?php

namespace Drupal\moodle_sync_enrollment\Service;

use Drupal\moodle_sync_enrollment\Utility\MoodleEnrollment;

class UpdateService {
  /**
   * Updates a Moodle enrollment.
   *
   * @param $registration Drupal entity.
   *
   **/
  public function updateEnrollment($registration) {

    // Get config.
    $config = \Drupal::config('moodle_sync_enrollment.settings');

    // Check if this entity should be processed.
    if (!$registration_types = $config->get('registration_types')) {
      return;
    }
    if (!in_array($registration->bundle(), $registration_types)) {
      return;
    }

    // Determine if there has been a change in the registration.
    if (!$original = $registration->original) {
      return;
    }
    $oldroles = MoodleEnrollment::getRoles($original, $config);
    $newroles = MoodleEnrollment::getRoles($registration, $config);

    if ($oldroles == $newroles) {
      return;
    }

    $roles_to_add = array_diff($newroles, $oldroles);
    $roles_to_remove = array_diff($oldroles, $newroles);

    // Common parameters.
    if ($roles_to_add || $roles_to_remove) {

      // Get service Moodle Sync.
      $service = \Drupal::service('moodle_sync.sync');

      // Get entities.
      $host = $registration->getHostEntity();
      $node = $host->getEntity();
      $user = $registration->user_uid->entity;

      // Get parameters.
      if (!$userid = MoodleEnrollment::getUserId($user)) {
        return;
      }
      if (!$courseid = MoodleEnrollment::getCourseId($node)) {
        return;
      }
    }

    // Add roles.
    if ($roles_to_add) {

      // Set WS function.
      $function = 'enrol_manual_enrol_users';

      // Build params.
      $params = array();
      foreach ($roles_to_add as $key => $role) {
        $params["enrolments[$key][userid]"] = $userid;
        $params["enrolments[$key][roleid]"] = $role;
        $params["enrolments[$key][courseid]"] = $courseid;
        $params["enrolments[$key][suspend]"] = 0;
      }

      // Call Moodle Sync service for API call.
      $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

      // Analyze response and log results.
      if ($response && property_exists($response, 'exception')) {
        $message = t('Error adding Moodle role for Drupal registration. No role was created. <p>Error: @exception</p><p>Params: @params</p>',
          array('@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';

      } else {
        $type = 'info';
        $message = t('Added Role(s) for Moodle user @userid in Moodle course @courseid.',
          array('@userid' => $userid, '@courseid' => $courseid));
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $userid, null);

    }

    // Remove roles.
    if ($roles_to_remove) {

      // Set WS function.
      $function = 'core_role_unassign_roles';

      // Get entities.
      $host = $registration->getHostEntity();
      $node = $host->getEntity();
      $user = $registration->user_uid->entity;

      // Get parameters.
      if (!$userid = MoodleEnrollment::getUserId($user)) {
        return;
      }
      if (!$courseid = MoodleEnrollment::getCourseId($node)) {
        return;
      }

      // Build params.
      $params = array();
      foreach ($roles_to_remove as $key => $role) {
        $params["unassignments[$key][userid]"] = $userid;
        $params["unassignments[$key][roleid]"] = $role;
        $params["unassignments[$key][instanceid]"] = $courseid;
        $params["unassignments[$key][contextlevel]"] = 'course';
      }

      // Call Moodle Sync service for API call.
      $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

      // Analyze response and log results.
      if ($response && property_exists($response, 'exception')) {
        $message = t('Error removing Moodle role for Drupal registration. No role was created. <p>Error: @exception</p><p>Params: @params</p>',
          array('@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';

      } else {
        $type = 'info';
        $message = t('Removed Role(s) for Moodle user @userid in Moodle course @courseid.',
          array('@userid' => $userid, '@courseid' => $courseid));
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $userid, null);

    }

  }

}
