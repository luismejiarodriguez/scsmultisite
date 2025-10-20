<?php

namespace Drupal\moodle_sync\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Drupal\Core\Config\ConfigFactoryInterface;

class MoodleSync {

  protected $config;

  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('moodle_sync.settings');
  }


  /**
   * Gets the display value of a field to sync to Moodle.
   *
   * @param object $entity Drupal entity.
   * @param string $fieldname Name of the field.
   *
   * @return string Value of the field.
   *
   */
  public function getValue($entity, $fieldname) {

    // Special cases.
    if ($fieldname == 'id') {
      return $entity->id();
    }

    // Handle arrays.
    if (str_contains($fieldname, ':')) {
      $fieldnameValues = explode(':', $fieldname);
      $fieldname = $fieldnameValues[0];
      $subfieldname = $fieldnameValues[1];
      $isArray = TRUE;
    } else {
      $isArray = FALSE;
    }

    // Get field type.
    $type = $entity->get($fieldname)->getFieldDefinition()->getType();
    $value = $entity->get($fieldname)->getValue();

    if ($value) {
      if (is_array($value[0])) {

        // Get specified array key.
        if ($isArray) {
          if (array_key_exists($subfieldname, $value[0])) {
            return $value[0][$subfieldname];
          }
        }

        // If value exists, return value.
        if (array_key_exists('value', $value[0])) {
          return $value[0]['value'];
        }

        // If target_id exists, get entity.
        if (array_key_exists('target_id', $value[0])) {
          $target_entity = $entity->$fieldname->entity;

          // Try different functions to get name.
          $functions = ['getName', 'getTitle', 'getLabel', 'label'];
          foreach ($functions as $function) {
            if (method_exists($target_entity, $function)) {
              return $target_entity->$function();
            }
          }
        }
      }
    }

    // Encode value.
    if (isset($value) && !empty($value)) {
      $value = urlencode($value);
    }

    // No value to return.
    return null;

  }


  /**
   * Writes Drupal information to Moodle.
   *
   * @param string $function Moodle webservice function to be called.
   * @param string|array $params URL parameters.
   * @param string $check_drupal_path Only call Moodle API if Drupal site settings equals the actual Drupal site URL.
   *
   * @return response the webservice response.
   *
   */
  public function apiCall($function, $params, $check_drupal_path = true, $fullarray = false) {

    // Check if Drupal site settings equals the actual Drupal site URL.
    if ($check_drupal_path) {
      $drupalpaths_settings = trim($this->config->get('drupalpath'));
      $drupalpaths = explode(',', $drupalpaths_settings);
      $drupalpath_actual= trim(\Drupal::request()->getSchemeAndHttpHost());
      if (!in_array($drupalpath_actual, $drupalpaths, true)) {
        $message = t('Warning: Drupal site not set correctly in module settings (@drupalpath_settings vs @drupalpath_actual). Nothing will be written to Moodle. API call would have been: @function @params',
          array('@function' => $function, '@params' => json_encode($params), '@drupalpath_settings' => $drupalpaths_settings, '@drupalpath_actual' => $drupalpath_actual));
        \Drupal::service('moodle_sync.logger')->log($message, 'warning');

        // Return response object anyways.
        $response_object = new \stdClass();
        $response_object->exception = $message;
        return $response_object;
      }
    }

    // Get Moodle API settings.
    $moodlepath = $this->config->get('moodlepath');
    $token = $this->config->get('token');

    // Set parameters.
    if (is_array($params)) {
      $params = http_build_query($params);
    }
    $url = $moodlepath . '/webservice/rest/server.php?wstoken=' . $token . '&wsfunction=' . $function . '&moodlewsrestformat=json&' . $params;

    // Make REST API call.
    $client = new Client([
      'base_uri' => $url,
      'timeout' => 600,
      'connect_timeout' => 60,
    ]);

    try {

      // Get and format response.
      $response = $client->post($url);
      $response_body = $response->getBody()->getContents();
      $response_object = json_decode($response_body, false);

      // If we still have an array, take the first key.
      if (is_array($response_object)) {

        // If the array is empty, create object with exception message.
        if(count($response_object) == 0) {
          $response_object = new \stdClass();
          $response_object->exception = 'No response from Moodle API.';
          return $response_object;
        }

        // Collapse array for backwards compatibility reasons, since most functions cannot handle arrays.
        if (!$fullarray) {
          $response_object = $response_object[0];
        }

      }
      return $response_object;

    } catch (ConnectException $e) {
      $error = $e->getMessage();
      $error = str_replace($token, '***', $error);

      error_log('Connection failed: ' . $error); // Log to PHP error log
      $message = t('Connection to Moodle failed: @error', array('@error' => $error));
      \Drupal::service('moodle_sync.logger')->log($message, 'error');

      // Create object with exception message.
      $response_object = new \stdClass();
      $response_object->exception = $error;
      return $response_object;

    } catch (RequestException $e) {
      $error = $e->getMessage();
      $error = str_replace($token, '***', $error);

      $message = t('Moodle API request failed: @error', array('@error' => $error));
      \Drupal::service('moodle_sync.logger')->log($message, 'error');

      // Create object with exception message.
      $response_object = new \stdClass();
      $response_object->exception = $error;
      return $response_object;
    }
  }
}
