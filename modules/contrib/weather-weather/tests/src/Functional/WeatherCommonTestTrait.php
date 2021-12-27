<?php

namespace Drupal\Tests\weather\Functional;

use Drupal\weather\Entity\WeatherPlaceInterface;

/**
 * Provides a helper method for testing Weather module.
 */
trait WeatherCommonTestTrait {

  /**
   * Provides functionality for filling database tables from source XML file.
   *
   * @param string $source
   *   Source filename.
   */
  public function weatherFillWeatherSchema($source = 'geonames_2911298.xml') {
    // Fetch forecast data from xml file.
    $xml_source = drupal_get_path('module', 'weather') . '/tests/src/Functional/data/' . $source;
    $xml_source_stream_content = stream_get_contents(fopen($xml_source, 'rb'));
    $element_data = new \SimpleXMLElement($xml_source_stream_content);
    // Prepare data for DB compatible format.
    $geobase = $element_data->location->location->attributes()->{'geobase'};
    $geobase_id = $element_data->location->location->attributes()->{'geobaseid'};
    $latitude = $element_data->location->location->attributes()->{'latitude'};
    $longitude = $element_data->location->location->attributes()->{'longitude'};
    $name = $element_data->location->name;
    $country = $element_data->location->country;
    $last_update = $element_data->meta->lastupdate;
    $next_update = $element_data->meta->nextupdate;
    if ($source !== 'geonames_2911298.xml') {
      $link = 'Kiev/Kyiv';
    }
    else {
      $link = 'Hamburg/Hamburg';
    }

    // Get forecast from XML source.
    $forecasts = $element_data->forecast->tabular;
    $forecats_list = [];
    // @codingStandardsIgnoreLine
    foreach ($forecasts->time as $key => $forecast) {
      $from = $forecast->attributes()->{'from'};
      $to = $forecast->attributes()->{'to'};
      $symbol = (string) $forecast->symbol->attributes()->{'var'};
      // Remove moon phases, which are not supported.
      // This is in the format "mf/03n.56", where 56 would be the
      // percentage of the moon phase.
      if (strlen($symbol) > 3) {
        $symbol = substr($symbol, 3, 3);
      }
      $period = $forecast->attributes()->{'period'};
      $precipitation = $forecast->precipitation->attributes()->{'value'};
      $wind_direction = $forecast->windDirection->attributes()->{'deg'};
      $wind_speed = $forecast->windSpeed->attributes()->{'mps'};
      $temperature = $forecast->temperature->attributes()->{'value'};
      $pressure = $forecast->pressure->attributes()->{'value'};

      $forecats_list[] = [
        'time_from' => (string) $from,
        'time_to' => (string) $to,
        'period' => (string) $period,
        'symbol' => $symbol,
        'precipitation' => (string) $precipitation,
        'wind_direction' => (string) $wind_direction,
        'wind_speed' => (string) $wind_speed,
        'temperature' => (string) $temperature,
        'pressure' => (string) $pressure,
      ];
    }

    // Fill places table from XML source.
    $connection = \Drupal::database();
    $query = $connection->insert('weather_place');
    $query->fields([
      'geoid' => (string) $geobase . '_' . (string) $geobase_id,
      'latitude' => (string) $latitude,
      'longitude' => (string) $longitude,
      'name' => (string) $name,
      'country' => (string) $country,
      'link' => $link,
      'status' => WeatherPlaceInterface::STATUS_ORIGINAL,
    ]);
    $query->execute();

    // Fill forecast table from XML source.
    foreach ($forecats_list as $forecast_item) {
      $query = $connection->insert('weather_forecast');
      $query->fields([
        'id' => 0,
        'geoid' => (string) $geobase . '_' . (string) $geobase_id,
        'time_from' => $forecast_item['time_from'],
        'time_to' => $forecast_item['time_to'],
        'period' => $forecast_item['period'],
        'symbol' => $forecast_item['symbol'],
        'precipitation' => $forecast_item['precipitation'],
        'wind_direction' => $forecast_item['wind_direction'],
        'wind_speed' => $forecast_item['wind_speed'],
        'temperature' => $forecast_item['temperature'],
        'pressure' => $forecast_item['pressure'],
      ]);
      $query->execute();
    }

    // Fill forecast_information table from XML source.
    $query = $connection->insert('weather_forecast_information');
    $query->fields([
      'geoid' => (string) $geobase . '_' . (string) $geobase_id,
      'last_update' => (string) $last_update,
      'next_update' => (string) $next_update,
      'next_download_attempt' => (string) $next_update,
      'utc_offset' => '180',
    ]);
    $query->execute();

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
  public function weatherCreateWeatherArray(array $forecasts) {
    $weather = [];
    // Cycle through all forecasts and set up a hierarchical array structure.
    if (count($forecasts) === 1) {
      $forecasts = reset($forecasts);
    }
    foreach ($forecasts as $forecast) {
      // @codingStandardsIgnoreStart
      [$day_from, $time_from] = explode(' ', $forecast->time_from);
      $time_range = substr($time_from, 0, 5);
      [$day_to, $time_to] = explode(' ', $forecast->time_to);
      $time_range .= '-' . substr($time_to, 0, 5);
      $weather[$day_from][$time_range] = [
        'period' => $forecast->period,
        'symbol' => $forecast->symbol,
        'precipitation' => $forecast->precipitation,
        'wind_direction' => $forecast->wind_direction,
        'wind_speed' => $forecast->wind_speed,
        'temperature' => $forecast->temperature,
        'pressure' => $forecast->pressure,
      ];
    }
    // @codingStandardsIgnoreEnd
    return $weather;
  }

  /**
   * Get information about a GeoID.
   *
   * @param string $wanted_geoid
   *   GeoID.
   *
   * @return object
   *   The information about the GeoID or FALSE.
   */
  public function weatherGetInformationAboutGeoid($wanted_geoid) {
    $connection = \Drupal::database();
    $query = $connection->select('weather_place', 'wfi');
    $query->condition('wfi.geoid', $wanted_geoid, '=');
    $query->fields('wfi', ['geoid', 'link', 'country']);
    $query->range(0, 50);
    $result = $query->execute();
    return $result->fetchAssoc();
  }

  /**
   * Construct the link for the given GeoID.
   *
   * @param string $geoid
   *   The GeoID to construct the link for.
   * @param string $destination
   *   Destination to create the link for. Currently supported are
   *   'system-wide', 'default', 'user', 'yr', and 'autocomplete'.
   * @param int $number
   *   Optional number of display. Applies only to 'system-wide'.
   *
   * @return string
   *   The link for the GeoID.
   */
  public function weatherGetLinkForGeoId($geoid, $destination, $number = 1) {
    $info = $this->weatherGetInformationAboutGeoid($geoid);
    // Conversion rules for all link parts:
    // - Replace all spaces with an underscore.
    // - If the link part ends with a dot, use an underscore.
    $country = str_replace(' ', '_', $info['country']);
    if (substr($country, -1) == '.') {
      $country[strlen($country) - 1] = '_';
    }
    $link = $country . '/' . $info['link'];
    switch ($destination) {
      case 'system-wide':
        $link = 'weather/' . $link . '/' . $number;
        break;

      case 'default':
        $link = 'weather/' . $link;
        break;

      case 'user':
        $link = 'weather/' . $link . '/u';
        break;

      case 'yr':
        // Encode special characters except the '/' in the URL.
        // Otherwise, the request will fail on yr.no.
        $link = rawurlencode($link);
        $link = str_replace('%2F', '/', $link);
        $link = 'https://www.yr.no/place/' . $link . '/forecast.xml';
        break;

      case 'yr.no':
        $link = 'https://www.yr.no/place/' . $link . '/';
        break;

      case 'autocomplete':
        // Nothing to do here.
        break;
    }
    return $link;
  }

}
