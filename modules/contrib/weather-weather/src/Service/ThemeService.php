<?php

namespace Drupal\weather\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\weather\Entity\WeatherDisplayPlaceInterface;

/**
 * Prepare forecast data for displaying.
 */
class ThemeService {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The weather.helper service.
   *
   * @var \Drupal\weather\Service\HelperService
   */
  protected $weatherHelper;

  /**
   * Parser service.
   *
   * @var \Drupal\weather\Service\ParserService
   */
  protected $weatherParser;

  /**
   * The weather.data_service service.
   *
   * @var \Drupal\weather\Service\DataService
   */
  protected $weatherDataService;

  /**
   * Weather module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $weatherConfig;

  /**
   * Theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Date format service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Weather place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherPlaceStorage;

  /**
   * ThemeService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\weather\Service\HelperService $weather_helper
   *   Weather helper service.
   * @param \Drupal\weather\Service\ParserService $parserService
   *   Weather parser service.
   * @param \Drupal\weather\Service\DataService $weather_data_service
   *   Weather data service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration factory.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   Theme manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, HelperService $weather_helper, ParserService $parserService, DataService $weather_data_service, ConfigFactoryInterface $configFactory, ThemeManagerInterface $themeManager, DateFormatterInterface $dateFormatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->weatherHelper = $weather_helper;
    $this->weatherDataService = $weather_data_service;
    $this->weatherConfig = $configFactory->get('weather.settings');
    $this->themeManager = $themeManager;
    $this->dateFormatter = $dateFormatter;
    $this->weatherPlaceStorage = $entity_type_manager->getStorage('weather_place');
    $this->weatherParser = $parserService;
  }

  /**
   * Prepare variables to render weather display.
   */
  public function preprocessWeatherVariables(&$variables, bool $detailed = FALSE) {
    $displayPlaces = [];
    $forecast_days = $this->weatherConfig->get('weather_forecast_days');
    $display = $this->weatherHelper->getDisplayConfig($variables['display_type'], $variables['display_number']);

    // On detailed forecast page we show only 1 place.
    if ($detailed) {
      if ($variables['weather_display_place'] instanceof WeatherDisplayPlaceInterface) {
        $displayPlaces = [$variables['weather_display_place']];
        $forecast_days = 0;
      }
    }
    else {
      $displayPlaces = $this->weatherHelper->getPlacesInUse($variables['display_type'], $variables['display_number']);
    }

    foreach ($displayPlaces as $idx => $place) {
      $weather = $this->weatherParser->getWeather($place->geoid->target_id, $forecast_days, $detailed);
      $this->setWeatherVariables($variables, $idx, $place, $weather, $detailed);
      $this->setForecastsVariables($variables, $idx, $weather, $display);
    }
  }

  /**
   * Adds basic weather variables.
   */
  protected function setWeatherVariables(&$variables, $idx, $place, $weather, bool $detailed = FALSE) {
    $variables['weather'][$idx]['utc_offset'] = $weather['utc_offset'];
    $variables['weather'][$idx]['name'] = $place->displayed_name->value;
    $variables['weather'][$idx]['geoid'] = $place->geoid->target_id;

    if (!$detailed) {
      $link = $this->weatherHelper->getLinkForGeoid($place->geoid->target_id, $variables['destination'], $variables['display_number']);
      $variables['weather'][$idx]['link'] = Link::fromTextAndUrl($place->displayed_name->value, Url::fromUserInput($link));
    }

    $link = $this->weatherHelper->getLinkForGeoid($place->geoid->target_id, 'yr.no');
    $variables['weather'][$idx]['yr.no'] = $link;
  }

