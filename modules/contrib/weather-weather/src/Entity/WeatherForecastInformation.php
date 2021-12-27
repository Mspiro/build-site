<?php

namespace Drupal\weather\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Weather forecast information entity.
 *
 * @ingroup weather
 *
 * @ContentEntityType(
 *   id = "weather_forecast_information",
 *   label = @Translation("Weather forecast information"),
 *   description = @Translation("Information about the forecast data for a place"),
 *   base_table = "weather_forecast_information",
 *   entity_keys = {
 *     "id" = "geoid",
 *   },
 * )
 */
class WeatherForecastInformation extends ContentEntityBase implements WeatherForecastInformationInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['geoid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Geoid'))
      ->setRequired(TRUE)
      ->setDescription('GeoID of the location')
      ->setSetting('target_type', 'weather_place');

    $fields['last_update'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last update'))
      ->setDescription('UTC time of last update')
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['next_update'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Next scheduled update'))
      ->setDescription('UTC time of next scheduled update')
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['next_download_attempt'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Scheduled download attempt'))
      ->setDescription('UTC time of next scheduled download attempt')
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['utc_offset'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('UTC offset of local time'))
      ->setDescription(t('UTC offset of local time in minutes'))
      ->setDefaultValue(NULL);

    return $fields;
  }

}
