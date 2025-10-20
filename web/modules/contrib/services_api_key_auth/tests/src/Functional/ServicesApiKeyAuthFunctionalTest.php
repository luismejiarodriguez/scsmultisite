<?php

namespace Drupal\Tests\services_api_key_auth\Functional;

use Drupal\services_api_key_auth\Entity\ApiKey;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\Exception\ClientException;

/**
 * This class provides methods specifically for testing something.
 *
 * @group services_api_key_auth
 */
class ServicesApiKeyAuthFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'services_api_key_auth',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates an api key.
   */
  protected function createApiKey() {
    // We need to programmatically load the root user, because with the
    // "normal" rootUser object, we don't have the uuid:
    $rootUser = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($this->rootUser->id());
    $userUuid = $rootUser->uuid();
    // Create the API key:
    $apiKey = ApiKey::create([
      'id' => 'test_api_key',
      'label' => 'Test API Key',
      'key' => $this->randomString(),
      'user_uuid' => $userUuid,
    ]);
    $apiKey->save();
    return $apiKey;
  }

  /**
   * Tests if the api key header access works as expected.
   */
  public function testApiKeyAccessViaHeader() {
    // Create an api key:
    $apiKey = $this->createApiKey();
    // Set the api_key_request_header_name to "test_api_key" and disable all
    // other authentication methods:
    $this->config('services_api_key_auth.settings')
      ->set('api_key_request_header_name', 'test_api_key')
      ->set('api_key_get_parameter_name', '')
      ->set('api_key_post_parameter_name', '')
      ->save();
    $httpClient = $this->getHttpClient();
    // A logged out user should not have access to the admin page:
    $this->expectException(ClientException::class);
    $this->expectExceptionMessage('403 Forbidden');
    $httpClient->request('HEAD', $this->buildUrl('/admin'));
    // When passing the api key in the header, the user should
    // have access to the admin page:
    $httpClient->request('HEAD', $this->buildUrl('/admin'), ['test_api_key' => $apiKey->key]);
    $this->assertSession()->statusCodeEquals(200);
    // Now we remove the api_key_request_header_name name, which should disable
    // the header authentication entirely:
    $this->config('services_api_key_auth.settings')
      ->set('api_key_request_header_name', '')
      ->save();
    // We shouldn't be able to access the admin page with the api key anymore:
    $this->expectException(ClientException::class);
    $this->expectExceptionMessage('403 Forbidden');
    $httpClient->request('HEAD', $this->buildUrl('/admin'), ['test_api_key' => $apiKey->key]);
  }

  /**
   * Tests if the api key header access works as expected.
   */
  public function testApiKeyAccessViaGetParameter() {
    // Create an api key:
    $apiKey = $this->createApiKey();
    // Set the api_key_get_parameter_name to "test_api_key" and disable all
    // other authentication methods:
    $this->config('services_api_key_auth.settings')
      ->set('api_key_get_parameter_name', 'test_api_key')
      ->set('api_key_request_header_name', '')
      ->set('api_key_post_parameter_name', '')
      ->save();
    $httpClient = $this->getHttpClient();
    // A logged out user should not have access to the admin page:
    $this->expectException(ClientException::class);
    $this->expectExceptionMessage('403 Forbidden');
    $httpClient->request('GET', $this->buildUrl('/admin'));
    // When passing the api key in the query, the user should
    // have access to the admin page:
    $httpClient->request('GET', $this->buildUrl('/admin'), ['test_api_key' => $apiKey->key]);
    $this->assertSession()->statusCodeEquals(200);
    // Now we remove the api_key_get_parameter_name name, which should disable
    // the query authentication entirely:
    $this->config('services_api_key_auth.settings')
      ->set('api_key_get_parameter_name', '')
      ->save();
    // We shouldn't be able to access the admin page with the api key anymore:
    $this->expectException(ClientException::class);
    $this->expectExceptionMessage('403 Forbidden');
    $httpClient->request('GET', $this->buildUrl('/admin'), ['test_api_key' => $apiKey->key]);
  }

  /**
   * Tests if the api key header access works as expected.
   */
  public function testApiKeyAccessViaPostParameter() {
    // Create an api key:
    $apiKey = $this->createApiKey();
    // Set the api_key_post_parameter_name to "test_api_key" and disable all
    // other authentication methods:
    $this->config('services_api_key_auth.settings')
      ->set('api_key_post_parameter_name', 'test_api_key')
      ->set('api_key_request_header_name', '')
      ->set('api_key_get_parameter_name', '')
      ->save();
    $httpClient = $this->getHttpClient();
    $session = $this->assertSession();
    // We can try to do a post request on the settings page, but without the
    // api key, we should get an access denied error:
    $this->expectException(ClientException::class);
    $this->expectExceptionMessage('403 Forbidden');
    $httpClient->request('POST', $this->buildUrl('/admin'), ['test_api_key' => '']);

    // But passing the api key in the get post parameters, we should have
    // access:
    $httpClient->request('POST', $this->buildUrl('/admin'), ['test_api_key' => $apiKey->key]);
    $session->statusCodeEquals(200);
    // Now we remove the api_key_post_parameter_name name, which should disable
    // the post authentication entirely:
    $this->config('services_api_key_auth.settings')
      ->set('api_key_post_parameter_name', '')
      ->save();
    // We shouldn't be able to POST to the page with the api key anymore:
    $this->expectException(ClientException::class);
    $this->expectExceptionMessage('403 Forbidden');
    $httpClient->request('POST', $this->buildUrl('/admin'), ['test_api_key' => $apiKey->key]);
  }

}
