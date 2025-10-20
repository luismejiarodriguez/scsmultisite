<?php

declare(strict_types=1);

namespace Drupal\moodle_sync_course\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Defines 'moodle_sync_delete_course' queue worker.
 *
 * @QueueWorker(
 *   id = "moodle_sync_delete_course",
 *   title = @Translation("Delete Moodle Course Sync"),
 *   cron = {"time" = 60},
 * )
 */
final class DeleteCourse extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $moodle_id = $data['moodle_id'];
    if (!$moodle_id) {
      return;
    }
    \Drupal::service('moodle_sync_course.delete')->deleteRemoteCourse($moodle_id);
  }

}
