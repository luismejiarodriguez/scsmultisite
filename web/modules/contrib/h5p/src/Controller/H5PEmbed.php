<?php

namespace Drupal\h5p\Controller;

use Composer\InstalledVersions;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\Core\Render\HtmlResponse;

/**
 * The embed controller
 */
class H5PEmbed extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function embed($id) {

    /**
     * @var \Drupal\h5p\AssetFileStorage $asset_manager
     */
    $asset_manager = \Drupal::service('h5p.asset_file_manager');
    // Because embeds do not use the h5p library definitions for getting assets,
    // h5p_library_info_build() might not have run, which means we can't be sure
    // that the vendor files have been mirrored. Check now and mirror them if
    // necessary.
    if (!file_exists($asset_manager->getDirectoryUri() . 'h5p-core/')) {
      $asset_manager->mirrorAssets();
    }

    // Prepare reponse with cache tag for invalidation
    $response = [
      '#cache' => [
        'tags' => [
          'h5p_content:' . $id,
        ],
        // H5P core uses a hash token that becomes invalid between 12 and 24
        // hours. To be sure the cache doesn't serve an out-of-date token, set
        // the max age to 12 hours.
        'max-age' => 12 * 60 * 60,
      ],
    ];

    $h5p_module_path = \Drupal::service('extension.list.module')->getPath('h5p');

    // Load requested content
    $h5p_content = H5PContent::load($id);

    // TODO: Check access to field / entity ?
    if (empty($h5p_content)) {
      $response['#markup'] = '<body style="margin:0"><div style="background: #fafafa url(' . \Drupal::service('file_url_generator')->generate('assets://h5p/h5p-core/images/h5p.svg')->toString(). ')  no-repeat center;background-size: 50% 50%;width: 100%;height: 100%;"></div><div style="width:100%;position:absolute;top:75%;text-align:center;color:#434343;font-family: Consolas,monaco,monospace">' . t('Content unavailable.') . '</div></body>';
      return new HtmlResponse($response);
    }

    // Grab the core integration settings
    $integration = H5PDrupal::getGenericH5PIntegrationSettings();

    // Add content specific settings
    $content_id_string = 'cid-' . $id;
    $integration['contents'][$content_id_string] = $h5p_content->getH5PIntegrationSettings();

    // Load core assets
    $coreAssets = H5PDrupal::getCoreAssets();

    // Load dependencies
    $core = H5PDrupal::getInstance('core');
    $preloaded_dependencies = $core->loadContentDependencies($id, 'preloaded');
    $files = $core->getDependenciesFiles($preloaded_dependencies, H5PDrupal::getRelativeH5PPath());

    // Invoke any alter hooks
    $library_list = [];
    foreach ($preloaded_dependencies as $dependency) {
      $library_list[$dependency['machineName']] = [
        'majorVersion' => $dependency['majorVersion'],
        'minorVersion' => $dependency['minorVersion'],
      ];
    }
    $mode = 'external';
    \Drupal::moduleHandler()->alter('h5p_scripts', $files['scripts'], $library_list, $mode);
    \Drupal::moduleHandler()->alter('h5p_styles', $files['styles'], $library_list, $mode);

    // Load public files
    $jsFilePaths = array_map(function($asset){ return $asset->path; }, $files['scripts']);
    $cssFilePaths = array_map(function($asset){ return $asset->path; }, $files['styles']);

    // Get aggregated assets
    $aggregatedAssets = H5PDrupal::aggregatedAssets([$coreAssets['scripts'], $jsFilePaths], [$coreAssets['styles'], $cssFilePaths]);
    // Merge assets
    $scripts = array_merge($aggregatedAssets['scripts'][0], $aggregatedAssets['scripts'][1]);
    $styles = array_merge($aggregatedAssets['styles'][0], $aggregatedAssets['styles'][1]);

    // Get current language
    $metadata = $h5p_content->getMetadata();
    $lang = isset($metadata['defaultLanguage'])
      ? $metadata['defaultLanguage']
      : \Drupal::languageManager()->getCurrentLanguage()->getId();

    $content = [
      'id' => $id,
      'title' => "H5P Content {$id}",
    ];

    // Render the page and add to the response
    ob_start();
    include InstalledVersions::getInstallPath("h5p/h5p-core") . '/embed.php';
    $response['#markup'] = ob_get_clean();

    return new HtmlResponse($response);
  }

}
