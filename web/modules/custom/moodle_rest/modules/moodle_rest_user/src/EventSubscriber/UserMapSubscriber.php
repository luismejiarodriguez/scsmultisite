<?php

namespace Drupal\moodle_rest_user\EventSubscriber;

use Drupal\moodle_rest_user\Event\MoodleUserMap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Associate Used to Moodle with email address.
 */
class UserMapSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      MoodleUserMap::PUSH_EVENT => 'mapToMoodle',
      MoodleUserMap::PULL_EVENT => 'mapToDrupal',
    ];
  }

  /**
   * Map fields from Drupal User Account to Moodle Account.
   *
   * @param MoodleUserMap $event
   *   Mapping event.
   */
  public function mapToMoodle(MoodleUserMap $event): void
  {
    foreach ($event->getConfig() as $field_map) {
      $event->row->setDestinationProperty($field_map['moodle'], $event->row->getSourceProperty($field_map['drupal']));
    }
  }

  /**
   * Map fields from Drupal User Account to Moodle Account.
   *
   * @param MoodleUserMap $event
   *   Mapping event.
   */
  public function mapToDrupal(MoodleUserMap $event): void
  {
    foreach ($event->getConfig() as $field_map) {
      $event->row->setDestinationProperty($field_map['drupal'], $event->row->getSourceProperty($field_map['moodle']));
    }
  }

}
