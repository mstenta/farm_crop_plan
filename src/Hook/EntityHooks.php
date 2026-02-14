<?php

declare(strict_types=1);

namespace Drupal\farm_crop_plan\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\farm_crop_plan\Bundle\CropPlanting;

/**
 * Entity hook implementations for farm_crop_plan.
 */
class EntityHooks {

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (isset($bundles['plan_record']['crop_planting'])) {
      $bundles['plan_record']['crop_planting']['class'] = CropPlanting::class;
    }
  }

}
