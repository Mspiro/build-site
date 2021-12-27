<?php

namespace Drupal\weather\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Weather display place entity.
 *
 * @ingroup weather
 *
 * @ContentEntityType(
 *   id = "weather_display_place",
 *   label = @Translation("Weather display place"),
 *   description = @Translation("Places used in weather displays"),
 *   base_table = "weather_display_place",
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\weather\Form\WeatherDisplayPlaceForm",
 *       "edit" = "Drupal\weather\Form\WeatherDisplayPlaceForm",
 *       "delete" = "Drupal\weather\Form\WeatherDisplayPlaceDeleteForm",
 *     },
 *     "access" = "Drupal\weather\WeatherAccessControlHandler",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/user-interface/weather/weather-display-place/{weather_display_place}/edit",
 *     "delete-form" = "/admin/config/user-interface/weather/weather-display-place/{weather_display_place}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *   },
 * )
 */
class WeatherDisplayPlace extends ContentEntityBase implements WeatherDisplayPlaceInterface {

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

    $fields['display_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type of display'))
      ->setDescription('Type of display (system-wide, user, location, default, ...).')
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['display_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Display number'))
      ->setDescription(t('Display number'))
      ->setRequired(TRUE)
      ->setDefaultValue(0);

    $fields['geoid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Geoid'))
      ->setRequired(TRUE)
      ->setDescription('GeoID of the location')
      ->setSetting('target_type', 'weather_place');

    $fields['displayed_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Displayed name of place'))
      ->setDescription('Displayed name of place')
      ->setSetting('max_length', 255);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight of the location'))
      ->setDescription(t('Weight of the location'))
      ->setRequired(TRUE)
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Make sure display for this place exists.
    $displayStorage = $this->entityTypeManager()->getStorage('weather_display');
    $display = $displayStorage->loadByProperties([
      'type' => WeatherDisplayInterface::USER_TYPE,
      'number' => $this->display_number->value,
    ]);

    if (empty($display)) {
      $values = [
        'config' => \Drupal::service('weather.helper')->getDefaultConfig(),
        'type' => WeatherDisplayInterface::USER_TYPE,
        'number' => $this->display_number->value,
      ];
      $displayStorage->create($values)->save();
    }

    parent::preSave($storage);
  }

}
