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

    // Add a link to the Planting quick form on the "Add planting" form, if the
    // module is installed.
    if ($route_name == 'farm_crop_plan.add_planting' && $this->moduleHandler->moduleExists('farm_quick_planting')) {
      $quick_planting_url = Url::fromRoute('farm.quick.planting', ['plan' => $route_match->getParameter('plan')->id()])->toString();
      $output .= '<p>' . $this->t('Tip: Use the <a href=":url">Planting quick form</a> to create a plant asset. You will be redirected back here to fill in more details for the plan.', [':url' => $quick_planting_url]) . '</p>';
    }

    return $output;
  }

}