  /**
   * Adds variables related to weather forecast.
   */
  protected function setForecastsVariables(&$variables, $idx, $weather, $display) {
    // Use a day counter to prepend "today" and "tomorrow" to forecast dates.
    $day_counter = 0;
    $forecasts = [];
    foreach ($weather['forecasts'] as $date => $time_ranges) {
      $formatted_date = $this->dateFormatter->format(strtotime($date), 'weather_short');
      if ($day_counter == 0) {
        $formatted_date = $this->t('Today, @date', ['@date' => $formatted_date]);
      }
      elseif ($day_counter == 1) {
        $formatted_date = $this->t('Tomorrow, @date', ['@date' => $formatted_date]);
      }
      $forecasts[$date]['formatted_date'] = $formatted_date;
      // Calculate sunrise and sunset information, if desired.
      if ($display['show_sunrise_sunset']) {
        $forecasts[$date]['sun_info'] = $this->calculateSunInfo($date, $variables['weather'][$idx]['utc_offset'], $variables['weather'][$idx]['geoid']);
      }
      foreach ($time_ranges as $time_range => $data) {
        $condition = $this->formatCondition($data['symbol']);
        $forecasts[$date]['time_ranges'][$time_range]['condition'] = $condition;
        $forecasts[$date]['time_ranges'][$time_range]['symbol'] = $this->formatImage($data['symbol'], $condition);
        $forecasts[$date]['time_ranges'][$time_range]['temperature'] = $this->formatTemperature($data['temperature'], $display['temperature']);
        if ($display['show_windchill_temperature']) {
          $forecasts[$date]['time_ranges'][$time_range]['windchill'] = $this->formatWindchillTemperature($data['temperature'], $data['wind_speed'], $display['temperature']);
        }
        $forecasts[$date]['time_ranges'][$time_range]['precipitation'] = $this->t('@precipitation mm',
          ['@precipitation' => $data['precipitation']]);
        $forecasts[$date]['time_ranges'][$time_range]['pressure'] = $this->formatPressure(
          $data['pressure'], $display['pressure']);
        $forecasts[$date]['time_ranges'][$time_range]['wind'] = $this->formatWind(
          $data['wind_direction'],
          $data['wind_speed'],
          $display['windspeed'],
          $display['show_abbreviated_directions'],
          $display['show_directions_degree']
        );
      }
      $day_counter++;
    }

    $variables['weather'][$idx]['forecasts'] = $forecasts;
  }

  /**
   * Calculate the times of sunrise and sunset.
   *
   * @param string $date
   *   The date for which the calculation should be made.
   * @param int $utc_offset
   *   UTC offset of local time in minutes.
   * @param string $geoid
   *   The GeoID of a place.
   *
   * @return array|string
   *   An array with sunrise and sunset times in the local timezone.
   *   If a string is returned, this is the special case for polar
   *   day or polar night without sunrise and sunset.
   */
  protected function calculateSunInfo($date, $utc_offset, $geoid) {
    // Get the coordinates for sunrise and sunset calculation.
    $weatherPlace = $this->weatherPlaceStorage->load($geoid);
    // Calculate the timestamp for the local time zone.
    $time = strtotime($date . ' 12:00:00 UTC') - 60 * $utc_offset;
    $sun_info = date_sun_info($time, $weatherPlace->latitude->value, $weatherPlace->longitude->value);
    // Handle special cases (no sunrise or sunset at all).
    if ($sun_info['sunrise'] == 0 and $sun_info['sunset'] == 0) {
      // Sun is always below the horizon.
      $result = $this->t('No sunrise, polar night');
    }
    elseif ($sun_info['sunrise'] == 1 and $sun_info['sunset'] == 1) {
      // Sun is always above the horizon.
      $result = $this->t('No sunset, polar day');
    }
    else {
      // There is a sunrise and a sunset.
      // We don't need the exact second of the sunrise and sunset.
      // Therefore, the times are rounded to the next minute.
      $time = round($sun_info['sunrise'] / 60) * 60;
      $time = gmdate('H:i', $time + 60 * $utc_offset);
      $result['sunrise'] = $time;
      $time = round($sun_info['sunset'] / 60) * 60;
      $time = gmdate('H:i', $time + 60 * $utc_offset);
      $result['sunset'] = $time;
    }
    return $result;
  }

