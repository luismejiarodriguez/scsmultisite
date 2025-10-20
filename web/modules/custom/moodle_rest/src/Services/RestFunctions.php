<?php

namespace Drupal\moodle_rest\Services;

use Drupal\Core\Utility\Error;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Moodle Rest Functions Service.
 *
 * Adds helpers for Moodle Rest Webservice functions.
 * Helper methods should document the required parameters, and the returned
 * expected data, log warnings. Links to the moodle definitions included.
 * If you have access to the site you can also see a summary of all webservice
 * functions at '/admin/webservice/documentation.php'.
 */
class RestFunctions {

  /**
   * The Rest WS connector.
   *
   * @var MoodleRest
   */
  protected MoodleRest $rest;

  /**
   * The module logger channel.
   *
   * @var LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Static cache for repeat queries in the same call.
   *
   * @var []
   */
  protected static $cache = [];

  /**
   * Constructs a RestFunctions object.
   *
   * @param LoggerInterface $logger
   *   The logger.channel.moodle_rest service.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Set the Moodle Rest Webservice client.
   *
   * Otherwise, default client from container will be used. Setting manually
   * allows overriding the configured Moodle server and token.
   */
  public function setRestClient(MoodleRest $rest): void
  {
    self::$cache = [];
    $this->rest = $rest;
  }

  /**
   * Get the Moodle Rest Webservice client.
   */
  public function getRestClient() {
    if (empty($this->rest)) {
      $this->rest = \Drupal::service('moodle_rest.rest_ws');
    }
    return $this->rest;
  }

  /**
   * Core webservice.
   *
   * Moodle `core_webservice_*` functions.
   */

  /**
   * Get some site info / user info / list web service functions.
   *
   * Moodle function: core_webservice_get_site_info.
   *
   * @return array
   *   Array as defined by version of Moodle.
   *
   * @throws MoodleRestException|GuzzleException
   */
  public function getSiteInfo(): array
  {
    if (empty(self::$cache['site_info'])) {
      self::$cache['site_info'] = $this->getRestClient()->requestFunction('core_webservice_get_site_info');
    }

    return self::$cache['site_info'];
  }

  /**
   * Get Moodle Version.
   *
   * YYYYMMDD      = date of the 1.9 branch (don't change)
   *         X     = release number 1.9.[0,1,2,3,4,5...]
   *          Y.YY = micro-increments between releases.
   *
   * @return string
   *   Version number, or unknown if access to function denied, or error.
   * @throws GuzzleException
   */
  public function getSiteInfoVersion(): string
  {
    try {
      $site_info = $this->getSiteInfo();
    }
    catch (MoodleRestException $e) {
      if ($e->getCode() == 403 && $e->getBody()['errorcode'] = 'accessexception') {
        return 'unknown';
      }
      $this->logException($e);
      return 'error';
    }

    return $site_info['version'];
  }

  /**
   * Get Moodle Release.
   *
   * Human friendly form of version.
   *
   * @return string
   *   Release or unknown if access to function denied.
   * @throws GuzzleException
   */
  public function getSiteInfoRelease(): string
  {
    try {
      $site_info = $this->getSiteInfo();
    }
    catch (MoodleRestException $e) {
      if ($e->getCode() == 403 && $e->getBody()['errorcode'] = 'accessexception') {
        return 'unknown';
      }
      $this->logException($e);
      return 'error';
    }

    return $site_info['release'];
  }

  /**
   * Get Moodle Username.
   *
   * Name of user accessing the Webservice.
   *
   * @return string
   *   Username or '' on error including access denied to the function.
   * @throws GuzzleException
   * @todo what is returned for anonymous access.
   *
   */
  public function getSiteInfoUsername(): string
  {
    try {
      $site_info = $this->getSiteInfo();
    }
    catch (MoodleRestException $e) {
      if ($e->getCode() == 403 && $e->getBody()['errorcode'] = 'accessexception') {
        return '';
      }
      $this->logException($e);
      return '';
    }

    return $site_info['username'];
  }

