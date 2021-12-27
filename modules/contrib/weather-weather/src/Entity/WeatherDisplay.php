<?php

namespace Drupal\weather\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Weather display entity.
 *
 * @ingroup weather
 *
 * @ContentEntityType(
 *   id = "weather_display",
 *   label = @Translation("Weather display"),
 *   description = @Translation("Configuration of weather display"),
 *   base_table = "weather_display",
 *   admin_permission = "administer site configuration",
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\weather\Form\WeatherDisplayForm",
 *       "edit" = "Drupal\weather\Form\WeatherDisplayForm",
 *       "delete" = "Drupal\weather\Form\WeatherDisplayDeleteForm",
 *     },
 *     "access" = "Drupal\weather\WeatherAccessControlHandler",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/user-interface/weather/system-wide/{display_number}/edit",
 *     "delete-form" = "/admin/config/user-interface/weather/system-wide/{display_number}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *   }
 * )
 */
class WeatherDisplay extends ContentEntityBase implements WeatherDisplayInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Display ID'))
      ->setDescription(t('Weather Display ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type of display'))
      ->setDescription('Type of display (system-wide, user, default).')
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['number'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Display number'))
      ->setDescription(t('Display number'))
      ->setRequired(TRUE)
      ->setDefaultValue(0);

    $fields['config'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Configuration for display'))
      ->setDescription(t('Configuration for display (units and settings).'));

    return $fields;
  }

}
