<?php

namespace trader;

use pocketmine\Player;

use trader\GUITrader;
use trader\inventory\TradeInventory;
use trader\scheduler\TradeRequestTask;

class RequestManager {

    private $trader;

    private $request = [];

    private $inventory = [];

    public function __construct(GUITrader $trader) {
        $this->trader = $trader;
    }

    public function existRequest(Player $sender, Player $target): bool {
        $senderName = $sender->getName();
        $targetName = $target->getName();

        return array_key_exists($senderName, $this->request) && array_key_exists($targetName, $this->request[$senderName]);
    }

    public function sendRequest(Player $sender, Player $target): void {
        $senderName = $sender->getName();
        $targetName = $target->getName();

        if (!array_key_exists($senderName, $this->request)) {
            $this->request[$senderName] = [];
        }

        $handler = $this->trader->getScheduler()->scheduleDelayedTask(new TradeRequestTask($this->trader, $sender, $target), 20 * 30);
        $this->request[$senderName][$targetName] = $handler->getTaskId();
    }

    public function acceptRequest(Player $sender, Player $target): void {
        $senderName = $sender->getName();
        $targetName = $target->getName();

        $id = $this->request[$senderName][$targetName];
        $this->trader->getScheduler()->cancelTask($id);
        unset($this->request[$senderName][$targetName]);

        $senderInventory = new TradeInventory($this->trader, $sender, $target);
        $sender->addWindow($senderInventory);
        $this->inventory[$senderName] = $senderInventory;
        $targetInventory = new TradeInventory($this->trader, $target, $sender);
        $target->addWindow($targetInventory);
        $this->inventory[$targetName] = $targetInventory;

        $senderInventory->linkTargetInventory($targetInventory);
        $targetInventory->linkTargetInventory($senderInventory);
    }

    public function removeRequest(Player $sender, Player $target): void {
        $senderName = $sender->getName();
        $targetName = $target->getName();
        unset($this->request[$senderName][$targetName]);
    }

    public function getTradeInventory(Player $player): ?TradeInventory {
        $name = $player->getName();
        if (array_key_exists($name, $this->inventory)) {
            return $this->inventory[$name];
        }

        return null;
    }
}