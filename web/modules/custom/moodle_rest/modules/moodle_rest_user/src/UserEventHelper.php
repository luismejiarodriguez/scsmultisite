<?php

namespace Drupal\moodle_rest_user;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\migrate\Row;
use Drupal\moodle_rest\Services\MoodleRestException;
use Drupal\moodle_rest\Services\RestFunctions;
use Drupal\moodle_rest_user\Event\MoodleUserAssociate;
use Drupal\moodle_rest_user\Event\MoodleUserMap;
use Drupal\user\UserInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Helper class for User CRUD hooks.
 */
class UserEventHelper implements ContainerInjectionInterface {

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
   * Moodle Rest Functions connector.
   *
   * @var RestFunctions
   */
  protected RestFunctions $moodle;

  /**
   * User Event Helper constructor.
   *
   * @param EventDispatcherInterface $event_dispatcher
   *   Event dispatcher.
   * @param ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param RestFunctions $moodle
   *   The Moodle REST Functions connector.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, ConfigFactoryInterface $config_factory, RestFunctions $moodle) {
    $this->eventDispatcher = $event_dispatcher;
    $this->settings = $config_factory->get('moodle_rest_user.settings');
    $this->moodle = $moodle;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static
  {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('config.factory'),
      $container->get('moodle_rest.rest_functions')
    );
  }

  /**
   * Operate on hook_user_login().
   *
   * @throws EntityStorageException
   * @throws MissingDataException|ReadOnlyException
   * @see moodle_rest_user_user_login()
   */
  public function userLogin(UserInterface $account): void
  {
    if (
      $this->isUpdating($account) ||
      ($moodle_id = $this->getMoodleId($account)) === FALSE
    ) {
      return;
    }
    $updated = FALSE;
    if (!$moodle_id && $moodle_id = $this->settings->get('associate')) {
      if ($moodle_id = $this->associateAccount($account)) {
        $account->get($this->settings->get('moodle_id_field'))->setValue($moodle_id);
        $updated = TRUE;
      }
    }

    if ($moodle_id && $this->settings->get('pull')['login']) {
      $pulled_account = $this->pullUser($account, $moodle_id);
      if ($pulled_account && $pulled_account !== $account) {
        $account = $pulled_account;
        $updated = TRUE;
      }
    }

    if ($updated) {
      $account->moodle_rest_update = TRUE;
      $account->save();
    }
  }

  /**
   * Operate on hook_user_presave().
   *
   * @throws MissingDataException|ReadOnlyException
   * @see moodle_rest_user_user_presave()
   */
  public function userPresave(UserInterface $account): void
  {
    if (
      $this->isUpdating($account) ||
      ($moodle_id = $this->getMoodleId($account)) === FALSE
    ) {
      return;
    }

    if (!$moodle_id && $this->settings->get('associate')) {
      if ($moodle_id = $this->associateAccount($account)) {
        $account->get($this->settings->get('moodle_id_field'))->setValue($moodle_id);
      }
    }
  }

  /**
   * Operate on hook_user_insert().
   *
   * @throws ReadOnlyException|EntityStorageException
   * @throws MissingDataException|GuzzleException
   * @see moodle_rest_user_user_insert()
   */
  public function userInsert(UserInterface $account): void
  {
    if (
      $this->isUpdating($account) ||
      ($moodle_id = $this->getMoodleId($account)) === FALSE
    ) {
      return;
    }

    if (!$moodle_id && $this->settings->get('create')) {
      if ($moodle_id = $this->createMoodleUser($account)) {
        $account->get($this->settings->get('moodle_id_field'))->setValue($moodle_id);
        $account->moodle_rest_update = TRUE;
        $account->save();
      }
    }
  }

  /**
   * Operate on hook_user_update().
   *
   * @throws MissingDataException|GuzzleException
   * @see moodle_rest_user_user_update()
   */
  public function userUpdate(UserInterface $account): void
  {
    if (
      $this->isUpdating($account) ||
      ($moodle_id = $this->getMoodleId($account)) === FALSE
    ) {
      return;
    }

    if ($moodle_id && $this->settings->get('update')) {
      $moodle_id = $this->pushUser($account, $moodle_id);
    }
  }

  /**
   * Operate on hook_user_prepare_form().
   *
   * @throws ReadOnlyException|MissingDataException
   * @see moodle_rest_user_user_prepare_form()
   */
  public function userEdit(UserInterface $account, FormStateInterface $form_state): void
  {
    if (
      !$this->settings->get('pull')['edit'] ||
      $this->isUpdating($account) ||
      ($moodle_id = $this->getMoodleId($account)) === FALSE
    ) {
      return;
    }

    if (!$moodle_id && $this->settings->get('associate')) {
      if ($moodle_id = $this->associateAccount($account)) {
        $account->get($this->settings->get('moodle_id_field'))->setValue($moodle_id);
      }
    }

    if ($moodle_id) {
      $pulled_account = $this->pullUser($account, $moodle_id);
      // @todo rebuild form with $pulled_account.
    }
  }

