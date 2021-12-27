<?php

namespace Drupal\weather\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Weather place entity.
 *
 * @ingroup weather
 *
 * @ContentEntityType(
 *   id = "weather_place",
 *   label = @Translation("Weather place"),
 *   description = @Translation("Information about known places at yr.no."),
 *   base_table = "weather_place",
 *   entity_keys = {
 *     "id" = "geoid",
 *   },
 * )
 */
class WeatherPlace extends ContentEntityBase implements WeatherPlaceInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['geoid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Geoid'))
      ->setRequired(TRUE)
      ->setDescription('GeoID of the location')
      ->setSetting('max_length', 20);

    $fields['latitude'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Latitude'))
      ->setDescription('Latitude of location')
      ->setRequired(TRUE)
      ->setDefaultValue(0.0);

    $fields['longitude'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Longitude'))
      ->setDescription('Longitude of location')
      ->setRequired(TRUE)
      ->setDefaultValue(0.0);

    $fields['country'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Country'))
      ->setRequired(TRUE)
      ->setDescription('Country of location')
      ->setSetting('max_length', 225);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setDescription('Name of location')
      ->setSetting('max_length', 225);

    $fields['link'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Link'))
      ->setRequired(TRUE)
      ->setDescription('Shortened link of location at yr.no.')
      ->setSetting('max_length', 225);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDescription('Status of place')
      ->setSetting('max_length', 8);

    return $fields;
  }

}
