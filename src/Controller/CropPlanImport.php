<?php

namespace Drupal\farm_crop_plan\Controller;

use Drupal\farm_import_csv\Controller\CsvImportController;

/**
 * Crop plan import controller.
 */
class CropPlanImport extends CsvImportController {

  /**
   * {@inheritdoc}
   */
  public function importer(string $migration_id): array {
    $build = parent::importer($migration_id);

    // Remove the template download link.
    if (!empty($build['columns']['template'])) {
      unset($build['columns']['template']);
    }

    return $build;
  }

}
