<?php


declare(strict_types=1);

namespace Drupal\moodle_admin_categories\Controller;

use Drupal\Core\Controller\ControllerBase;


/**
 * Returns responses for Moodle Connector routes.
 */
final class MoodleAdminCategoriesController extends ControllerBase {

  /**
   * Consulta categorías desde Moodle y las pasa a la plantilla Twig.
   *
   */
  public function getMoodleCategories(): array {

    $token = 'e980fbc7ca7843affef4d8bac6d78bf3';
    $endpoint = 'https://cursosdrupal.es/webservice/rest/server.php';

//    $token = '7241eb6311891ccacce9b04e94654500';
//    $endpoint = 'https://scsmoodle.ddev.site/webservice/rest/server.php';

    $params = [
      'wstoken' => $token,
      'wsfunction' => 'core_course_get_categories',
      'moodlewsrestformat' => 'json',
    ];

    $url = $endpoint . '?' . http_build_query($params);

    try {
      $client = \Drupal::httpClient();
      $response = $client->get($url, [
        'verify' => false,
      ]);
      $categories = json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('No se pudo conectar con Moodle: @error', ['@error' => $e->getMessage()]));
      $categories = [];
    }

    return [
      '#theme' => 'moodle_category_list',
      '#categories' => $categories,
      '#cache' => ['max-age' => 0],
    ];
  }

}
