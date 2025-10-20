<?php

declare(strict_types=1);

namespace Drupal\moodle_sync_course\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;

/**
 * Defines 'moodle_sync_create_course' queue worker.
 *
 * @QueueWorker(
 *   id = "moodle_sync_create_course",
 *   title = @Translation("Create Moodle Course Sync"),
 *   cron = {"time" = 60},
 * )
 */
final class CreateCourse extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity = Node::load($data['entity_id']);
    \Drupal::service('moodle_sync_course.create')->createCourse($entity);
  }
}
