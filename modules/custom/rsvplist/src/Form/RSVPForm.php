<?php

/**
 * @file
 * Contains \Drupal\rsvplist\Form\RSVPForm
 */

namespace Drupal\rsvplist\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use function PHPSTORM_META\type;

/**
 * Provides an RSVP Email form
 */

class RSVPForm extends FormBase
{
  /**
   * (@inheritdoc)
   */
  public function getFormId()
  {
    return 'rsvplist_email_form';
  }

  /**
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    $form['email'] = array(
      '#title' => 'Email Address',
      '#type' => 'textfield',
      '#size' => 25,
      '#description' => "we'll send updates to the email adress your provide.",
      '#required' => TRUE,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'RSVP',
    );
    $form['nid'] = array(
      '#type' => 'hidden',
      '#value' => $nid,
    );
    return $form; 
  }
  /**
   * (@inheritdoc)
   */

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    \Drupal::messenger()->addMessage('Test form is working');
  }
}
