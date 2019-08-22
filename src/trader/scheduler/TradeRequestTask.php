<?php

namespace trader\scheduler;

use pocketmine\Player;
use pocketmine\scheduler\Task;

use trader\GUITrader;

class TradeRequestTask extends Task {

    private $trader;

    private $sender;
    private $target;

    public function __construct(GUITrader $trader, Player $sender, Player $target) {
        $this->trader = $trader;

        $this->sender = $sender;
        $this->target = $target;
    }

    public function onRun(int $currentTick) {
        $this->trader->getRequest()->removeRequest($this->sender, $this->target);

        $this->sender->sendMessage("§4" . $this->target->getName() . " とのトレードのリクエストの有効期限が切れました");
        $this->target->sendMessage("§4" . $this->sender->getName() . " とのトレードのリクエストの有効期限が切れました");
    }
}