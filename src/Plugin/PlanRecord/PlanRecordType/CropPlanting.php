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
      'seeding_date' => [
        'type' => 'timestamp',
        'label' => $this->t('Seeding date'),
      ],
      'transplant_days' => [
        'type' => 'integer',
        'label' => $this->t('Days to transplant'),
        'min' => 1,
        'max' => 365,
      ],
      'maturity_days' => [
        'type' => 'integer',
        'label' => $this->t('Days to maturity'),
        'min' => 1,
        'max' => 365,
      ],
      'harvest_days' => [
        'type' => 'integer',
        'label' => $this->t('Harvest window (days)'),
        'min' => 1,
        'max' => 365,
      ],
    ];
    foreach ($field_info as $name => $info) {
      $fields[$name] = $this->farmFieldFactory->bundleFieldDefinition($info);
    }
    return $fields;
  }

}
