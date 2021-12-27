<?php

namespace Drupal\weather\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Drupal\weather\Service\HelperService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'WeatherUserDisplayBlock' block plugin.
 *
 * @Block(
 *   id = "weather_user_display_block",
 *   admin_label = @Translation("Weather: custom user"),
 * )
 */
class WeatherUserDisplayBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   Route Match.
   * @param \Drupal\weather\Service\HelperService $helperService
   *   Helper Service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    CurrentRouteMatch $routeMatch,
    HelperService $helperService
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $currentUser;
    $user_id = $routeMatch->getRawParameter('user');

    $allowed_to_display = FALSE;
    if (!empty($user_id)) {
      $config = $helperService->getDisplayConfig(WeatherDisplayInterface::USER_TYPE, $user_id);
      $allowed_to_display = $config['displays_for_everyone'] ?? FALSE;
    }

    $weatherDisplay = $entityTypeManager->getStorage('weather_display')
      ->loadByProperties(
        [
          'type' => WeatherDisplayInterface::USER_TYPE,
          'number' => $allowed_to_display ? $user_id : $this->currentUser->id(),
        ]
      );
    $this->weatherDisplay = reset($weatherDisplay);
    $this->weatherDisplayPlaceStorage = $entityTypeManager->getStorage('weather_display_place');
    $this->destination = WeatherDisplayInterface::USER_TYPE;
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
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('weather.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account, $return_as_object = FALSE) {
    // Allow access if user has permission and has configured
    // own weather display already.
    return AccessResult::allowedIf(
      $this->currentUser->hasPermission('administer custom weather block')
      && $this->weatherDisplay instanceof WeatherDisplayInterface);
  }

}
