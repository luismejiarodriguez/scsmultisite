<?php

declare(strict_types=1);

namespace Drupal\moodle_admin_courses\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Returns responses for Moodle Admin Courses routes.
 */
final class MoodleAdminCoursesController extends ControllerBase {

  public function getCourses(): array {

    $courses = [];

    $token = 'e980fbc7ca7843affef4d8bac6d78bf3';
    $endpoint = 'https://cursosdrupal.es/webservice/rest/server.php';

//    $token = '7241eb6311891ccacce9b04e94654500';
//    $endpoint = 'https://scsmoodle.ddev.site/webservice/rest/server.php';

    $params = [
      'wstoken' => $token,
      'wsfunction' => 'core_course_get_courses',
      'moodlewsrestformat' => 'json',
    ];

//
//    $moodle_url = 'https://cursosdrupal.es/webservice/rest/server.php';
//    $token = 'e980fbc7ca7843affef4d8bac6d78bf3';
//    $function = 'core_course_get_courses';
//    $response_format = 'json';

//    $url = "{$moodle_url}?wstoken={$token}&wsfunction={$function}&moodlewsrestformat={$response_format}";
    $url = $endpoint . '?' . http_build_query($params);

//    dump($url); die();

    try {
      $client = \Drupal::httpClient();
      $response = $client->get($url, [
        'verify' => false,
      ]);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (is_array($data)) {
        $courses = $data;

//        dump($courses); die();
      }
    }
    catch (RequestException $e) {
        $this->messenger()->addError($this->t('Error connecting to Moodle: @error', ['@error' => $e->getMessage()]));
        $courses = NULL;

//      \Drupal::logger('moodle_admin_courses')->error($e->getMessage());
//      $this->messenger()->addError('No se pudo obtener el listado de cursos desde Moodle.');
    }

    return [
      '#theme' => 'moodle_courses_list',
      '#courses' => $courses,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