  /**
   * Get Moodle Sitename.
   *
   * Name of user accessing the Webservice.
   *
   * @return string
   *   Username or '' on error including access denied to the function.
   * @throws GuzzleException
   * @todo what is returned for anonymous access.
   *
   */
  public function getSiteInfoSitename(): string
  {
    try {
      $site_info = $this->getSiteInfo();
    }
    catch (MoodleRestException $e) {
      if ($e->getCode() == 403 && $e->getBody()['errorcode'] = 'accessexception') {
        return '';
      }
      $this->logException($e);
      return '';
    }

    return $site_info['sitename'];
  }

  /**
   * Get Moodle Functions available.
   *
   * An array of the function currently available to the user of the webservice.
   *
   * @return array
   *   name => (string) Function name
   *   version => (string) The version number of the component to which the
   *   function belongs. Empty on error including access denied to the info
   *   function itself.
   * @throws GuzzleException
   */
  public function getSiteInfoFunctions(): array
  {
    try {
      $site_info = $this->getSiteInfo();
    }
    catch (MoodleRestException $e) {
      if ($e->getCode() == 403 && $e->getBody()['errorcode'] = 'accessexception') {
        return [];
      }
      $this->logException($e);
      return [];
    }

    return $site_info['functions'];
  }

  /**
   * Course webservice.
   *
   * Moodle `core_course_*` functions.
   *
   * @see https://github.com/moodle/moodle/blob/master/course/externallib.php
   */

  /**
   * Courses List.
   *
   * @param array $ids
   *   Optional. Array of course ids. If empty returns all courses except frontpage course.
   *   https://github.com/moodle/moodle/blob/master/course/externallib.php::get_courses_parameters().
   *
   * @return array
   *   Array of courses.
   *   https://github.com/moodle/moodle/blob/master/course/externallib.php::get_courses_returns().
   * @throws GuzzleException
   */
  public function getCourses(array $ids = []): array {
    if (empty($ids)) {
      return $this->getRestClient()->requestFunction('core_course_get_courses');
    }
    else {
      return $this->getRestClient()->requestFunction('core_course_get_courses', ['options' => ['ids' => $ids]]);
    }
  }

  /**
   * Course list by field.
   *
   * @param string $field
   *   Optional field to search by:
   *     'id': course id
   *     'ids': comma separated course ids
   *     'shortname': course short name
   *     'idnumber': course id number
   *     'category': category id the course belongs to.
   * @param string $value
   *   Used for 'value' => 'search_by'.
   *
   * @return array
   *   Array of courses.
   * @throws GuzzleException
   */
  public function getCoursesByField(string $field = '', string $value = ''): array {
    $options = [];
    if (!empty($field)) {
      $options = ['field' => $field, 'value' => $value];
    }
    $result = $this->getRestClient()->requestFunction('core_course_get_courses_by_field', $options);
    $this->logWarning($result);
    return $result['courses'];
  }

  /**
   * Create courses.
   *
   * core_course_external::create_courses_parameters()
   *
   * @param array $courses
   *   Required fields:
   *     'fullname': full name,
   *     'shortname': course short name,
   *     'categoryid' => category id,
   *   Optional fields:
   *     'idnumber': id number usual an external id,
   *     'summary': summary text,
   *     'summaryformat': summary format,
   *     'format': course format: weeks, topics, social, site,..,
   *     'showgrades': 1 if grades are shown, otherwise 0,
   *     'newsitems': number of recent items appearing on the course page,
   *     'startdate': timestamp when the course start,
   *     'enddate': timestamp when the course end,
   *     'numsections': (deprecated, use courseformatoptions) number of weeks/topics,
   *     'maxbytes': largest size of file that can be uploaded into the course,
   *     'showreports': are activity report shown (yes = 1, no =0)
   *     'visible': 1: available to student, 0:not available
   *     'hiddensections': (deprecated, use courseformatoptions) How the hidden sections in the course are displayed to students
   *     'groupmode': no group, separate, visible,
   *     'groupmodeforce': 1: yes, 0: no
   *     'defaultgroupingid': default grouping id
   *     'enablecompletion': Enabled, control via completion and activity settings. Disabled, not shown in activity settings.
   *     'completionnotify': 1: yes 0: no
   *     'lang': forced course language
   *     'forcetheme: name of the force theme
   *     'courseformatoptions': ['name' => 'option name', 'value' => 'option value']
   *     'additional options for particular course format': []
   *
   * @return array
   *   [ ['id' => Moodle Id, 'shortname' => course short name] ]
   *
   * @throws MoodleRestException|GuzzleException
   *   errorcatcontextnotvalid, errorinvalidparam.
   */
  public function createCourses(array $courses): array {
    return $this->getRestClient()->requestFunction('core_course_create_courses', ['courses' => $courses]);
  }

