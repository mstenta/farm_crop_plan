<?php

namespace Drupal\farm_crop_plan\Form;

use Drupal\asset\Entity\AssetInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\plan\Entity\PlanInterface;
use Drupal\plan\Entity\PlanRecord;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Crop plan add planting form.
 */
class CropPlanAddPlantingForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The current Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The crop plan service.
   *
   * @var \Drupal\farm_crop_plan\CropPlanInterface
   */
  protected CropPlanInterface $cropPlan;

  /**
   * CropPlanAddPlantingForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current Request object.
   * @param \Drupal\farm_crop_plan\CropPlanInterface $crop_plan
   *   The crop plan service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, Request $request, CropPlanInterface $crop_plan) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->request = $request;
    $this->cropPlan = $crop_plan;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('farm_crop_plan'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_crop_plan_add_planting_form';
  }

  /**
   * Title callback.
   *
   * @param \Drupal\plan\Entity\PlanInterface|null $plan
   *   The plan entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Returns the title.
   */
  public function title(PlanInterface $plan = NULL) {
    if (empty($plan)) {
      return $this->t('Add planting');
    }
    return $this->t('Add planting to @plan', ['@plan' => $plan->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PlanInterface $plan = NULL) {
    if (empty($plan)) {
      return $form;
    }
    $form_state->set('plan_id', $plan->id());

    $form['plant'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Plant asset'),
      '#target_type' => 'asset',
      '#selection_settings' => [
        'target_bundles' => ['plant'],
      ],
      '#ajax' => [
        'wrapper' => 'planting-details',
        'callback' => [$this, 'plantingDetailsCallback'],
        'event' => 'autocompleteclose change',
      ],
      '#required' => TRUE,
    ];

    // If a plant ID was provided via query parameter, load it and set the
    // form value.
    $plant_id = $this->request->get('plant');
    if ($plant_id) {
      $plant = $this->entityTypeManager->getStorage('asset')->load($this->request->get('plant'));
      if (!empty($plant) && $plant->bundle() == 'plant') {
        $form['plant']['#default_value'] = $plant;
        $form_state->setValue('plant', $plant_id);
      }
    }

    $form['details'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'planting-details',
      ],
    ];

    // If the form is being built with a plant asset selected, reset planting
    // details and populate their default values.
    $plant = NULL;
    if ($form_state->getValue('plant')) {
      $this->resetPlantingDetails($form_state);
      $plant = $this->entityTypeManager->getStorage('asset')->load($form_state->getValue('plant'));
    }
    $default_values = $this->plantingDefaultValues($plant);

    $form['details']['seeding_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Seeding date'),
      '#default_value' => $default_values['seeding_date'],
      '#required' => TRUE,
    ];

    if ($this->moduleHandler->moduleExists('farm_transplanting')) {
      $form['details']['transplant_days'] = [
        '#type' => 'number',
        '#title' => $this->t('Days to transplant'),
        '#step' => 1,
        '#min' => 1,
        '#max' => 365,
        '#default_value' => $default_values['transplant_days'],
      ];
    }

    $form['details']['maturity_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to maturity'),
      '#step' => 1,
      '#min' => 1,
      '#max' => 365,
      '#default_value' => $default_values['maturity_days'],
      '#required' => TRUE,
    ];

    $form['details']['harvest_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Harvest window (days)'),
      '#step' => 1,
      '#min' => 1,
      '#max' => 365,
      '#default_value' => $default_values['harvest_days'],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Ajax callback for planting details.
   */
  public function plantingDetailsCallback(array $form, FormStateInterface $form_state) {
    return $form['details'];
  }

  /**
   * Reset planting details.
   */
  public function resetPlantingDetails(FormStateInterface $form_state) {
    $details_fields = [
      'seeding_date',
      'transplant_days',
      'maturity_days',
      'harvest_days',
    ];
    $user_input = $form_state->getUserInput();
    foreach ($details_fields as $field_name) {
      unset($user_input[$field_name]);
    }
    $form_state->setUserInput($user_input);
    $form_state->setRebuild();
  }

  /**
   * Get default values for planting details.
   *
   * @param \Drupal\asset\Entity\AssetInterface|null $plant
   *   A plant asset (optional).
   *
   * @return array
   *   Returns a keyed array of planting default values, including:
   *   - seeding_date
   *   - transplant_days
   *   - maturity_days
   *   - harvest_days
   */
  public function plantingDefaultValues($plant = NULL) {

    // Start with defaults.
    $values = [
      'seeding_date' => new DrupalDateTime('midnight', $this->currentUser()->getTimeZone()),
      'transplant_days' => NULL,
      'maturity_days' => NULL,
      'harvest_days' => NULL,
    ];

    // If a plant asset was provided, attempt to load more details from it.
    if (!is_null($plant) && $plant instanceof AssetInterface) {

      // Load the plant_type term.
      $plant_type = $plant->get('plant_type')->first()?->entity;

      // Load seeding date from the first seeding log.
      $seeding_log = $this->cropPlan->getFirstLog($plant, 'seeding');
      if (!empty($seeding_log)) {
        $values['seeding_date'] = DrupalDateTime::createFromTimestamp($seeding_log->get('timestamp')->value);
      }

      // If the farm_transplanting module is enabled attempt to populate the
      // transplant_days value.
      if ($this->moduleHandler->moduleExists('farm_transplanting')) {

        // Calculate transplant_days from the first transplanting log.
        $transplanting_log = $this->cropPlan->getFirstLog($plant, 'transplanting');
        if (!empty($seeding_log) && !empty($transplanting_log)) {
          $values['transplant_days'] = round(($transplanting_log->get('timestamp')->value - $seeding_log->get('timestamp')->value) / (60 * 60 * 24));
        }

        // Or, if a transplanting log does not exist, pull from the plant_type.
        elseif ($plant_type->get('transplant_days')->value) {
          $values['transplant_days'] = $plant_type->get('transplant_days')->value;
        }
      }

      // Calculate maturity_days from the first harvest log.
      $harvest_log = $this->cropPlan->getFirstLog($plant, 'harvest');
      if (!empty($seeding_log) && !empty($harvest_log)) {
        $values['maturity_days'] = round(($harvest_log->get('timestamp')->value - $seeding_log->get('timestamp')->value) / (60 * 60 * 24));
      }

      // Or, if a harvest log does not exist, pull from the plant_type.
      elseif ($plant_type->get('maturity_days')->value) {
        $values['maturity_days'] = $plant_type->get('maturity_days')->value;
      }

      // Populate harvest_days from the plant_type.
      if ($plant_type->get('harvest_days')->value) {
        $values['harvest_days'] = $plant_type->get('harvest_days')->value;
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Require plant asset.
    $plant = $form_state->getValue('plant');
    if (empty($plant)) {
      $form_state->setErrorByName('plant', $this->t('Select a plant asset.'));
      return;
    }

    // Check for existing crop_planting records for the plan and plant.
    $plan_id = $form_state->get('plan_id');
    $existing = $this->entityTypeManager->getStorage('plan_record')->getQuery()
      ->accessCheck(FALSE)
      ->condition('plan', $plan_id)
      ->condition('plant', $plant)
      ->count()
      ->execute();
    if ($existing > 0) {
      $form_state->setErrorByName('plant', $this->t('This plant asset is already added to the plan.'));
    }

    // Days to maturity must be greater than days to transplant.
    if ($form_state->getValue('transplant_days')) {
      $maturity_days = (int) $form_state->getValue('maturity_days');
      $transplant_days = (int) $form_state->getValue('transplant_days');
      if ($maturity_days <= $transplant_days) {
        $form_state->setErrorByName('maturity_days', $this->t('Days to maturity must be greater than days to transplant.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $plan_id = $form_state->get('plan_id');
    $plant = $form_state->getValue('plant');
    $record = PlanRecord::create([
      'type' => 'crop_planting',
      'plan' => $plan_id,
      'plant' => $plant,
      'seeding_date' => $form_state->getValue('seeding_date')->getTimestamp(),
      'transplant_days' => $form_state->getValue('transplant_days'),
      'maturity_days' => $form_state->getValue('maturity_days'),
      'harvest_days' => $form_state->getValue('harvest_days'),
    ]);
    $record->save();
    $this->messenger()->addMessage($this->t('Added @crop_planting', ['@crop_planting' => $record->label()]));
    $form_state->setRedirect('entity.plan.canonical', ['plan' => $plan_id]);
  }

}
