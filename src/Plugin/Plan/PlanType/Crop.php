<?php

namespace Drupal\farm_crop_plan\Plugin\Plan\PlanType;

use Drupal\farm_entity\Plugin\Plan\PlanType\FarmPlanType;

/**
 * Provides the crop plan type.
 *
 * @PlanType(
 *   id = "crop",
 *   label = @Translation("Crop"),
 * )
 */
class Crop extends FarmPlanType {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();
    $field_info = [
      'season' => [
        'type' => 'entity_reference',
        'label' => $this->t('Season'),
        'description' => $this->t('Assign this to a season for easier searching later.'),
        'target_type' => 'taxonomy_term',
        'target_bundle' => 'season',
        'auto_create' => TRUE,
        'multiple' => TRUE,
        'weight' => [
          'form' => -50,
          'view' => -50,
        ],
      ],
    ];
    foreach ($field_info as $name => $info) {
      $fields[$name] = $this->farmFieldFactory->bundleFieldDefinition($info);
    }
    return $fields;
  }

}