  /**
   * Update courses.
   *
   * @param array $courses
   *   Array of courses, as create. Id required, and fields to update.
   *
   * @throws MoodleRestException|GuzzleException
   */
  public function updateCourses(array $courses): void {
    $this->getRestClient()->requestFunction('core_course_update_courses', ['courses' => $courses]);
  }

  /**
   * Delete courses.
   *
   * @param int[] $course_ids
   *   Course IDs.
   * @throws MoodleRestException|GuzzleException
   */
  public function deleteCourses(array $course_ids): void {
    $this->getRestClient()->requestFunction('core_course_delete_courses', ['courseids' => $course_ids]);
  }

  /**
   * User webservice.
   *
   * Moodle `core_user_*` functions.
   *
   * @see https://github.com/moodle/moodle/blob/master/user/externallib.php
   */

  /**
   * Create Users.
   *
   * @param array $users
   *   Array of users. Required keys:
   *   - username string Username policy is defined in Moodle security config.
   *   - firstname string The first name(s) of the user.
   *   - lastname string The family name of the user.
   *   - email string A valid and unique email address.
   *   And one of:
   *   - createpassword int
   *     True if password should be created and mailed to user.
   *   - password string
   *     Plain text password consisting of any characters.
   *
   * @return array
   *   Array of [
   *     id int user id
   *     username string user name
   *   ].
   *
   * @throws MoodleRestException|GuzzleException
   *   Notably: Invalid parameter value detected. Including with message
   *   Invalid parameter value detected (Username already exists: username).
   */
  public function createUsers(array $users): array {
    return $this->getRestClient()->requestFunction('core_user_create_users', ['users' => $users]);
  }

  /**
   * Update users.
   *
   * @param array $users
   *   Array of users. Required key 'id', and fields to update.
   *
   * @throws MoodleRestException|GuzzleException
   */
  public function updateUsers(array $users): void {
    $this->getRestClient()->requestFunction('core_user_update_users', ['users' => $users]);
  }

  /**
   * Delete users.
   *
   * @param int[] $user_ids
   *   User IDs.
   *
   * @throws MoodleRestException|GuzzleException
   */
  public function deleteUsers(array $user_ids): void {
    $this->getRestClient()->requestFunction('core_user_delete_users', ['userids' => $user_ids]);
  }

  /**
   * Search users.
   *
   * @param array $criteria
   *   An array of search pairs ['field' => 'value'] to search.
   *   The search is executed with AND operator on the criterias.
   *   Invalid criterias (keys) are ignored.
   *
   * @return array
   *   Array of matching users.
   *
   * @throws MoodleRestException|GuzzleException
   */
  public function getUsers(array $criteria): array {
    $arguments = [];
    foreach ($criteria as $key => $value) {
      $arguments[] = [
        'key' => $key,
        'value' => $value,
      ];
    }
    $result = $this->getRestClient()->requestFunction('core_user_get_users', ['criteria' => $arguments]);
    $this->logWarning($result);
    return $result['users'];
  }

