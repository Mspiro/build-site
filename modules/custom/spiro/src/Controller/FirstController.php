<?php

/**
 * @file
 * Contains \Drupal\sprio\Controller\SpiroController.
 */

namespace Drupal\spiro\Controller;

use Drupal\Core\Controller\ControllerBase;

class FirstController extends ControllerBase
{
  public function content()
  {
    return array (
      '#type' => 'markup',
      '#markup' => 'This is my menu linked custom page',
    );
  }
}
