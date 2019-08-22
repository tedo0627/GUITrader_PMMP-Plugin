<?php

namespace trader\listener;

use pocketmine\event\Listener;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;

use trader\inventory\TradeInventory;

class InventoryListener implements Listener {

    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();

        $actions = $transaction->getActions();
        foreach ($actions as $action) {
            if (!($action instanceof SlotChangeAction)) {
                continue;
            }

            $inventory = $action->getInventory();
            if (!($inventory instanceof TradeInventory)) {
                continue;
            }

            $player = $transaction->getSource();
            $slot = $action->getSlot();
            if ($inventory->onClick($player, $slot)) {
                $event->setCancelled();
                return;
            }
        }
    }
}