<?php declare(strict_types = 1);

namespace Drupal\moodle_sync_category\Service;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * @todo Add class description.
 */
final class SettingsFormOptionsService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a GroupCreation object.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Gets taxonomy terms as options.
   */
  public function getTaxonomyOptions() {

    $vocabularies = Vocabulary::loadMultiple();
    //$taxonomy_options = ['' => '-- Select a Taxonomy --'];
    $taxonomy_options = [];

    foreach ($vocabularies as $vocabulary) {
      $vocabulary_id = $vocabulary->id();
      $fields = $this->getFieldMachineNames($vocabulary_id);

      // Only add the vocabulary if it has fields associated with it.
      if (!empty($fields)) {
        $taxonomy_options[$vocabulary_id] = $vocabulary->label();
      }
    }

    return $taxonomy_options;
  }

  /**
   * Gets field machine names for a vocabulary.
   */
  private function getFieldMachineNames($vocabulary_id) {
    $field_machine_names = [];
    $vocabulary = Vocabulary::load($vocabulary_id);

    if ($vocabulary) {
      // Load the fields associated with the taxonomy vocabulary.
      $field_definitions = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('taxonomy_term', $vocabulary_id);

      // Iterate through the fields and add their machine names to the options.
      foreach ($field_definitions as $field_name => $field_definition) {
        // Exclude any system or base fields that may not be suitable.
        if (!$field_definition->getFieldStorageDefinition()->isBaseField() &&
            $field_definition->getType() !== 'entity_reference') {
          $field_machine_names[$field_name] = $field_name;
        }
      }
    }

    return $field_machine_names;
  }

}