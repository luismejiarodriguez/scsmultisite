<?php

namespace Drupal\moodle_sync_user\Service;

use Drupal\moodle_sync_user\Service;
use Drupal\moodle_sync_user\Utility\MoodleUser;

class DeleteService {

  /*
   * Deletes a Moodle user.
   *
   * @param $entity Drupal user.
   *
   */
  public function deleteUser($user) {

    // Get config.
    $config = \Drupal::config('moodle_sync_user.settings');

    // Check if deletion is enabled in config.
    if (!$config->get('deletion')) {
      return;
    }

    // Check if this entity should be processed.
    if (!MoodleUser::process($user, $config)) {
      return;
    }

    $function = 'core_user_delete_users';

    $moodle_id = $user->get('field_moodle_id')->getString();

    if ($moodle_id) {
      // Set parameters.
      $params = [
        'userids[0]' => $moodle_id,
      ];

      // Send the API request to delete the Moodle User.
      $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params);

      // Analyze response and log results.
      if ($response) {
        if (property_exists($response, 'exception')) {
          $message = t('Error deleting Moodle user. Error: @exception. Query string: @query_string',
            array('@exception' => json_encode($response), '@query_string' => json_encode($params)));
          $type = 'error';

        } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
          $message = $response->warnings[0]->message;
          $type = 'warning';
        }

      // Moodle gives no response if the user was deleted.
      } else {
        $type = 'info';
        $message = t('Deleted Moodle user @moodle_id.',
          array('@moodle_id' => $moodle_id));
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params), $user->id(), null);
    }
  }
}
