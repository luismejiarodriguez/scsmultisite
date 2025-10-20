<?php

namespace Drupal\Tests\services_api_key_auth\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;

/**
 * Generic module test for services_api_key_auth.
 *
 * @group services_api_key_auth
 */
class GenericServicesApiKeyAuthTest extends GenericModuleTestBase {

  /**
   * {@inheritDoc}
   */
  protected function assertHookHelp(string $module): void {
    // Don't do anything here. We intend to implement hook_help() differently.
  }

}
