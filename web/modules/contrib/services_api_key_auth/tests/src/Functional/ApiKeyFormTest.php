<?php

namespace Drupal\Tests\services_api_key_auth\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * This class provides methods specifically for testing something.
 *
 * @group services_api_key_auth
 */
class ApiKeyFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'services_api_key_auth',
    'saka_form_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Login as root user:
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests if the module installation, won't break the site.
   *
   * @see https://www.drupal.org/project/services_api_key_auth/issues/2928649
   */
  public function testApiKeyFormElementCausingAccessDenied() {
    // Set the api_key_post_parameter_name to "api_key":
    $this->config('services_api_key_auth.settings')
      ->set('api_key_post_parameter_name', 'api_key')
      ->save();
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Go to the test module settings page:
    $this->drupalGet('/admin/config/system/test-settings');
    $session->statusCodeEquals(200);
    $page->fillField('api_key', 'test-api-key');
    $page->pressButton('Save configuration');
    // Since "api_key" exists as form element and has a value, it will be one
    // of the post parameters and therefore collide, with our
    // "api_key_post_parameter_name" key name. This will cause an access denied error:
    $session->statusCodeEquals(403);

    // Remove the "api_key_post_parameter_name" value, so that authentication through
    // post parameters is disabled:
    $this->config('services_api_key_auth.settings')
      ->set('api_key_post_parameter_name', '')
      ->save();
    // Now we should be able to save the form:
    $this->drupalGet('/admin/config/system/test-settings');
    $session->statusCodeEquals(200);
    $page->fillField('api_key', 'test-api-key');
    $page->pressButton('Save configuration');
    $session->statusCodeEquals(200);
    $session->pageTextContains('Form submitted successfully.');
  }

}

