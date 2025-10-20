<?php

namespace Drupal\h5p\Plugin\Field\FieldFormatter;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'h5p_default' formatter.
 *
 * @FieldFormatter(
 *   id = "h5p_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "h5p"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class H5PDefaultFormatter extends FormatterBase {

  /**
   * The h5p settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected ModuleHandlerInterface $moduleHandler,
    protected AssetResolverInterface $assetResolver,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->config = $config_factory->get('h5p.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('module_handler'),
      $container->get('asset.resolver'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = t('Displays interactive H5P content.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();

    foreach ($items as $delta => $item) {
      $value = $item->getValue();

      // Load H5P Content entity
      $h5p_content = H5PContent::load($value['h5p_content_id']);
      if (empty($h5p_content)) {
        continue;
      }

      // Grab generic integration settings
      $h5p_integration = H5PDrupal::getGenericH5PIntegrationSettings();

      // Add content specific settings
      $content_id_string = 'cid-' . $h5p_content->id();
      $h5p_integration['contents'][$content_id_string] = $h5p_content->getH5PIntegrationSettings($item->getEntity()->access('update'));

      $core = H5PDrupal::getInstance('core');
      $preloaded_dependencies = $core->loadContentDependencies($h5p_content->id(), 'preloaded');

      $loadpackages = [
        'h5p/h5p.content',
      ];

      // Get the H5P interactive's dependencies.
      $h5p_packages = [];
      foreach ($preloaded_dependencies as $dependency) {
        $h5p_packages[] = 'h5p/' . _h5p_library_machine_to_id($dependency);
      }

      // Determine embed type and HTML to use
      if ($h5p_content->isDivEmbeddable()) {
        // For div embed, merge the H5P packages into the load packages, since
        // in this mode we can attach everything to the render array.
        $loadpackages = array_merge($loadpackages, $h5p_packages);
        $html = '<div class="h5p-content" data-content-id="' . $h5p_content->id() . '"></div>';
      }
      // The iframe mode requires more careful handling of the JS/CSS.
      else {
        // Use the asset manager to get a full list of the JS/CSS needed by
        // this interactive.
        $assets = new AttachedAssets();
        $assets->setLibraries($h5p_packages);
        // Exclude 'h5p.content' because we only want library files specific to
        // this interactive.
        $assets->setAlreadyLoadedLibraries(['h5p/h5p.content']);

        // Get all the libraries and dependencies, but without aggregation.
        // The h5p.js iframe initializer expects an array of JS/CSS and some
        // H5P interactives depend on each individual library being listed.
        $scripts = $this->assetResolver->getJsAssets($assets, FALSE);
        $styles = $this->assetResolver->getCssAssets($assets, FALSE);

        // set html
        $metadata = $h5p_content->getMetadata();
        $language = isset($metadata['defaultLanguage'])
          ? $metadata['defaultLanguage']
          : 'en';
        $title = isset($metadata['title'])
          ? $metadata['title']
          : 'H5P content';

        $html = '<div class="h5p-iframe-wrapper"><iframe id="h5p-iframe-' . $h5p_content->id() . '" class="h5p-iframe" data-content-id="' . $h5p_content->id() . '" style="height:1px" frameBorder="0" scrolling="no" lang="' . $language . '" title="' . $title . '"></iframe></div>';

        [$header_js, $footer_js] = $scripts;
        $all_js = array_merge($header_js, $footer_js);
        $file_url_generator = \Drupal::service('file_url_generator');
        foreach ($all_js as $asset) {
          // Ignore any settings that might come from the libraries since H5P
          // will ignore them anyway.
          if (is_string($asset['data'])) {
            $jsFilePaths[] = $file_url_generator->generateAbsoluteString($asset['data']);
          }
        }

        foreach ($styles as $style) {
          $cssFilePaths[] = $file_url_generator->generateAbsoluteString($style['data']);
        }

        $files = array();
        \Drupal::moduleHandler()->alter('h5p_scripts', $files['scripts'], $loadpackages, $h5p_content->getLibrary()->embed_types);
        \Drupal::moduleHandler()->alter('h5p_styles', $files['styles'], $loadpackages, $h5p_content->getLibrary()->embed_types);
        if($files['scripts']) {
          $jsCustomFilePaths = array_map(function($asset){ return $asset->path; }, $files['scripts']);
          $aggregatedJS = H5PDrupal::aggregatedAssets([$jsCustomFilePaths], []);
          $jsFilePaths = array_merge($jsFilePaths, $aggregatedJS['scripts'][0]);
        }
        if($files['styles']) {
          $cssCustomFilePaths = array_map(function($asset){ return $asset->path; }, $files['styles']);
          $aggregatedCSS = H5PDrupal::aggregatedAssets([], [$cssCustomFilePaths]);
          $cssFilePaths = array_merge($cssFilePaths, $aggregatedCSS['styles'][0]);
        }

        // Load core assets
        $coreAssets = H5PDrupal::getCoreAssets();
        foreach ($coreAssets as $type => $core_assets) {
          foreach ($core_assets as $key => $value) {
            $coreAssets[$type][$key] = $file_url_generator->generateAbsoluteString($value);
          }
        }

        $h5p_integration['core']['scripts'] = $coreAssets['scripts'];
        $h5p_integration['core']['styles'] = $coreAssets['styles'];
        $h5p_integration['contents'][$content_id_string]['scripts'] = $jsFilePaths;
        $h5p_integration['contents'][$content_id_string]['styles'] = $cssFilePaths;
      }

      // Render each element as markup.
      $element[$delta] = array(
        '#type' => 'markup',
        '#markup' => $html,
        '#allowed_tags' => ['div','iframe'],
        '#attached' => [
          'drupalSettings' => [
            'h5p' => [
              'H5PIntegration' => $h5p_integration,
            ]
          ],
          'library' => $loadpackages,
        ],
      );

      // Collect the cacheability metadata for the element.
      $cacheability = CacheableMetadata::createFromObject($h5p_content)
        ->addCacheTags(['h5p_content'])
        ->addCacheTags($this->config->getCacheTags());
      if (H5PDrupal::getInstance()->getOption('save_content_state', FALSE)) {
        $cacheability->addCacheContexts(['user']);
      }

      // H5P core uses a hash token that becomes invalid between 12 and 24
      // hours. To be sure the cache doesn't serve an out-of-date token, set
      // the max age to 12 hours.
      $cacheability->setCacheMaxAge(12 * 60 * 60);

      $cacheability->applyTo($element[$delta]);
    }
    $this->moduleHandler->alter('h5p_formatter', $element, $items);

    return $element;
  }

}
