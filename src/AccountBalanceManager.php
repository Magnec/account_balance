<?php

namespace Drupal\account_balance;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Handles balance calculations for account transactions.
 */
class AccountBalanceManager {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Updates the balance of a referenced account after a transaction.
   */
  public function updateBalanceOnTransactionChange(EntityInterface $transaction, $is_update = FALSE) {
    $bundle = $transaction->bundle();
    if (!in_array($bundle, ['incoming', 'outgoing'])) {
      return;
    }

    $account = $transaction->get('field_cari')->entity;
    if (!$account || !$account->hasField('field_balance')) {
      return;
    }

    $balance_field = $account->get('field_balance');
    $current_balance = (float) $balance_field->value;
    $transaction_price = (float) $transaction->get('field_price')->value;

    if ($is_update && !$transaction->original->isNew()) {
      $old_price = (float) $transaction->original->get('field_price')->value;
      if ($bundle === 'incoming') {
        $current_balance -= $old_price;
      }
      else {
        $current_balance += $old_price;
      }
    }

    if ($bundle === 'incoming') {
      $current_balance += $transaction_price;
    }
    else {
      $current_balance -= $transaction_price;
    }

    $balance_field->value = number_format($current_balance, 2, '.', '');

    try {
      $account->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('account_balance')->error('Cari bakiyesi güncellenemedi: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Reverts the balance of an account when a transaction is deleted.
   */
  public function revertBalanceOnTransactionDelete(EntityInterface $transaction) {
    $bundle = $transaction->bundle();
    if (!in_array($bundle, ['incoming', 'outgoing'])) {
      return;
    }

    $account = $transaction->get('field_cari')->entity;
    if (!$account || !$account->hasField('field_balance')) {
      return;
    }

    $balance_field = $account->get('field_balance');
    $current_balance = (float) $balance_field->value;
    $transaction_price = (float) $transaction->get('field_price')->value;

    if ($bundle === 'incoming') {
      $current_balance -= $transaction_price;
    }
    else {
      $current_balance += $transaction_price;
    }

    $balance_field->value = number_format($current_balance, 2, '.', '');

    try {
      $account->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('account_balance')->error('Silmede cari güncelleme hatası: @msg', ['@msg' => $e->getMessage()]);
    }
  }
}
