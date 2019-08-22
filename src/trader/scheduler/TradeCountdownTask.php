<?php

namespace trader\scheduler;

use pocketmine\item\Item;
use pocketmine\scheduler\Task;

use trader\inventory\TradeInventory;

class TradeCountdownTask extends Task {

    private $inventory;
    private $target;

    private $count = 6;

    public function __construct(TradeInventory $inventory, TradeInventory $target) {
        $this->inventory = $inventory;
        $this->target = $target;
    }

    public function onRun(int $currentTick) {
        $this->count--;
        if ($this->count == 0) {
            $this->inventory->trade();
            return;
        }

        $item = Item::get(Item::DYE, 9, $this->count);
        $item->setCustomName("§b残り " . $this->count . " 秒");
        $item->setLore(["キャンセルする場合は、左の赤の羊毛をクリックするか", "インベントリーを閉じてください"]);

        $this->inventory->setItem(3, $item);
        $this->inventory->setItem(5, $item);
        $this->target->setItem(3, $item);
        $this->target->setItem(5, $item);
    }
}