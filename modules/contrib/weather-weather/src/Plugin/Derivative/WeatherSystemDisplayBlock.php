<?php

namespace Drupal\weather\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for nodes.
 *
 * @see \Drupal\weather\Plugin\Block\WeatherSystemDisplayBlock
 */
class WeatherSystemDisplayBlock extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherDisplayStorage;

  /**
   * Constructs new WeatherSystemDisplayBlock.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $weatherDisplayStorage
   *   The weather displays storage.
   */
  public function __construct(EntityStorageInterface $weatherDisplayStorage) {
    $this->weatherDisplayStorage = $weatherDisplayStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')->getStorage('weather_display')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $weatherDisplays = $this->weatherDisplayStorage->loadByProperties(['type' => WeatherDisplayInterface::SYSTEM_WIDE_TYPE]);
    foreach ($weatherDisplays as $weatherDisplay) {
      $display_number = $weatherDisplay->number->value;
      $this->derivatives[$weatherDisplay->id()] = $base_plugin_definition;
      $this->derivatives[$weatherDisplay->id()]['admin_label'] = $this->t('Weather: system-wide display (#@number)', ['@number' => $display_number]);
    }

    return $this->derivatives;
  }

}
