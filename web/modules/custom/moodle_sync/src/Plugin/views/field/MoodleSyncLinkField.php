<?php

namespace Drupal\moodle_sync\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Defines a custom views field that gets its value from configuration.
 *
 * @ViewsField("moodle_sync_link_field")
 */
class MoodleSyncLinkField extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid the field being used in the query.
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $moodlepath = \Drupal::config('moodle_sync.settings')->get('moodlepath');
    return $moodlepath;
  }
}
