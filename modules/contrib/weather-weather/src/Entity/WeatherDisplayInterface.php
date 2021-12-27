<?php

namespace Drupal\weather\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining Weather display entities.
 *
 * @ingroup weather
 */
interface WeatherDisplayInterface extends ContentEntityInterface {

  const SYSTEM_WIDE_TYPE = 'system-wide';
  const DEFAULT_TYPE = 'default';
  const USER_TYPE = 'user';

}
