<?php

namespace Drupal\moodle_rest_user;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\moodle_rest\Services\MoodleRestException;
use Drupal\moodle_rest\Services\RestFunctions;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds pseudo fields for related user course moodle data.
 */
class ExtraFields implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Moodle REST functions service.
   *
   * @var RestFunctions
   */
  protected RestFunctions $moodle;

  /**
   * Current user.
   *
   * @var AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Current Route Match.
   *
   * @var RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Module handler.
   *
   * @var ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * DirectoryExtraFieldDisplay constructor.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param RestFunctions $moodle
   *   Moodle REST functions service.
   * @param AccountProxyInterface $current_user
   *   Current user.
   * @param RouteMatchInterface $route_match
   *   Current Route Match.
   * @param ModuleHandlerInterface $module_handler
   *   Module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RestFunctions $moodle, AccountProxyInterface $current_user, RouteMatchInterface $route_match, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moodle = $moodle;
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('moodle_rest.rest_functions'),
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('module_handler')
    );
  }

  /**
   * Gets the "extra fields" for a bundle.
   *
   * @see hook_entity_extra_field_info()
   */
  public function entityExtraFieldInfo(): array
  {
    $fields = [];
    if ($this->moduleHandler->moduleExists('moodle_rest_course')) {
      $fields['node']['moodle_course']['display']['moodle_user_completion'] = [
        'label' => $this->t('User course completion'),
        'description' => $this->t("Current user course completion progress."),
        'visible' => FALSE,
      ];
    }

    return $fields;
  }

  /**
   * Adds view with arguments to view render array if required.
   *
   * @see moodle_rest_user_node_view()
   */
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, $view_mode): void
  {
    // Add course completion status.
    if ($display->getComponent('moodle_user_completion')) {
      $build['moodle_user_completion'] = $this->getCourseCompletion($node);
    }
  }

  /**
   * Retrieves view, and sets render array.
   */
  protected function getCourseCompletion(NodeInterface $node) {
    if (empty($node->moodle_course_id)) {
      return;
    }
    $moodle_course_id = $node->moodle_course_id->value;
    if (empty($moodle_course_id)) {
      return;
    }

    if ($user = $this->routeMatch->getParameter('user')) {
      if (is_int($user)) {
        try {
          $user = $this->entityTypeManager->getStorage('user')->load($user);
        } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {

        }
      }
    }
    else {
      try {
        $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {

      }
    }
    $moodle_user_id = $user->moodle_user_id->value;
    if (empty($moodle_user_id)) {
      return;
    }

    try {
      $progress = $this->moodle->getCourseCompletionPercentage($moodle_course_id, $moodle_user_id);
    }
    catch (MoodleRestException $e) {
      \watchdog_exception('moodle_rest_user', $e);
      return;
    }
    return [
      '#theme' => 'moodle_rest_user_course_completion',
      '#progress' => $progress,
      '#attached' => [
        'library' => [
          'moodle_rest_user/course',
        ],
      ],
    ];

  }

}
