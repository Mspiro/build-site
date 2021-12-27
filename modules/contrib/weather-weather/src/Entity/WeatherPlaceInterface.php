<?php

namespace Drupal\weather\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining Weather place entities.
 *
 * @ingroup weather
 */
interface WeatherPlaceInterface extends ContentEntityInterface {

  /**
   * Status weather place will get when first time imported from csv file.
   */
  public const STATUS_ORIGINAL = 'original';

  /**
   * This status means some data for this place was changed on yr.no site.
   */
  public const STATUS_MODIFIED = 'modified';

  /**
   * Place was added by user, manually, on module's settings page.
   */
  public const STATUS_ADDED = 'added';

}