  /**
   * Returns the <img> tag for the weather image for the current condition.
   *
   * @param mixed $symbol
   *   The weather condition number from yr.no.
   * @param string $condition
   *   The translated condition text.
   *
   * @return array
   *   Image render array.
   */
  protected function formatImage($symbol, $condition) {
    // Support a custom image directory.
    // If the variable is not set or the specified file is not available,
    // fall back to the default images of the module.
    // Determine the active theme path.
    $theme_path = $this->themeManager->getActiveTheme()->getPath();
    $custom_path = $theme_path . '/' . $this->weatherConfig->get('weather_image_directory') . '/';
    // Construct the filename.
    $image = $custom_path . $symbol . '.png';
    if (!is_readable($image)) {
      $default_path = drupal_get_path('module', 'weather') . '/images/';
      $image = $default_path . $symbol . '.png';
    }
    $size = getimagesize($image);
    // Prepare the <img> tag.
    return [
      '#theme' => 'image',
      '#uri' => $image,
      '#width' => $size[0],
      '#height' => $size[1],
      '#alt' => $condition,
      '#title' => $condition,
      '#attributes' => [
        'class' => [
          'weather-image',
        ],
      ],
    ];
  }

  /**
   * Converts temperatures.
   *
   * @param int $temperature
   *   Temperature in degree celsius.
   * @param string $unit
   *   Unit to be returned (celsius, fahrenheit, ...).
   *
   * @return array
   *   Formatted representation in the desired unit.
   */
  protected function formatTemperature($temperature, $unit) {
    // Do the calculation.
    $fahrenheit = (int) ($temperature * 9 / 5) + 32;
    // Format the temperature.
    if ($unit == 'fahrenheit') {
      $result = $this->t('@temperature&thinsp;°F', ['@temperature' => $fahrenheit]);
    }
    elseif ($unit == 'celsiusfahrenheit') {
      $result = $this->t('@temperature_c&thinsp;°C / @temperature_f&thinsp;°F',
        [
          '@temperature_c' => $temperature,
          '@temperature_f' => $fahrenheit,
        ]
      );
    }
    elseif ($unit == 'fahrenheitcelsius') {
      $result = $this->t('@temperature_f&thinsp;°F / @temperature_c&thinsp;°C',
        [
          '@temperature_f' => $fahrenheit,
          '@temperature_c' => $temperature,
        ]
      );
    }
    elseif ($unit == 'celsius_value') {
      $result = $temperature;
    }
    elseif ($unit == 'fahrenheit_value') {
      $result = $fahrenheit;
    }
    else {
      // Default to metric units.
      $result = $this->t('@temperature&thinsp;°C', ['@temperature' => $temperature]);
    }

    return [
      '#markup' => preg_replace("/([^ ]*)&thinsp;([^ ]*)/", '<span style="white-space:nowrap;">\1&thinsp;\2</span>', $result),
    ];
  }

  /**
   * Calculates windchill temperature.
   *
   * Windchill temperature is only defined for temperatures at or below
   * 10 °C (50 °F) and wind speeds above 1.34 m/s (3 mph). Bright sunshine
   * may increase the wind chill temperature.
   * @link http://en.wikipedia.org/wiki/Wind_chill.
   *
   * @param int $temperature
   *   Temperature in degree celsius.
   * @param int $wind_speed
   *   Wind speed in m/s.
   * @param string $unit
   *   Unit to be returned (celsius, fahrenheit, ...).
   *
   * @return string
   *   Formatted representation in the desired unit. If the windchill is not
   *   defined for the current conditions, returns NULL.
   */
  protected function formatWindchillTemperature($temperature, $wind_speed, $unit) {
    // Set up the empty result, if windchill temperature is not defined
    // for current conditions.
    $result = NULL;
    // First, check conditions for windchill temperature.
    if ($temperature <= 10 and $wind_speed >= 1.34) {
      // Convert wind speed to km/h for formula.
      $wind_speed = $wind_speed * 3.6;
      // Calculate windchill (in degree Celsius).
      // The integer cast is necessary to avoid a result of '-0°C'.
      $windchill = (int) round(13.12 + 0.6215 * $temperature -
        11.37 * pow($wind_speed, 0.16) +
        0.3965 * $temperature * pow($wind_speed, 0.16));
      $result = $this->formatTemperature($windchill, $unit);
    }

    return $result;
  }

