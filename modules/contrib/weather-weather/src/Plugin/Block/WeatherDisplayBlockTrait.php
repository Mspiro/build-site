<?php

namespace Drupal\weather\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\weather\Entity\WeatherDisplayInterface;

/**
 * Provides reusable functions for weather block plugins.
 */
trait WeatherDisplayBlockTrait {

  /**
   * Weather display to show in this block.
   *
   * @var \Drupal\weather\Entity\WeatherDisplayInterface
   */
  protected $weatherDisplay;

  /**
   * Weather display Place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherDisplayPlaceStorage;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Block type. Either 'user' or 'system-wide'.
   *
   * @var string
   */
  protected $destination;

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->weatherDisplay instanceof WeatherDisplayInterface) {
      return [];
    }

    $type = $this->weatherDisplay->type->value;
    $number = $this->weatherDisplay->number->value;
    $display_places = $this->weatherDisplayPlaceStorage->loadByProperties([
      'display_type' => $type,
      'display_number' => $number,
    ]);

    if (empty($display_places)) {
      return [];
    }

    $build = [
      '#theme' => 'weather',
      '#display_type' => $type,
      '#display_number' => $number,
      '#destination' => $this->destination,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {

    // Update this block after related weather_display
    // or one of related weather_display_places updated.
    $parent = parent::getCacheTags();
    $displayTags = $this->weatherDisplay->getCacheTags();
    $tags = Cache::mergeTags($parent, $displayTags);
    $display_places = $this->weatherDisplayPlaceStorage->loadByProperties([
      'display_type' => $this->weatherDisplay->type->value,
      'display_number' => $this->weatherDisplay->number->value,
    ]);

    foreach ($display_places as $display_place) {
      $tags = Cache::mergeTags($tags, $display_place->getCacheTags());
    }

    // Depends on weather module settings also.
    $tags = Cache::mergeTags($tags, ['config:weather.settings']);

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.path'];
  }

}
