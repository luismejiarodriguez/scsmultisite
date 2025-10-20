<?php

namespace Drupal\moodle_sync_category\Utility;

use Drupal\Core\Entity\EntityInterface;

class MoodleCourseCategory {

  /**
   * Determines if an entity should be processed.
   *
   * @param $entity Drupal entity.
   * @param $config module config.
   *
   * @return boolean.
   */
  static function process($entity, $config) {

    $config = \Drupal::config('moodle_sync_course.settings');
    $classnamefull = get_class($entity);
    $classname = substr($classnamefull, strrpos($classnamefull, '\\') + 1);
    $entity_type = $config->get('entity_type');
    $entity_name = $config->get('entity_name');

    // Entity type custom and classname correct, or both entity type and classname correct.
    if ($entity_type == 'custom' && $classname == $entity_name ||
      strtolower($classname) == $entity_type && $entity->bundle() == $entity_name) {

      // Process entity.
      return true;
    }

    // Ignore entity.
    return false;

  }


  /**
   * Gets course category ID for a taxonomy term.
   *
   * @param $term_id Drupal taxonomy term id.
   *
   * @return string Moodle course category id:
   *
   */
  static function getCourseCategory($term_id) {

    // Just return field_moodle_id if it is set.
    // $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term_id);
    // if ($term->hasField('field_moodle_id')) {
    //   if ($moodle_id = $term->get('field_moodle_id')->value) {
    //     return $moodle_id;
    //   }
    // }

    // Get Moodle course category ID.
    $function = 'core_course_get_categories';
    $params = [
        'criteria[0][key]' => 'idnumber',
        'criteria[0][value]' => $term_id,
    ];

    $params = http_build_query($params);
    $response = \Drupal::service('moodle_sync.sync')->apiCall($function, $params, false);

    // Return Moodle course category ID.
    if (isset($response->id)) {

      // Return Moodle category ID.
        return $response->id;

    }
    else {

      // Return 0 if no Moodle category ID.
      return 0;

    }
  }


}
