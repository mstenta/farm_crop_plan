<?php

namespace Drupal\farm_crop_plan\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Url;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\farm_import_csv\Access\CsvImportMigrationAccess;
use Drupal\farm_import_csv\Controller\CsvImportController;
use Drupal\farm_location\LogLocationInterface;
use Drupal\farm_log\AssetLogsInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Crop plan import controller.
 */
class CropPlanImport extends CsvImportController implements ContainerInjectionInterface {

  /**
   * The crop plan service.
   *
   * @var \Drupal\farm_crop_plan\CropPlanInterface
   */
  protected $cropPlan;

  /**
   * The asset logs service.
   *
   * @var \Drupal\farm_log\AssetLogsInterface
   */
  protected $assetLogs;

  /**
   * Log location service.
   *
   * @var \Drupal\farm_location\LogLocationInterface
   */
  protected $logLocation;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * Constructs a new CropPlanImport.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $plugin_manager_migration
   *   The migration plugin manager.
   * @param \Drupal\farm_import_csv\Access\CsvImportMigrationAccess $migration_access
   *   The CSV import migration access service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\farm_crop_plan\CropPlanInterface $crop_plan
   *   The crop plan service.
   * @param \Drupal\farm_log\AssetLogsInterface $asset_logs
   *   The asset logs service.
   * @param \Drupal\farm_location\LogLocationInterface $log_location
   *   Log location service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree, FormBuilderInterface $form_builder, MigrationPluginManager $plugin_manager_migration, CsvImportMigrationAccess $migration_access, Connection $database, CropPlanInterface $crop_plan, AssetLogsInterface $asset_logs, LogLocationInterface $log_location, SerializerInterface $serializer) {
    parent::__construct($menu_link_tree, $form_builder, $plugin_manager_migration, $migration_access, $database);
    $this->cropPlan = $crop_plan;
    $this->assetLogs = $asset_logs;
    $this->logLocation = $log_location;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.link_tree'),
      $container->get('form_builder'),
      $container->get('plugin.manager.migration'),
      $container->get('farm_import_csv.access'),
      $container->get('database'),
      $container->get('farm_crop_plan'),
      $container->get('asset.logs'),
      $container->get('log.location'),
      $container->get('serializer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function importer(string $migration_id, $plan = NULL): array {

    // If a plan was not provided, bail.
    if (empty($plan)) {
      return [];
    }

    // Start with the default importer elements.
    $build = parent::importer($migration_id);

    // Remove the template download link.
    if (!empty($build['columns']['template'])) {
      unset($build['columns']['template']);
    }

    // Add a "How it works" overview.
    $build['overview'] = [
      '#type' => 'details',
      '#title' => $this->t('How it works'),
      '#weight' => -10,
    ];
    $build['overview']['list'] = [
      '#theme' => 'item_list',
      '#items' => [
        t('A crop plan consists of a set of plant assets, each with their own logs.'),
        t('Alongside each plant asset, some additional information is maintained for planning purposes. This includes the planned seeding date, days to maturity, days of harvest, etc.'),
        t('The "Download CSV" link will export the current state of the plan as a CSV file. If this is a new plan, the CSV will only include the header row. If the plan has plant assets associated with it, they will each be included as a separate row.'),
        t('Adding rows to the CSV will create new plant assets and add them to the plan.'),
        t('If ...'),
        t('If ...'),
        t('If ...'),
        t('If ...'),
        t('Editing existing rows of the CSV will update the existing plant assets and their corresponding planning information. This requires the plant_id column to look up the plant by its asset ID.'),
        t('Refer to "CSV Columns" for more information about what each column expects.'),
      ],
    ];

    // Add a link to download a CSV of the plan.
    $build['form']['download'] = [
      '#type' => 'link',
      '#title' => $this->t('Download CSV'),
      '#url' => Url::fromRoute('farm_crop_plan.export', ['plan' => $plan->id()]),
    ];

    // Allow updating.
    $build['form']['update_existing_records']['#value'] = TRUE;

    return $build;
  }

  /**
   * Download a CSV representation of the plan.
   *
   * @param string $migration_id
   *   The migration ID.
   * @param \Drupal\plan\Entity\PlanInterface $plan
   *   The crop plan entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An application/csv file download response object.
   */
  public function exporter(string $migration_id, $plan) {

    // Draft an application/csv response.
    $filename = 'crop-plan-' . $plan->id() . '.csv';
    $response = new Response();
    $response->headers->set('Content-Type', 'application/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    // Load expected column names.
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->pluginManagerMigration->getDefinition($migration_id);
    $column_names = array_filter(array_column($migration['third_party_settings']['farm_import_csv']['columns'] ?? [], 'name'));

    // Load crop_planting records for the plan.
    $crop_plantings = $this->cropPlan->getCropPlantings($plan);

    // If there are no crop_planting records, add a single template row.
    $row_template = array_fill_keys($column_names, '');
    $row_template['plan_id'] = $plan->id();
    $data = [];
    if (empty($crop_plantings)) {
      $data[] = $row_template;
    }

    // Iterate through the crop_planting records and build data rows.
    foreach ($crop_plantings as $crop_planting) {
      $row = $row_template;

      // Set the plant asset ID.
      $row['plant_id'] = $crop_planting->getPlant()->id();

      // Load plant type labels.
      $plant_types = array_map(function ($term) {
        return $term->label(0);
      }, $crop_planting->getPlant()->get('plant_type')->referencedEntities());
      $row['plant_type'] = implode(', ', $plant_types);

      // Look up the first seeding log location and timestamp.
      $log = $this->assetLogs->getFirstLog($crop_planting->getPlant(), 'seeding');
      if (!empty($log)) {
        $locations = array_map(function ($asset) {
          return $asset->label(0);
        }, $this->logLocation->getLocation($log));
        $row['seeding_location'] = implode(', ', $locations);
        $row['seeding_date'] = date('c', $log->get('timestamp')->value);
      }

      // Look up the first transplanting log location and timestamp.
      $log = $this->assetLogs->getFirstLog($crop_planting->getPlant(), 'transplanting');
      if (!empty($log)) {
        $locations = array_map(function ($asset) {
          return $asset->label(0);
        }, $this->logLocation->getLocation($log));
        $row['transplanting_location'] = implode(', ', $locations);
        $row['transplanting_date'] = date('c', $log->get('timestamp')->value);
      }

      // Add transplant_days, if available.
      if (!empty($crop_planting->get('transplant_days')->value)) {
        $row['transplant_days'] = $crop_planting->get('transplant_days')->value;
      }

      // Add maturity_days, if available.
      if (!empty($crop_planting->get('maturity_days')->value)) {
        $row['maturity_days'] = $crop_planting->get('maturity_days')->value;
      }

      // Add harvest_days, if available.
      if (!empty($crop_planting->get('harvest_days')->value)) {
        $row['harvest_days'] = $crop_planting->get('harvest_days')->value;
      }

      // Add the row data.
      $data[] = $row;
    }

    // Serialize and return the data.
    $response->setContent($this->serializer->serialize($data, 'csv'));
    return $response;
  }

}
