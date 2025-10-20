<?php

namespace Drupal\moodle_rest_user\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\migrate\Row;

/**
 * Map fields for push to, pull from, Moodle.
 */
class MoodleUserMap extends Event {

  public const string PUSH_EVENT = 'moodle_rest_user.push';
  public const string PULL_EVENT = 'moodle_rest_user.pull';

  /**
   * The Row with the source and destination.
   *
   * Using migrate row here because it would be nice if it could be unpicked
   * to use the process plugins, even the source and destination, to do the
   * work. But it'll take some more thought about how to implement sane
   * MigrationInterface and MigrateExecutableInterface that are needed by and
   * by.
   *
   * What row brings for now is just handling making sure the fields are
   * there and calling the NestedArray functions. Well that and having a source
   * and destination.
   *
   * @var Row
   */
  public Row $row;

  /**
   * The Mapping configuration.
   *
   * @var array
   */
  protected array $config;

  /**
   * Event constructor.
   *
   * @param Row $row
   *   The row being mapped.
   * @param array $config
   *   The mapping configuration.
   */
  public function __construct(Row $row, array $config) {
    $this->row = $row;
    $this->config = $config;
  }

  /**
   * The mapping configuration.
   *
   * @return array
   *   Configuration.
   */
  public function getConfig(): array
  {
    return $this->config;
  }

}
