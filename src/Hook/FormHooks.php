<?php

declare(strict_types=1);

namespace Drupal\farm_crop_plan\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form hook implementations for farm_crop_plan.
 */
class FormHooks {

  use AutowireTrait;

  public function __construct(
    protected RequestStack $requestStack,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_plan_record_crop_planting_edit_form_alter')]
  public function formPlanRecordCropPlantingEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {

    // Hide plan and plant fields in crop_planting edit forms.
    if (!empty($form['plan'])) {
      $form['plan']['#access'] = FALSE;
    }
    if (!empty($form['plant'])) {
      $form['plant']['#access'] = FALSE;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_quick_form_planting_alter')]
  public function formQuickFormPlantingAlter(&$form, FormStateInterface $form_state, $form_id) {

    // Alter the planting quick form, if a crop plan was specified.
    $plan_id = $this->requestStack->getCurrentRequest()->query->get('plan');
    if (empty($plan_id)) {
      return;
    }
    /** @var \Drupal\plan\Entity\PlanInterface|null $plan */
    $plan = $this->entityTypeManager->getStorage('plan')->load($plan_id);
    if (is_null($plan) || $plan->bundle() !== 'crop') {
      return;
    }

    // Save the plan ID.
    $form['plan_id'] = [
      '#type' => 'value',
      '#value' => $plan->id(),
    ];

    // If the crop plan has a season, set the plant season default value.
    if (!empty($plan->get('season')->referencedEntities())) {
      $form['seasons']['#default_value'] = $plan->get('season')->referencedEntities();
    }

    // Add a submit function that will redirect to the "Add planting" form with
    // the new plant asset pre-populated.
    $form['#submit'][] = [self::class, 'plantingQuickFormSubmit'];
  }

  /**
   * Planting quick form submit function.
   */
  public static function plantingQuickFormSubmit(array $form, FormStateInterface $form_state) {

    // Find the asset that was just created.
    $asset_id = \Drupal::database()->query("SELECT entity_id FROM {asset__quick} WHERE quick_value = 'planting' ORDER BY entity_id DESC LIMIT 1")->fetchField();

    // Redirect to the "Add planting" form with the asset ID pre-populated.
    $form_state->setRedirect('farm_crop_plan.add_planting', ['plan' => $form_state->getValue('plan_id')], ['query' => ['plant' => $asset_id]]);
  }

}