  /**
   * Operate on hook_user_view().
   *
   * @throws EntityStorageException
   * @throws MissingDataException|ReadOnlyException
   * @see moodle_rest_user_user_view()
   */
  public function userView(UserInterface $account, array $build): void
  {
    if (
      !$this->settings->get('pull')['view'] ||
      ($moodle_id = $this->getMoodleId($account)) === FALSE
    ) {
      return;
    }

    $updated = FALSE;
    if (!$moodle_id && $this->settings->get('associate')) {
      if ($moodle_id = $this->associateAccount($account)) {
        $account->get($this->settings->get('moodle_id_field'))->setValue($moodle_id);
        $updated = TRUE;
      }
    }

    if ($moodle_id && $this->settings->get('pull')['edit']) {
      $pulled_account = $this->pullUser($account, $moodle_id);
      if ($pulled_account && $pulled_account !== $account) {
        $account = $pulled_account;
        $updated = TRUE;
      }
      // @todo update $build with $pulled_account.
    }

    if ($updated) {
      $account->moodle_rest_update = TRUE;
      $account->save();
    }
  }

  /**
   * User entity is saved for a moodle update.
   *
   * @param UserInterface $account
   * @return bool
   */
  private function isUpdating(UserInterface $account): bool
  {
    return !empty($account->moodle_rest_update);
  }

  /**
   * Get Moodle ID.
   *
   * @param UserInterface $account
   * @return int|bool
   * @throws MissingDataException
   */
  public function getMoodleId(UserInterface $account): bool|int
  {
    $moodle_field = $account->get($this->settings->get('moodle_id_field'));
    if (!$moodle_field) {
      return FALSE;
    }
    return (int) $moodle_field->isEmpty() ? 0 : $moodle_field->first()->getValue()['value'];
  }

  /**
   * Try to associate user with existing moodle account.
   */
  public function associateAccount(UserInterface $account): int {
    $event = new MoodleUserAssociate($account);
    $this->eventDispatcher->dispatch($event, MoodleUserAssociate::EVENT_NAME);
    return $event->moodleId;
  }

  /**
   * Push a new user into Moodle.
   *
   * @param UserInterface $account
   *   The user to be added to Moodle.
   * @throws GuzzleException
   */
  protected function createMoodleUser(UserInterface $account) {
    $mapping = $this->settings->get('push_fields');
    $source = $this->userSourceFromMapping($account, $mapping);
    $row = new Row($source, array_flip(array_column($mapping, 'drupal')));
    // @todo Map can throw an exception for missing fields?
    $event = new MoodleUserMap($row, $mapping);
    $this->eventDispatcher->dispatch($event, MoodleUserMap::PUSH_EVENT);
    try {
      $result = $this->moodle->createUsers([$event->row->getDestination()]);
      $result = reset($result);
      if ($moodle_id = $result['id']) {
        return $moodle_id;
      }
    }
    catch (MoodleRestException $e) {
      // @todo Notify user? Log?
    }
  }

  /**
   * Pull user fields from Moodle to a user.
   *
   * @param UserInterface $account
   *   The user account to be pulled.
   * @param int $moodle_id
   *   Moodle ID of account.
   * @throws ReadOnlyException
   */
  protected function pullUser(UserInterface $account, int $moodle_id) {
    try {
      $result = $this->moodle->getUsers(['id' => $moodle_id]);
    }
    catch (MoodleRestException $e) {
      // @todo Notify user? Log?
      return;
    } catch (GuzzleException $e) {

    }

    $update_account = clone $account;
    if ($moodle_user = reset($result)) {
      $mapping = $this->settings->get('pull_fields');
      $row = new Row($moodle_user, array_flip(array_column($mapping, 'moodle')));
      $event = new MoodleUserMap($row, $mapping);
      $this->eventDispatcher->dispatch($event, MoodleUserMap::PULL_EVENT);
      foreach ($row->getDestination() as $field_name => $values) {
        $field = $update_account->$field_name;
        if ($field instanceof TypedDataInterface) {
          $field->setValue($values);
        }
      }
    }

    return $update_account;
  }

  /**
   * Push user fields to a Moodle user.
   *
   * @param UserInterface $account
   *   The user account to push.
   * @param int $moodle_id
   *   Moodle ID of account.
   * @throws GuzzleException
   */
  protected function pushUser(UserInterface $account, int $moodle_id): void
  {
    $mapping = $this->settings->get('push_fields');
    $source = $this->userSourceFromMapping($account, $mapping);
    $row = new Row($source, array_flip(array_column($mapping, 'drupal')));
    // @todo Map can throw an exception for missing fields?
    $event = new MoodleUserMap($row, $mapping);
    $this->eventDispatcher->dispatch($event, MoodleUserMap::PUSH_EVENT);
    try {
      $mapped_fields = $event->row->getDestination();
      $mapped_fields['id'] = $moodle_id;
      $result = $this->moodle->updateUsers([$mapped_fields]);
    }
    catch (MoodleRestException $e) {
      // @todo Notify user? Log?
    }
  }

  protected function userSourceFromMapping(UserInterface $user, array $mapping): array
  {
    $fields = array_map(function ($value) {
      return strstr($value['drupal'] . '/', '/', TRUE);
    }, $mapping);

    $source = [];
    foreach ($fields as $field_name) {
      $field = $user->{$field_name};
      if ($field instanceof TypedDataInterface) {
        if ($field->getDataDefinition()->isList()) {
          $source[$field_name] = $field->getValue();
        }
        else {
          $source[$field_name] = $field->value;
        }
      }
    }

    return $source;
  }

}
