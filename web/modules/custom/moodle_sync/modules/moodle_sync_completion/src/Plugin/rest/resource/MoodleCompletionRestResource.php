<?php

namespace Drupal\moodle_sync_completion\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;

/**
 * Provides a Rest Resource for Moodle to report completions.
 *
 * @RestResource(
 *   id = "moodle_completion_rest_resource",
 *   label = @Translation("Moodle Sync Completion Rest Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/moodle-sync-completion",
 *     "create" = "/api/moodle-sync-completion"
 *   }
 * )
 */
class MoodleCompletionRestResource extends ResourceBase {

  /**
   * Responds to GET requests. This is just to test connections.
   *
   * @return \Drupal\rest\ResourceResponse
   *
   */
  public function get() {
    $data = ['status' => 1, 'message' => 'Connection successful'];
    return new ResourceResponse($data);
  }

  /**
   * Responds to POST requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *  The request object.
   *
   * @return \Drupal\rest\ResourceResponse
   *
   */
  public function post(Request $request) {

    // Load config.
    $config = \Drupal::config('moodle_sync_completion.settings');
    $user_completion_fields = [
      'field_course_completed',
      'field_course_completed_date',
      'field_completed',
    ];
    foreach ($user_completion_fields as $field) {
      $$field = $config->get($field);
    }
    $field_total = $config->get('field_total');

    // Initialize counters.
    $updated_nodes = 0;
    $updated_registrations = 0;

    // Get the content of the POST request.
    $data = json_decode($request->getContent(), TRUE);

    // Log the received data.
    \Drupal::logger('moodle_sync_completion')->notice('POST request received: ' . print_r($data, TRUE));
    if (empty($data)) {
      return new ResourceResponse(['message' => 'Invalid input.'], 400);
    }

    // Get data from the request.
    foreach ($data as $nid => $completion) {

      // If total was sent, this means course completion for the course was updated.
      if (array_key_exists('total', $completion)) {
        $total = $completion['total'];

        // Update total number of activities in the course.
        if ($node = Node::load($nid)) {
          if ($node->hasField($field_total)) {
            $node->$field_total->value = $total;
            $node->save();
            $updated_nodes++;
          }
        }

        // Delete the completion fields for all registrations.
        $registrations = \Drupal::entityTypeManager()->getStorage('registration')->loadByProperties([
          'entity_id' => $nid,
        ]);
        foreach ($registrations as $registration) {
          foreach ($user_completion_fields as $field) {
            $fieldname = $$field;
            if ($fieldname && $registration->hasField($fieldname)) {

              // Get field name and array key.
              $fieldname = $$field;
              $key = str_replace('field_', '', $field);
              $rid = $registration->id();

              // Check field and data.
              if (!$registration->hasField($fieldname)) {
                \Drupal::logger('moodle_sync_completion')->error("Error resetting value: registration $rid does not have field $fieldname");
                continue;
              }

              $registration->$fieldname->value = null;
              \Drupal::logger('moodle_sync_completion')->notice("Reset registration $rid field $fieldname");

            }
          }
          $registration->save();
          $updated_registrations++;
        }
      }

      // Get the completion data for users.
      $users = array();
      if (array_key_exists('users', $completion)) {
        $users = $completion['users'];
      }

      // Get registration entities for all users.
      foreach ($users as $uid => $user_completion) {
        $registrations = \Drupal::entityTypeManager()->getStorage('registration')->loadByProperties([
          'user_uid' => $uid,
          'entity_id' => $nid,
        ]);

        // Set all user completion fields in the registration.
        foreach ($registrations as $registration) {
          foreach ($user_completion_fields as $field) {

            // Get field name and array key.
            $fieldname = $$field;
            $key = str_replace('field_', '', $field);
            $rid = $registration->id();

            // Check field and data.
            if (!$registration->hasField($fieldname)) {
              \Drupal::logger('moodle_sync_completion')->error("Cannot set value: registration $rid does not have field $fieldname");
              continue;
            }

            if (array_key_exists($key, $user_completion)) {
              $value = $user_completion[$key];

              // Convert timestamp to ISO 8601 format
              if ($key == 'course_completed_date') {
                $value = gmdate('Y-m-d\TH:i:s', $value);
              }

              $registration->$fieldname->value = $value;
              \Drupal::logger('moodle_sync_completion')->notice("Set registration $rid field $fieldname to value $value");
            }
          }
          $saved = $registration->save();
          $updated_registrations++;
        }
      }
    }

    // Return response.
    $message = "Updated $updated_nodes nodes and $updated_registrations registrations";
    \Drupal::logger('moodle_sync_completion')->notice($message);
    return new ResourceResponse([
      'status' => 1,
      'message' => $message,
    ]);
  }
}
