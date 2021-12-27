<?php

namespace Drupal\weather\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Drupal\weather\Service\HelperService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Weather routes.
 */
class WeatherUserConfiguredDisplayController extends ControllerBase {

  /**
   * The weather.helper service.
   *
   * @var \Drupal\weather\Service\HelperService
   */
  protected $weatherHelper;

  /**
   * The entity.type_manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The controller constructor.
   *
   * @param \Drupal\weather\Service\HelperService $weather_helper
   *   The weather.helper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity.type_manager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(HelperService $weather_helper, EntityTypeManagerInterface $entity_type_manager, Renderer $renderer) {
    $this->weatherHelper = $weather_helper;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('weather.helper'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Builds the response.
   */
  public function content(UserInterface $user) {
    $output = [];
    $weatherDisplayPlaceStorage = $this->entityTypeManager->getStorage('weather_display_place');

    $header = [
      $this->t('Displayed name'),
      $this->t('Weight'),
      $this->t('Operations'),
    ];

    $rows = [];
    $result = $weatherDisplayPlaceStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('display_type', WeatherDisplayInterface::USER_TYPE)
      ->condition('display_number', $user->id())
      ->sort('weight', 'ASC')
      ->sort('displayed_name', 'ASC')
      ->execute();

    if ($result) {
      foreach ($weatherDisplayPlaceStorage->loadMultiple($result) as $location) {
        $operations = [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute(
                'weather.user.weather_display_place.edit_form',
                [
                  'user' => $user->id(),
                  'weather_display_place' => $location->id(),
                ]
              ),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute(
                'weather.user.weather_display_place.delete_form',
                [
                  'user' => $user->id(),
                  'weather_display_place' => $location->id(),
                ]
              ),
            ],
          ],
        ];

        $rows[] = [
          $location->displayed_name->value,
          $location->weight->value,
          $this->renderer->render($operations),
        ];
        $this->renderer->addCacheableDependency($output, $location);
      }
    }
    $output["#cache"]["tags"][] = 'weather_display:' . $user->id();

    // Insert link for adding locations into the table as last row.
    $rows[] = [
      [
        'data' => Link::createFromRoute($this->t('Add location to this display'), 'weather.user.weather_display_place.add_form', ['user' => $user->id()]),
        'colspan' => 3,
      ],
    ];

    $output['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    // Generate link to Add or Edit user's weather display.
    $url = Url::fromRoute('weather.user.weather_display.add_form', ['user' => $user->id()]);

    $output['edit_display'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit configuration of display'),
      '#url' => $url,
    ];

    return $output;
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, UserInterface $user) {
    return AccessResult::allowedIf(
      $user->id() == $account->id() &&
      $account->hasPermission('administer custom weather block')
    );
  }

}
