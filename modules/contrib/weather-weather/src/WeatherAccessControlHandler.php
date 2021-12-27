<?php

namespace Drupal\weather;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\weather\Entity\WeatherDisplayInterface;

/**
 * Access controller for weather entities.
 */
class WeatherAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return $this->commonAccessCheck($account, $entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return $this->commonAccessCheck($account);
  }

  /**
   * For all weather entities we do the same access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Entity interface.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
   *   Access result,
   */
  protected function commonAccessCheck(AccountInterface $account, EntityInterface $entity = NULL) {
    // Allow everything for administrators.
    if ($account->hasPermission('administer site configuration')) {
      return AccessResult::allowed();
    }

    // If user can administer own weather block - allow only some entities.
    $entityTypesAllowed = [
      'weather_display',
      'weather_display_place',
    ];
    if ($account->hasPermission('administer custom weather block') && in_array($this->entityTypeId, $entityTypesAllowed)) {
      // In case we updating entity.
      if ($entity instanceof EntityInterface) {

        $typeFieldName = $this->entityTypeId == 'weather_display' ? 'type' : 'display_type';
        $ownerFieldName = $this->entityTypeId == 'weather_display' ? 'number' : 'display_number';

        $type = $entity->{$typeFieldName}->value;
        $owner = $entity->{$ownerFieldName}->value;

        return AccessResult::allowedIf($type == WeatherDisplayInterface::USER_TYPE && $owner == $account->id());
      }

      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

}
