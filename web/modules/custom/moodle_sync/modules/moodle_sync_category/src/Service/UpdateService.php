<?php

namespace Drupal\moodle_sync_category\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\moodle_sync\Service\MoodleSync;
use Drupal\moodle_sync\Service\MoodleSyncLogger;
use Drupal\taxonomy\Entity\Term;
use Drupal\moodle_sync_category\Utility\MoodleCourseCategory;

class UpdateService {

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
   * Updates a Moodle category from a Drupal taxonomy term.
   *
   * @param $term Drupal taxonomy term.
   *
   */
  public function updateCategory(Term $term) {

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

    // If our fields did not change, nothing to do.
    if ($term->original) {
      $fields = ['name', 'field_description', 'parent', 'field_moodle_id'];
      $updateneeded = FALSE;
      foreach ($fields as $field) {
        if ($term->hasField($field)) {
          if ($term->get($field)->getValue() !== $term->original->get($field)->getValue()) {
            $updateneeded = TRUE;
          }
        }
      }
      if (!$updateneeded) {
        return;
      }
    }

    // Get basics.
    $term_id = $term->id();
    $parent_term = $term->parent->entity;

    // Check if parent term exists in Moodle.
    $parent_moodle_id = null;
    if ($parent_term) {
      $parent_moodle_id = MoodleCourseCategory::getCourseCategory($parent_term->id());
      if ($parent_moodle_id == 0) {
        // Create the parent term in Moodle.
        \Drupal::service('moodle_sync_category.create')->createCategory($parent_term);
        $parent_moodle_id = MoodleCourseCategory::getCourseCategory($parent_term->id());
      }
    }

    // Get field_moodle_id value from parent term.
    if ($parent_term && $parent_term->hasField('field_moodle_id')) {
      $parent_moodle_id = $parent_term->get('field_moodle_id')->value;
    } 
    else {
      $parent_moodle_id = null;
    }

    // Check if the current updated term exists in Moodle.
    $current_term_moodle_id = MoodleCourseCategory::getCourseCategory($term_id);
    if ($current_term_moodle_id == 0) {
      // Create the term in Moodle.
      $current_term_moodle_id = \Drupal::service('moodle_sync_category.create')->createCategory($term, $parent_moodle_id);
    }
    else {
      if($current_term_moodle_id != $term->get('field_moodle_id')->value) {
        // Update the term in Moodle.
        $term->set('field_moodle_id', $current_term_moodle_id);
        $term->save();
      }
    }

    // Build data to update a Moodle category.
    $function = 'core_course_update_categories';
    $params = [
        'categories[0][id]'   => $current_term_moodle_id,
        'categories[0][name]' => $term->getName(),
    ];
    if ($term->hasField('field_description')) {
      $params['categories[0][description]'] = $term->field_description->value;
    }

    // Set parent term if it exists.
    if (isset($parent_moodle_id) && $parent_moodle_id != 0) {
      // Set parent term for Moodle Category.
      $params['categories[0][parent]'] = $parent_moodle_id;
    } 
    else {
      // Set parent to 0 if there's no parent term.
      $params['categories[0][parent]'] = 0;
    }

    // Make API call.
    $query_string = http_build_query($params);
    $response = $this->moodleSync->apiCall($function, $query_string);

    // Process the response and update moodle_id with the Moodle Category ID.
    if ($response !== null) {
      $message = t('Failed to update Moodle category for term with ID @id. Params: @params, Moodle API response: @response',
        array('@id' => $term_id, '@response' => json_encode($response), '@params' => json_encode($params)));
      $type = 'error';

      $this->logger->log($message, $type, $function, json_encode($params), $term_id, $current_term_moodle_id);
    }
    else {
      $message = t('Updated Moodle course category @name from drupal taxonomy term @term_id.',
        array('@name' => $term->name->value, '@term_id' => $term_id));
      $type = 'info';

      $this->logger->log($message, $type, $function, json_encode($params), $term_id, $current_term_moodle_id);
    }
  }


}
