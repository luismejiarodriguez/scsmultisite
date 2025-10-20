<?php

namespace Drupal\moodle_sync_user\Service;

use Drupal\moodle_sync_user\Utility\MoodleUser;

class CreateService {

  /*
   * Creates a Moodle user.
   *
   * @param $user Drupal user.
   *
   */
  public function createUser($user) {

    // Get config.
    $config = \Drupal::config('moodle_sync_user.settings');

    // If Moodle ID is already set, do not create a new user.
    if ($user->field_moodle_id->value) {
      return;
    }

    // Check if this entity should be processed.
    if (!MoodleUser::process($user, $config)) {
      return;
    }

    // Get entity id and fields to map.
    $map_fields = $config->get('map_fields');
    $map_customfields = $config->get('map_customfields');
    $query_string = array();

    // Set basic parameters.
    $function = 'core_user_create_users';
    $query_string['users[0][idnumber]'] = $user->id();
    $query_string['users[0][auth]'] = $config->get('auth');
    $query_string['users[0][password]'] = 'soVERYcomplicated1234_';
    $query_string['users[0][firstname]'] = 'tbd';
    $query_string['users[0][lastname]'] = 'tbd';

    // Get field mappings.
    if (!$config || !$map_fields) {
      $message = t('Failed to create Moodle User because no fields are mapped in the settings.');
      $type = 'error';
      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($query_string), $user->id(), null);
      return;
    }

    // Add user base fields.
    $service = \Drupal::service('moodle_sync.sync');
    foreach ($map_fields as $moodlefield => $drupalfield) {
      $value = null;
      if ($drupalfield) {

        // Skip user profile fields - these are handled elsewhere.
        if (strpos($drupalfield, "profile_") === 0) {
          continue;
        }

        // Set username and email to lowercase for Moodle.
        if ($moodlefield == 'username' || $moodlefield == 'email') {
          if ($value = $service->getValue($user, $drupalfield)) {
            $value = strtolower($value);
          } else {
            $message = t('Cannot create Moodle user with empty username or email.');
            $type = 'error';
            \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($query_string), $user->id(), null);
            return;
          }
        } else {
          $value = $service->getValue($user, $drupalfield);
        }
      }

      // Add value to query string.
      if ($value) {
        $query_string["users[0][$moodlefield]"] = $value;
      }
    }

    // Send the API request to create the Moodle User.
    $response = $service->apiCall($function, $query_string);

    // Analyze response and log results.
    $moodle_id = null;
    if (property_exists($response, 'exception')) {
      $message = t('Error creating Moodle user. Error: @exception. Query string: @query_string',
        array('@exception' => json_encode($response), '@query_string' => json_encode($query_string)));
      $type = 'error';

    } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
      $message = $response->warnings[0]->message;
      $type = 'warning';

    } else {
      if (property_exists($response, 'id')) {
        $moodle_id = $response->id;
      }
      $message = t('Created Moodle user id @moodle_id.',
        array('@moodle_id' => $moodle_id));
      $type = 'info';
    }

    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($query_string), $user->id(), $moodle_id);

    // Write back moodle id to Drupal entity.
    if ($moodle_id) {
      $user->field_moodle_id->value = $moodle_id;
      $user->save();
    }
  }
}
