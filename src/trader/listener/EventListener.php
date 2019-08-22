<?php

namespace trader\listener;

use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;

use trader\GUITrader;
use trader\inventory\TradeInventory;

class EventListener implements Listener {

    private $trader;

    public function __construct(GUITrader $trader) {
        $this->trader = $trader;
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $inventory = $this->trader->getRequest()->getTradeInventory($player);
        if ($inventory == null) {
            return;
        }

        if ($player->getWindowId($inventory) != -1) {
            $player->removeWindow($inventory);
        }
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        if (!($packet instanceof InventoryTransactionPacket)) {
            return;
        }

        if ($packet->transactionType != InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) {
            return;
        }

        if ($packet->trData->actionType != InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_INTERACT) {
            return;
        }

        $sender = $event->getPlayer();
        if (!$sender->isSneaking()) {
            return;
        }

        $target = $sender->getLevel()->getEntity($packet->trData->entityRuntimeId);
        if (!($target instanceof Player)) {
            return;
        }

        $request = $this->trader->getRequest();
        $senderName = $sender->getName();
        $targetName = $target->getName();

        if ($request->existRequest($target, $sender)) {
            $request->acceptRequest($target, $sender);
            
            $sender->sendMessage("§b" . $targetName . " とのトレードを開始します");
            $target->sendMessage("§b" . $senderName . " とのトレードを開始します");
            return;
        }

        if (!$request->existRequest($sender, $target)) {
            $request->sendRequest($sender, $target);
        
            $sender->sendMessage("§b" . $targetName . " にトレードのリクエストを送りました");
            $target->sendMessage("§b" . $senderName . " からトレードのリクエストが届きました");
            $target->sendMessage("§bスニークして " . $senderName . " をタップすると、トレードを開始します");
            return;
        }

        $sender->sendMessage("§4既にトレードのリクエストを送信済みです");
    }
}