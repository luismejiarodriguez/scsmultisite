<?php

namespace Drupal\moodle_rest_user;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\moodle_rest\Services\MoodleRestException as ServicesMoodleRestException;
use Drupal\moodle_rest_user\Event\MoodleUserAssociate;
use Drupal\user\UserInterface;
use Drupal\user\UserStorage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Batch job helper for Moodle REST user actions.
 */
class UserBatchHelper implements ContainerInjectionInterface {

  use MessengerTrait, StringTranslationTrait;

  /**
   * The event dispatcher.
   *
   * @var EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Module settings.
   *
   * @var ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * User storage.
   *
   * @var UserStorage
   */
  protected UserStorage $userStorage;

  /**
   * Batch Helper constructor.
   *
   * @param EventDispatcherInterface $event_dispatcher
   *   Event Dispatcher.
   * @param ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->eventDispatcher = $event_dispatcher;
    $this->settings = $config_factory->get('moodle_rest_user.settings');
    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static
  {
    try {
      return new static(
        $container->get('event_dispatcher'),
        $container->get('config.factory'),
        $container->get('entity_type.manager')
      );
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {

    }
  }

  /**
   * Associate a single user with moodle id.
   *
   * @param int $uid
   *   ID of user to update.
   * @param bool $update
   *   True if to update a user with an existing Moodle ID.
   *
   * @return string
   *   Description of the result.
   * @throws ReadOnlyException
   * @throws EntityStorageException
   * @todo result turn into constants?
   */
  public function associateAccountById(int $uid, bool $update = FALSE): string
  {
    $account = $this->userStorage->load($uid);
    if (empty($account)) {
      return 'user not found';
    }
    assert($account instanceof UserInterface);
    $moodle_field = $account->get($this->settings->get('moodle_id_field'));
    $moodle_id = $moodle_field->getValue();
    if (!empty($moodle_id) && !$update) {
      return 'existing id not updated';
    }

    try {
      $event = new MoodleUserAssociate($account);
      $this->eventDispatcher->dispatch($event, MoodleUserAssociate::EVENT_NAME);
    }
    catch (ServicesMoodleRestException $e) {
      \watchdog_exception('issup_moodle', $e, '%type: @message "@body" in %function (line %line of %file).', ['@body' => $e->getBody()['exception']]);
      return 'error fetching moodle user';
    }

    if ($event->moodleId) {
      $moodle_field->setValue($event->moodleId);
      $account->save();
      return 'user id saved';
    }
    else {
      return 'no moodle user found';
    }
  }

  /**
   * Batch process to associate user with Moodle ID.
   *
   * @see ::associateUsersBatchCallback()
   */
  public function associateUsersBatch($update, &$context): void
  {
    // Set up count to run through initially if first call.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_uid'] = 0;
      $context['sandbox']['max'] = $this->userStorage
        ->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->execute();
    }

    // Retrieve the next 10 users and process.
    $user_result = $this->userStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('uid')
      ->range($context['sandbox']['current_uid'], 10)
      ->execute();
    foreach ($user_result as $uid) {
      try {
        $result = $this->associateAccountById($uid, $update);
      } catch (EntityStorageException|ReadOnlyException $e) {

      }
      $context['results'][$uid] = $result;
      $context['sandbox']['progress']++;
      $context['sandbox']['current_uid'] = $uid;
    }

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch operation callback.
   *
   * @param bool $update
   *   True if to update a user with an existing Moodle ID.
   * @param object $context
   *   Context for operations.
   *
   * @see ::associateUsersBatch()
   */
  public static function associateUsersBatchCallback(bool $update, object &$context): void
  {
    \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(self::class)
      ->associateUsersBatch($update, $context);
  }

  /**
   * Batch operation report.
   *
   * @see ::associateUsersBatch()
   */
  public static function associateUsersBatchFinished($success, array $results, array $operations): void
  {
    if ($success) {
      $message = \t("@count users were processed.\n", [
        '@count' => count($results),
      ]);
      $summary_results = array_count_values($results);
      foreach ($summary_results as $result_type => $result_count) {
        $message .= "$result_type: $result_count\n";
      }
      \Drupal::messenger()->addMessage($message);
    }
    else {
      $error_operation = reset($operations);
      $message = \t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      \Drupal::messenger()->addError($message);
    }
  }

}
