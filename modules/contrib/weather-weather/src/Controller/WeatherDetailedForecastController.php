<?php

namespace Drupal\weather\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\weather\Entity\WeatherPlaceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Weather routes.
 */
class WeatherDetailedForecastController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Display Place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $displayPlaceStorage;

  /**
   * Weather Place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherPlaceStorage;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->displayPlaceStorage = $this->entityTypeManager->getStorage('weather_display_place');
    $this->weatherPlaceStorage = $this->entityTypeManager->getStorage('weather_place');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Builds the response for weather.detailed_forecast route.
   */
  public function detailedForecast(string $country, string $place, string $city, string $destination) {
    $link = $place . '/' . $city;

    $weatherPlace = $this->weatherPlaceStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('country', $country, 'LIKE')
      ->condition('link', $link, 'LIKE')
      ->execute();

    if ($weatherPlace) {
      $weatherPlace = $this->weatherPlaceStorage->load(reset($weatherPlace));
    }

    // If the last part of the link contains an appended slash
    // and a number, this indicates the display configuration of
    // the system-wide display with the given number.
    $display_type = 'default';
    $display_number = 0;
    // Examine the last element of the link.
    if (preg_match('/^[0-9]+$/', $destination)) {
      // Use the system-wide display with the given number.
      $display_type = 'system-wide';
      $display_number = $destination;
    }
    elseif ($destination == 'u') {
      // Use the user's custom display.
      $display_type = 'user';
      $display_number = $this->currentUser()->id();
    }

    if ($weatherPlace instanceof WeatherPlaceInterface) {
      // Show detailed forecast only if Weather Place
      // was configured for the Display.
      $configured = $this->displayPlaceStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('geoid', $weatherPlace->id())
        ->condition('display_type', $display_type)
        ->condition('display_number', $display_number)
        ->execute();

      if ($configured) {
        return [
          '#theme' => 'weather_detailed_forecast',
          '#weather_display_place' => $this->displayPlaceStorage->load(reset($configured)),
          '#display_type' => $display_type,
          '#display_number' => $display_number,
        ];
      }
    }

    throw new NotFoundHttpException();
  }

}
