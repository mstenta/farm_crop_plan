<?php

namespace Drupal\farm_crop_plan\Bundle;

use Drupal\asset\Entity\AssetInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\plan\Entity\PlanRecord;

/**
 * Bundle logic for Crop Planting.
 */
class CropPlanting extends PlanRecord implements CropPlantingInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {

    // Build label with the referenced plan and plant.
    if ($plan = $this->getPlan()) {
      if ($plant = $this->get('plant')->first()?->entity) {
        return $this->t('Crop planting: %plant - %plan', ['%plant' => $plant->label(), '%plan' => $plan->label()]);
      }

      // Use the plan if no plant reference.
      return $this->t('Crop Planting - %plan', ['@plan' => $plan->label()]);
    }

    // Fallback to default.
    return parent::label();
  }

  /**
   * {@inheritdoc}
   */
  public function getPlant(): ?AssetInterface {
    return $this->get('plant')->first()?->entity;
  }

}
