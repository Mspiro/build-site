<?php

namespace Drupal\weather\Service;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\weather\Entity\WeatherPlaceInterface;

/**
 * Installation, update and removal of weather places.
 */
class DataService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $placeStorage;

  /**
   * Batch Builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  protected $batchBuilder;

  /**
   * DataService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->placeStorage = $entity_type_manager->getStorage('weather_place');
    $this->batchBuilder = new BatchBuilder();
  }

  /**
   * Helper function for installation and upgrades.
   *
   * This function inserts data into the weather_place table.
   *
   * The data file lists one table entry per line. Fields are separated
   * by tab stops. The format is as follows:
   *
   * GeoID
   *   Primary key, string. Currently supported keys are:
   *   - GeoNames ID, prefixed with geonames_
   *   - Sentralt stadnamnregister ID, prefixed with ssr_
   *   (tab stop)
   * Latitude
   *   -90 .. 90, Positive values for north, negative for south.
   *   (tab stop)
   * Longitude
   *   -180 .. 180, Positive values for east, negative for west.
   *   (tab stop)
   * Country
   *   (tab stop)
   * Name
   *   (tab stop)
   * Link to yr.no weather forecast
   *   (newline)
   *
   * To save space, the link to the actual yr.no URL has been shortened.
   * Every URL starts with "http://www.yr.no/place/", followed by the
   * country.
   *
   * In the country's name, every whitespace gets replaced with an
   * underscore, all other characters are kept as they are. The only
   * exception is the last dot in the following country:
   *
   * "Virgin Islands, U.S." -> "Virgin_Islands,_U.S_"
   */
  public function weatherDataInstallation() {
    // Delete all original entries from the table, if any.
    $deleteIds = $this->placeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', WeatherPlaceInterface::STATUS_ORIGINAL)
      ->execute();

    // Get all remaining entries in the table.
    $changed_geoids = $this->placeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', WeatherPlaceInterface::STATUS_ORIGINAL, '<>')
      ->execute();

    // Read the data file and create Places in system.
    $file = fopen(drupal_get_path('module', 'weather') . '/files/weather_data.csv', 'r');
    $items = [];
    while (($line = fgetcsv($file, 0, '	')) !== FALSE) {
      // Check if the geoid has been modified, if so, skip it.
      if (!in_array($line[0], $changed_geoids)) {
        $items[] = $line;
      }
    }
    fclose($file);

    $op = 'Importing';
    if ($deleteIds) {
      $op = 'Re-importing';
      $this->batchBuilder->addOperation([
        self::class,
        'processBatch',
      ], [array_values($deleteIds), 'remove']);
    }
    $this->batchBuilder->addOperation([self::class, 'processBatch'], [
      $items,
      'add',
    ]);

    $this->batchBuilder
      ->setTitle($this->t('@operation weather places', ['@operation' => $op]))
      ->setInitMessage($this->t('Weather places import is starting.'))
      ->setProgressMessage($this->t('Processed @current out of @total.'))
      ->setErrorMessage($this->t('Weather places import has encountered an error.'))
      ->setFinishCallback([self::class, 'finishBatch']);

    batch_set($this->batchBuilder->toArray());
  }

  /**
   * Batch process callback.
   */
  public static function processBatch($items, $operation, &$context) {
    $placeStorage = \Drupal::entityTypeManager()->getStorage('weather_place');

    // Use the $context['sandbox'] at your convenience to store the
    // information needed to track progression between successive calls.
    if (empty($context['sandbox'])) {
      $context['sandbox'] = [];
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($items);
    }

    $limit = 50;

    // Retrieve the next group.
    $range = array_slice($items, $context['sandbox']['progress'], $limit);

    foreach ($range as $item) {
      $id = $operation == 'add' ? $item[0] : $item;

      if ($operation == 'add') {
        $placeStorage->create([
          'geoid' => $item[0],
          'latitude' => $item[1],
          'longitude' => $item[2],
          'country' => $item[3],
          'name' => $item[4],
          'link' => trim($item[5]),
          'status' => WeatherPlaceInterface::STATUS_ORIGINAL,
        ])->save();
      }
      elseif ($operation == 'remove') {
        $placeStorage->load($item)->delete();
      }

      $context['message'] = t('@op @current out of @total weather places. @details',
        [
          '@op' => $operation == 'add' ? 'Imported' : 'Deleted',
          '@current' => $context['sandbox']['progress'],
          '@total' => $context['sandbox']['max'],
          '@details' => $id,
        ]
      );
      $context['results'][] = $id;
      $context['sandbox']['progress']++;
    }

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch finish callback.
   */
  public static function finishBatch($success, $results, $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addMessage(t('@count items processed.', ['@count' => count($results)]));
    }
    else {
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