  /**
   * Returns weather condition as translated text from yr.no symbol number.
   *
   * @param mixed $condition_no
   *   The weather condition number from yr.no.
   *
   * @return string
   *   Translated text with corresponding weather condition
   */
  protected function formatCondition($condition_no) {
    // Strip the suffix "d", "n", and "m".
    // (day, night, mørketid -> polar night)
    $condition_no = substr($condition_no, 0, 2);
    switch ($condition_no) {
      case 1:
        $txt = $this->t('Clear sky');
        break;

      case 2:
        $txt = $this->t('Fair');
        break;

      case 3:
        $txt = $this->t('Partly cloudy');
        break;

      case 4:
        $txt = $this->t('Cloudy');
        break;

      case 5:
        $txt = $this->t('Rain showers');
        break;

      case 6:
        $txt = $this->t('Rain showers and thunder');
        break;

      case 7:
        $txt = $this->t('Sleet showers');
        break;

      case 8:
        $txt = $this->t('Snow showers');
        break;

      case 9:
        $txt = $this->t('Rain');
        break;

      case 10:
        $txt = $this->t('Heavy rain');
        break;

      case 11:
        $txt = $this->t('Heavy rain and thunder');
        break;

      case 12:
        $txt = $this->t('Sleet');
        break;

      case 13:
        $txt = $this->t('Snow');
        break;

      case 14:
        $txt = $this->t('Snow and thunder');
        break;

      case 15:
        $txt = $this->t('Fog');
        break;

      case 20:
        $txt = $this->t('Sleet showers and thunder');
        break;

      case 21:
        $txt = $this->t('Snow showers and thunder');
        break;

      case 22:
        $txt = $this->t('Rain and thunder');
        break;

      case 23:
        $txt = $this->t('Sleet and thunder');
        break;

      case 24:
        $txt = $this->t('Light rain showers and thunder');
        break;

      case 25:
        $txt = $this->t('Heavy rain showers and thunder');
        break;

      case 26:
        $txt = $this->t('Light sleet showers and thunder');
        break;

      case 27:
        $txt = $this->t('Heavy sleet showers and thunder');
        break;

      case 28:
        $txt = $this->t('Light snow showers and thunder');
        break;

      case 29:
        $txt = $this->t('Heavy snow showers and thunder');
        break;

      case 30:
        $txt = $this->t('Light rain and thunder');
        break;

      case 31:
        $txt = $this->t('Light sleet and thunder');
        break;

      case 32:
        $txt = $this->t('Heavy sleet and thunder');
        break;

      case 33:
        $txt = $this->t('Light snow and thunder');
        break;

      case 34:
        $txt = $this->t('Heavy snow and thunder');
        break;

      case 40:
        $txt = $this->t('Light rain showers');
        break;

      case 41:
        $txt = $this->t('Heavy rain showers');
        break;

      case 42:
        $txt = $this->t('Light sleet showers');
        break;

      case 43:
        $txt = $this->t('Heavy sleet showers');
        break;

      case 44:
        $txt = $this->t('Light snow showers');
        break;

      case 45:
        $txt = $this->t('Heavy snow showers');
        break;

      case 46:
        $txt = $this->t('Light rain');
        break;

      case 47:
        $txt = $this->t('Light sleet');
        break;

      case 48:
        $txt = $this->t('Heavy sleet');
        break;

      case 49:
        $txt = $this->t('Light snow');
        break;

      case 50:
        $txt = $this->t('Heavy snow');
        break;

      default:
        $txt = $this->t('No data');
    }
    return $txt;
  }

