<?php

namespace Drupal\moodle_sync_user\Batch;

use Symfony\Component\HttpFoundation\RedirectResponse;


// @codingStandardsIgnoreEnd

/**
 * Methods for running user import in a batch.
 *
 * @package Drupal\csvimport
 */
class MoodleSyncAllUsersBatch {

   /*
    * Processes a single user in the batch.
    *
    * @param $user Drupal user.
    * @param $context Batch context.
    */
    public static function processUser($user, &$context) {

      if (!array_key_exists('processed', $context['results'])) {
        $context['results']['processed'] = 0;
      }

      if($user->get('field_moodle_id')->getString() === null || $user->get('field_moodle_id')->getString() === '') {
        \Drupal::logger('moodle_sync_user')->notice(t('Sync all users: creating Moodle user @uid.',
          ['@uid' => $user->id()]));
        \Drupal::service('moodle_sync_user.create_service')->createUser($user);
        $context['results']['created'][] = $user->id();
      } else {
        \Drupal::logger('moodle_sync_user')->notice(t('Sync all users: updating Moodle user @uid.',
          ['@uid' => $user->id()]));
        \Drupal::service('moodle_sync_user.update_service')->updateUser($user, true);
        $context['results']['updated'][] = $user->id();
      }

      $context['results']['processed']++;

    }

    /*
    * Processes a single user in the batch.
    *
    * @param $user Drupal user.
    */
    public static function finished($success, $results, $operations) {
      $message = t('Processed @count user(s), updating @updated user(s) and creating @created user(s). See Drupal log for details.',
        ['@count' => $results['processed'], '@updated' => count($results['updated']), '@created' => count($results['created'])]);
      \Drupal::messenger()->addMessage($message);

      return new RedirectResponse('/admin/config/moodle_sync/user/settings');

    }

}
