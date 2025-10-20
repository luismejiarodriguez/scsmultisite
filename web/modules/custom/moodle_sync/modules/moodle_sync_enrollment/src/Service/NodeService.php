<?php

namespace Drupal\moodle_sync_enrollment\Service;

use Drupal\moodle_sync_enrollment\Utility\MoodleEnrollment;
use Drupal\user\Entity\User;

class NodeService {
  /**
   * Syncs referenced users in a node to Moodle.
   *
   * @param $node Drupal Node.
   *
   **/
  public function syncField($node) {

    // Get config.
    $config = \Drupal::config('moodle_sync_enrollment.settings');

    // Check if this entity should be processed.
    if (!$courseid = MoodleEnrollment::getCourseId($node)) {
      return;
    }
    if (!$fieldname = $config->get('fieldname')) {
      return;
    }
    if (!$role = $config->get('role')) {
      return;
    }

    // If the course has just been created, sync all users.
    if (!$node->original->field_moodle_id->value) {
      $users_to_enroll = explode(', ', $node->get($fieldname)->getString());
      $users_to_suspend = array();
    } else {
      $users = explode(', ', $node->get($fieldname)->getString());
      $users_old = explode(', ', $node->original->get($fieldname)->getString());
      $users_to_enroll = array_diff($users, $users_old);
      $users_to_suspend = array_diff($users_old, $users);
    }

    // Nothing to do.
    if (!$users_to_enroll && !$users_to_suspend) {
      return;
    }

    // Get service Moodle Sync.
    $service = \Drupal::service('moodle_sync.sync');
    $function = 'enrol_manual_enrol_users';
    $i = 0;
    $params = array();
    $enrolled = array();
    $suspended = array();

    // Enroll users.
    foreach ($users_to_enroll as $uid) {

      // Get user.
      if (!$user = User::load($uid)) {
        continue;
      }

      // Get Moodle user ID.
      if (!$userid = MoodleEnrollment::getUserId($user)) {
        continue;
      }

      // Add to parameters.
      $params["enrolments[$i][userid]"] = $userid;
      $params["enrolments[$i][roleid]"] = $role;
      $params["enrolments[$i][courseid]"] = $courseid;
      $params["enrolments[$i][suspend]"] = 0;

      $i++;
      $enrolled[] = $userid;
    }

    // Suspend users.
    foreach ($users_to_suspend as $uid) {

      // Get user.
      if (!$user = User::load($uid)) {
        continue;
      }

      // Get Moodle user ID.
      if (!$userid = MoodleEnrollment::getUserId($user)) {
        continue;
      }

      // Add to parameters.
      $params["enrolments[$i][userid]"] = $userid;
      $params["enrolments[$i][roleid]"] = $role;
      $params["enrolments[$i][courseid]"] = $courseid;
      $params["enrolments[$i][suspend]"] = 1;

      $i++;
      $suspended[] = $userid;
    }

    // Nothing to do.
    if (!$params) {
      return;
    }

    // Call Moodle Sync service for API call.
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

    // Cleanup: delete enrollments if no other role assignments exist.
    $unenrol_users = array();
    foreach ($suspended as $userid) {

      // Check existing roles in course for user.
      $function = 'core_enrol_get_enrolled_users';
      $params = array();
      $params['courseid'] = $courseid;
      $response2 = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, false, true);

      // Find our user in the response.
      if (is_array($response2)) {
        foreach ($response2 as $enrollment) {
          if ($enrollment->id == $userid) {

            // Collect roles.
            foreach ($enrollment->roles as $assigned_role) {
              $roles[] = $assigned_role->roleid;
            }

            // Only one role, and it is our configured role.
            if (count($roles) == 1 && $role[0] == $role) {
              $unenrol_users[] = $userid;
            }
          }
        }
      }
    }

    // Unenrol users.
    if ($unenrol_users) {

      // Build params.
      $function = 'enrol_manual_unenrol_users';
      $params = array();
      $i = 0;
      foreach ($unenrol_users as $userid) {
        $params["enrolments[$i][userid]"] = $userid;
        $params["enrolments[$i][courseid]"] = $courseid;
      }

      \Drupal::service('moodle_sync.sync')->apiCall($function, $params);
    }

    // Analyze response from first API call, and log results.
    if ($response && property_exists($response, 'exception')) {
      $message = t('Error syncing user reference field @field to Moodle enrollments in Moodle course @courseid. <p>Error: @exception</p><p>Params: @params</p>',
        array('@courseid' => $courseid,
              '@field' => $fieldname,
              '@exception' => json_encode($response),
              '@params' => json_encode($params)));
      $type = 'error';

    } else {
      $type = 'info';
      $message = t('Updated Moodle enrollments in Moodle course @courseid for users referenced in @field. Enrolled: @enrolled. Suspended: @suspended. Removed: @removed.',
        array('@courseid' => $courseid,
              '@field' => $fieldname,
              '@enrolled' => count($enrolled),
              '@suspended' => count($suspended) - count($unenrol_users),
              '@removed' => count($unenrol_users)));
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), null, null);
  }
}
