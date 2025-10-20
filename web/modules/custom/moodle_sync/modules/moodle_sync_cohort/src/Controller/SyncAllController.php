<?php
namespace Drupal\moodle_sync_cohort\Controller;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SyncAllController extends ControllerBase {

  /*
  * Syncs all Drupal taxonomy terms to Moodle cohorts.
  */
  public function syncAll() {
    $config = \Drupal::config('moodle_sync_cohort.settings');
    $vocabulary = $config->get('vocabulary');

    // Query for all terms in the specified vocabulary.
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocabulary)
      ->accessCheck(FALSE);

    // Get the template ids
    $tids = $query->execute();

    // Load the terms with all fields.
    $terms = Term::loadMultiple($tids);

    // Create batch.
    $batch = [
      'title' => t('Syncing Drupal cohorts to Moodle...'),
      'operations'  => [],
      'finished'  => '\Drupal\moodle_sync_cohort\Batch\MoodleSyncAllCohortsBatch::finished',
      'init_message'  => $this->t('Commencing'),
      'progress_message' => t('Processed @current out of @total'),
      'error_message' => $this->t('An error occurred during processing'),
    ];

    // Add batch operations.
    foreach ($terms as $term) {
      if ($term->id()) {
        $batch['operations'][] = [
          '\Drupal\moodle_sync_cohort\Batch\MoodleSyncCohortBatch::processCohort', [$term]
        ];
      }
    }

    batch_set($batch);

    // Process and redirect.
    return(batch_process('/admin/config/moodle_sync/cohort/settings'));

  }

}
