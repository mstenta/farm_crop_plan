<?php

namespace Drupal\farm_crop_plan\Plugin\PlanRecord\PlanRecordType;

use Drupal\farm_entity\Plugin\PlanRecord\PlanRecordType\FarmPlanRecordType;

/**
 * Provides the crop planting plan record type.
 *
 * @PlanRecordType(
 *   id = "crop_planting",
 *   label = @Translation("Crop Planting"),
 * )
 */
class CropPlanting extends FarmPlanRecordType {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();
    $field_info = [
      'plant' => [
        'type' => 'entity_reference',
        'label' => $this->t('Plant'),
        'description' => $this->t('Associates the crop plan with a Plant asset.'),
        'target_type' => 'asset',
        'target_bundle' => 'plant',
        'cardinality' => 1,
        'required' => TRUE,
      ],
    ];
    foreach ($field_info as $name => $info) {
      $fields[$name] = $this->farmFieldFactory->bundleFieldDefinition($info);
    }
    return $fields;
  }

}
