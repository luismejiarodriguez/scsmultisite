<?php

namespace Drupal\moodle_sync_enrollment\Service;

use Drupal\moodle_sync_enrollment\Utility\MoodleEnrollment;

class CreateService {
  /**
   * Creates a Moodle enrollment.
   *
   * @param $registration Drupal entity.
   * @param $suspend 0 to suspend enrollment, 1 to activate.
   *
   **/
  public function createEnrollment($registration, $suspend) {

    // Get config.
    $config = \Drupal::config('moodle_sync_enrollment.settings');

    // Check if this entity should be processed.
    if (!$registration_types = $config->get('registration_types')) {
      return;
    }
    if (!in_array($registration->bundle(), $registration_types)) {
      return;
    }

    // Get service Moodle Sync.
    $service = \Drupal::service('moodle_sync.sync');
    $function = 'enrol_manual_enrol_users';

    // Get entities.
    $user = null;
    if ($host = $registration->getHostEntity()) {
      if ($node = $host->getEntity()) {
        $user = $registration->user_uid->entity;
      }
    }
    if (!$user) {
      return;
    }

    // Get parameters.
    if (!$userid = MoodleEnrollment::getUserId($user)) {
      return;
    }
    if (!$courseid = MoodleEnrollment::getCourseId($node)) {
      return;
    }
    $roles = MoodleEnrollment::getRoles($registration, $config);

    // Build params.
    $params = array();
    foreach ($roles as $key => $role) {
      $params["enrolments[$key][userid]"] = $userid;
      $params["enrolments[$key][roleid]"] = $role;
      $params["enrolments[$key][courseid]"] = $courseid;
      $params["enrolments[$key][suspend]"] = $suspend;
    }

    // Call Moodle Sync service for API call.
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

    // Define action.
    $verb = $suspend ? 'suspending' : 'creating';
    $action = $suspend ? 'Suspended' : 'Created';

    // Analyze response and log results.
    if ($response && property_exists($response, 'exception')) {
      $message = t('Error @verb Moodle enrollment for Drupal registration. No enrollment was created. <p>Error: @exception</p><p>Params: @params</p>',
        array('@verb' => $verb, '@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';

    } else {
      $type = 'info';
      $message = t('@action Moodle enrollment for Moodle user @userid in course @courseid.',
        array('@action' => $action, '@userid' => $userid, '@courseid' => $courseid));
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $userid, null);

  }

}
