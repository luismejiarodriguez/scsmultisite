<?php

namespace Drupal\moodle_rest_user\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\moodle_rest\Services\MoodleRestException;
use Drupal\moodle_rest\Services\RestFunctions;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines CourseController class.
 */
class CourseController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Moodle Rest Functions Service.
   *
   * @var RestFunctions
   */
  protected RestFunctions $moodle;

  /**
   * Construct for Course Controller.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param RestFunctions $moodle
   *   Moodle rest functions service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RestFunctions $moodle) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moodle = $moodle;
  }

  /**
   * {@inheritdoc}
   * @param ContainerInterface $container
   * @param $base_plugin_id
   */
  public static function create(ContainerInterface $container, $base_plugin_id): CourseController|\Drupal\Core\DependencyInjection\AutowireTrait|static
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('moodle_rest.rest_functions')
    );
  }

  /**
   * List users enrolled courses.
   */
  public function userEnrolledCoursesList(AccountInterface $user): array
  {
    $moodle_user_id = $user->moodle_user_id->value;
    $moodle_course_list = [];
    $courses = [];

    if ($moodle_user_id) {
      try {
        $moodle_course_list = $this->moodle->getUsersCourses($moodle_user_id);
      }
      catch (MoodleRestException $e) {
        $this->messenger()->addError($e->getMessage());
      } catch (GuzzleException $e) {
      }
    }
    else {
      $this->messenger()->addMessage($this->t('User account is not associated with a Moodle Account'));
    }

    if ($this->moduleHandler()->moduleExists('moodle_rest_course')) {
      $builder = $this->entityTypeManager()->getViewBuilder('node');
      try {
        $storage = $this->entityTypeManager()->getStorage('node');
      } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {

      }
      foreach ($moodle_course_list as $delta => $course) {
        $course_nid = $storage->getQuery()
          ->accessCheck(TRUE)
          ->condition('type', 'moodle_course')
          ->condition('moodle_course_id', $course['id'])
          ->execute();
        if (!empty($course_nid) && ($node = $storage->load(reset($course_nid)))) {
          $courses[$delta] = $builder->view($node, 'moodle_rest_user_teaser');
        }
      }
    }

    foreach ($moodle_course_list as $delta => $moodle_course) {
      if (empty($courses[$delta])) {
        $course = [
          '#theme' => 'moodle_rest_user_course',
          '#moodle_data' => $moodle_course,
        ];
        $course['#completion'] = [
          '#theme' => 'moodle_rest_user_course_completion',
          '#progress' => $this->courseCompletion($moodle_course['id'], $moodle_user_id),
        ];
        $courses[$delta] = $course;
      }
    }

    return [
      '#theme' => 'moodle_rest_user_course_list',
      '#course_list' => $courses,
      '#attached' => [
        'library' => [
          'moodle_rest_user/course',
        ],
      ],
    ];
  }

  /**
   * Get any course completion progress.
   *
   * @return int|null
   *   Percentage complete, or null if information not available.
   */
  public function courseCompletion($moodle_course_id, $moodle_user_id): ?int
  {
    try {
      return $this->moodle->getCourseCompletionPercentage($moodle_course_id, $moodle_user_id);
    }
    catch (MoodleRestException $e) {
      \watchdog_exception('moodle_rest_user', $e);
      return NULL;
    }
  }

  /**
   * Function to Unenrol.
   *
  public function courseUnenrol($userid, $courseid) {
    if ($userid != $this->currentUser()->id()) {
      throw new AccessDeniedException("You can only unenrol yourself.");
    }
    $this->courseService->courseUnEnrol($userid, $courseid);
    return $this->redirect('moodle_rest_course_activities.controller', ['courseid' => $courseid]);
  }
  */
}
