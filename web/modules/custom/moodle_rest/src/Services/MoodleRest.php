<?php

namespace Drupal\moodle_rest\Services;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * MoodleRest basic communication with Guzzle.
 */
class MoodleRest {

  /**
   * The HTTP client.
   *
   * @var ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The config factory.
   *
   * @var ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The Base URL of the Moodle site.
   *
   * @var string
   */
  protected string $baseUrl;

  /**
   * The token for the Rest Webservice.
   *
   * @var string
   */
  protected string $token;

  /**
   * Constructs a MoodleRest object.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
  }

  /**
   * Set Moodle Site Base URL.
   *
   * The default URL is set in configuration.
   */
  public function setBaseUrl(string $url): void
  {
    $this->baseUrl = $url;
  }

  /**
   * Sets token for Moodle Webservice.
   *
   * The default token is set in the cofiguration.
   */
  public function setToken(string $token): void
  {
    $this->token = $token;
  }

  /**
   * Get Moodle Site Base URL.
   */
  public function getBaseUrl() {
    if (empty($this->baseUrl)) {
      $config = $this->configFactory->get('moodle_rest.settings');
      $this->baseUrl = $config->get('url');
    }

    return $this->baseUrl;
  }

  /**
   * Get Moodle Webservice token.
   */
  public function getToken() {
    if (empty($this->token)) {
      $config = $this->configFactory->get('moodle_rest.settings');
      $this->token = $config->get('wstoken');
    }

    return $this->token;
  }

  /**
   * Call a Moodle WS API function.
   *
   * @param string $name
   *   API function name.
   *   https://docs.moodle.org/dev/Web_service_API_functions
   * @param array $params
   *   Parameters for the function.
   *
   * @return mixed
   *   NULL, string or array as defined by the Webservice Function.
   *
   * @throws MoodleRestException|GuzzleException
   */
  public function requestFunction(string $name, array $params = []): mixed
  {
    $host = $this->getBaseUrl();
    $wstoken = $this->getToken();
    if (empty($host) || empty($wstoken)) {
      throw new MoodleRestException('Moodle host or wstoken not configured.', 1);
    }

    $options = [];
    $options['query']['wsfunction'] = $name;

    try {
      // Moodle merges $_GET and $_POST. So we can safely put all $params into a
      // POST so it will work no matter length.
      if (empty($params)) {
        $response = $this->request($options);
      }
      else {
        $options['form_params'] = $params;
        $response = $this->request($options, 'POST');
      }
    }
    catch (TransferException $e) {
      // Chances are it'll be 500. But really this can be any
      // https://docs.guzzlephp.org/en/stable/quickstart.html#exceptions
      throw new MoodleRestException('Guzzle Exception', 500, $e);
    }

    // Moodle will also reply with HTTP 200. Examples:
    // @code
    // {
    //   "exception":"invalid_parameter_exception",
    //   "errorcode":"invalidparameter",
    //   "message":"Invalid parameter value detected"
    // }
    // @endcode
    // @code
    // {
    //   "exception":"webservice_access_exception",
    //   "errorcode":"accessexception",
    //   "message":"Access control exception"
    // }
    // @endcode
    $content = json_decode($response->getBody(), TRUE);
    if (isset($content['exception'])) {
      if ($content['errorcode'] == 'accessexception') {
        throw new MoodleRestException('Moodle access exception', 403, NULL, $content);
      }
      else {
        throw new MoodleRestException($content['message'], 400, NULL, $content);
      }
    }

    return $content;
  }

  /**
   * Basic request to Moodle WS.
   *
   * Wrapper around Guzzle. Accepts the same options. Will add the wstoken if
   * not present, will call the configured host url WS endpoint. Handles missing
   * configuration. For use if more complicated direct guzzle interaction is
   * needed that can't be achieved with the user, course service classes, or
   * even self::requestFunction().
   *
   * @param array $options
   *   Requires at least the Moodle 'wsfunction' to be set.
   *   Format and Token do not need to be set as they are added if not in the
   *   query.
   *   \GuzzleHttp\ClientInterface::request()
   * @param string $type
   *   Optional request type.
   *
   * @return ResponseInterface
   *   Guzzle response object.
   *
   * @throws MoodleRestException
   * @throws TransferException|GuzzleException
   */
  public function request(array $options, string $type = 'GET'): ResponseInterface
  {
    $host = $this->getBaseUrl();
    $wstoken = $this->getToken();
    if (empty($host) || empty($wstoken)) {
      throw new MoodleRestException('Host or Token not configured', 1, NULL);
    }
    if (empty($options['query']['wsfunction']) && empty($options['form_params']['wsfunction'])) {
      throw new MoodleRestException('No WS Function specified', 404, NULL);
    }
    $options['query'] = $options['query'] ?? [];
    $options['query'] += ['moodlewsrestformat' => 'json'];
    $options['query'] += ['wstoken' => $wstoken];

    return $this->httpClient->request($type, $host . '/webservice/rest/server.php', $options);
  }

  /**
   * Request a file.
   *
   * The file webservice isn't a direct part of the Moodle REST service, but
   * logs the user in with the service token, admittedly with a different option
   * key.
   *
   * @param string $url
   *   The path, or full url (host and webservice/pluginfile.php), for the file
   *   to retrieve.
   * @param string $preview
   *   Optional Use preview in order to display the preview of the file
   *   (e.g. "thumb" for a thumbnail).
   * @param bool|null $offline
   *   Optional means download the file from the repository and serve it, even
   *   if it was an external link. The repository may have to export the file
   *   to an offline format.
   *
   * @return ResponseInterface
   *   Guzzle response object.
   *
   * @throws MoodleRestException
   * @throws TransferException|GuzzleException
   */
  public function requestFile(string $url, string $preview = '', bool $offline = NULL): ResponseInterface
  {
    $host = $this->getBaseUrl();
    $wstoken = $this->getToken();
    if (empty($host) || empty($wstoken)) {
      throw new MoodleRestException('Host or Token not configured', 1, NULL);
    }

    // URL is often given with the full path, if it has just passthrough, if
    // not add it. Maintain our schema for contacting service.
    $url_parts = parse_url($url);
    $host_parts = parse_url($host);
    if ($url_parts['host'] != $host_parts['host'] ||
      !str_starts_with($url_parts['path'], '/webservice/pluginfile.php')) {
      if (!str_starts_with($url, '/')) {
        $url = '/' . $url;
      }
      $url = $host . '/webservice/pluginfile.php' . $url;
    }

    $options['query'] = ['token' => $wstoken];
    if ($preview != '') {
      $options['query']['preview'] = $preview;
    }
    if (!is_null($offline)) {
      $options['query']['offline'] = $offline;
    }

    return $this->httpClient->request('GET', $url, $options);
  }

}
