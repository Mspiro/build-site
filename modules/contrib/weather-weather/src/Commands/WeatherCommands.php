<?php

// @codingStandardsIgnoreFile

namespace Drupal\weather\Commands;

use Drush\Commands\DrushCommands;

/**
 * Drush command to add support to weather module (import weather csv file).
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class WeatherCommands extends DrushCommands {

  /**
   * Items from csv.
   *
   * @var array
   */
  private $items;

  /**
   * Import data from .csv to database and log.
   *
   * @usage weather-import
   *   No args needed.
   *
   * @command weather:import
   * @aliases weather-i
   */
  public function import() {
    $this->del()
      ->read()
      ->add()
      ->logger()
      ->success(dt('Ok.'));
  }

  /**
   * Delete.
   */
  private function del(): self {
    $all = $this->store()->loadByProperties(['status' => 'original']);
    $i = 0;
    $c = count($all);
    foreach ($all as $del) {
      $del->delete();
      if ((++$i) % 50 === 0) {
        $this->logger()->info("del $i / $c");
      }
    }

    return $this;
  }

  /**
   * Open file.
   */
  private function csv() {
    return fopen(drupal_get_path('module', 'weather') . '/files/weather_data.csv', 'r');
  }

  /**
   * Save.
   */
  private function store() {
    return \Drupal::service('entity_type.manager')
      ->getStorage('weather_place');
  }

  /**
   * Read.
   */
  private function read(): self {
    $file = $this->csv();
    $i = 0;
    $items = [];
    while (($line = fgetcsv($file, 0, '	')) !== FALSE) {
      $items[] = $line;
      if ((++$i) % 500 === 0) {
        $this->logger()->info("read $i");
      }
    }
    fclose($file);
    $this->items = $items;

    return $this;
  }

  /**
   * Add.
   */
  private function add(): self {
    $s = $this->store();

    $i = 0;
    $c = count($this->items);
    $this->logger()->info("will add: $c");
    foreach ($this->items as $item) {
      $s->create([
        'geoid' => $item[0],
        'latitude' => $item[1],
        'longitude' => $item[2],
        'country' => $item[3],
        'name' => $item[4],
        'link' => trim($item[5]),
        'status' => 'original',
      ])->save();
      if ((++$i) % 50 === 0) {
        $this->logger()->success("add $i / $c");
      }
    }
    return $this;
  }

}