  /**
   * Get users by 'id', 'idnumber', 'username' or 'email'.
   *
   * Retrieve users' information for a specified unique field.
   * If you want to do a user search, use ::getUsers().
   *
   * @param string $field
   *   One of 'id' | 'idnumber' | 'username' | 'email'.
   * @param string[] $values
   *   Values to match.
   *
   * @return array
   *   Array of matching users.
   *
   * @throws MoodleRestException|GuzzleException
   */
  public function getUsersByField(string $field, array $values): array {
    return $this->getRestClient()->requestFunction('core_user_get_users_by_field', [
      'field' => $field,
      'values' => $values,
    ]);
  }

  /**
   * Enrol webservice.
   *
   * Moodle `core_enrol_*` functions.
   *
   * @see https://github.com/moodle/moodle/blob/master/enrol/externallib.php
   */

  /**
   * Get list of courses user is enrolled in.
   *
   * Only active enrolments are returned.
   * Please note the current user must be able to access the course,
   * otherwise the course is not included.
   *
   * @param int $moodle_id
   *   The user Moodle ID.
   * @param bool $return_user_count
   *   Include count of enrolled users for each course.
   *   This can add several seconds to the response time.
   *    Optional (default: false).
   *
   * @return array
   *   Courses.
   * @throws GuzzleException
   */
  public function getUsersCourses(int $moodle_id, bool $return_user_count = FALSE): array {
    $options = [
      'userid' => $moodle_id,
      'returnusercount' => $return_user_count,
    ];
    return $this->getRestClient()->requestFunction('core_enrol_get_users_courses', $options);
  }

  /**
   * Enrol manual webservice.
   *
   * Moodle `enrol_manual_*`` functions.
   *
   * @see https://github.com/moodle/moodle/blob/master/enrol/manual/externallib.php
   */

  /**
   * Enrol a single user on a single course.
   *
   * Creates the array and uses enrolUsersCourses().
   *
   * @param int $user_id
   *   The user Moodle ID.
   * @param int $course_id
   *   Moodle ID of the Course.
   * @param int $role_id
   *   The Moodle role ID.
   * @param int|null $timestart
   *   Optional.
   * @param int|null $timeend
   *   Optional.
   * @param bool $suspend
   *   Optional. True if suspended.
   */
  public function enrolUserCourse(int $user_id, int $course_id, int $role_id, ?int $timestart = NULL, ?int $timeend = NULL, ?bool $suspend = NULL): void {
    $enrolment = [
      'userid' => $user_id,
      'courseid' => $course_id,
      'roleid' => $role_id,
    ];
    if (!is_null($timestart)) {
      $enrolment['timestart'] = $timestart;
    }
    if (!is_null($timeend)) {
      $enrolment['timeend'] = $timeend;
    }
    if (!is_null($suspend)) {
      $enrolment['suspend'] = $suspend;
    }
    $this->enrolUsersCourses([$enrolment]);
  }

  /**
   * Enrol users to a courses.
   *
   * @param array $enrolments
   *   Keyed: userid, courseid, roleid required, and optional timestart, timend, suspend.
   *   See ::enrolUserCourse().
   *
   * @throws MoodleRestException|GuzzleException
   *   Ids `wsnoinstance` if there is no course, `wscannoctenrol` if it can't enrol.
   */
  public function enrolUsersCourses(array $enrolments): void {
    $this->getRestClient()->requestFunction('enrol_manual_enrol_users', ['enrolments' => $enrolments]);
  }

  /**
   * Enrol a single user on a single course.
   *
   * Creates the array and uses enrolUsersCourses().
   *
   * @param int $user_id
   *   The user Moodle ID.
   * @param int $course_id
   *   Moodle ID of the Course.
   * @param int $role_id
   *   The Moodle role ID.
   */
  public function unenrolUserCourse(int $user_id, int $course_id, int $role_id, ?int $timestart = NULL, ?int $timeend = NULL, ?bool $suspend = NULL): void {
    $enrolment = [
      'userid' => $user_id,
      'courseid' => $course_id,
      'roleid' => $role_id,
    ];
    $this->unenrolUsersCourses([$enrolment]);
  }

