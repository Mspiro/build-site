<?php

namespace Drupal\Tests\weather\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\weather\Entity\WeatherPlaceInterface;

/**
 * Tests parsing of XML weather forecasts.
 *
 * @group Weather
 */
class ParserTest extends BrowserTestBase {

  use WeatherCommonTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['weather'];

  /**
   * The tests don't need markup, so use 'stark' as theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

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
  private function weatherGetForecastsFromDatabase($geoid, $utc_offset, $days, $detailed, $time) {
    // Fetch the first forecast. This must be done separately, because
    // sometimes the first forecast is already on the next day (this can
    // happen e.g. late in the evenings). Otherwise, the calculation of
    // 'tomorrow' would fail.
    $current_local_time = gmdate('Y-m-d H:i:s', $time + $utc_offset * 60);

    $connection = \Drupal::database();
    $query = $connection->select('weather_forecast', 'wfi');
    $query->condition('wfi.geoid', $geoid, '=');
    $query->condition('wfi.time_to', $current_local_time, '>=');
    $query->fields('wfi', [
      'geoid',
      'time_from',
      'time_to',
      'period',
      'symbol',
      'precipitation',
      'wind_direction',
      'wind_speed',
      'temperature',
      'pressure',
    ]);
    $query->range(0, 50);
    $result = $query->execute();
    $first_forecast = $result->fetchAll();

    // If there are no forecasts available, return an empty array.
    if ($first_forecast === FALSE) {
      return [];
    }
    $weather = $this->weatherCreateWeatherArray([$first_forecast]);
    // Calculate tomorrow based on result.
    $first_forecast_day = explode('-', key($weather));
    $tomorrow_local_time = gmdate('Y-m-d H:i:s',
      gmmktime(0, 0, 0, $first_forecast_day[1], $first_forecast_day[2] + 1,
        $first_forecast_day[0])
    );
    $forecasts_until_local_time = gmdate('Y-m-d 23:59:59',
      gmmktime(23, 59, 59, $first_forecast_day[1],
        $first_forecast_day[2] + $days - 1, $first_forecast_day[0])
    );
    if ($detailed) {
      // Fetch all available forecasts.
      if ($days > 0) {
        $query = $connection->select('weather_forecast', 'wfi');
        $query->condition('wfi.geoid', $geoid, '=');
        $query->condition('wfi.time_to', $forecasts_until_local_time, '>=');
        $query->fields('wfi', [
          'geoid',
          'time_from',
          'time_to',
          'period',
          'symbol',
          'precipitation',
          'wind_direction',
          'wind_speed',
          'temperature',
          'pressure',
        ]);
        $query->range(0, 50);
        $result = $query->execute();
        $forecasts = $result->fetchAll();
      }
      else {
        $query = $connection->select('weather_forecast', 'wfi');
        $query->condition('wfi.geoid', $geoid, '=');
        $query->condition('wfi.time_to', $current_local_time, '>=');
        $query->fields('wfi', [
          'geoid',
          'time_from',
          'time_to',
          'period',
          'symbol',
          'precipitation',
          'wind_direction',
          'wind_speed',
          'temperature',
          'pressure',
        ]);
        $query->range(0, 50);
        $result = $query->execute();
        $forecasts = $result->fetchAll();
      }

      $weather = $this->weatherCreateWeatherArray($forecasts);
    }
    else {
      if ($days > 1) {
        $query = $connection->select('weather_forecast', 'wfi');
        $query->condition('wfi.geoid', $geoid, '=');
        $query->condition('wfi.time_from', $tomorrow_local_time, '>=');
        $query->condition('wfi.time_to', $forecasts_until_local_time, '>=');
        $query->fields('wfi', [
          'geoid',
          'time_from',
          'time_to',
          'period',
          'symbol',
          'precipitation',
          'wind_direction',
          'wind_speed',
          'temperature',
          'pressure',
        ]);
        $query->range(0, 50);
        $result = $query->execute();
        $forecasts = $result->fetchAll();

        $weather = array_merge($weather,
          $this->weatherCreateWeatherArray($forecasts));

      }
      elseif ($days == 0) {

        $query = $connection->select('weather_forecast', 'wfi');
        $query->condition('wfi.geoid', $geoid, '=');
        $query->condition('wfi.time_from', $tomorrow_local_time, '>=');
        $query->fields('wfi', [
          'geoid',
          'time_from',
          'time_to',
          'period',
          'symbol',
          'precipitation',
          'wind_direction',
          'wind_speed',
          'temperature',
          'pressure',
        ]);
        $query->range(0, 50);
        $result = $query->execute();
        $forecasts = $result->fetchAll();

        $weather = array_merge($weather,
          $this->weatherCreateWeatherArray($forecasts));
      }
    }
    return $weather;
  }

  /**
   * Handle updates to the weather_places table.
   */
  public function weatherUpdatePlaces($fc) {
    // Extract GeoID and latitude/longitude of returned XML data.
    // This might differ from the data we have in the database. An example
    // was Heraklion (ID 261745), which got the forecast for
    // Nomós Irakleíou (ID 261741).
    // Data to extract are:
    // geoid, latitude, longitude, country, name.
    $place['geoid'] = $fc->location->location['geobase'] . "_" . $fc->location->location['geobaseid'];
    $place['latitude'] = (string) $fc->location->location['latitude'];
    $place['latitude'] = round($place['latitude'], 5);
    $place['longitude'] = (string) $fc->location->location['longitude'];
    $place['longitude'] = round($place['longitude'], 5);
    $place['country'] = (string) $fc->location->country;
    $place['name'] = (string) $fc->location->name;
    $url = (string) $fc->credit->link['url'];
    // Remove "http://www.yr.no/place/" from the URL.
    // fixme: not reliable in case we have https;//... at the beginning.
    $link = substr($url, 23);
    // Split by slashes and remove country (first item)
    // and "forecast.xml" (last item)
    $link_parts = explode('/', $link);
    // Remove country.
    array_shift($link_parts);
    // Remove "forecast.xml".
    array_pop($link_parts);
    $place['link'] = implode('/', $link_parts);
    // Fetch stored information about geoid.
    $info = $this->weatherGetInformationAboutGeoid($place['geoid']);
    // If the geoid is not in the database, add it.
    if ($info === FALSE) {
      $place['status'] = 'added';

      // Insert the record to table.
      \Drupal::database()->insert('weather_place')
        ->fields($place)
        ->execute();
    }
    else {
      // Compare the stored information with the downloaded information.
      // If they differ, update the database.
      $stored_info = (array) $info;
      unset($stored_info['status']);
      $diff = array_diff_assoc($stored_info, $place);
      if (!empty($diff)) {
        $place['status'] = WeatherPlaceInterface::STATUS_MODIFIED;

        \Drupal::database()->update('weather_place')
          ->condition('geoid', $place['geoid'])
          ->updateFields($place)
          ->execute();
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
   * @throws \Exception
   */
  public function weatherParseForecast($xml, $geoid = '') {
    // In case the parsing fails, do not output all error messages.
    $use_errors = libxml_use_internal_errors(TRUE);
    $fc = simplexml_load_string($xml);
    // Restore previous setting of error handling.
    libxml_use_internal_errors($use_errors);
    if ($fc === FALSE) {
      return FALSE;
    }
    // Update weather_places table with downloaded information, if necessary.
    $this->weatherUpdatePlaces($fc);
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
    // Merge meta information for this location.
    // This prevents an integrity constraint violation, if multiple
    // calls to this function occur at the same time. See bug #1412352.
    //
    \Drupal::database()->merge('weather_forecast_information')
      ->key(['geoid' => $meta['geoid']])
      ->insertFields($meta)
      ->updateFields($meta)->execute();

    // Remove all forecasts for this location.
    \Drupal::database()->delete('weather_forecast')
      ->condition('geoid', $meta['geoid'])
      ->execute();

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

      // Use db_merge to prevent integrity constraint violation, see above.
      \Drupal::database()->merge('weather_forecast')
        ->key([
          'geoid' => $meta['geoid'],
          'time_from' => $forecast['time_from'],
        ])
        ->insertFields($forecast)
        ->updateFields($forecast)->execute();
    }
    return TRUE;
  }

  /**
   * Downloads a new forecast from yr.no.
   *
   * @param string $geoid
   *   The GeoID for which the forecasts should be downloaded.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  private function weatherDownloadForecast($geoid) {
    // Do not download anything if the variable
    // 'weather_time_for_testing' is set.
    // In this case, we are in testing mode and only load defined
    // forecasts to get always the same results.
    $config = \Drupal::configFactory()->getEditable('weather.settings');
    $time = $config->get('weather_time_for_testing');
    if ($time !== \Drupal::time()->getRequestTime()) {
      $path = drupal_get_path('module', 'weather') . '/tests/src/Functional/data/' . $geoid . '.xml';
      if (is_readable($path)) {
        $xml = file_get_contents($path);
      }
      else {
        $xml = '';
      }
      return $this->weatherParseForecast($xml, $geoid);
    }
    // Specify timeout in seconds.
    $timeout = 10;

    $url = $this->weatherGetLinkForGeoId($geoid, 'yr');
    $response = $this->drupalGet($url, ['timeout' => $timeout]);
    // Extract XML data from the received forecast.
    if (!isset($response->error)) {
      return $this->weatherParseForecast($response->data, $geoid);
    }
    else {
      // Make an entry about this error into the watchdog table.
      \Drupal::logger('weather')->error($response->error);
      // Get the current user.
      $user = \Drupal::currentUser();
      // Check for permission.
      $user->hasPermission('administer site configuration');
      // Show a message to users with administration priviledges.
      if ($user->hasPermission('administer custom weather block') or $user->hasPermission('administer site configuration')) {
        \Drupal::messenger()->addMessage('Download of forecast failed:' . $response->error);
      }
    }
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
   *
   * @throws \Exception
   */
  private function weatherGetWeather($geoid, $days = 0, $detailed = TRUE) {

    // Support testing of module with fixed times instead of current time.
    $config = \Drupal::configFactory()->getEditable('weather.settings');
    $time = $config->get('weather_time_for_testing');

    // Get the scheduled time of next update. If there is no entry for
    // the specified GeoID, $meta will be FALSE.
    $query = \Drupal::database()->select('weather_forecast_information', 'wfi');
    $query->fields('wfi', ['geoid']);
    $query->condition('wfi.geoid', $geoid, '=');
    $meta = $query->execute();
    $meta = $meta->fetchAssoc();

    $current_utc_time = gmdate('Y-m-d H:i:s', $time);
    // If the next scheduled download is due, try to get forecasts.
    if (!empty($meta)) {
      $meta['next_download_attempt'] = $time;
    }
    if (($meta === FALSE) or ($current_utc_time >= $meta['next_download_attempt'])) {
      $result = $this->weatherDownloadForecast($geoid);
      // If that worked, get the next scheduled update time.
      $query = \Drupal::database()
        ->select('weather_forecast_information', 'wfi');
      $query->fields('wfi', ['geoid']);
      $query->condition('wfi.geoid', $geoid, '=');
      $meta = $query->execute();
      $meta = $meta->fetchAssoc();

      // If there is no entry yet, set up initial values.
      if ($meta === FALSE) {
        $meta['geoid'] = $geoid;
        $meta['last_update'] = $current_utc_time;
        $meta['next_update'] = $current_utc_time;
        $meta['next_download_attempt'] = $current_utc_time;
        $meta['utc_offset'] = 120;
      }
      if (empty($meta['next_update'])) {
        $meta['next_update'] = $time;
      }
      // The second check is needed if the download did succeed, but
      // the returned forecast is old and the next update is overdue.
      // In that case, the download attempt needs to wait as well,
      // otherwise, a new download will occur on every page load.
      if (($result == FALSE) || ($current_utc_time >= $meta['next_update'])) {
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
        $meta['next_download_attempt'] = gmdate('Y-m-d H:i:s',
          $next_update + $seconds_to_retry);

        \Drupal::database()->delete('weather_forecast_information')
          ->condition('geoid', $meta['geoid'])
          ->execute();

        // Write new entry.
        \Drupal::database()->insert('weather_forecast_information')
          ->fields($meta)
          ->execute();
      }
    }

    if (empty($meta['utc_offset'])) {
      $meta['utc_offset'] = 120;
    }
    $return_array['forecasts'] = $this->weatherGetForecastsFromDatabase($geoid, $meta['utc_offset'], $days, $detailed, $time);
    $return_array['utc_offset'] = $meta['utc_offset'];
    return $return_array;
  }

  /**
   * Internal helper function for getting information about a forecast.
   */
  private function getInfoAboutForecast($time) {

    // Set the testing time.
    $config = \Drupal::configFactory()->getEditable('weather.settings');
    $config->set('weather_time_for_testing', $time)->save();

    // Fetch weather forecasts for Hamburg.
    $this->weatherGetWeather('geonames_2911298', 1, FALSE);

    // Return the parsed information.
    $connection = \Drupal::database();
    $query = $connection->select('weather_forecast_information', 'wfi');

    $query->condition('wfi.geoid', 'geonames_2911298', '=');
    $query->fields('wfi', [
      'geoid',
      'last_update',
      'next_update',
      'next_download_attempt',
      'utc_offset',
    ]);
    $query->range(0, 50);
    $result = $query->execute();
    return $result->fetch();
  }

  /**
   * Test parsing of information about a forecast.
   */
  public function testParsingOfInformation() {
    // 2013-10-07 20:00:00 UTC.
    $info = $this->getInfoAboutForecast(1381176000);
    // Check that the information has been parsed correctly.
    $this->assertEquals('geonames_2911298', $info->geoid);
    $this->assertEquals('2013-10-07 15:30:00', $info->last_update);
    $this->assertEquals('2013-10-08 04:00:00', $info->next_update);
    $this->assertEquals('2013-10-08 04:00:00', $info->next_download_attempt);
    $this->assertEquals(120, $info->utc_offset);
  }

  /**
   * Test the parser with different days of forecast data.
   */
  public function testDifferentDaysOfForecasts() {

    // These are all days from the forecast.
    $days = [
      '2013-10-07',
      '2013-10-08',
      '2013-10-09',
      '2013-10-10',
      '2013-10-11',
      '2013-10-12',
      '2013-10-13',
      '2013-10-14',
      '2013-10-15',
      '2013-10-16',
      '2013-10-17',
    ];

    // Set a fixed time for testing to 2013-10-07 20:00:00 UTC.
    $config = \Drupal::configFactory()->getEditable('weather.settings');
    $config->set('weather_time_for_testing', 1381176000)->save();

    // Fetch all weather forecasts for Hamburg
    // and check the correct days of forecasts.
    $weather = $this->weatherGetWeather('geonames_2911298', 0, TRUE);
    $this->assertSame(array_keys($weather['forecasts']), $days);

    // Go a few days forward ...
    // Set a fixed time for testing to 2013-10-12 10:00:00 UTC.
    $config = \Drupal::configFactory()->getEditable('weather.settings');
    $config->set('weather_time_for_testing', 1381572000)->save();

    // Fetch all weather forecasts for Hamburg
    // and check the correct days of forecasts.
    $weather = $this->weatherGetWeather('geonames_2911298', 0, TRUE);
    $this->assertSame(array_keys($weather['forecasts']), array_slice($days, 5));
  }

}
