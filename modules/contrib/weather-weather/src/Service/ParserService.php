<?php

namespace Drupal\weather\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\weather\Entity\WeatherForecastInformationInterface;
use Drupal\weather\Entity\WeatherPlaceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Parsing of XML weather forecasts from yr.no.
 */
class ParserService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Weather helper service.
   *
   * @var \Drupal\weather\Service\HelperService
   */
  protected $weatherHelper;

  /**
   * Http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Weather forecast storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherForecastInfoStorage;

  /**
   * Weather forecast storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherForecastStorage;

  /**
   * Weather Places storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherPlaceStorage;

  /**
   * ParserService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\weather\Service\HelperService $weatherHelper
   *   Weather helper service.
   * @param \GuzzleHttp\Client $httpClient
   *   Http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal messenegr service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, HelperService $weatherHelper, Client $httpClient, LoggerChannelFactoryInterface $loggerFactory, AccountProxyInterface $current_user, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->weatherHelper = $weatherHelper;
    $this->httpClient = $httpClient;
    $this->logger = $loggerFactory;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->weatherForecastInfoStorage = $entity_type_manager->getStorage('weather_forecast_information');
    $this->weatherForecastStorage = $entity_type_manager->getStorage('weather_forecast');
    $this->weatherPlaceStorage = $entity_type_manager->getStorage('weather_place');
  }

  /**
   * Downloads a new forecast from yr.no.
   *
   * @param string $geoid
   *   The GeoID for which the forecasts should be downloaded.
   * @param string $url
   *   Full url of the forecast.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function downloadForecast(string $geoid = '', string $url = '') {
    // Specify timeout in seconds.
    $timeout = 10;
    if ($geoid) {
      $url = $this->weatherHelper->getLinkForGeoid($geoid, 'yr');
    }

    $client = $this->httpClient;
    try {
      $response = $client->get($url, ['timeout' => $timeout]);
      // Extract XML data from the received forecast.
      return $this->parseForecast($response->getBody(), $geoid);
    }
    catch (RequestException $e) {
      // Make an entry about this error.
      $this->logger->get('weather')
        ->error($this->t('Download of forecast failed: @error',
          ['@error' => $e->getMessage()]));

      // Show a message to users with administration privileges.
      if ($this->currentUser->hasPermission('administer site configuration')) {
        $this->messenger->addError($this->t('Download of forecast failed: @error', ['@error' => $e->getMessage()]));
      }
    }
  }

  /**
   * Parses an XML forecast supplied by yr.no.
   *
   * @param string $xml
   *   XML to be parsed.
   * @param string $geoid
   *   The GeoID for which the forecasts should be parsed.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function parseForecast($xml, $geoid = '') {
    // In case the parsing fails, do not output all error messages.
    $use_errors = libxml_use_internal_errors(TRUE);
    $fc = simplexml_load_string($xml);
    // Restore previous setting of error handling.
    libxml_use_internal_errors($use_errors);
    if ($fc === FALSE) {
      return FALSE;
    }

    // Update weather_places table with downloaded information, if necessary.
    $this->updatePlaces($fc);

    // Extract meta information.
    // @todo Extract GeoID of returned XML data.
    // This might differ from the data we have in the database. An example
    // was Heraklion (ID 261745), which got the forecast for
    // Nomós Irakleíou (ID 261741).
    if ($geoid == '') {
      $geoid = $fc->location->location['geobase'] . "_" . $fc->location->location['geobaseid'];
    }
    $meta['geoid'] = $geoid;
    $meta['utc_offset'] = (int) $fc->location->timezone['utcoffsetMinutes'];
    // Calculate the UTC time.
    $utctime = strtotime((string) $fc->meta->lastupdate . ' UTC') - 60 * $meta['utc_offset'];
    $meta['last_update'] = gmdate('Y-m-d H:i:s', $utctime);
    // Calculate the UTC time.
    $utctime = strtotime((string) $fc->meta->nextupdate . ' UTC') - 60 * $meta['utc_offset'];
    $meta['next_update'] = gmdate('Y-m-d H:i:s', $utctime);
    $meta['next_download_attempt'] = $meta['next_update'];
    // Write/Update information for this location.
    $forecastInfo = $this->weatherForecastInfoStorage->load($meta['geoid']);
    if ($forecastInfo instanceof WeatherForecastInformationInterface) {
      foreach ($meta as $field => $value) {
        $forecastInfo->{$field} = $value;
      }
      $forecastInfo->save();
    }
    else {
      $this->weatherForecastInfoStorage->create($meta)->save();
    }

    // Remove all forecasts for this location.
    $outdated = $this->weatherForecastStorage->loadByProperties(['geoid' => $meta['geoid']]);
    $this->weatherForecastStorage->delete($outdated);

    // Cycle through all forecasts and write them to the table.
    foreach ($fc->forecast->tabular->time as $time) {
      $forecast = [];
      $forecast['geoid'] = $meta['geoid'];
      $forecast['time_from'] = str_replace('T', ' ', (string) $time['from']);
      $forecast['time_to'] = str_replace('T', ' ', (string) $time['to']);
      $forecast['period'] = (string) $time['period'];
      $forecast['symbol'] = (string) $time->symbol['var'];
      // Remove moon phases, which are not supported.
      // This is in the format "mf/03n.56", where 56 would be the
      // percentage of the moon phase.
      if (strlen($forecast['symbol']) > 3) {
        $forecast['symbol'] = substr($forecast['symbol'], 3, 3);
      }
      $forecast['precipitation'] = (float) $time->precipitation['value'];
      $forecast['wind_direction'] = (int) $time->windDirection['deg'];
      $forecast['wind_speed'] = (float) $time->windSpeed['mps'];
      $forecast['temperature'] = (int) $time->temperature['value'];
      $forecast['pressure'] = (int) $time->pressure['value'];

      // Create Forecast if not exists.
      $fcExists = $this->weatherForecastStorage->loadByProperties(
        [
          'geoid' => $meta['geoid'],
          'time_from' => $forecast['time_from'],
        ]
      );
      if (!$fcExists) {
        $this->weatherForecastStorage->create($forecast)->save();
      }
    }
    return TRUE;
  }

  /**
   * Handle updates to the weather_places entity.
   */
  protected function updatePlaces($fc) {
    // Extract GeoID and latitude/longitude of returned XML data.
    // This might differ from the data we have in the database. An example
    // was Heraklion (ID 261745), which got the forecast for
    // Nomós Irakleíou (ID 261741).
    // Data to extract are:
    // geoid, latitude, longitude, country, name.
    // @codingStandardsIgnoreStart
    $place['geoid'] = $fc->location->location['geobase'] . "_" . $fc->location->location['geobaseid'];
    $place['latitude'] = (string) $fc->location->location['latitude'];
    $place['latitude'] = round($place['latitude'], 5);
    $place['longitude'] = (string) $fc->location->location['longitude'];
    $place['longitude'] = round($place['longitude'], 5);
    $place['country'] = (string) $fc->location->country;
    $place['name'] = (string) $fc->location->name;
    $url = (string) $fc->credit->link['url'];

    [$country, $link] = $this->weatherHelper->parsePlaceUrl($url);
    $place['link'] = $link;
    // @codingStandardsIgnoreEnd

    // Fetch stored information about geoid.
    $existingPlace = $this->weatherPlaceStorage->load($place['geoid']);

    // If the geoid is not in the database, add it.
    if (!$existingPlace) {
      $place['status'] = 'added';
      $this->weatherPlaceStorage->create($place)->save();
    }
    else {
      // Compare the stored information with the downloaded information.
      // If they differ, update the database.
      $modified = FALSE;
      foreach ($place as $field => $value) {
        $existingValue = $existingPlace->{$field}->value;
        if ($existingPlace->hasField($field) && $existingValue != $value) {
          $existingPlace->{$field} = $value;
          $modified = TRUE;
        }
      }
      if ($modified) {
        $existingPlace->status = WeatherPlaceInterface::STATUS_MODIFIED;
        $existingPlace->save();
      }
    }
  }

  /**
   * Try to fetch forecasts from the database.
   *
   * @param string $geoid
   *   GeoID of the place for which the weather is desired.
   * @param string $utc_offset
   *   UTC offset of place in minutes.
   * @param int $days
   *   Return weather for specified number of days (0 = all available days).
   * @param bool $detailed
   *   Return detailed forecasts or just one forecast per day.
   * @param int $time
   *   Timestamp for which the weather should be returned. This is only
   *   needed to enable proper testing of the module.
   *
   * @return array
   *   Weather array with forecast information.
   */
  public function getForecastsFromDatabase($geoid, $utc_offset, $days, $detailed, $time) {
    // Fetch the first forecast. This must be done separately, because
    // sometimes the first forecast is already on the next day (this can
    // happen e.g. late in the evenings). Otherwise, the calculation of
    // 'tomorrow' would fail.
    $current_local_time = gmdate('Y-m-d H:i:s', $time + $utc_offset * 60);

    $first_forecast = $this->weatherForecastStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('geoid', $geoid)
      ->condition('time_to', $current_local_time, '>=')
      ->sort('time_from', 'ASC')
      ->range(0, 1)
      ->execute();

    // If there are no forecasts available, return an empty array.
    if (!$first_forecast) {
      return [];
    }
    $first_forecast = $this->weatherForecastStorage->load(reset($first_forecast));

    $weather = $this->createWeatherArray([$first_forecast]);

    // Calculate tomorrow based on result.
    $first_forecast_day = explode('-', key($weather));
    $tomorrow_local_time = gmdate('Y-m-d H:i:s',
      gmmktime(0, 0, 0, $first_forecast_day[1], $first_forecast_day[2] + 1, $first_forecast_day[0])
    );
    $forecasts_until_local_time = gmdate('Y-m-d 23:59:59',
      gmmktime(23, 59, 59, $first_forecast_day[1], $first_forecast_day[2] + $days - 1, $first_forecast_day[0])
    );

    $query = $this->weatherForecastStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('geoid', $geoid);

    if ($detailed) {
      $query->condition('time_to', $current_local_time, '>=');
      // Fetch all available forecasts.
      if ($days > 0) {
        $query->condition('time_from', $forecasts_until_local_time, '<=');
      }
      $query->sort('time_from', 'ASC');

      $forecasts = $query->execute();
      $forecasts = $this->weatherForecastStorage->loadMultiple($forecasts);
      $weather = $this->createWeatherArray($forecasts);
    }
    elseif ($days > 1 || $days == 0) {
      $query->condition('time_from', $tomorrow_local_time, '>=');
      $query->condition('period', '2');

      if ($days > 1) {
        $query->condition('time_from', $forecasts_until_local_time, '<=');
      }

      $query->sort('time_from', 'ASC');

      $forecasts = $query->execute();
      $forecasts = $this->weatherForecastStorage->loadMultiple($forecasts);
      $weather = array_merge($weather, $this->createWeatherArray($forecasts));
    }

    return $weather;
  }

  /**
   * Create a weather array with the forecast data from database.
   *
   * @param array $forecasts
   *   Raw forecast data from database.
   *
   * @return array
   *   Weather array with forecast information.
   */
  protected function createWeatherArray(array $forecasts) {
    $weather = [];
    $day_from = '';
    $time_from = '';
    $day_to = '';
    $time_to = '';
    // Cycle through all forecasts and set up a hierarchical array structure.
    foreach ($forecasts as $forecast) {
      [$day_from, $time_from] = explode(' ', $forecast->time_from->value);
      $time_range = substr($time_from, 0, 5);
      [$day_to, $time_to] = explode(' ', $forecast->time_to->value);
      $time_range .= '-' . substr($time_to, 0, 5);
      // @todo Refactor condition below and $day_to var if it will not needed.
      if ($day_to === $day_from) {
        unset($day_to);
      }
      $weather[$day_from][$time_range] = [
        'period' => $forecast->period->value,
        'symbol' => $forecast->symbol->value,
        'precipitation' => $forecast->precipitation->value,
        'wind_direction' => $forecast->wind_direction->value,
        'wind_speed' => $forecast->wind_speed->value,
        'temperature' => $forecast->temperature->value,
        'pressure' => $forecast->pressure->value,
      ];
    }
    return $weather;
  }

  /**
   * Returns a weather object for the specified GeoID.
   *
   * @param string $geoid
   *   GeoID of the place for which the weather is desired.
   * @param int $days
   *   Return weather for specified number of days (0 = all available days).
   * @param bool $detailed
   *   Return detailed forecasts or just one forecast per day.
   *
   * @return array
   *   Weather array with forecast information.
   */
  public function getWeather($geoid, $days = 0, $detailed = TRUE) {
    // We need this variable for tests in future.
    $time = time();

    // Make sure weather forecast for this GeoID exists in DB.
    $result = $this->downloadWeather($geoid, $time);

    // Get weather from DB.
    $weather['forecasts'] = $this->getForecastsFromDatabase($geoid, $result['utc_offset'], $days, $detailed, $time);
    $weather['utc_offset'] = $result['utc_offset'];

    return $weather;
  }

  /**
   * Downloads forecast from yr.no and puts it do DB.
   */
  protected function downloadWeather(string $geoid, int $time): array {
    $currentUtcTime = gmdate('Y-m-d H:i:s');
    $meta = [
      'geoid' => $geoid,
      'last_update' => $currentUtcTime,
      'next_update' => $currentUtcTime,
      'next_download_attempt' => $currentUtcTime,
      'utc_offset' => 0,
    ];

    // Get the scheduled time of next update. If there is no entry for
    // the specified GeoID, $meta will have default values.
    $forecastInfo = $this->weatherForecastInfoStorage->load($geoid);

    if ($forecastInfo instanceof WeatherForecastInformationInterface) {
      // Update $meta with DB record.
      foreach ($meta as $key => $value) {
        if ($key != 'geoid' && $forecastInfo->hasField($key) && !$forecastInfo->{$key}->isEmpty()) {
          $meta[$key] = $forecastInfo->{$key}->value;
        }
      }
    }

    // If the next scheduled download is due, try to get forecasts.
    if ($currentUtcTime >= $meta['next_download_attempt']) {
      $result = $this->downloadForecast($geoid);
      if (!$result) {
        $this->setNextAttempt($meta, $time);
      }
      else {
        // If that worked, get the next scheduled update time.
        $forecastInfo = $this->weatherForecastInfoStorage->load($geoid);

        // If the download did succeed, but
        // the returned forecast is old and the next update is overdue.
        // In that case, the download attempt needs to wait as well,
        // otherwise, a new download will occur on every page load.
        $newNextUpdate = $forecastInfo->next_update->value;
        if ($currentUtcTime >= $newNextUpdate) {
          // Update $meta with DB record.
          foreach ($meta as $key => $value) {
            if ($key != 'geoid' && $forecastInfo->hasField($key) && !$forecastInfo->{$key}->isEmpty()) {
              $meta[$key] = $forecastInfo->{$key}->value;
            }
          }

          $this->setNextAttempt($meta, $time);
        }
      }
    }

    return $meta;
  }

  /**
   * Sets time for next download attempt.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setNextAttempt($meta, $time) {
    // The download did not succeed. Set next download attempt accordingly.
    // Calculate the UTC timestamp.
    $next_update = strtotime($meta['next_update'] . ' UTC');
    // Initial retry after 675 seconds (11.25 minutes).
    // This way, the doubling on the first day leads to exactly 86400
    // seconds (one day) update interval.
    $seconds_to_retry = 675;
    while (($next_update + $seconds_to_retry) <= $time) {
      if ($seconds_to_retry < 86400) {
        $seconds_to_retry = $seconds_to_retry * 2;
      }
      else {
        $seconds_to_retry = $seconds_to_retry + 86400;
      }
    }

    // Finally, calculate the UTC time of the next download attempt.
    $meta['next_download_attempt'] = gmdate('Y-m-d H:i:s', $next_update + $seconds_to_retry);

    $forecastInfo = $this->weatherForecastInfoStorage->load($meta['geoid']);
    if ($forecastInfo) {
      $this->weatherForecastInfoStorage->delete([$forecastInfo]);
    }
    $this->weatherForecastInfoStorage->create($meta)->save();
  }

}
