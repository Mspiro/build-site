<?php

namespace Drupal\weather\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Drupal\weather\Entity\WeatherDisplayPlaceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the weather_display_place entity edit forms.
 *
 * @ingroup weather_display_place
 */
class WeatherDisplayPlaceForm extends ContentEntityForm {

  /**
   * Weather place storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $weatherPlaceStorage;

  /**
   * WeatherDisplayForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->weatherPlaceStorage = $entityTypeManager->getStorage('weather_place');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $display_type = NULL, $display_number = NULL, WeatherDisplayPlaceInterface $weather_display_place = NULL) {
    // Check if we have weather places to connect with displays.
    $weatherPlacesExists = $this->weatherPlaceStorage->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if (empty($weatherPlacesExists)) {
      $form['no_places_added'] = [
        '#markup' => $this->t('You do not have any weather places in system. Go to <a href="@url">settings page</a> and run weather places import', [
          '@url' => Url::fromRoute('weather.settings')
            ->toString(),
        ]),
      ];
      return $form;
    }

    if ($display_type == WeatherDisplayInterface::USER_TYPE) {
      $display_number = $this->currentUser()->id();
    }

    $form = parent::buildForm($form, $form_state);

    // If we are on edit form, display type and number not passed here.
    if ($weather_display_place instanceof WeatherDisplayPlaceInterface) {
      $display_type = $weather_display_place->display_type->value;
      $display_number = $weather_display_place->display_number->value;
    }

    // If the place exists, get the configuration.
    // If it does not exist - get the default place configuration.
    $settings = $this->getLocationSettings($weather_display_place);
    if (!empty($form_state->getValue('country'))) {
      $settings['country'] = $form_state->getValue('country');
    }
    $settings['places'] = $this->getAvailablePlacesOptions($settings['country']);
    $form['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#description' => $this->t('Select a country to narrow down your search.'),
      '#default_value' => $settings['country'],
      '#options' => $this->getAvailableCountriesOptions(),
      '#ajax' => [
        'callback' => '::countryAjaxCallback',
        'wrapper' => 'weather_place_replace',
      ],
    ];
    $form['geoid'] = [
      '#type' => 'select',
      '#title' => $this->t('Place'),
      '#description' => $this->t('Select a place in that country for the weather display.'),
      '#default_value' => $settings['geoid'],
      '#options' => $settings['places'],
      '#prefix' => '<div id="weather_place_replace">',
      '#ajax' => [
        'callback' => '::placeAjaxCallback',
        'wrapper' => 'weather_displayed_name_replace',
      ],
    ];
    $form['displayed_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Displayed name for the selected place'),
      '#default_value' => $settings['displayed_name'],
      '#description' => $this->t('You may enter another name for the place selected above.'),
      '#required' => TRUE,
      '#size' => '30',
      '#prefix' => '<div id="weather_displayed_name_replace">',
      '#suffix' => '</div></div>',
    ];
    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $settings['weight'],
      '#description' => $this->t('Optional. In the block, the heavier locations will sink and the lighter locations will be positioned nearer the top. Locations with equal weights are sorted alphabetically.'),
    ];
    $form['display_type'] = [
      '#type' => 'value',
      '#value' => $display_type,
    ];
    $form['display_number'] = [
      '#type' => 'value',
      '#value' => $display_number,
    ];

    // If the form is regenerated during an AJAX callback, get the
    // country selected by the user.
    if ($triggeredBy = $form_state->getTriggeringElement()) {
      $settings['country'] = $form_state->getValue('country');

      if ($triggeredBy["#name"] == 'country') {
        $settings['places'] = $this->getAvailablePlacesOptions($settings['country']);
        $settings['geoid'] = key($settings['places']);
        $settings['displayed_name'] = $settings['places'][$settings['geoid']];
        $form['geoid']['#options'] = $settings['places'];
        $form['geoid']['#value'] = $settings['geoid'];
        $form['displayed_name']['#value'] = $settings['displayed_name'];
      }
      if ($triggeredBy["#name"] == 'geoid') {
        $settings['displayed_name'] = $settings['places'][$form_state->getValue('geoid')];
        $form['displayed_name']['#value'] = $settings['displayed_name'];
      }
    }

    // Use different path for delete form for non-admin user.
    if ($display_type == WeatherDisplayInterface::USER_TYPE && $weather_display_place instanceof WeatherDisplayPlaceInterface) {
      $form["actions"]["delete"]["#url"] = Url::fromRoute('weather.user.weather_display_place.delete_form', [
        'user' => $display_number,
        'weather_display_place' => $weather_display_place->id(),
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    // Invalidate related weather display cache, once place is saved.
    $display_type = $this->entity->display_type->value;
    $display_number = $this->entity->display_number->value;
    $displays = $this->entityTypeManager->getStorage('weather_display')
      ->loadByProperties([
        'type' => $display_type,
        'number' => $display_number,
      ]);
    foreach ($displays as $display) {
      $tags = $display->getCacheTags();

      // Separately invalidate cache_tag for user.
      // For correct page update after adding new location.
      $tags[] = 'weather_display:' . $this->currentUser()->id();
      Cache::invalidateTags($tags);
    }

    // Show message.
    if ($status == SAVED_NEW) {
      $message = $this->t('Added new place to weather display');
    }
    else {
      $message = $this->t('Updated existing place in weather display');
    }
    $this->messenger()->addStatus($message);

    switch ($this->entity->display_type->value) {
      case WeatherDisplayInterface::USER_TYPE:
        $form_state->setRedirectUrl(Url::fromRoute('weather.user.settings', ['user' => $this->entity->display_number->value]));
        break;

      default:
        $form_state->setRedirectUrl(Url::fromRoute('weather.settings'));
        break;
    }

    return $status;
  }

  /**
   * Finds location settings for display Place form.
   *
   * @param \Drupal\weather\Entity\WeatherDisplayPlaceInterface|null $weather_display_place
   *   Weather display place entity.
   *
   * @return array
   *   Settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLocationSettings(WeatherDisplayPlaceInterface $weather_display_place = NULL) {
    // Set defaults.
    $settings = [
      'geoid' => 'geonames_703448',
      'displayed_name' => 'Kyiv',
      'weight' => 0,
      'country' => 'Ukraine',
    ];

    if ($weather_display_place instanceof WeatherDisplayPlaceInterface) {
      foreach ($settings as $field_name => $value) {
        if ($weather_display_place->hasField($field_name)) {
          $type = $weather_display_place->get($field_name)
            ->getFieldDefinition()
            ->getType();
          if ($type == 'entity_reference') {
            $settings[$field_name] = $weather_display_place->{$field_name}->target_id;
          }
          else {
            $settings[$field_name] = $weather_display_place->{$field_name}->value;
          }
        }
      }

      // Find related country.
      $place = $this->entityTypeManager->getStorage('weather_place')
        ->load($settings['geoid']);
      $settings['country'] = $place->country->value;
    }

    return $settings;
  }

  /**
   * Builds array of options for 'country' select.
   */
  protected function getAvailableCountriesOptions() {
    $result = $this->weatherPlaceStorage->getAggregateQuery()
      ->accessCheck()
      ->groupBy('country')
      ->sort('country', 'ASC')
      ->execute();

    foreach ($result as $row) {
      $countries[$row['country']] = $row['country'];
    }

    return $countries;
  }

  /**
   * Builds array of options for 'place' select.
   */
  protected function getAvailablePlacesOptions(string $country) {
    $places = [];
    $result = $this->weatherPlaceStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('country', $country)
      ->sort('name', 'ASC')
      ->execute();

    foreach ($result as $id) {
      $place = $this->weatherPlaceStorage->load($id);
      $places[$place->geoid->value] = $place->name->value;
    }

    return $places;
  }

  /**
   * AJAX callback for location settings form.
   */
  public function countryAjaxCallback(array &$form, FormStateInterface $form_state) {
    $ret['geoid'] = $form['geoid'];
    $ret['displayed_name'] = $form['displayed_name'];

    return $ret;
  }

  /**
   * AJAX callback for location settings form.
   */
  public function placeAjaxCallback($form, $form_state) {
    return $form['displayed_name'];
  }

}
