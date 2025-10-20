<?php

namespace Drupal\moodle_sync_user\Utility;

use Drupal\Core\Entity\EntityInterface;

class MoodleUser {

  /**
   * Determines if an entity should be processed.
   *
   * @param $entity Drupal entity.
   * @param $config module config.
   *
   * @return boolean.
   */
  static function process($entity, $config) {

    // Get blacklisted roles.
    $allowedRoles = $config->get('roles');

    // Get user roles.
    $roles = $entity->getRoles();

    // Check if any of those roles is blacklisted.
    // And check if allowedRoles is an array.
    foreach ($roles as $role) {
      if(isset($allowedRoles) && is_array($allowedRoles)) {
        if(array_key_exists($role, $allowedRoles)) {
          if ($allowedRoles[$role] == 1) {
            $message = t('User @uid has not been synced to moodle because of the role settings.',
              ['@uid' => $entity->id()]);
            $type = 'warning';
            \Drupal::service('moodle_sync.logger')->log($message, $type, null, null);
            return false;
          }
        }
      }
    }

    // foreach ($roles as $role) {
    //   if(array_key_exists($role, $allowedRoles)) {
    //     if ($allowedRoles[$role] == 1) {
    //       $message = t('User @uid has not been synced to moodle because of the role settings.',
    //         ['@uid' => $entity->id()]);
    //       $type = 'warning';
    //       \Drupal::service('moodle_sync.logger')->log($message, $type, null, null);
    //       return false;
    //     }
    //   }
    // }

    // Process entity.
    return true;

  }

}
