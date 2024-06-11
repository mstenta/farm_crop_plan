<?php

/**
 * @file
 * Post update functions for farm_crop_plan module.
 */

/**
 * Install the farmOS Timeline module.
 */
function farm_crop_plan_post_update_install_farm_timeline(&$sandbox = NULL) {
  if (!\Drupal::service('module_handler')->moduleExists('farm_timeline')) {
    \Drupal::service('module_installer')->install(['farm_timeline']);
  }
}