  /**
   * Convert pressure.
   *
   * @param int $pressure
   *   Pressure in hPa.
   * @param string $unit
   *   Unit to be returned (for example, inHg, mmHg, hPa, kPa).
   *
   * @return array
   *   Formatted representation.
   */
  protected function formatPressure($pressure, $unit) {
    if ($unit == 'inhg') {
      $result = $this->t('@pressure&thinsp;inHg',
        ['@pressure' => round($pressure * 0.02953, 2)]);
    }
    elseif ($unit == 'inhg_value') {
      $result = round($pressure * 0.02953, 2);
    }
    elseif ($unit == 'mmhg') {
      $result = $this->t('@pressure&thinsp;mmHg',
        ['@pressure' => round($pressure * 0.75006, 0)]);
    }
    elseif ($unit == 'mmhg_value') {
      $result = round($pressure * 0.75006, 0);
    }
    elseif ($unit == 'kpa') {
      $result = $this->t('@pressure&thinsp;kPa',
        ['@pressure' => round($pressure / 10, 1)]);
    }
    elseif ($unit == 'kpa_value') {
      $result = round($pressure / 10, 1);
    }
    elseif ($unit == 'hpa_value') {
      $result = $pressure;
    }
    else {
      // Default to metric units.
      $result = $this->t('@pressure&thinsp;hPa', ['@pressure' => $pressure]);
    }
    return [
      '#markup' => preg_replace("/([^ ]*)&thinsp;([^ ]*)/",
      '<span style="white-space:nowrap;">\1&thinsp;\2</span>', $result),
    ];
  }

  /**
   * Convert wind.
   *
   * @param string $wind_direction
   *   Wind direction (Compass bearings, for example, '080')
   * @param int $wind_speed
   *   Wind speed in m/s.
   * @param string $unit
   *   Unit to be returned (km/h, knots, meter/s, ...).
   * @param mixed $abbreviated
   *   Whether or not to show abbreviated directions (E, NE, SSW).
   * @param mixed $exact_degree
   *   Whether or not to show exact compass bearings.
   *
   * @return string
   *   Formatted representation in the desired unit.
   */
  protected function formatWind($wind_direction, $wind_speed, $unit, $abbreviated, $exact_degree) {
    $direction = $this->bearingToText($wind_direction, $abbreviated);
    $beaufort = $this->calculateBeaufort($wind_speed);
    // Set up the wind speed.
    $speed = $this->formatWindSpeed($wind_speed, $unit);
    if ($exact_degree) {
      $result = $this->t('@description, <span style="white-space:nowrap;">@speed</span> from @direction (@degree°)',
        [
          '@description' => $beaufort['description'],
          '@speed' => $speed,
          '@direction' => $direction,
          '@degree' => $wind_direction,
        ]
      );
    }
    else {
      $result = $this->t('@description, @speed from @direction',
        [
          '@description' => $beaufort['description'],
          '@speed' => $speed,
          '@direction' => $direction,
        ]
      );
    }
    return $result;
  }

  /**
   * Converts a compass bearing to a text direction.
   *
   * This function can be used to get a text representation of a compass
   * bearing (for example, 0° North, 86° East, ...).
   *
   * @param string $bearing
   *   Compass bearing in degrees.
   * @param bool $abbreviated
   *   If true, return abbreviated directions (N, NNW) instead of
   *   full text (North, North-Northwest). Defaults to full text directions.
   *
   * @return string
   *   Formatted representation.
   */
  protected function bearingToText($bearing, $abbreviated = FALSE) {
    // Determine the sector. This works for 0° up to 348.75°
    // If the bearing was greater than 348.75°, perform a wrap (%16)
    $sector = floor(($bearing + 11.25) / 22.5) % 16;

    if (!$abbreviated) {
      $direction = [
        $this->t('North'),
        $this->t('North-Northeast'),
        $this->t('Northeast'),
        $this->t('East-Northeast'),
        $this->t('East'),
        $this->t('East-Southeast'),
        $this->t('Southeast'),
        $this->t('South-Southeast'),
        $this->t('South'),
        $this->t('South-Southwest'),
        $this->t('Southwest'),
        $this->t('West-Southwest'),
        $this->t('West'),
        $this->t('West-Northwest'),
        $this->t('Northwest'),
        $this->t('North-Northwest'),
      ];
    }
    else {
      $direction = [
        $this->t('N'),
        $this->t('NNE'),
        $this->t('NE'),
        $this->t('ENE'),
        $this->t('E'),
        $this->t('ESE'),
        $this->t('SE'),
        $this->t('SSE'),
        $this->t('S'),
        $this->t('SSW'),
        $this->t('SW'),
        $this->t('WSW'),
        $this->t('W'),
        $this->t('WNW'),
        $this->t('NW'),
        $this->t('NNW'),
      ];
    }
    return $direction[$sector];
  }

