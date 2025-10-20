<?php

declare(strict_types=1);

namespace Drupal\moodle_sync_course\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;

/**
 * Defines 'moodle_sync_update_course' queue worker.
 *
 * @QueueWorker(
 *   id = "moodle_sync_update_course",
 *   title = @Translation("Update Moodle Course Sync"),
 *   cron = {"time" = 60},
 * )
 */
final class UpdateCourse extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity = Node::load($data['entity_id']);
    $entity_original = $data['entity_original'];
    \Drupal::service('moodle_sync_course.update')->updateRemoteCourse($entity, $entity_original);
  }
}
