<?php

namespace Drupal\moodle_sync_enrollment\Utility;

use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\Entity\Term;

class MoodleEnrollment {

  /**
   * Returns role ids for Moodle enrollment.
   *
   * @param $registration Drupal registration.
   * @param $config module config.
   *
   * @return array.
   */
  static function getRoles($registration, $config) {

    $roles = array();
    if ($rolefield = $config->get('role_field')) {
      if ($registration->hasField($rolefield)) {
        $roleterms = $registration->get($rolefield)->getValue();
        foreach ($roleterms as $roleterm) {
          $term = Term::load($roleterm['target_id']);
          if ($term->field_moodle_id) { // hasField() doesnt work for Terms.
            if ($roleid = $term->field_moodle_id->value) {
              $roles[] = $roleid;
            }
          }
        }
      }
    }
    if (!$roles) {
      $roles = ['5']; // Default role: student.
    }

    return $roles;
  }

  /**
   * Returns course id for Moodle enrollment.
   *
   * @param $node Drupal node.
   *
   * @return array.
   */
  static function getCourseId($node) {

    if (!$node->hasField('field_moodle_id')) {
      return;
    }
    if (!$courseid = $node->field_moodle_id->value) {
      $message = t('No Moodle ID set on Drupal node. Enrollment cannot be processed.');
      $type = 'warning';
      \Drupal::service('moodle_sync.logger')->log($message, $type, null, null, $node->id());
      return;
    }

    return $courseid;
  }

  /**
   * Returns user id for Moodle enrollment.
   *
   * @param $user Drupal user.
   *
   * @return array.
   */
  static function getUserId($user) {

    // Get Moodle userid.
    if (!$user->hasField('field_moodle_id')) {
      $message = t('No Moodle ID field found on Drupal user. Enrollment cannot be processed.');
      $type = 'warning';
      \Drupal::service('moodle_sync.logger')->log($message, $type);
      return;
    }
    if (!$userid = $user->field_moodle_id->value) {
      $message = t('No Moodle ID set on Drupal user. Enrollment cannot be processed.');
      $type = 'warning';
      \Drupal::service('moodle_sync.logger')->log($message, $type);
      return;
    }

    return $userid;
  }

}
