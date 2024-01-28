<?php

namespace Drupal\Tests\farm_crop_plan\Functional;

use Drupal\Tests\farm_crop_plan\Traits\MockCropPlanEntitiesTrait;
use Drupal\Tests\farm_test\Functional\FarmBrowserTestBase;

/**
 * Tests for farmOS crop plan.
 *
 * @group farm_crop_plan
 */
class CropPlanTest extends FarmBrowserTestBase {

  use MockCropPlanEntitiesTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'farm_crop_plan',
    'farm_land',
    'farm_seeding',
    'farm_transplanting',
  ];

  /**
   * Test crop plan export.
   */
  public function testCropPlanExport() {

    // Create mock plan entities.
    $this->createMockPlanEntities();

    // Create and login a test user with access to view crop plans and logs.
    $user = $this->createUser(['view any crop plan', 'view any log']);
    $this->drupalLogin($user);

    // Test that the plan export contains expected headers and content.
    $output = $this->drupalGet('plan/1/export');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'application/csv');
    $this->assertSession()->responseHeaderContains('Content-Disposition', 'attachment; filename="crop-plan-1.csv');
    $expected = file_get_contents(__DIR__ . '/../../files/export-crop-plan.csv');
    $this->assertEquals($expected, $output);
  }

}
