<?php

namespace Drupal\farm_crop_plan;

use Drupal\asset\Entity\AssetInterface;
use Drupal\farm_crop_plan\Bundle\CropPlantingInterface;
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
   * @return \Drupal\farm_crop_plan\Bundle\CropPlantingInterface[]
   *   Returns an array of plan_record entities of type crop_planting.
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
   * Get all crop planting records for a given plan, indexed by location.
   *
   * A plant may have multiple locations, which means the same crop planting
   * record may appear under multiple locations.
   *
   * Note that this looks for actual asset location movement logs. Therefore,
   * it represents "actual locations" (past, present, and future). The crop
   * planning module does not have a concept of planned vs actual locations.
   * It only tracks planned vs actual timelines.
   *
   * @param \Drupal\plan\Entity\PlanInterface $plan
   *   The plan entity.
   *
   * @return array
   *   Returns a keyed array of plan_record entity arrays, where each key is
   *   a location asset ID.
   */
  public function getCropPlantingsByLocation(PlanInterface $plan): array;

  /**
   * Get all logs for the plant asset.
   *
   * @param \Drupal\asset\Entity\AssetInterface $asset
   *   The crop_planting plan_record entity.
   * @param bool $access_check
   *   Whether to check log entity access.
   *
   * @return \Drupal\log\Entity\LogInterface[]
   *   Returns an array of Log entities.
   */
  public function getLogs(AssetInterface $asset, bool $access_check = TRUE): array;

  /**
   * Get crop planting stages for the timeline.
   *
   * @param \Drupal\farm_crop_plan\Bundle\CropPlantingInterface $crop_planting
   *   The crop_planting plan_record entity.
   *
   * @return array
   *   Returns an array of stages.
   */
  public function getCropPlantingStages(CropPlantingInterface $crop_planting): array;

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
