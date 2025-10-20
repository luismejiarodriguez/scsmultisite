<?php

namespace Drupal\moodle_sync_category\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\moodle_sync\Service\MoodleSync;
use Drupal\moodle_sync\Service\MoodleSyncLogger;
use Drupal\taxonomy\Entity\Term;
use Drupal\moodle_sync_category\Utility\MoodleCourseCategory;

class DeleteService {

  protected $config;
  protected $moodleSync;
  protected $entityTypeManager;
  protected $logger;

  public function __construct(
      ConfigFactoryInterface $configFactory,
      MoodleSync $moodleSync,
      EntityTypeManagerInterface $entityTypeManager,
      MoodleSyncLogger $logger) {
    $this->config = $configFactory->get('moodle_sync_category.settings');
    $this->moodleSync = $moodleSync;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
  }

  /**
   * Creates a Moodle category from a Drupal taxonomy term.
   *
   * @param $term Drupal taxonomy term.
   *
   * @return string Moodle category ID.
   */
  public function deleteCategory(Term $term) {

    $vocabulary = $this->config->get('selected_taxonomy_term');

    //Check if a vocabulary is selected.
    if(!isset($vocabulary)) {
      return;
    }

    // Check if this entity should be processed.
    if ($term->bundle() !== $vocabulary) {
      return;
    }

    // Check if trash bin is set.
    if (!$moodle_trashbin_id = $this->config->get('moodle_trashbin_id')) {
      return;
    }

    // If no Moodle ID is set, nothing to do.
    if (!$moodle_id = $term->get('field_moodle_id')->value) {
      return;
    }

    $term_id = $term->id();

    // Build data to update a new Moodle category.
    $function = 'core_course_update_categories';
    $params = [
      'categories[0][id]' => $moodle_id,
      'categories[0][parent]' => $moodle_trashbin_id,
    ];

    // Ensure $term and $term_id are valid before making the API call.
    $query_string = http_build_query($params);
    $response = $this->moodleSync->apiCall($function, $query_string);

    // Process the response and update moodle_id with the Moodle Category ID.
    if ($response) {
      $message = t('Failed to update Moodle category for term with ID @id. Params: @params, Moodle API response: @response',
        array('@id' => $term_id, '@response' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';

      $this->logger->log($message, $type, $function, json_encode($params), $term_id, $moodle_id);
    }
    else {
      $message = t('Deleted Moodle course category @name from drupal taxonomy term @term_id.',
        array('@name' => $term->name->value, '@term_id' => $term_id));
      $type = 'info';

      $this->logger->log($message, $type, $function, json_encode($params), $term_id, $moodle_id);
    }
  }


}
