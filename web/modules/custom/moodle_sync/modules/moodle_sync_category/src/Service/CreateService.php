<?php

namespace Drupal\moodle_sync_category\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\moodle_sync\Service\MoodleSync;
use Drupal\moodle_sync\Service\MoodleSyncLogger;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\TermInterface;
use Drupal\moodle_sync_category\Utility\MoodleCourseCategory;

class CreateService {

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
  public function createCategory(Term $term) {

    $vocabulary = $this->config->get('selected_taxonomy_term');

    //Check if a vocabulary is selected.
    if(!isset($vocabulary)) {
      return;
    }

    // Check if this entity should be processed.
    if ($term->bundle() !== $vocabulary) {
      return;
    }

    // If field_moodle_id is not set, nothing to do.
    if (!$term->hasField('field_moodle_id')) {
      return;
    }

    $term_id = $term->id();
    $moodle_id = $term->get('field_moodle_id')->value;

    // If $new_parent_moodle_id is not provided, attempt to get the parent term.
    if ($term->parent->entity) {
      $parent_moodle_id = MoodleCourseCategory::getCourseCategory($term->parent->target_id);
    }

    // Build data to create a new Moodle category.
    $function = 'core_course_create_categories';
    $params = [
      'categories[0][name]'     => $term->getName(),
      'categories[0][idnumber]' => $term_id,
    ];
    if ($term->hasField('field_description')) {
      $params['categories[0][description]'] = $term->field_description->value;
    }
    if (isset($parent_moodle_id)) {
      $params['categories[0][parent]'] = $parent_moodle_id;
    }

    // Make API call.
    $query_string = http_build_query($params);
    $response = $this->moodleSync->apiCall($function, $query_string);

    // Process the response and update moodle_id with the Moodle Category ID.
    if (isset($response->id)) {

      $moodle_id = $response->id;

      // Update moodle_id with the Moodle Category ID.
      $term->set('field_moodle_id', $moodle_id);
      $term->save();

      $message = t('Created Moodle course category @moodle_id from drupal taxonomy term @term_id.',
        array('@moodle_id' => $moodle_id, '@term_id' => $term_id));
      $type = 'info';

      $this->logger->log($message, $type, $function, json_encode($params), $term_id, $moodle_id);

    }
    else {
      $message = t('Failed to create Moodle category for term with ID @id. Moodle API response: @response, Params: @params.',
        array('@id' => $term_id, '@response' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';

      $this->logger->log($message, $type, $function, json_encode($params), $term_id, $moodle_id);
    }

    return $moodle_id;
  }


}
