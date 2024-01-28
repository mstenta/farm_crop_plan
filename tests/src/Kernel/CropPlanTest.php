<?php

namespace Drupal\Tests\farm_crop_plan\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\farm_crop_plan\Traits\MockCropPlanEntitiesTrait;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Tests for farmOS crop plan.
 *
 * @group farm_crop_plan
 */
class CropPlanTest extends MigrateTestBase {

  use MockCropPlanEntitiesTrait;

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
    'farm_migrate',
    'farm_plant',
    'farm_plant_type',
    'farm_seeding',
    'farm_transplanting',
    'field',
    'file',
    'geofield',
    'image',
    'log',
    'migrate_plus',
    'migrate_source_csv',
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

    // Set the private:// filesystem to use the tests/files directory of the
    // farm_crop_plan module.
    $this->setSetting('file_private_path', \Drupal::service('extension.list.module')->getPath('farm_crop_plan') . '/tests/files');
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Register the private:// stream wrapper.
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * Test the crop plan service.
   */
  public function testCropPlanService() {

    // Create mock plan entities.
    $this->createMockPlanEntities();

    // Test getting all crop_planting plan_record entities for a plan.
    $crop_records = \Drupal::service('farm_crop_plan')->getCropPlantings($this->plan);
    $this->assertCount(3, $crop_records);
    usort($crop_records, function ($a, $b) {
      return ($a->id() < $b->id()) ? -1 : 1;
    });
    foreach ($crop_records as $delta => $crop_record) {
      $this->assertEquals($this->plantAssets[$delta]->id(), $crop_record->getPlant()->id());
      $this->assertEquals($this->seedingLogs[$delta]->get('timestamp')->value, $crop_record->get('seeding_date')->value);
      $this->assertEquals(30, $crop_record->get('transplant_days')->value);
      $this->assertEquals(60, $crop_record->get('maturity_days')->value);
      $this->assertEquals(7, $crop_record->get('harvest_days')->value);
    }

    // Test getting crop_planting records by plant type.
    $crop_records_by_type = \Drupal::service('farm_crop_plan')->getCropPlantingsByType($this->plan);
    $this->assertEquals(count($this->plantTypes), count($crop_records_by_type));
    $plant_asset_ids = array_map(function ($asset) {
      return $asset->id();
    }, $this->plantAssets);
    $crop_planting_asset_ids = [];
    foreach ($this->plantTypes as $plant_type) {
      $this->assertNotEmpty($crop_records_by_type[$plant_type->id()]);
      $this->assertCount(1, $crop_records_by_type[$plant_type->id()]);
      foreach ($crop_records_by_type[$plant_type->id()] as $crop_planting) {
        $crop_planting_asset_ids[] = $crop_planting->getPlant()->id();
      }
    }
    $this->assertEquals($plant_asset_ids, $crop_planting_asset_ids);

    // Test getting crop_planting records by location.
    $crop_records_by_location = \Drupal::service('farm_crop_plan')->getCropPlantingsByLocation($this->plan);
    $this->assertEquals(1, count($crop_records_by_location));
    $this->assertNotEmpty($crop_records_by_location[$this->landAsset->id()]);
    $this->assertCount(3, $crop_records_by_location[$this->landAsset->id()]);
    $crop_planting_asset_ids = array_map(function ($crop_planting) {
      return $crop_planting->getPlant()->id();
    }, array_values($crop_records_by_location[$this->landAsset->id()]));
    $this->assertEquals($plant_asset_ids, $crop_planting_asset_ids);

    // Test getting all logs for a crop_planting plant asset.
    foreach ($crop_records as $crop_record) {
      $logs = \Drupal::service('farm_crop_plan')->getLogs($crop_record->getPlant(), FALSE);
      $this->assertCount(2, $logs);
      foreach ($logs as $log) {
        $this->assertEquals($crop_record->getPlant()->id(), $log->get('asset')->referencedEntities()[0]->id());
      }
    }

    // Test getting the first seeding logs for each crop_planting plant asset.
    foreach ($crop_records as $i => $crop_record) {
      $log = \Drupal::service('farm_crop_plan')->getFirstLog($crop_record->getPlant(), 'seeding', FALSE);
      $this->assertEquals($this->seedingLogs[$i]->id(), $log->id());
    }

    // Test getting crop planting timeline stages.
    foreach ($crop_records as $crop_record) {
      $expected_stages = [
        [
          'type' => 'seeding',
          'start' => $crop_record->get('seeding_date')->value,
          'end' => $crop_record->get('seeding_date')->value + ($crop_record->get('transplant_days')->value * 3600 * 24),
          'location' => [],
        ],
        [
          'type' => 'transplanting',
          'start' => $crop_record->get('seeding_date')->value + ($crop_record->get('transplant_days')->value * 3600 * 24),
          'end' => $crop_record->get('seeding_date')->value + ($crop_record->get('maturity_days')->value * 3600 * 24),
          'location' => [],
        ],
        [
          'type' => 'harvest',
          'start' => $crop_record->get('seeding_date')->value + ($crop_record->get('maturity_days')->value * 3600 * 24),
          'end' => $crop_record->get('seeding_date')->value + ($crop_record->get('maturity_days')->value * 3600 * 24) + ($crop_record->get('harvest_days')->value * 3600 * 24),
          'location' => [],
        ],
      ];
      $stages = \Drupal::service('farm_crop_plan')->getCropPlantingStages($crop_record, FALSE);
      $this->assertCount(3, $stages);
      foreach ($stages as $i => $stage) {
        $this->assertEquals($expected_stages[$i]['type'], $stage['type']);
        $this->assertEquals($expected_stages[$i]['start'], $stage['start']);
        $this->assertEquals($expected_stages[$i]['end'], $stage['end']);
        $this->assertEmpty($stage['location']);
      }
    }

    // Test getting asset location timeline stages.
    $expected_stages = [
      [
        [
          'type' => 'location',
          'start' => $this->seedingLogs[0]->get('timestamp')->value,
          'end' => $this->transplantingLogs[0]->get('timestamp')->value,
          'location' => [$this->landAsset],
        ],
        [
          'type' => 'location',
          'start' => $this->transplantingLogs[0]->get('timestamp')->value,
          'end' => NULL,
          'location' => [$this->landAsset],
        ],
      ],
      [
        [
          'type' => 'location',
          'start' => $this->seedingLogs[1]->get('timestamp')->value,
          'end' => $this->transplantingLogs[1]->get('timestamp')->value,
          'location' => [$this->landAsset],
        ],
        [
          'type' => 'location',
          'start' => $this->transplantingLogs[1]->get('timestamp')->value,
          'end' => NULL,
          'location' => [$this->landAsset],
        ],
      ],
      [
        [
          'type' => 'location',
          'start' => $this->seedingLogs[2]->get('timestamp')->value,
          'end' => $this->transplantingLogs[2]->get('timestamp')->value,
          'location' => [$this->landAsset],
        ],
        [
          'type' => 'location',
          'start' => $this->transplantingLogs[2]->get('timestamp')->value,
          'end' => NULL,
          'location' => [$this->landAsset],
        ],
      ],
    ];
    foreach ($crop_records as $i => $crop_record) {
      $asset = $crop_record->getPlant();
      $stages = \Drupal::service('farm_crop_plan')->getAssetLocationStages($asset);
      $this->assertEquals(count($expected_stages[$i]), count($stages));
      foreach ($stages as $j => $stage) {
        $this->assertEquals($expected_stages[$i][$j]['type'], $stage['type']);
        $this->assertEquals($expected_stages[$i][$j]['start'], $stage['start']);
        $this->assertEquals($expected_stages[$i][$j]['end'], $stage['end']);
        $this->assertEquals($expected_stages[$i][$j]['location'][0]->id(), $stage['location'][0]->id());
      }
    }
  }

  /**
   * Test crop plan import.
   */
  public function testCropPlanImport() {

    // Set the source file path configuration.
    $configuration['source']['path'] = 'private://import-crop-plan.csv';

    // Initialize and run the migration.
    $migration = $this->container->get('plugin.manager.migration')->createInstance('crop_plan_records', $configuration);
    $this->executeMigration($migration);
  }

  /**
   * Test crop plan update.
   */
  public function testCropPlanUpdate() {

    // Set the source file path configuration.
    $configuration['source']['path'] = 'private://update-crop-plan.csv';

    // Initialize and run the migration.
    $migration = $this->container->get('plugin.manager.migration')->createInstance('crop_plan_records', $configuration);
    $this->executeMigration($migration);
  }

}
