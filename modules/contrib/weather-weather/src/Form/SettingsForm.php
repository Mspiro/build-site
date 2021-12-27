<?php

namespace Drupal\weather\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Drupal\weather\Service\DataService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Weather settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Entity Type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Weather displays storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherDisplayStorage;

  /**
   * Weather display places storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherDisplayPlaceStorage;

  /**
   * Weather Data service.
   *
   * @var \Drupal\weather\Service\DataService
   */
  protected $weatherDataService;

  /**
   * The Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a \Drupal\weather\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager storage.
   * @param \Drupal\weather\Service\DataService $weatherDataService
   *   Weather data service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Renderer.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, DataService $weatherDataService, Renderer $renderer) {
    parent::__construct($config_factory);

    $this->entityTypeManager = $entityTypeManager;
    $this->weatherDisplayStorage = $entityTypeManager->getStorage('weather_display');
    $this->weatherDisplayPlaceStorage = $entityTypeManager->getStorage('weather_display_place');
    $this->weatherDataService = $weatherDataService;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('weather.data_service'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'weather_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['weather.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->addWeatherDisplayOverview($form, $form_state);

    // Import/Reimport places.
    $form['import_places'] = [
      '#type' => 'details',
      '#title' => $this->t('Import/Reimport places'),
    ];
    $form['import_places']['description'] = [
      '#type' => 'markup',
      '#prefix' => '<span>',
      '#markup' => $this->t('After the installation, you need to import weather places into the system.'),
      '#suffix' => '</span>',
    ];
    $form['import_places']['import_places_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import places'),
    ];

    // Additional weather settings.
    $theme = $this->config('system.theme')->get('default');
    $theme_path = drupal_get_path('theme', $theme);
    $config = $this->config('weather.settings');

    $form['weather_image_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory for custom images'),
      '#description' => $this->t('Use custom images for displaying weather conditions. The name of this directory can be chosen freely. It will be searched in your active theme (currently %theme_path).',
        ['%theme_path' => $theme_path]),
      '#default_value' => $config->get('weather_image_directory', ''),
    ];
    $options = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];
    $form['weather_forecast_days'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of forecast days'),
      '#description' => $this->t('You can configure the number of days for the forecast displays in blocks.'),
      '#default_value' => $config->get('weather_forecast_days', '2'),
      '#options' => array_combine($options, $options),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds system wide weather displays overview.
   */
  protected function addWeatherDisplayOverview(array &$form, FormStateInterface $form_state) {
    $displays = $this->weatherDisplayStorage->loadByProperties(['type' => WeatherDisplayInterface::SYSTEM_WIDE_TYPE]);

    foreach ($displays as $display) {
      $display_number = $display->number->value;
      $form['system_displays'][$display_number] = [
        '#type' => 'table',
        '#header' => [
          Link::fromTextAndUrl(
            $this->t('System-wide display (#@number)', ['@number' => $display_number]),
            Url::fromRoute('entity.weather_display.edit_form', ['display_number' => $display_number])
          ),
          $this->t('Weight'),
          $this->t('Operations'),
        ],
      ];

      $locations = $this->weatherDisplayPlaceStorage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('display_type', WeatherDisplayInterface::SYSTEM_WIDE_TYPE)
        ->condition('display_number', $display_number)
        ->sort('weight', 'ASC')
        ->sort('displayed_name', 'ASC')
        ->execute();

      foreach ($locations as $locationId) {
        $location = $this->weatherDisplayPlaceStorage->load($locationId);
        $operations = [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute(
                'entity.weather_display_place.edit_form',
                [
                  'display_type' => $location->display_type->value,
                  'display_number' => $display_number,
                  'weather_display_place' => $locationId,
                ]
              ),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute(
                'entity.weather_display_place.delete_form',
                [
                  'display_type' => $location->display_type->value,
                  'display_number' => $display_number,
                  'weather_display_place' => $locationId,
                ]
              ),
            ],
          ],
        ];

        $form['system_displays'][$display_number]['#rows'][] = [
          $location->displayed_name->value,
          $location->weight->value,
          $this->renderer->render($operations),
        ];
      }

      // Insert link for adding locations into the table as last row.
      $form['system_displays'][$display_number]['#rows'][] = [
        'link' => Link::fromTextAndUrl(
          $this->t('Add location to this display'),
          Url::fromRoute('entity.weather_display_place.add_form', [
            'display_type' => WeatherDisplayInterface::SYSTEM_WIDE_TYPE,
            'display_number' => $display_number,
          ])
        ),
        '#wrapper_attributes' => [
          'colspan' => 3,
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggeredBy = $form_state->getTriggeringElement();
    if ($triggeredBy && $triggeredBy['#id'] == 'edit-import-places-submit') {
      $this->weatherDataService->weatherDataInstallation();
    }
    else {
      $this->config('weather.settings')
        ->set('weather_image_directory', $form_state->getValue('weather_image_directory'))
        ->set('weather_forecast_days', $form_state->getValue('weather_forecast_days'))
        ->save();

      parent::submitForm($form, $form_state);
    }
  }

}
