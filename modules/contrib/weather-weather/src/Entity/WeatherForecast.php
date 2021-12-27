<?php

namespace Drupal\weather\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Weather forecast entity.
 *
 * @ingroup weather
 *
 * @ContentEntityType(
 *   id = "weather_forecast",
 *   label = @Translation("Weather forecast"),
 *   description = @Translation("Parsed XML forecast data from yr.no."),
 *   base_table = "weather_forecast",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 * )
 */
class WeatherForecast extends ContentEntityBase implements WeatherForecastInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['geoid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Geoid'))
      ->setRequired(TRUE)
      ->setDescription('GeoID of the location')
      ->setSetting('target_type', 'weather_place');

    $fields['time_from'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Start time of forecast'))
      ->setDescription('Start time of forecast')
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['time_to'] = BaseFieldDefinition::create('string')
      ->setLabel(t('End time of forecast'))
      ->setDescription('End time of forecast')
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['period'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Period of day'))
      ->setDescription('Period of day')
      ->setRequired(TRUE)
      ->setSetting('max_length', 1);

    $fields['symbol'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Symbol to use for weather display'))
      ->setDescription('Symbol to use for weather display')
      ->setRequired(TRUE)
      ->setSetting('max_length', 3);

    $fields['precipitation'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Precipitation'))
      ->setDescription('Amount of precipitation in mm')
      ->setDefaultValue(NULL);

    $fields['wind_direction'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Wind direction'))
      ->setDescription(t('Wind direction in degrees'))
      ->setDefaultValue(NULL);

    $fields['wind_speed'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Wind speed'))
      ->setDescription('Wind speed in m/s')
      ->setDefaultValue(NULL);

    $fields['temperature'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Temperature'))
      ->setDescription(t('Temperature in degree celsius'))
      ->setDefaultValue(NULL);

    $fields['pressure'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Pressure'))
      ->setDescription(t('Pressure in hPa'))
      ->setDefaultValue(NULL);

    return $fields;
  }

}
