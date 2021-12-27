<?php

namespace Drupal\weather\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Drupal\weather\Service\HelperService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the weather_display entity edit forms.
 *
 * @ingroup weather_display
 */
class WeatherDisplayForm extends ContentEntityForm {

  /**
   * Weather helper service.
   *
   * @var \Drupal\weather\Service\HelperService
   */
  protected $weatherHelperService;

  /**
   * Weather display storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherDisplayStorage;

  /**
   * Block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * WeatherDisplayForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\weather\Service\HelperService $weatherHelperService
   *   Weather helper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityRepositoryInterface $entity_repository, HelperService $weatherHelperService, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, BlockManagerInterface $block_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->weatherHelperService = $weatherHelperService;
    $this->weatherDisplayStorage = $entityTypeManager->getStorage('weather_display');
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('weather.helper'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.block')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $display_type = '', int $display_number = 0) {
    $form = parent::buildForm($form, $form_state);

    $savedConfig = [];

    // Try to load saved config when we are editing
    // Default or User weather display.
    if ($display_type == WeatherDisplayInterface::DEFAULT_TYPE) {
      $display_number = 1;
      $defaultDisplay = $this->weatherDisplayStorage->loadByProperties([
        'type' => WeatherDisplayInterface::DEFAULT_TYPE,
      ]);
      $defaultDisplay = reset($defaultDisplay);
      if ($defaultDisplay instanceof WeatherDisplayInterface) {
        $savedConfig = $defaultDisplay->config->getValue()[0];
      }
    }
    elseif ($display_type == WeatherDisplayInterface::USER_TYPE) {
      $display_number = $this->currentUser()->id();
      $displayExists = $this->weatherDisplayStorage->loadByProperties([
        'type' => WeatherDisplayInterface::USER_TYPE,
        'number' => $this->currentUser()->id(),
      ]);
      $systemwideDisplay = reset($displayExists);
      if ($systemwideDisplay instanceof WeatherDisplayInterface) {
        $savedConfig = $systemwideDisplay->config->getValue()[0];
      }
    }
    elseif ($display_type == WeatherDisplayInterface::SYSTEM_WIDE_TYPE) {
      $displayExists = $this->weatherDisplayStorage->loadByProperties([
        'type' => WeatherDisplayInterface::SYSTEM_WIDE_TYPE,
        'number' => $display_number,
      ]);
      $systemwideDisplay = reset($displayExists);
      if ($systemwideDisplay instanceof WeatherDisplayInterface) {
        $savedConfig = $systemwideDisplay->config->getValue()[0];
      }
    }

    $defaultConfig = $this->weatherHelperService->getDisplayConfig(WeatherDisplayInterface::DEFAULT_TYPE);

    $form['config'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    ];
    $form['config']['temperature'] = [
      '#type' => 'select',
      '#title' => $this->t('Temperature'),
      '#description' => $this->t('Unit for displaying temperatures.'),
      '#default_value' => $savedConfig['temperature'] ?? $defaultConfig['temperature'],
      '#options' => [
        'celsius' => $this->t('Celsius'),
        'fahrenheit' => $this->t('Fahrenheit'),
        'celsiusfahrenheit' => $this->t('Celsius / Fahrenheit'),
        'fahrenheitcelsius' => $this->t('Fahrenheit / Celsius'),
      ],
    ];
    $form['config']['windspeed'] = [
      '#type' => 'select',
      '#title' => $this->t('Wind speed'),
      '#description' => $this->t('Unit for displaying wind speeds.'),
      '#default_value' => $savedConfig['windspeed'] ?? $defaultConfig['windspeed'],
      '#options' => [
        'kmh' => $this->t('km/h'),
        'mph' => $this->t('mph'),
        'knots' => $this->t('Knots'),
        'mps' => $this->t('meter/s'),
        'beaufort' => $this->t('Beaufort'),
      ],
    ];
    $form['config']['pressure'] = [
      '#type' => 'select',
      '#title' => $this->t('Pressure'),
      '#description' => $this->t('Unit for displaying pressure.'),
      '#default_value' => $savedConfig['pressure'] ?? $defaultConfig['pressure'],
      '#options' => [
        'hpa' => $this->t('hPa'),
        'kpa' => $this->t('kPa'),
        'inhg' => $this->t('inHg'),
        'mmhg' => $this->t('mmHg'),
      ],
    ];
    $form['config']['distance'] = [
      '#type' => 'select',
      '#title' => $this->t('Distance'),
      '#description' => $this->t('Unit for displaying distances.'),
      '#default_value' => $savedConfig['distance'] ?? $defaultConfig['distance'],
      '#options' => [
        'kilometers' => $this->t('Kilometers'),
        'miles' => $this->t('UK miles'),
      ],
    ];
    $form['config']['show_sunrise_sunset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show times of sunrise and sunset'),
      '#default_value' => $savedConfig['show_sunrise_sunset'] ?? $defaultConfig['show_sunrise_sunset'],
      '#description' => $this->t('Displays the times of sunrise and sunset. This is always the local time.'),
    ];
    $form['config']['show_windchill_temperature'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show windchill temperature'),
      '#default_value' => $savedConfig['show_windchill_temperature'] ?? $defaultConfig['show_windchill_temperature'],
      '#description' => $this->t('Displays the windchill temperature. This is how the temperature <q>feels like</q>. Note that windchill temperature is only defined for temperatures below 10 °C (50 °F) and wind speeds above 1.34 m/s (3 mph).'),
    ];
    $form['config']['show_abbreviated_directions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show abbreviated wind directions'),
      '#default_value' => $savedConfig['show_abbreviated_directions'] ?? $defaultConfig['show_abbreviated_directions'],
      '#description' => $this->t('Displays abbreviated wind directions like N, SE, or W instead of North, Southeast, or West.'),
    ];
    $form['config']['show_directions_degree'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show degrees of wind directions'),
      '#default_value' => $savedConfig['show_directions_degree'] ?? $defaultConfig['show_directions_degree'],
      '#description' => $this->t('Displays the degrees of wind directions, for example, North (20°).'),
    ];
    $form['type'] = [
      '#type' => 'value',
      '#value' => $display_type,
    ];
    $form['number'] = [
      '#type' => 'value',
      '#value' => $display_number,
    ];

    // Show a 'reset' button if editing the default or user display.
    if (in_array($display_type, [
      WeatherDisplayInterface::DEFAULT_TYPE,
      WeatherDisplayInterface::USER_TYPE,
    ])) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#weight' => 10,
        '#submit' => ['::submitForm', '::save'],
      ];
    }

    // Use different path for delete form for non-admin user.
    if ($display_type == WeatherDisplayInterface::USER_TYPE) {
      $form['config']['displays_for_everyone'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Displays your weather for another users'),
        '#default_value' => $savedConfig['displays_for_everyone'] ?? FALSE,
        '#description' => $this->t('Displays your weather block for all users(on User page)'),
      ];
      $form["actions"]["delete"]["#url"] = Url::fromRoute('weather.user.weather_display.delete_form', [
        'user' => $display_number,
        'weather_display' => $this->entity->id(),
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggeredBy = $form_state->getTriggeringElement();
    if ($triggeredBy && $triggeredBy['#id'] == 'edit-reset') {
      $defaultConfig = $this->weatherHelperService->getDefaultConfig();
      $form_state->setValue('config', $defaultConfig);
    }
    Cache::invalidateTags(['config:weather.settings']);
    $this->blockManager->clearCachedDefinitions();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $type = $form_state->getValue('type');
    $display_number = $form_state->getValue('number');

    // Set display number before save.
    if ($form_state->getValue('number') == NULL) {
      $free_number = $this->getFreeDisplayNumber($type);
      $this->entity->set('number', $free_number);
    }

    $this->entity->set('config', $form_state->getValue('config'));

    // Make sure we have only one instance of display with type 'default'.
    if ($type == WeatherDisplayInterface::DEFAULT_TYPE) {
      $defaultDisplay = $this->weatherDisplayStorage->loadByProperties([
        'type' => WeatherDisplayInterface::DEFAULT_TYPE,
      ]);
      $defaultDisplay = reset($defaultDisplay);
      if ($defaultDisplay instanceof WeatherDisplayInterface) {
        $this->entity->id = $defaultDisplay->id();
        $this->entity->enforceIsNew(FALSE);
      }
    }
    // Make sure only one Display per user.
    elseif ($type == WeatherDisplayInterface::USER_TYPE) {
      $userDisplayExists = $this->weatherDisplayStorage->loadByProperties([
        'type' => WeatherDisplayInterface::USER_TYPE,
        'number' => $this->currentUser()->id(),
      ]);
      $userDisplay = reset($userDisplayExists);
      if ($userDisplay instanceof WeatherDisplayInterface) {
        $this->entity->id = $userDisplay->id();
        $this->entity->enforceIsNew(FALSE);
      }
    }
    // Make sure to update an existing system-wide display.
    elseif ($type == WeatherDisplayInterface::SYSTEM_WIDE_TYPE) {
      $displayExists = $this->weatherDisplayStorage->loadByProperties([
        'type' => WeatherDisplayInterface::SYSTEM_WIDE_TYPE,
        'number' => $display_number,
      ]);
      $systemwideDisplay = reset($displayExists);
      if ($systemwideDisplay instanceof WeatherDisplayInterface) {
        $this->entity->id = $systemwideDisplay->id();
        $this->entity->enforceIsNew(FALSE);
      }
    }

    $status = parent::save($form, $form_state);
    if ($status == SAVED_NEW) {
      $message = $this->t('Created new Weather display');
    }
    else {
      $message = $this->t('Updated existing Weather display');
    }

    $this->messenger()->addStatus($message);

    switch ($this->entity->type->value) {
      case WeatherDisplayInterface::USER_TYPE:
        $form_state->setRedirectUrl(Url::fromRoute('weather.user.settings', ['user' => $this->entity->number->value]));
        break;

      default:
        $form_state->setRedirectUrl(Url::fromRoute('weather.settings'));
        break;
    }

    return $status;
  }

  /**
   * Finds first free display number for given display type.
   */
  protected function getFreeDisplayNumber(string $displayType): int {
    // User display ID is always equal UID.
    if ($displayType == WeatherDisplayInterface::USER_TYPE) {
      return $this->currentUser()->id();
    }

    // Find next number for system-wide display.
    $used_numbers = Database::getConnection()
      ->select('weather_display', 'wd')
      ->fields('wd', ['number'])
      ->condition('type', $displayType)
      ->execute();

    $free_number = 1;
    foreach ($used_numbers as $row) {
      if ($row->number > $free_number) {
        break;
      }
      else {
        $free_number++;
      }
    }
    return $free_number;
  }

}
