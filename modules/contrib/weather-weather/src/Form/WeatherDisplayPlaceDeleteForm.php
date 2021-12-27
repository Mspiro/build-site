<?php

namespace Drupal\weather\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\weather\Entity\WeatherDisplayInterface;

/**
 * Provides a form for deleting a weather_display_place entity.
 *
 * @ingroup weather_display_place
 */
class WeatherDisplayPlaceDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are sure you want to remove this weather display place?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('weather.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $this->entity->displayed_name->value;
    $display_type = $this->entity->display_type->value;

    $this->entity->delete();

    $this->messenger()->addStatus($this->t('Weather display place @name, was removed.', ['@name' => $name]));

    switch ($display_type) {
      case WeatherDisplayInterface::USER_TYPE:
        $form_state->setRedirectUrl(Url::fromRoute('weather.user.settings', ['user' => $this->entity->display_number->value]));
        break;

      default:
        $form_state->setRedirectUrl(Url::fromRoute('weather.settings'));
        break;
    }

    parent::submitForm($form, $form_state);
  }

}
