<?php

namespace Drupal\weather\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'WeatherSystemDisplayBlock' block plugin.
 *
 * @Block(
 *   id = "weather_system_display_block",
 *   admin_label = @Translation("Weather: system-wide display"),
 *   deriver = "Drupal\weather\Plugin\Derivative\WeatherSystemDisplayBlock"
 * )
 */
class WeatherSystemDisplayBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use WeatherDisplayBlockTrait;

  /**
   * WeatherSystemDisplayBlock constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->weatherDisplay = $entityTypeManager->getStorage('weather_display')->load($this->getDerivativeId());
    $this->weatherDisplayPlaceStorage = $entityTypeManager->getStorage('weather_display_place');
    $this->currentUser = $currentUser;
    $this->destination = WeatherDisplayInterface::SYSTEM_WIDE_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account, $return_as_object = FALSE) {
    return AccessResult::allowedIfHasPermission($this->currentUser, 'access content');
  }

}
