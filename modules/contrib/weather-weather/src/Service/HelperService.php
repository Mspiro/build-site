<?php

namespace Drupal\weather\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\weather\Entity\WeatherDisplayInterface;

/**
 * Some small helper functions.
 */
class HelperService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Weather display storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherDisplayStorage;

  /**
   * Weather display place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherDisplayPlaceStorage;

  /**
   * Weather place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherPlaceStorage;

  /**
   * HelperService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->weatherDisplayStorage = $entity_type_manager->getStorage('weather_display');
    $this->weatherDisplayPlaceStorage = $entity_type_manager->getStorage('weather_display_place');
    $this->weatherPlaceStorage = $entity_type_manager->getStorage('weather_place');
  }

  /**
   * Return display configuration for a specific display.
   *
   * If there is no configuration yet, get the default configuration
   * instead.
   *
   * @param string $display_type
   *   Display type.
   * @param int $display_number
   *   Display number.
   *
   * @return array
   *   Display configuration.
   */
  public function getDisplayConfig($display_type, $display_number = NULL) {
    // Set default config.
    $config = $this->getDefaultConfig();

    // Try to find configuration for the display in DB.
    $display_number = $display_number != NULL ? $display_number : 1;

    $display = $this->weatherDisplayStorage->loadByProperties([
      'type' => $display_type,
      'number' => $display_number,
    ]);
    $display = reset($display);

    if ($display instanceof WeatherDisplayInterface) {
      $config = $display->config->getValue()[0];
    }

    return $config;
  }

  /**
   * Returns default configuration for Displays.
   */
  public function getDefaultConfig() {
    return [
      'temperature' => 'celsius',
      'windspeed' => 'kmh',
      'pressure' => 'hpa',
      'distance' => 'kilometers',
      'show_sunrise_sunset' => FALSE,
      'show_windchill_temperature' => FALSE,
      'show_abbreviated_directions' => FALSE,
      'show_directions_degree' => FALSE,
    ];
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
  public function getLinkForGeoid($geoid, $destination, $number = 1) {
    $weatherPlace = $this->weatherPlaceStorage->load($geoid);

    // Conversion rules for all link parts:
    // - Replace all spaces with an underscore.
    // - If the link part ends with a dot, use an underscore.
    $country = str_replace(' ', '_', $weatherPlace->country->value);
    if (substr($country, -1) == '.') {
      $country[strlen($country) - 1] = '_';
    }
    $link = $country . '/' . $weatherPlace->link->value;
    switch ($destination) {
      case 'system-wide':
        $link = '/weather/' . $link . '/' . $number;
        break;

      case 'default':
        $link = '/weather/' . $link;
        break;

      case 'user':
        $link = '/weather/' . $link . '/u';
        break;

      case 'yr':
        // Encode special characters except the '/' in the URL.
        // Otherwise, the request will fail on yr.no.
        $link = rawurlencode($link);
        $link = str_replace('%2F', '/', $link);
        $link = 'http://www.yr.no/place/' . $link . '/forecast.xml';
        break;

      case 'yr.no':
        $link = 'http://www.yr.no/place/' . $link . '/';
        break;

      case 'autocomplete':
        // Nothing to do here.
        break;
    }

    return $link;
  }

  /**
   * Get all currently used places for a display.
   *
   * @param string $display_type
   *   Display type.
   * @param int $display_number
   *   Display number.
   *
   * @return array
   *   Array of sorted places.
   */
  public function getPlacesInUse($display_type, $display_number) {
    $result = $this->weatherDisplayPlaceStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('display_type', $display_type)
      ->condition('display_number', $display_number)
      ->sort('weight', 'ASC')
      ->sort('displayed_name', 'ASC')
      ->execute();

    if ($result) {
      $result = $this->weatherDisplayPlaceStorage->loadMultiple($result);
    }

    return $result;
  }

  /**
   * Parse full link to the place at yr.no site and return useful info.
   *
   * @param string $url
   *   Full url to the place e.g: https://www.yr.no/place/Ukraine/Kiev/Kyiv/.
   *
   * @return array
   *   Array with two parsed values:
   *     $country - Country of this place (e.g Ukraine or United_States).
   *     $link - Internal used link (e.g. Kiev/Kyiv, Maryland/Baltimore)
   */
  public function parsePlaceUrl(string $url): array {
    // Remove "http://www.yr.no/place/" or "https://www.yr.no/place/"
    // from the URL.
    $url = str_replace('http://www.yr.no/place/', '', $url);
    $url = str_replace('https://www.yr.no/place/', '', $url);

    // Split by slashes and remove country (first item)
    // and "forecast.xml" (last item).
    $parts = explode('/', $url);

    // Remove country.
    $country = array_shift($parts);
    // Remove "forecast.xml".
    array_pop($parts);

    $link = implode('/', $parts);

    return [$country, $link];
  }

}
