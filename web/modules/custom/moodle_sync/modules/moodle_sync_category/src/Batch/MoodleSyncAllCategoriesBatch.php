<?php

namespace Drupal\moodle_sync_category\Batch;

use Symfony\Component\HttpFoundation\RedirectResponse;


// @codingStandardsIgnoreEnd

/**
 * Methods for running category import in a batch.
 *
 * @package Drupal\csvimport
 */
class MoodleSyncAllCategoriesBatch {

   /*
    * Processes a single category in the batch.
    *
    * @param $category Drupal category.
    * @param $context Batch context.
    */
    public static function processCategory($category, &$context) {

      if (!array_key_exists('processed', $context['results'])) {
        $context['results']['processed'] = 0;
      }

      if($category->get('field_moodle_id')->getString() === null || $category->get('field_moodle_id')->getString() === '') {
        \Drupal::logger('moodle_sync_category')->notice(t('Sync all categories: creating Moodle category @cid.',
          ['@cid' => $category->id()]));
        \Drupal::service('moodle_sync_category.create')->createCategory($category);
        $context['results']['created'][] = $category->id();
      } else {
        \Drupal::logger('moodle_sync_category')->notice(t('Sync all categories: updating Moodle category @cid.',
          ['@cid' => $category->id()]));
        \Drupal::service('moodle_sync_category.update')->updateCategory($category, true);
        $context['results']['updated'][] = $category->id();
      }

      $context['results']['processed']++;

    }

    /*
    * Processes a single category in the batch.
    *
    * @param $category Drupal category.
    */
    public static function finished($success, $results, $operations) {
      $count_updated = isset($results['updated']) && is_array($results['updated']) ? count($results['updated']) : 0;
      $count_created = isset($results['created']) && is_array($results['created']) ? count($results['created']) : 0;
      $count_processed = isset($results['processed']) && is_array($results['processed']) ? count($results['processed']) : 0;

      $message = t('Processed @count categories, updating @updated categories and creating @created categories. See Drupal log for details.',
          ['@count' => $count_processed, '@updated' => $count_updated, '@created' => $count_created]);
      \Drupal::messenger()->addMessage($message);

      return new RedirectResponse('/admin/config/moodle_sync/category/settings');
  }

}
