<?php

namespace Drupal\moodle_sync\Service;

use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\moodle_sync_category\Utility\MoodleApiHelper;

class MoodleSyncLogger {

  protected $config;

  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('moodle_sync.settings');
  }

  /**
   * Writes logfiles, dblog and shows message to user.
   *
   * @param string $message Message to show.
   * @param string $type Type of message (error, warning or status).
   * @param string $function webservice function that was called.
   * @param string $params url params.
   * @param string $id Drupal entity id.
   * @param string $moodle_id Moodle component id.
   *
   */
  public function log($message, $type, $function = null, $params = null, $id = null, $moodle_id = null) {

    // Get config.
    $config = \Drupal::config('moodle_sync.settings');

    // Log to file. //TODO: do we still need this?
    if ($config->get('log_file')) {

      // Set and create directory for logfiles.
      $directory = 'private://moodle_sync';
      if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY && FileSystemInterface::MODIFY_PERMISSIONS)) {
        \Drupal::messenger()->addError(t('directory not writable: @directory', ['@directory' => $directory]));
        return;
      }
      $realpath = \Drupal::service('file_system')->realpath($directory);
      $datestamp = date("Y-m");
      $logfile = "$realpath/log_$datestamp.txt";
      $tab = '	';

      // Write header if file doesnt exist yet.
      if (!file_exists($logfile)) {
        $line = 'date	type	message	function	params	drupal id	moodle id' . PHP_EOL;
        $file = fopen($logfile, 'a');
        fwrite($file, $line);
        fclose($file);
      }

      // Write to log file.
      $date = date("Y-m-d H:i:s");
      $line = "$date	$type	$message	$function	$params	$id	$moodle_id"	. PHP_EOL;
      $file = fopen($logfile, 'a');
      fwrite($file, strip_tags($line));
      fclose($file);
    }

    // Drupal logging.
    if ($config->get('log_drupal')) {
      \Drupal::logger('moodle_sync')->$type("$message (function: $function, params: $params)");
    }

    // Onscreen message.
    if ($config->get('log_onscreen')) {
      if ($type == 'info') {
        $type = 'status';
      }
      \Drupal::messenger()->addMessage($message, $type);
    }
  }
}
