<?php

declare(strict_types=1);

namespace Drupal\moodle_admin_courses\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controlador para mostrar el detalle de un curso de Moodle.
 */
final class MoodleCourseDetailController extends ControllerBase {


  /**
   * Muestra los detalles de un curso por ID.
   */
  public function getCourseDetail($courseid): array {
    $moodle_url = 'https://cursosdrupal.es/webservice/rest/server.php';
    $token = 'e980fbc7ca7843affef4d8bac6d78bf3';

    $params = [
      'wstoken' => $token,
      'wsfunction' => 'core_course_get_courses_by_field',
      'moodlewsrestformat' => 'json',
      'field' => 'id',
      'value' => $courseid,
    ];

    $url = $moodle_url . '?' . http_build_query($params);

    try {
      $client = \Drupal::httpClient();
      $response = $client->get($url);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      $course = $data['courses'][0] ?? NULL;
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error connecting to Moodle: @error', ['@error' => $e->getMessage()]));
      $course = NULL;
    }


    try {
      // POST con parámetros codificados.
      $client = \Drupal::httpClient();
//      $response = $client->post($url);
      $response = $client->request('POST', $moodle_url, [
        'form_params' => [
          'wstoken' => $token,
          'wsfunction' => 'core_course_get_contents',
          'moodlewsrestformat' => 'json',
          'courseid' => $courseid,
        ],
      ]);
//      dump($response);die();

            $data = json_decode($response->getBody()->getContents(), true);


//      // Verifica si hay error del tipo 'exception'
//      if (isset($data['exception'])) {
//        \Drupal::logger('moodle_ws')->error('Moodle WS Error: @message', ['@message' => $data['message']]);
//        return [
//          '#markup' => $this->t('Error desde Moodle: @message', ['@message' => $data['message']]),
//        ];
//      }

      return [
        '#theme' => 'moodle_course_detail',
        '#course' => $course,
        '#sections' => $data,
        '#courseid' => $courseid,
        '#cache' => ['max-age' => 0], // No cache para desarrollo.
      ];

    } catch (\Exception $e) {
      \Drupal::logger('moodle_ws')->error('HTTP Error: @error', ['@error' => $e->getMessage()]);
      return [
        '#markup' => $this->t('Error al conectarse con Moodle: @error', ['@error' => $e->getMessage()]),
      ];
    }
  }

}
