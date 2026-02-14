<?php

declare(strict_types=1);

namespace Drupal\farm_crop_plan\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\farm_crop_plan\Form\CropPlanTimelineForm;

/**
 * Theme hook implementations for farm_crop_plan.
 */
class ThemeHooks {

  use AutowireTrait;

  public function __construct(
    protected CropPlanInterface $cropPlan,
    protected FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('plan_view')]
  public function planView(array &$build, EntityInterface $plan, EntityViewDisplayInterface $display, $view_mode) {
    /** @var \Drupal\plan\Entity\PlanInterface $plan */

    // If this is not a crop plan in full view mode, bail.
    if (!($plan->bundle() == 'crop' && $view_mode == 'full')) {
      return;
    }

    // If there are no crop plantings, bail.
    if (empty($this->cropPlan->getCropPlantings($plan))) {
      return;
    }

    // Render the crop plan timeline.
    $build['crop_plan_timeline'] = $this->formBuilder->getForm(CropPlanTimelineForm::class, $plan);
  }

  /**
   * Implements hook_farm_ui_theme_region_items().
   */
  #[Hook('farm_ui_theme_region_items')]
  public function farmUiThemeRegionItems(string $entity_type) {

    // Position the crop plan timeline in the top region.
    if ($entity_type == 'plan') {
      return [
        'top' => [
          'crop_plan_timeline',
        ],
      ];
    }
    return [];
  }

}
