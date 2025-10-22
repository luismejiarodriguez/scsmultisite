<?php

namespace Drupal\h5p;

use Composer\InstalledVersions;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;

/**
 * Class AssetFileStorage.
 */
class AssetFileStorage {

  use LoggerChannelTrait;

  /**
   * Directory to store all the assets in --`h5p`.
   */
  protected string $assetPrefix;

  /**
   * Drupal's FileSystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  public function __construct(FileSystemInterface $fileSystem) {
    $this->fileSystem = $fileSystem;
    $this->assetPrefix = 'h5p';
  }

  /**
   * Get the URI where the assets will be stored.
   *
   * @return string
   *   Internal URI
   */
  public function getDirectoryUri(): string {
    $directory_uri = "assets://{$this->assetPrefix}/";

    return $directory_uri;
  }

  /**
   * Create file and return internal uri.
   *
   * @return string
   *   Internal file URI using asset:// stream wrapper.
   */
  public function prepareFile(string $internal_uri): string {
    if (!is_file($internal_uri)) {
      $directory = dirname($internal_uri);

      try {
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      }
      catch (\Throwable $e) {
        $this->getLogger('h5p')
          ->error('Failed to create asset file @uri. Ensure directory permissions are correctly configured: @message', [
            '@uri' => $internal_uri,
            '@message' => $e->getMessage(),
          ]);
      }
    }

    return $internal_uri;
  }

  public function mirrorAssets(): void {
    $specifications = [
      'h5p-core' => [
        'directories' => [
          'js',
          'fonts',
          'images',
          'styles',
        ],
      ],
      'h5p-editor' => [
        'directories' => [
          'ckeditor',
          'images',
          'language',
          'libs',
          'scripts',
          'styles/css',
        ],
      ],
    ];
    foreach ($specifications as $project_name => $params) {
      $source_root = InstalledVersions::getInstallPath("h5p/$project_name");

      foreach ($params['directories'] as $directory) {
        $source_path = "$source_root/$directory";
        $files = [];
        $file_sources = $this->fileSystem->scanDirectory($source_path, '/\.(js|css|jpe?g|png|gif|svg|webp|bmp|ico|eot|ttf|woff|woff2)/');

        foreach ($file_sources as $file_source) {
          // The structure of the files array is [source => destination].
          $files[$file_source->uri]
            = $this->getDirectoryUri()
            . substr($file_source->uri,
              // We need to offset the destination path by the length of h5p/ or
              // else all the assets will be double nested in /h5p/h5p.
              (stripos($file_source->uri, "{$this->assetPrefix}/$project_name") + strlen("h5p/"))
            );
        }

        foreach ($files as $source_path => $destination_path) {
          $this->prepareFile($destination_path);
          $this->fileSystem->copy($source_path, $destination_path, fileExists::Replace);

        }
      }
    }
  }

}
