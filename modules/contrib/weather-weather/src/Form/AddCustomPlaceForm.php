<?php

namespace Drupal\weather\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\weather\Entity\WeatherPlaceInterface;
use Drupal\weather\Service\DataService;
use Drupal\weather\Service\HelperService;
use Drupal\weather\Service\ParserService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Weather settings for this site.
 */
class AddCustomPlaceForm extends FormBase {

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
   * Weather helper service.
   *
   * @var \Drupal\weather\Service\HelperService
   */
  protected $weatherHelper;

  /**
   * Parser service.
   *
   * @var \Drupal\weather\Service\ParserService
   */
  protected $weatherParser;

  /**
   * Constructs a \Drupal\weather\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager storage.
   * @param \Drupal\weather\Service\DataService $weatherDataService
   *   Weather data service.
   * @param \Drupal\weather\Service\HelperService $helperService
   *   Weather helper service.
   * @param \Drupal\weather\Service\ParserService $parserService
   *   Weather parser service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal messenegr service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DataService $weatherDataService, HelperService $helperService, ParserService $parserService, MessengerInterface $messenger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->weatherDisplayStorage = $entityTypeManager->getStorage('weather_display');
    $this->weatherDisplayPlaceStorage = $entityTypeManager->getStorage('weather_display_place');
    $this->weatherDataService = $weatherDataService;
    $this->weatherHelper = $helperService;
    $this->weatherParser = $parserService;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('weather.data_service'),
      $container->get('weather.helper'),
      $container->get('weather.parser'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'weather_settings_places';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $placesStorage = $this->entityTypeManager->getStorage('weather_place');

    $form['weather_yrno_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL of English weather forecast on yr.no'),
      '#description' => $this->t('Example: https://www.yr.no/place/Ukraine/Kiev/Kyiv/.'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save place'),
    ];

    // Create tables for modified places.
    $tables = [
      WeatherPlaceInterface::STATUS_ADDED => $this->t('Added places'),
      WeatherPlaceInterface::STATUS_MODIFIED => $this->t('Modified places'),
    ];
    foreach ($tables as $status => $caption) {
      $header = [
        $this->t('GeoID'),
        $this->t('Latitude'),
        $this->t('Longitude'),
        $this->t('Country'),
        $this->t('Name'),
        $this->t('Link'),
      ];
      $rows = [];
      $result = $placesStorage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', $status)
        ->sort('country', 'ASC')
        ->sort('name', 'ASC')
        ->execute();

      if (!empty($result)) {
        foreach ($placesStorage->loadMultiple($result) as $place) {
          $rows[] = [
            $place->geoid->value,
            $place->latitude->value,
            $place->longitude->value,
            $place->country->value,
            $place->name->value,
            $place->link->value,
          ];
        }
        $form['places'][] = [
          '#theme' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#caption' => $caption,
          '#empty' => $this->t('No places.'),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Remove whitespaces.
    $url = trim($form_state->getValue('weather_yrno_url'));

    // Check for the english version.
    if (substr($url, 0, 24) != 'https://www.yr.no/place/') {
      $form_state->setErrorByName('weather_yrno_url', $this->t('Please make sure to use the English version of the forecast, starting with "https://www.yr.no/<strong>place</strong>/".'));
    }

    list($country, $link) = $this->weatherHelper->parsePlaceUrl($url);

    $placeExists = $this->entityTypeManager
      ->getStorage('weather_place')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('country', $country)
      ->condition('link', $link)
      ->execute();

    if ($placeExists) {
      $form_state->setErrorByName('weather_yrno_url', $this->t('The place is already in the database'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('weather_yrno_url');
    $url = trim($url) . 'forecast.xml';

    if ($this->weatherParser->downloadForecast('', $url)) {
      $this->messenger->addStatus($this->t('The new place has been saved.'));
    }
    else {
      $this->messenger->addError($this->t('The download from the given URL did not succeed.'));
    }
  }

}