  /**
   * Unenrol users from courses.
   *
   * @param array $enrolments
   *   Keyed: userid, courseid, roleid.
   *   See ::unenrolUserCourse().
   *
   * @throws MoodleRestException|GuzzleException
   *   Ids `wsnoinstance` if there is no course, `wscannoctunenrol` if it can't enrol.
   */
  public function unenrolUsersCourses(array $enrolments): void {
    $this->getRestClient()->requestFunction('enrol_manual_unenrol_users', ['enrolments' => $enrolments]);
  }

  /**
   * Completion webservice.
   *
   * Moodle `core_completion_*` functions.
   *
   * @see https://github.com/moodle/moodle/blob/master/completion/classes/external.php
   */

  /**
   * Get Course completion status.
   *
   * @param int $course_id
   *   Moodle ID of the Course.
   * @param int $user_id
   *   Moodle ID of the User.
   *
   * @return array
   *   Course completion status.
   *
   * @throws moodle_exception
   */
  public function getCourseCompletionStatus(int $course_id, int $user_id): array {
    $options = [
      'courseid' => $course_id,
      'userid' => $user_id,
    ];
    $result = $this->getRestClient()->requestFunction('core_completion_get_course_completion_status', $options);
    $this->logWarning($result);
    return $result['completionstatus'];
  }

  /**
   * Helper function to get course completion percentage.
   *
   * This combines retrieving _get_course_completion_status and
   * _activity_status. It will just return the % activies completed if
   * appropriate.
   *
   * @param int $course_id
   *   Moodle ID of the Course.
   * @param int $user_id
   *   Moodle ID of the User.
   *
   * @return int|null
   *   Percentage 0 - 100 or void if no criteria set or not enrolled.
   *
   * @throws moodle_exception
   */
  public function getCourseCompletionPercentage(int $course_id, int $user_id): ?int {
    $completion = [];
    try {
      $completion = $this->getCourseCompletionStatus($course_id, $user_id);
    }
    catch (MoodleRestException $e) {
      if (($moodle_error = $e->getBody()) && isset($moodle_error['errorcode']) && ($moodle_error['errorcode'] == 'nocriteriaset')) {
        return NULL;
      }
      else {
        throw $e;
      }
    }
    if ($completion['completed']) {
      return 100;
    }

    $activities = $this->getActivitiesCompletionStatus($course_id, $user_id);
    if (empty($activities)) {
      return NULL;
    }

    $completed_activities = array_filter($activities, function ($activity) {
      return (bool) $activity['timecompleted'];
    });
    return (int) round(count($completed_activities) / count($activities) * 100);
  }

  /**
   * Get Course activity completion status.
   *
   * @param int $course_id
   *   Moodle ID of the Course.
   * @param int $user_id
   *   Moodle ID of the User.
   *
   * @return array
   *   Course activity completion status.
   *
   * @throws moodle_exception
   */
  public function getActivitiesCompletionStatus(int $course_id, int $user_id): array {
    $options = [
      'courseid' => $course_id,
      'userid' => $user_id,
    ];
    $result = $this->getRestClient()->requestFunction('core_completion_get_activities_completion_status', $options);
    $this->logWarning($result);
    return $result['statuses'];
  }

  /**
   * Internal functions.
   */

  /**
   * Write an Moodle Rest Exception to the logger channel.
   *
   * Will include data from the Moodle response body as appropriate.
   */
  private function logException(MoodleRestException $e): void {
    $vars = Error::decodeException($e);
    $vars['@moodle'] = implode(', ', $e->getBody());
    $this->logger->error('%type: @message @moodle in %function (line %line of %file).', $vars);
  }

  /**
   * Log warnings.
   *
   * Moodle includes with some results an additional 'warnings' key value.
   */
  private function logWarning(array $results): void {
    if (!empty($results['warnings'])) {
      $backtrace = debug_backtrace(0, 2);
      foreach ($results['warnings'] as $warning) {
        $vars = [
          '@message' => print_r($warning, TRUE),
          '%function' => $backtrace[1]['function'],
          '%line' => $backtrace[1]['line'],
          '%file' => $backtrace[1]['file'],
        ];
      }
      $this->logger->warning('@message in %function (line %line of %file).', $vars);
    }
  }

}
