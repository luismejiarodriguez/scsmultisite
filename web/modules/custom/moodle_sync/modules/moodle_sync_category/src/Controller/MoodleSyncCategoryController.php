<?php
namespace Drupal\moodle_sync_category\Controller;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\moodle_sync_category\Service\CreateService;
use Drupal\moodle_sync_category\Service\UpdateService;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MoodleSyncCategoryController extends ControllerBase {

  /*
  * Syncs all Drupal categories to Moodle.
  */
  public function syncAllCategories() {
    $config = \Drupal::config('moodle_sync_category.settings');
    $entity_name = $config->get('selected_taxonomy_term');

    // Load the taxonomy vocabulary with the machine name.
    $vocabulary = Vocabulary::load($entity_name);

    // Query for all terms in the specified vocabulary.
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocabulary->id())
      ->accessCheck(FALSE);

    // Get the template ids
    $category_ids = $query->execute();

    // Load the terms with all fields.
    $categories = Term::loadMultiple($category_ids);

    // Create batch.
    $batch = [
      'title' => t('Syncing Drupal categories to Moodle...'),
      'operations'  => [],
      'finished'  => '\Drupal\moodle_sync_category\Batch\MoodleSyncAllCategoriesBatch::finished',
      'init_message'  => $this->t('Commencing'),
      'progress_message' => t('Processed @current out of @total'),
      'error_message' => $this->t('An error occurred during processing'),
    ];

    // Add batch operations.
    // $categories = array_slice($categories, 0, 10); // for testing.
    foreach ($categories as $category) {
      if ($category->id()) {
        $batch['operations'][] = [
          '\Drupal\moodle_sync_category\Batch\MoodleSyncAllCategoriesBatch::processCategory', [$category]
        ];
      }
    }

    batch_set($batch);

    // Process and redirect.
    return(batch_process('/admin/config/moodle_sync/category/settings'));

  }

}
