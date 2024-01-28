<?php

namespace Drupal\farm_crop_plan;

use Drupal\asset\Entity\AssetInterface;
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
   * Get all crop planting records for a given plan, indexed by plant type.
   *
   * A plant may have multiple plant types, which means the same crop planting
   * record may appear under multiple plant types.
   *
   * @param \Drupal\plan\Entity\PlanInterface $plan
   *   The plan entity.
   *
   * @return array
   *   Returns a keyed array of plan_record entity arrays, where each key is
   *   a plant_type term ID.
   */
  public function getCropPlantingsByType(PlanInterface $plan): array;

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

  /**
   * Get crop planting stages for the timeline.
   *
   * @param \Drupal\plan\Entity\PlanRecordInterface $crop_planting
   *   The crop_planting plan_record entity.
   *
   * @return array
   *   Returns an array of stages.
   */
  public function getCropPlantingStages(PlanRecordInterface $crop_planting): array;

  /**
   * Get asset location stages for the timeline.
   *
   * @param \Drupal\asset\Entity\AssetInterface $asset
   *   The asset entity.
   *
   * @return array
   *   Returns an array of stages.
   *
   * @todo Move this to farmOS core?
   */
  public function getAssetLocationStages(AssetInterface $asset): array;

}
