<?php

namespace Drupal\augmentor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\augmentor\Event\AugmentorInputEvent;
use Drupal\augmentor\Event\AugmentorOutputEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Augmentor plugin manager.
 */
class AugmentorManager extends DefaultPluginManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The array of augmentors.
   *
   * @var array
   */
  protected $augmentors = [];

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs AugmentorManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher) {
    parent::__construct(
      'Plugin/Augmentor',
      $namespaces,
      $module_handler,
      'Drupal\augmentor\AugmentorInterface',
      'Drupal\augmentor\Annotation\Augmentor'
    );
    $this->configFactory = $config_factory;
    $this->alterInfo('augmentor_info');
    $this->setCacheBackend($cache_backend, 'augmentor_plugins');
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getAugmentor($augmentor_id) {
    $augmentors = $this->getAugmentors();

    if (array_key_exists($augmentor_id, $augmentors)) {
      $augmentor_type = $augmentors[$augmentor_id]['type'];
      $augmentor = $this->createInstance($augmentor_type);
      $augmentor->setConfiguration($augmentors[$augmentor_id]['configuration']);
      $augmentor->setUuid($augmentor_id);

      return $augmentor;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAugmentors() {
    $this->augmentors = $this->getAugmentorConfig()->get('augmentors');
    return $this->augmentors;
  }

  /**
   * {@inheritdoc}
   */
  public function getAugmentorConfig() {
    return $this->configFactory->getEditable('augmentor.settings');
  }

  /**
   * Process the input of the augmentor.
   *
   * @param string $input
   *   The input to be processed.
   *
   * @return string
   *   The processed input.
   */
  public function processInput($input) {
    $event = new AugmentorInputEvent($input);
    $this->eventDispatcher->dispatch($event, AugmentorInputEvent::ALTER);
    return $event->getInput();
  }

  /**
   * Process the output of the augmentor.
   *
   * @param array $output
   *   The output of the augmentor.
   *
   * @return array
   *   The processed output.
   */
  public function processOutput($output) {
    $event = new AugmentorOutputEvent($output);
    $this->eventDispatcher->dispatch($event, AugmentorOutputEvent::ALTER);
    return $event->getOutput();
  }

  /**
   * Executes the given augmentor.
   *
   * @param string $augmentor_id
   *   The augmentor id.
   * @param string $input
   *   The input to be augmented.
   *
   * @return array
   *   The augmented input or an error message.
   */
  public function executeAugmentor($augmentor_id, $input) {
    try {
      if (empty($input)) {
        throw new \Exception('The input is empty.');
      }

      $augmentor = $this->getAugmentor($augmentor_id);
      if (!$augmentor) {
        throw new \Exception('Augmentor not found: ' . $augmentor_id);
      }

      // Process the input.
      $this->processInput($input);

      // Execute the augmentor and get the response.
      $response = $augmentor->execute($input);

      // Process the output.
      $this->processOutput($response);

      // Debug the input and response.
      $augmentor->debug([
        'input' => $input,
        'response' => $response,
      ]);

      // Check if the response is not null and is an array.
      if (!is_array($response)) {
        throw new \Exception('Invalid response format from augmentor.');
      }

      // Check for specific response conditions, like status code, timeouts, etc.
      if (array_key_exists('status', $response) && $response['status'] !== 200) {
        throw new \Exception('Augmentor response error: Invalid status code.');
      }

      return $response;

    }
    catch (\Exception $e) {
      // Return an error message in a structured format.
      return [
        '_errors' => [$e->getMessage()],
      ];
    }
  }

  /**
   * Validates the target field of the augmentor and updates the allowed fields list.
   *
   * This function checks if the field name is valid and, if so, adds its formatted
   * label to the list of allowed fields.
   *
   * @param string $field_name
   *   The target field name to validate.
   * @param array &$allowed_fields
   *   A reference to an array where the label of a valid field will be added.
   */
  public function isAugmentorValidTarget($field_name, array &$allowed_fields) {
    // Define valid fields and prefixes.
    $validFields = ['body', 'title'];
    $validPrefixes = ['field_', 'schema_'];

    // Check if the field is valid based on exact matches or prefixes.
    if (in_array($field_name, $validFields) || $this->startsWithValidPrefix($field_name, $validPrefixes)) {
      $this->addFormattedFieldLabel($field_name, $allowed_fields);
    }
  }

  /**
   * Checks if the given field name starts with any of the valid prefixes.
   *
   * @param string $field_name
   *   The name of the field to check.
   * @param array $validPrefixes
   *   An array of valid prefixes.
   *
   * @return bool
   *   Returns TRUE if the field name starts with a valid prefix, FALSE otherwise.
   */
  private function startsWithValidPrefix($field_name, array $validPrefixes) {
    foreach ($validPrefixes as $prefix) {
      if (str_starts_with($field_name, $prefix)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Adds a formatted label for a valid field to the allowed fields list.
   *
   * @param string $field_name
   *   The field name to format.
   * @param array &$allowed_fields
   *   A reference to the array where the formatted label will be stored.
   */
  private function addFormattedFieldLabel($field_name, array &$allowed_fields) {
    $label = str_replace(['field_', 'schema_'], '', $field_name);
    $allowed_fields[$field_name] = ucwords(str_replace('_', ' ', $label));
  }

}
