<?php

namespace Drupal\Tests\farm_crop_plan\Kernel;

use Drupal\asset\Entity\Asset;
use Drupal\KernelTests\KernelTestBase;
use Drupal\log\Entity\Log;
use Drupal\plan\Entity\Plan;
use Drupal\plan\Entity\PlanRecord;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests for farmOS crop plan.
 *
 * @group farm_crop_plan
 */
class CropPlanTest extends KernelTestBase {

  /**
   * Season term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seasonTerm = NULL;

  /**
   * Plant type terms.
   *
   * @var \Drupal\taxonomy\Entity\Term[]
   */
  protected $plantTypes = [];

  /**
   * Plant assets.
   *
   * @var \Drupal\asset\Entity\AssetInterface[]
   */
  protected $plantAssets = [];

  /**
   * Land asset.
   *
   * @var \Drupal\asset\Entity\AssetInterface
   */
  protected $landAsset = NULL;

  /**
   * Seeding logs.
   *
   * @var \Drupal\log\Entity\LogInterface[]
   */
  protected $seedingLogs = [];

  /**
   * Transplanting logs.
   *
   * @var \Drupal\log\Entity\LogInterface[]
   */
  protected $transplantingLogs = [];

  /**
   * Crop plan.
   *
   * @var \Drupal\plan\Entity\PlanInterface
   */
  protected $plan = NULL;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'asset',
    'entity',
    'entity_reference_validators',
    'farm_crop_plan',
    'farm_entity',
    'farm_field',
    'farm_land',
    'farm_location',
    'farm_log',
    'farm_log_asset',
    'farm_map',
    'farm_plant',
    'farm_plant_type',
    'farm_seeding',
    'farm_transplanting',
    'field',
    'file',
    'geofield',
    'image',
    'log',
    'options',
    'plan',
    'state_machine',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('asset');
    $this->installEntitySchema('log');
    $this->installEntitySchema('plan');
    $this->installEntitySchema('plan_record');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installConfig([
      'farm_crop_plan',
      'farm_land',
      'farm_plant',
      'farm_plant_type',
      'farm_seeding',
      'farm_transplanting',
    ]);
    $this->createMockPlanEntities();
  }

  /**
   * Test the crop plan service.
   */
  public function testCropPlanService() {

    // Test getting all crop_planting plan_record entities for a plan.
    $crop_records = \Drupal::service('farm_crop_plan')->getCropPlantings($this->plan);
    $this->assertCount(3, $crop_records);
    usort($crop_records, function ($a, $b) {
      return ($a->id() < $b->id()) ? -1 : 1;
    });
    foreach ($crop_records as $delta => $crop_record) {
      $this->assertEquals($this->plantAssets[$delta]->id(), $crop_record->get('plant')->referencedEntities()[0]->id());
      $this->assertEquals($this->seedingLogs[$delta]->get('timestamp')->value, $crop_record->get('seeding_date')->value);
      $this->assertEquals(30, $crop_record->get('transplant_days')->value);
      $this->assertEquals(60, $crop_record->get('maturity_days')->value);
      $this->assertEquals(7, $crop_record->get('harvest_days')->value);
    }
  }

  /**
   * Create mock crop plan entities.
   */
  public function createMockPlanEntities() {

    // Create a season term.
    $this->seasonTerm = Term::create([
      'name' => date('Y'),
      'vid' => 'season',
    ]);
    $this->seasonTerm->save();

    // Create plant_type terms.
    $plant_type_names = [
      'Corn',
      'Beans',
      'Squash',
    ];
    foreach ($plant_type_names as $name) {
      $term = Term::create([
        'name' => $name,
        'vid' => 'plant_type',
      ]);
      $term->save();
      $this->plantTypes[] = $term;
    }

    // Create plant assets for each plant type.
    foreach ($this->plantTypes as $plant_type) {
      $asset = Asset::create([
        'name' => $this->seasonTerm->label() . ' ' . $plant_type->label(),
        'type' => 'plant',
        'plant_type' => [['target_id' => $plant_type->id()]],
        'status' => 'active',
      ]);
      $asset->save();
      $this->plantAssets[] = $asset;
    }

    // Create a land asset.
    $this->landAsset = Asset::create([
      'name' => 'Field A',
      'type' => 'land',
      'land_type' => 'field',
      'is_fixed' => TRUE,
      'is_location' => TRUE,
      'status' => 'active',
    ]);
    $this->landAsset->save();

    // Create seeding logs for each plant asset.
    $timestamp = strtotime('-6 month');
    foreach ($this->plantAssets as $plant_asset) {
      $timestamp = strtotime('+1 month', $timestamp);
      $log = Log::create([
        'name' => 'Seed ' . $plant_asset->label() . ' in ' . $this->landAsset->label(),
        'type' => 'seeding',
        'timestamp' => $timestamp,
        'asset' => [
          ['target_id' => $plant_asset->id()],
        ],
        'location' => [
          ['target_id' => $this->landAsset->id()],
        ],
        'is_movement' => TRUE,
        'status' => 'done',
      ]);
      $log->save();
      $this->seedingLogs[] = $log;
    }

    // Create transplanting logs for each plant asset.
    $timestamp = strtotime('-5 month');
    foreach ($this->plantAssets as $plant_asset) {
      $timestamp = strtotime('+1 month', $timestamp);
      $log = Log::create([
        'name' => 'Transplant ' . $plant_asset->label() . ' in ' . $this->landAsset->label(),
        'type' => 'transplanting',
        'timestamp' => $timestamp,
        'asset' => [
          ['target_id' => $plant_asset->id()],
        ],
        'location' => [
          ['target_id' => $this->landAsset->id()],
        ],
        'is_movement' => TRUE,
        'status' => 'done',
      ]);
      $log->save();
      $this->transplantingLogs[] = $log;
    }

    // Create a crop plan for the season.
    $this->plan = Plan::create([
      'name' => $this->seasonTerm->label() . ' Crop Plan',
      'type' => 'crop',
      'season' => [
        ['target_id' => $this->seasonTerm->id()],
      ],
      'status' => 'active',
    ]);
    $this->plan->save();

    // Create crop_planting plan_record entities to link plant assets to the
    // plan.
    foreach ($this->plantAssets as $i => $plant_asset) {
      $crop_planting = PlanRecord::create([
        'type' => 'crop_planting',
        'plan' => ['target_id' => $this->plan->id()],
        'plant' => ['target_id' => $plant_asset->id()],
        'seeding_date' => $this->seedingLogs[$i]->get('timestamp')->value,
        'transplant_days' => 30,
        'maturity_days' => 60,
        'harvest_days' => 7,
      ]);
      $crop_planting->save();
    }
  }

}
