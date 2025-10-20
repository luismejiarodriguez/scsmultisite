<?php

namespace Drupal\moodle_sync_cohort\Batch;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Methods for running cohort import in a batch.
 */
class MoodleSyncCohortBatch {

   /*
    * Processes a single item in the batch.
    *
    * @param $term taxonomy term.
    * @param $context Batch context.
    */
    public static function processCohort($term, &$context) {

      $tid = $term->id();
      $service = \Drupal::service('moodle_sync_cohort.update');

      // Initialize results.
      if (!array_key_exists('processed', $context['results'])) {
        $context['results']['processed'] = 0;
      }

      // No Moodle id, create cohort.
      if($term->get('field_moodle_id')->getString() === null || $term->get('field_moodle_id')->getString() === '') {
        \Drupal::logger('moodle_sync_cohort')->notice(t('Sync all cohorts: creating Moodle cohort @tid.',
          ['@tid' => $tid]));
        $service->createCohort($term);
        $context['results']['created'][] = $tid;

      // Update cohort.
      } else {
        \Drupal::logger('moodle_sync_cohort')->notice(t('Sync all cohorts: updating Moodle cohort @tid.',
          ['@tid' => $tid]));
        $service->updateCohort($term, true);
        $context['results']['updated'][] = $tid;
      }

      $context['results']['processed']++;
    }

    /*
    * Processes a single item in the batch.
    *
    * @param $term Drupal taxonomy term.
    */
    public static function finished($success, $results, $operations) {
      $count_updated = isset($results['updated']) && is_array($results['updated']) ? count($results['updated']) : 0;
      $count_created = isset($results['created']) && is_array($results['created']) ? count($results['created']) : 0;
      $count_processed = isset($results['processed']) && is_array($results['processed']) ? count($results['processed']) : 0;

      $message = t('Processed @count categories, updating @updated categories and creating @created categories. See Drupal log for details.',
          ['@count' => $count_processed, '@updated' => $count_updated, '@created' => $count_created]);
      \Drupal::messenger()->addMessage($message);

      return new RedirectResponse('/admin/config/moodle_sync/cohort/settings');
  }

}