  /**
   * Calculate Beaufort wind scale for given wind speed.
   *
   * @link http://en.wikipedia.org/wiki/Beaufort_scale
   *
   * @param int $wind_speed
   *   Wind speed in m/s.
   *
   * @return array
   *   Beaufort number and description.
   */
  protected function calculateBeaufort($wind_speed) {
    // Set up an array of wind descriptions according to Beaufort scale.
    $description = [
      $this->t('Calm'),
      $this->t('Light air'),
      $this->t('Light breeze'),
      $this->t('Gentle breeze'),
      $this->t('Moderate breeze'),
      $this->t('Fresh breeze'),
      $this->t('Strong breeze'),
      $this->t('Near gale'),
      $this->t('Gale'),
      $this->t('Strong gale'),
      $this->t('Storm'),
      $this->t('Violent storm'),
      $this->t('Hurricane'),
    ];
    $number = 0;
    if ($wind_speed >= 0.3) {
      $number = 1;
    }
    if ($wind_speed >= 1.6) {
      $number = 2;
    }
    if ($wind_speed >= 3.5) {
      $number = 3;
    }
    if ($wind_speed >= 5.5) {
      $number = 4;
    }
    if ($wind_speed >= 8.0) {
      $number = 5;
    }
    if ($wind_speed >= 10.8) {
      $number = 6;
    }
    if ($wind_speed >= 13.9) {
      $number = 7;
    }
    if ($wind_speed >= 17.2) {
      $number = 8;
    }
    if ($wind_speed >= 20.8) {
      $number = 9;
    }
    if ($wind_speed >= 24.5) {
      $number = 10;
    }
    if ($wind_speed >= 28.5) {
      $number = 11;
    }
    if ($wind_speed >= 32.7) {
      $number = 12;
    }
    return ['number' => $number, 'description' => $description[$number]];
  }

  /**
   * Convert wind speed.
   *
   * @param int $wind_speed
   *   Wind speed in m/s.
   * @param string $unit
   *   Unit to be returned (km/h, knots, meter/s, ...).
   *
   * @return string
   *   Formatted representation in the desired unit.
   */
  protected function formatWindSpeed($wind_speed, $unit) {
    if ($unit == 'mph') {
      $result = $this->t('@speed&thinsp;mph',
        ['@speed' => round($wind_speed * 2.23694, 1)]);
    }
    elseif ($unit == 'mph_value') {
      $result = round($wind_speed * 2.23694, 1);
    }
    elseif ($unit == 'knots') {
      $result = $this->t('@speed&thinsp;knots',
        ['@speed' => round($wind_speed * 1.94384, 1)]);
    }
    elseif ($unit == 'knots_value') {
      $result = round($wind_speed * 1.94384, 1);
    }
    elseif ($unit == 'kmh') {
      $result = $this->t('@speed&thinsp;km/h',
        ['@speed' => round($wind_speed * 3.6, 1)]);
    }
    elseif ($unit == 'kmh_value') {
      $result = round($wind_speed * 3.6, 1);
    }
    elseif ($unit == 'beaufort') {
      $beaufort = $this->calculateBeaufort($wind_speed);
      $result = $this->t('Beaufort @number',
        ['@number' => $beaufort['number']]);
    }
    elseif ($unit == 'beaufort_value') {
      $beaufort = $this->calculateBeaufort($wind_speed);
      $result = $beaufort['number'];
    }
    elseif ($unit == 'mps_value') {
      $result = $wind_speed;
    }
    else {
      // Default to m/s.
      $result = $this->t('@speed&thinsp;meter/s', ['@speed' => $wind_speed]);
    }

    return $result;
  }

}
