<?php

namespace Drupal\weather\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\weather\Entity\WeatherDisplayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a weather_display entity.
 *
 * @ingroup weather_display
 */
class WeatherDisplayDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * Block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * WeatherDisplayDeleteForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, BlockManagerInterface $block_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->blockManager = $block_manager;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.block')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are sure you want to remove this weather display?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Related block and display places will be removed as well. This action cannot be undone.');
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
    $display_type = $this->entity->type->value;
    $display_number = $this->entity->number->value;

    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Weather display was removed.'));

    switch ($display_type) {
      case WeatherDisplayInterface::USER_TYPE:
        $form_state->setRedirectUrl(Url::fromRoute('weather.user.settings', ['user' => $display_number]));
        break;

      default:
        $form_state->setRedirectUrl(Url::fromRoute('weather.settings'));
        break;
    }
    $this->blockManager->clearCachedDefinitions();
    parent::submitForm($form, $form_state);
  }

}
