<?php

namespace Drupal\farm_crop_plan\Bundle;

use Drupal\asset\Entity\AssetInterface;
use Drupal\plan\Entity\PlanRecordInterface;

/**
 * Bundle logic for Crop Planting.
 */
interface CropPlantingInterface extends PlanRecordInterface {

  /**
   * Returns the Plant asset the crop planting is assigned to.
   *
   * @return \Drupal\asset\Entity\AssetInterface|null
   *   The plant asset or NULL if not assigned.
   */
  public function getPlant(): ?AssetInterface;

}
