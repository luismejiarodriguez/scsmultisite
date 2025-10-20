<?php

namespace Drupal\moodle_sync_cohort\Utility;

use Drupal\Core\Entity\EntityInterface;

class MoodleCohort {

  /**
   * Determines if an entity should be processed.
   *
   * @param $term taxonomy term.
   *
   * @return boolean.
   */
  static function process($term) {

    $config = \Drupal::config('moodle_sync_cohort.settings');

    // Check vocabulary.
    $vocabulary = $config->get('vocabulary');
    if ($vocabulary != $term->bundle()) {
      return FALSE;
    }

    // Check field moodle id.
    if (!$term->hasField('field_moodle_id')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if a term changed.
   *
   * @param $term taxonomy term.
   *
   * @return boolean.
   */
  static function changed($term) {
    if(!$original = $term->original) {
      return FALSE;
    }
    if ($term->getName() != $original->getName()) {
      return TRUE;
    }
    if ($term->getDescription() != $original->getDescription()) {
      dd($term->getDescription());
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds the parameters for a cohort.
   *
   * @param $term taxonomy term.
   *
   * @return array.
   */
  static function getParams($term) {
    $tid = $term->id();
    $params = [];
    $params['cohorts[0][name]'] = $term->getName();
    $params['cohorts[0][idnumber]'] = $tid;
    $params['cohorts[0][categorytype][type]'] = 'system';
    $params['cohorts[0][categorytype][value]'] = 'ignored';
    if ($description = $term->getDescription()) {
      $params['cohorts[0][description]'] = $description;
    }
    $params['cohorts[0][descriptionformat]'] = 1;
    return $params;
  }
}
