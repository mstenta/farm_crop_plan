<?php

declare(strict_types=1);

namespace Drupal\farm_crop_plan\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Help hook implementations for farm_crop_plan.
 */
class HelpHooks {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    $output = '';

    // Add help text to the "Add planting" form.
    if ($route_name == 'farm_crop_plan.add_planting') {
      $output .= '<p>' . $this->t('Use this form to add a new "planting" to the plan. Plantings represent the lifecycle of a crop from seed to harvest. Most information (the plant type, <em>actual</em> seeding/harvest dates, etc.) is stored in linked plant assets and their associated logs. The <em>planned</em> seeding dates and durations are specific to this plan.') . '</p>';

      // If the Planting quick form module is installed, add a link to it.
      if ($this->moduleHandler->moduleExists('farm_quick_planting')) {
        $quick_planting_url = Url::fromRoute('farm.quick.planting', ['plan' => $route_match->getParameter('plan')->id()])->toString();
        $output .= '<p>' . $this->t('Tip: Use the <a href=":url">Planting quick form</a> to create a plant asset. You will be redirected back here to fill in more details for the plan.', [':url' => $quick_planting_url]) . '</p>';
      }
    }

    return $output;
  }

}
