<?php

namespace Drupal\moodle_sync_user\Service;

use Drupal\moodle_sync_user\Service;
use Drupal\moodle_sync_user\Utility\MoodleUser;

class UpdateService {

  /*
   * Updates a Moodle user.
   *
   * @param $user Drupal user.
   * @param $needsupdate Always update even if fields have not changed.
   * @param Profile $profile Drupal profile.
   *
   */
  public function updateUser($user, $needsupdate = false, $profile = null) {

    // Get config.
    $config = \Drupal::config('moodle_sync_user.settings');

    // Check if this entity should be processed.
    if (!MoodleUser::process($user, $config)) {
      return;
    }

    // If entity does not have a Moodle ID yet, try creating a Moodle user.
    if (!$moodle_id = $user->field_moodle_id->value) {
      \Drupal::service('moodle_sync_user.create_service')->createUser($user);
      return;
    }

    // If profile is updated, get the profile type.
    if ($profile) {
      $profileType = "profile_" . $profile->bundle();
    }

    // Get entity id and fields to map.
    $id = $user->id();
    $map_fields = $config->get('map_fields');
    $map_customfields = $config->get('map_customfields');
    $query_string = array();

    // Set basic parameters.
    $function = 'core_user_update_users';
    $query_string['users[0][id]'] = $moodle_id;
    $query_string['users[0][idnumber]'] = $user->id();

    // We will only call the API if one of the synced fields really did change.
    $needsupdate = false;

    // Add user base fields for all fields that changed.
    $service = \Drupal::service('moodle_sync.sync');
    foreach ($map_fields as $moodlefield => $drupalfield) {
      $value = null;
      if ($drupalfield) {

        // Profile fields.
        if (strpos($drupalfield, "profile_") === 0) {

          // Get fields from profile.
          if ($profile) {
            if (strpos($drupalfield, $profileType) === 0) {
              $drupalfield = str_replace($profileType . "_", '', $drupalfield);
              $value = $service->getValue($profile, $drupalfield);
              $query_string["users[0][$moodlefield]"] = $value;
            }
          }
          continue;
        }

        // Add changed fields to params.
        if ($user->original) {
          if ($user->$drupalfield->getString() !== $user->original->$drupalfield->getString()) {
            $needsupdate = true;
            $value = $service->getValue($user, $drupalfield);
          }

        // Add all fields to params if no original entity exists (being run from profile hook).
        } else {
          $needsupdate = true;
          $value = $service->getValue($user, $drupalfield);
        }

        // Set username and email to lowercase for Moodle.
        if ($moodlefield == 'username' || $moodlefield == 'email') {
          if ($value = $service->getValue($user, $drupalfield)) {
            $value = strtolower($value);
          }
        }
      }

      // Add value to query string.
      if ($value) {
        $query_string["users[0][$moodlefield]"] = $value;
      }
    }

    // Add user custom fields for all fields that changed.
    foreach ($map_customfields as $moodlefield => $drupalfield) {
      $value = null;
      if ($drupalfield) {

        // Profile fields.
        if (strpos($drupalfield, "profile_") === 0) {

          // Get fields from profile.
          if ($profile) {
            if (strpos($drupalfield, $profileType) === 0) {
              $drupalfield = str_replace($profileType . "_", '', $drupalfield);
              $value = $service->getValue($profile, $drupalfield);
            }
          }
          continue;
        }

        // Add changed fields to params.
        if ($user->original) {
          if ($user->$drupalfield->value !== $user->original->$drupalfield->value) {
            $needsupdate = true;
            $value = $service->getValue($user, $drupalfield);
          }

        // Add all fields to params if no original entity exists (being run from profile hook).
        } else {
          $value = $service->getValue($user, $drupalfield);
        }
      }

      // Add value to query string.
      if ($value) {
        $query_string["users[0][customfields][0][type]"] = $moodlefield;
        $query_string["users[0][customfields][0][value]"] = $user->$drupalfield->value;
      }
    }

    // Send the API request to create the Moodle User.
    if ($needsupdate) {
      $response = $service->apiCall($function, $query_string);

      // Analyze response and log results.
      if (property_exists($response, 'exception')) {
        $message = t('Error updating Moodle user. Error: @exception. Query string: @query_string',
          array('@exception' => json_encode($response), '@query_string' => json_encode($query_string)));
        $type = 'error';

      } elseif (property_exists($response, 'warnings') && count($response->warnings) > 0) {
        $message = t('Error updating Moodle user. Error: @warning. Query string: @query_string',
          array('@warning' => json_encode($response), '@query_string' => json_encode($query_string)));
        $type = 'warning';

      } else {
        $type = 'info';
        $message = t('Updated Moodle user @moodle_id with data from Drupal user @uid.',
          array('@uid' => $user->id(), '@moodle_id' => $moodle_id));
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($query_string), $user->id(), null);
    }

  }
}
