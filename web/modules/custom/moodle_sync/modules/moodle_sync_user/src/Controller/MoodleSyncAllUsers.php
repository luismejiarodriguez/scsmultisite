<?php

namespace Drupal\moodle_sync_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\moodle_sync_user\Service\CreateService;
use Drupal\moodle_sync_user\Service\UpdateService;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MoodleSyncAllUsers extends ControllerBase {

   /*
    * Syncs all Drupal users to Moodle.
    */
    public function syncAllUsers() {
      $config = \Drupal::config('moodle_sync_user.settings');
      $users = \Drupal\user\Entity\User::loadMultiple();
      $blackList = [];

      // Get blacklisted roles.
      foreach($config->get('roles') as $role => $value) {
        if($value === 1) {
            $blackList[] = $role;
        }
      }

      // Sort out users with blacklisted roles.
      foreach ($users as $key => $user) {
        if(in_array($user->get('roles')->getString(), $blackList)) {
          unset($users[$key]);
        }
      }

      // Create batch.
      $batch = [
        'title' => t('Syncing Drupal users to Moodle...'),
        'operations' => [],
        'finished'         => '\Drupal\moodle_sync_user\Batch\MoodleSyncAllUsersBatch::finished',
        'init_message'     => $this->t('Commencing'),
        'progress_message' => t('Processed @current out of @total'),
        'error_message'    => $this->t('An error occurred during processing'),
      ];

      // Add batch operations.
      // $users = array_slice($users, 0, 10); // for testing.
      foreach ($users as $user) {
        if ($user->id()) {
          $batch['operations'][] = [
            '\Drupal\moodle_sync_user\Batch\MoodleSyncAllUsersBatch::processUser', [$user]
          ];
        }
      }

      batch_set($batch);

      // Process and redirect.
      return(batch_process('/admin/config/moodle_sync/user/settings'));

    }


}
