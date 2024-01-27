<?php

namespace Drupal\farm_crop_plan;

use Drupal\plan\Entity\PlanInterface;
use Drupal\plan\Entity\PlanRecordInterface;

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

  /**
   * Get all logs for the plant asset.
   *
   * @param \Drupal\plan\Entity\PlanRecordInterface $crop_planting
   *   The crop_planting plan_record entity.
   * @param bool $access_check
   *   Whether to check log entity access.
   *
   * @return \Drupal\log\Entity\LogInterface[]
   *   Returns an array of Log entities.
   */
  public function getLogs(PlanRecordInterface $crop_planting, bool $access_check = TRUE): array;

}
