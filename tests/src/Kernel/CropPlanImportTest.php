<?php

namespace Drupal\Tests\farm_crop_plan\Kernel;

use Drupal\Tests\farm_crop_plan\Traits\MockCropPlanEntitiesTrait;
use Drupal\Tests\farm_import_csv\Kernel\CsvImportTestBase;

/**
 * Tests for farmOS crop plan importer.
 *
 * @group farm_crop_plan
 */
class CropPlanImportTest extends CsvImportTestBase {

  use MockCropPlanEntitiesTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
//    'asset',
//    'entity',
//    'entity_reference_validators',
    'farm_crop_plan',
//    'farm_entity',
    'farm_id_tag',
//    'farm_import_csv',
//    'farm_field',
    'farm_land',
    'farm_location',
    'farm_log',
//    'farm_log_asset',
    'farm_map',
//    'farm_migrate',
    'farm_plant',
    'farm_plant_type',
    'farm_seeding',
    'farm_transplanting',
    'field',
//    'file',
    'geofield',
//    'image',
//    'log',
//    'migrate_plus',
//    'migrate_source_csv',
//    'options',
    'plan',
//    'quantity',
//    'state_machine',
//    'taxonomy',
//    'text',
//    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('plan');
    $this->installEntitySchema('plan_record');
    $this->installConfig([
      'farm_crop_plan',
      'farm_land',
      'farm_plant',
      'farm_plant_type',
      'farm_seeding',
      'farm_transplanting',
    ]);

    // Set the private:// filesystem to use this module's artifacts directory.
    $this->setSetting('file_private_path', \Drupal::service('extension.list.module')->getPath('farm_crop_plan_test') . '/artifacts');
  }

  /**
   * Test crop plan import.
   */
  public function testCropPlanImport() {
    $this->importCsv('import-crop-plan.csv', 'crop_plan_records');
    $d=1;
  }

  /**
   * Test crop plan update.
   */
  public function testCropPlanUpdate() {
    $this->importCsv('import-crop-plan.csv', 'crop_plan_records');
    $d=1;
  }

}
