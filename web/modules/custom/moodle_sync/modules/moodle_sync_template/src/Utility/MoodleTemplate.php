<?php

namespace Drupal\moodle_sync_template\Utility;

use Drupal\Core\Entity\EntityInterface;

class MoodleTemplate {

  /**
   * Determines if an entity should be processed.
   *
   * @param $entity Drupal entity.
   * @param $config module config.
   *
   * @return boolean.
   */
  static function process($entity, $config) {

    $config = \Drupal::config('moodle_sync_template.settings');
    $classnamefull = get_class($entity);
    $classname = substr($classnamefull, strrpos($classnamefull, '\\') + 1);
    $entity_name = $config->get('entity_name');

    // Check entity machine name.
    if ($entity->bundle() == $entity_name) {

      // Process entity.
      return true;
    }

    // Ignore entity.
    return false;

  }

  /**
   * Gets template category for a template.
   *
   * @param $entity Drupal entity.
   * @param $config module config.
   *
   * @return string Moodle template category id:
   *
   */
  static function getTemplateCategory($entity) {

    $config = \Drupal::config('moodle_sync_template.settings');
    $categoryType = $config->get('categories');
    $categoryid = $config->get('category_id');

    // Return category id.
    if ($categoryid) {
      return $categoryid;
    }
    else {
      $message = t("Warning: Moodle template category not given, or not found in Moodle, Can't create a category.");
      \Drupal::service('moodle_sync.logger')->log($message, 'warning');
      return NULL;
    }
  }

}
