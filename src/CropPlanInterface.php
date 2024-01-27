<?php

namespace Drupal\farm_crop_plan;

use Drupal\plan\Entity\PlanInterface;

/**
 * Crop plan logic.
 */
interface CropPlanInterface {

  /**
   * Get all crop planting plan entity relationship records for a given plan.
   *
   * @param \Drupal\plan\Entity\PlanInterface $plan
   *   The plan entity.
   *
   * @return \Drupal\plan\Entity\PlanRecordInterface[]
   *   Returns an array of plan_record entities.
   */
  public function getCropPlantings(PlanInterface $plan): array;

}
