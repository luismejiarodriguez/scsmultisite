<?php

namespace Drupal\moodle_rest_user\EventSubscriber;

use Drupal\moodle_rest\Services\MoodleRestException;
use Drupal\moodle_rest\Services\RestFunctions;
use Drupal\moodle_rest_user\Event\MoodleUserAssociate;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Associate Used to Moodle with email address.
 */
class AssociateEventSubscriber implements EventSubscriberInterface {

  /**
   * Moodle REST functions.
   *
   * @var RestFunctions
   */
  protected RestFunctions $moodle;

  /**
   * Constructor.
   */
  public function __construct(RestFunctions $moodle) {
    $this->moodle = $moodle;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      MoodleUserAssociate::EVENT_NAME => 'associateByEmail',
    ];
  }

  /**
   * Subscribe to the user login event dispatched.
   *
   * @param MoodleUserAssociate $event
   *   User association event.
   */
  public function associateByEmail(MoodleUserAssociate $event): void
  {
    // If an earlier subscriber has found an ID we don't run.
    if (!$event->moodleId) {
      // Search by email.
      $account = $event->getAccount();
      if ($email = $account->getEmail()) {
        try {
          $users = $this->moodle->getUsersByField('email', [$email]);
        }
        catch (MoodleRestException $e) {
          \watchdog_exception('moodle_rest_user', $e);
        } catch (GuzzleException $e) {
        }
      }
      if (!empty($users)) {
        $user = reset($users);
        $event->moodleId = $user['id'];
      }
    }
  }

}
