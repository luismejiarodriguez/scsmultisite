<?php

namespace Drupal\services_api_key_auth\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * HTTP Basic authentication provider.
 *
 * @deprecated in services_api_key_auth:3.0.6 and is removed from
 * services_api_key_auth:4.0.0. The service will be renamed.
 * @see https://www.drupal.org/project/services_api_key_auth/issues/2893183
 */
class ApiKeyAuth implements AuthenticationProviderInterface {
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The user auth service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructs a HTTP basic authentication provider object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $current_route_match) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRouteMatch = $current_route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Only apply this validation if request has a valid accept value.
    return $this->getKey($request) !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // Load config entity.
    $api_key_entities = $this->entityTypeManager
      ->getStorage('api_key')
      ->loadByProperties(['key' => $this->getKey($request)]);

    foreach ($api_key_entities as $key_item) {
      if ($this->getKey($request) == $key_item->key) {
        $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['uuid' => $key_item->user_uuid]);
        $account = reset($accounts);

        if (isset($account)) {
          // Authentication successful:
          return $account;
        }
        break;
      }
    }

    // Authentication failed.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup(Request $request) {}

  /**
   * {@inheritdoc}
   */
  public function handleException(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    if ($exception instanceof AccessDeniedHttpException) {
      $event->setThrowable(new UnauthorizedHttpException('Invalid consumer origin.', $exception));

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Retrieve key from request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that the service will respond to.
   *
   * @return bool
   *   True if api key is present
   */
  public function getKey(Request $request) {
    // Exempt this module's api key entitiy edit/delete form route:
    $route_name = $this->currentRouteMatch->getRouteName();
    if (str_contains($route_name ?? '', 'entity.api_key')) {
      return FALSE;
    }

    $settings = $this->configFactory->get('services_api_key_auth.settings');

    // Check for the api key inside the request header ($_SERVER), if a server
    // api key name is defined:
    if ($serverApiKeyName = $settings->get('api_key_request_header_name')) {
      $header_api_key = $request->headers->get($serverApiKeyName);
      if (!empty($header_api_key)) {
        return $header_api_key;
      }
    }

    // Check for the api key inside the request body parameters ($_POST), if a
    // post api key name is defined:
    if ($postApiKeyName = $settings->get('api_key_post_parameter_name')) {
      $form_api_key = $request->request->get($postApiKeyName);
      if (!empty($form_api_key)) {
        return $form_api_key;
      }
    }

    // Check for the api key inside the request query parameters ($_GET), if a
    // query api key name is defined:
    if ($queryApiKeyName = $settings->get('api_key_get_parameter_name')) {
      $query_api_key = $request->query->get($queryApiKeyName);
      if (!empty($query_api_key)) {
        return $query_api_key;
      }
    }
    return FALSE;
  }

}
