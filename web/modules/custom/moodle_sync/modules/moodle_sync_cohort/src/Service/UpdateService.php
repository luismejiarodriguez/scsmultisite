<?php

namespace Drupal\moodle_sync_cohort\Service;

use Drupal\moodle_sync_cohort\Utility\MoodleCohort;

class UpdateService {
  /*
   * Updates Moodle cohorts for a user.
   *
   * @param $user Drupal User
   * @param $profile Drupal Profile
   *
   */
  public function updateUserCohorts($user, $profile) {

    // Get config and basics.
    $config = \Drupal::config('moodle_sync_cohort.settings');
    if (!$entity_type = $config->get('reference_entity')) {
      return;
    }
    if (!$field = $config->get('reference_field')) {
      return;
    }
    $added = [];
    $removed = [];

    // User was updated.
    if ($user && $entity_type == 'user') {
      $entity = $user;

    // Profile was updated.
    } elseif ($profile && $entity_type == 'profile') {
      $profile_type = $config->get('profile_type');
      if ($profile->bundle() !== $profile_type) {
        return;
      }
      $entity = $profile;
      $user = $profile->getOwner();

    // Nothing to do.
    } else {
      return;
    }

    // User has no Moodle ID.
    if (!$userid = $user->field_moodle_id?->value) {
      return;
    }

    // If entity has original, check if the field has changed.
    if ($entity->original) {

      // If field is unchanged, do nothing.
      if ($entity->$field->getString() === $entity->original->$field->getString()) {
        return;
      }

      // Initialize arrays.
      $old_ids = [];
      $new_ids = [];

      // Get old entity IDs.
      foreach($entity->original->$field->getValue() as $item) {
        $old_ids[] = $item['target_id'];
      }

      // Get new entity IDs.
      foreach($entity->$field->getValue() as $item) {
        $new_ids[] = $item['target_id'];
      }

      // Get added and removed entity IDs.
      $added = array_diff($new_ids, $old_ids);
      $removed = array_diff($old_ids, $new_ids);
    }

    else {
      // Get new entity IDs.
      $new_ids = [];
      foreach($entity->$field->getValue() as $item) {
        $new_ids[] = $item['target_id'];
      }
      $added = $new_ids;
    }

    // Add cohorts.
    $params = [];
    foreach ($added as $key => $tid) {

      // Get cohort ID.
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
      if (!$cohortid = $term->field_moodle_id?->value) {
        unset($added[$key]);
        continue;
      }

      // Write params.
      $params["members[$key][cohorttype][type]"] = 'id';
      $params["members[$key][cohorttype][value]"] = $cohortid;
      $params["members[$key][usertype][type]"] = 'id';
      $params["members[$key][usertype][value]"] = $userid;
    }

    if ($params) {
      $function = 'core_cohort_add_cohort_members';
      $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, TRUE);

      // Analyze response and log results.
      if ($response && property_exists($response, 'exception')) {
        $message = t('Error adding Moodle user @userid to Moodle cohorts. <p>Error: @exception</p><p>Params: @params</p>',
          array('@userid' => $userid, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';
      }
      else {
        $message = t('Added Moodle user @userid to @count Moodle cohorts.',
          array('@userid' => $userid, '@count' => count($added)));
        $type = 'info';
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params));
    }

    // Remove cohorts.
    $params = [];
    foreach ($removed as $key => $tid) {

      // Get cohort ID.
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
      if (!$cohortid = $term->field_moodle_id?->value) {
        unset($removed[$key]);
        continue;
      }

      // Write params.
      $params["members[$key][cohortid]"] = $cohortid;
      $params["members[$key][userid]"] = $userid;
    }

    if ($params) {
      $function = 'core_cohort_delete_cohort_members';
      $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, TRUE);

      // Analyze response and log results.
      if ($response && property_exists($response, 'exception')) {
        $message = t('Error removing Moodle user @userid to Moodle cohorts. <p>Error: @exception</p><p>Params: @params</p>',
          array('@userid' => $userid, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';
      }
      else {
        $message = t('Removed Moodle user @userid from @count Moodle cohorts.',
          array('@userid' => $userid, '@count' => count($added)));
        $type = 'info';
      }

      \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params));
    }

  }

  /*
   * Creates Moodle cohort.
   *
   * @param $term taxonomy term.
   *
   */
  public function createCohort($term) {

    // Basic checks.
    $tid = $term->id();
    if (!MoodleCohort::process($term)) {
      return;
    }

    // API call.
    $params = MoodleCohort::getParams($term);
    $function = 'core_cohort_create_cohorts';
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, TRUE);

    // Analyze response and log results.
    if ($response && property_exists($response, 'exception')) {
      $message = t('Error creating Moodle cohort for Drupal taxonomy term @tid. <p>Error: @exception</p><p>Params: @params</p>',
        array('@tid' => $tid, '@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';
    }
    else {
      if (property_exists($response, 'id')) {

        // Save Moodle id.
        $cohortid = $response->id;
        $term->field_moodle_id = $cohortid;
        $term->save();

        $message = t('Created Moodle cohort @cohortid for Drupal taxonomy term @tid.',
          array('@cohortid' => $cohortid, '@tid' => $tid));
        $type = 'info';
      }
      else {
        $message = t('Unknown error creating Moodle cohort for Drupal taxonomy term @tid. <p>Error: @exception</p><p>Params: @params</p>',
          array('@tid' => $tid, '@exception' => json_encode($response), '@params' => json_encode($params)));
        $type = 'error';
      }
    }

    // Log.
    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params));
  }

  /*
   * Updates Moodle cohort.
   *
   * @param $term taxonomy term.
   *
   */
  public function updateCohort($term) {

    // Basic checks.
    $tid = $term->id();
    if (!MoodleCohort::process($term)) {
      return;
    }
    if (!$cohortid = $term->field_moodle_id->value) {
      $this->createCohort($term);
      return;
    }
    if (!MoodleCohort::changed($term)) {
      return;
    }

    // API call.
    $params = MoodleCohort::getParams($term);
    $params['cohorts[0][id]'] = $cohortid;
    $function = 'core_cohort_update_cohorts';
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, TRUE);

    // Analyze response and log results.
    if ($response && property_exists($response, 'exception')) {
      $message = t('Error updating Moodle cohort for Drupal taxonomy term @tid. <p>Error: @exception</p><p>Params: @params</p>',
        array('@tid' => $tid, '@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';
    }
    // Success gives no response. Thanks Moodle!
    else {
      $message = t('Updated Moodle cohort @cohortid for Drupal taxonomy term @tid.',
        array('@cohortid' => $cohortid, '@tid' => $tid));
      $type = 'info';
    }

    // Log.
    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params));
  }

  /*
   * Deletes a Moodle cohort.
   *
   * @param $term taxonomy term.
   *
   */
  public function deleteCohort($term) {

    // Basic checks.
    $tid = $term->id();
    if (!MoodleCohort::process($term)) {
      return;
    }
    if (!$cohortid = $term->field_moodle_id->value) {
      return;
    }

    // API call.
    $params = ['cohortids' => [$cohortid]];
    $function = 'core_cohort_delete_cohorts';
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, TRUE);

    // Analyze response and log results.
    if ($response && property_exists($response, 'exception')) {
      $message = t('Error updating Moodle cohort for Drupal taxonomy term @tid. <p>Error: @exception</p><p>Params: @params</p>',
        array('@tid' => $tid, '@exception' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';
    }
    // Success gives no response. Thanks Moodle!
    else {
      $message = t('Deleted Moodle cohort @cohortid for Drupal taxonomy term @tid.',
        array('@cohortid' => $cohortid, '@tid' => $tid));
      $type = 'info';
    }

    // Log.
    \Drupal::service('moodle_sync.logger')->log($message, $type, $function, json_encode($params));
  }
}
