<?php

namespace Drupal\moodle_sync_course\Utility;

class MoodleCourse {

  /**
   * Determines if an entity should be processed.
   *
   * @param $entity Drupal entity.
   * @param $config module config.
   *
   * @return boolean.
   */
  public static function process($entity, $config) {
    if ($entity == NULL) {
      return FALSE;
    }
    // Check entity machine name.
    if ($entity->bundle() == $config->get('entity_name')) {
      // Process entity.
      return TRUE;
    }
    // Ignore entity.
    return FALSE;
  }

  /**
   * Gets course category for a course.
   *
   * @param $entity Drupal entity.
   * @param $config module config.
   *
   * @return string Moodle course category id:
   *
   */
  public static function getCourseCategory($entity) {
    $config = \Drupal::config('moodle_sync_course.settings');
    $categoryType = $config->get('categories');
    $categoryid = NULL;
    // Fixed category id from settings.
    if ($categoryType == 'fixed_categories') {
      $categoryid = $config->get('category_id');
      // Category id from reference to category taxonomy.
    }
    elseif ($categoryType == 'entitity_categories') {
      $category_field = $config->get('category_field');
      if ($term = $entity->$category_field->entity) {
        $categoryid = $term->field_moodle_id->value;
      }
    }
    // Return category id or fallback to 1.
    if ($categoryid) {
      return $categoryid;
    }
    else {
      $message = t('Warning: Moodle course category not given, or not found in Moodle, Using fallback category.');
      \Drupal::service('moodle_sync.logger')->log($message, 'warning');
      if ($categoryid = $config->get('category_fallback')) {
        return $categoryid;
      }
      else {
        return '1';
      }
    }
  }

}
