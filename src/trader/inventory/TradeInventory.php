<?php

namespace trader\inventory;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\ContainerInventory;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\scheduler\Task;

use trader\GUITrader;
use trader\scheduler\TradeCountdownTask;

class TradeInventory extends ContainerInventory {

    private $trader;

    private $player;
    private $target;

    private $helper;

    private $inventory;

    private $countdownTaskId = -1;

    private $isReady = false;
    private $isClosed = false;

    public function __construct(GUITrader $trader, Player $player, Player $target) {
        $this->trader = $trader;

        $this->player = $player;
        $this->target = $target;

        $pos = $player->getLocation()->add(0, -2, 0)->floor();
        if ($player->getY() < 3) {
            $pos->add(0, 6, 0);
        }

        $this->helper = new FakeBlockHelper($trader, $pos, $this, $player, $target);

        parent::__construct($pos);

        $this->setDefaultItem();
    }

    public function getName(): string {
        return "TradeInventory";
    }

    public function getDefaultSize(): int {
        return 54;
    }

    public function getNetworkType(): int {
        return WindowTypes::CONTAINER;
    }
    
	public function onOpen(Player $who): void{
        BaseInventory::onOpen($who);

        $this->helper->openFakeChest();
	}

	public function onClose(Player $who): void {
        BaseInventory::onClose($who);

        $holder = $this->getHolder();
        if ($who->getWindowId($this) == -1) {
            $who->getLevel()->sendBlocks([$who], [$holder, $holder->add(1, 0, 0)]);
            return;
        }

        if ($this->isClosed) {
            return;
        }

        $this->isClosed = true;
        $this->target->removeWindow($this->inventory);

        for ($i = 9; $i < 50; ++$i) {
            if (3 < $i % 9) {
                continue;
            }

            $item = $this->getItem($i);
            if ($item->getId() != Item::AIR) {
                $who->getInventory()->addItem($item);
            }
        }
        $this->clearAll(false);

        $this->helper->closeFakeChest();
    }

    public function setItem(int $index, Item $item, bool $send = true): bool {
        $bool = parent::setItem($index, $item, $send);

        if ($index < 9 || 3 < $index % 9) {
            return $bool;
        }

        if ($this->inventory == null) {
            return $bool;
        }

        if ($item->getId() == Item::AIR) {
            $item = Item::get(Item::STAINED_GLASS_PANE, 7, 1)->setCustomName(" ");
        }
        $this->inventory->setItem($index + 5, $item);
        
        if ($this->isReady || $this->inventory->isReady) {
            $this->isReady = false;
            $this->inventory->isReady = false;

            $item = Item::get(Item::DYE, 8, 1)->setCustomName("§b準備中");
            $this->setItem(3, $item);
            $this->setItem(5, $item);
            $this->inventory->setItem(3, $item);
            $this->inventory->setItem(5, $item);
            $this->trader->getScheduler()->cancelTask($this->countdownTaskId);
        }
        return $bool;
    }

    public function onClick(Player $player, int $slot): bool {
        if ($slot == 2 || $slot == 3 || 3 < $slot % 9) {
            return true;
        }

        if ($slot == 0) {
            $this->isReady = true;
            $this->setItem(3, Item::get(Item::DYE, 10, 1)->setCustomName("§a準備が完了しました"));
            $this->inventory->setItem(5, Item::get(Item::DYE, 10, 1)->setCustomName("§a準備が完了しました"));
            $this->sendSound($player);

            if ($this->inventory->isReady) {
                $this->countdownTaskId = $this->trader->getScheduler()->scheduleRepeatingTask(new TradeCountdownTask($this, $this->inventory), 20)->getTaskId();
                $this->inventory->countdownTaskId = $this->countdownTaskId;
            }
            return true;
        }

        if ($slot == 1) {
            $player->removeWindow($this);
            $player->sendMessage("§4トレードをキャンセルしました");
            $this->target->sendMessage("§4トレードがキャンセルされました");
            $this->sendSound($player);
            return true;
        }

        return false;
    }

    public function linkTargetInventory(TradeInventory $inventory): void {
        $this->inventory = $inventory;
    }

    public function sendSound(Player $player): void {
        $pk = new PlaySoundPacket();
        $pk->soundName = "random.click";
        $pk->x = $player->getX();
        $pk->y = $player->getY();
        $pk->z = $player->getZ();
        $pk->volume = 1.0;
        $pk->pitch = 1.0;
        $player->dataPacket($pk);
    }

    public function trade(): void {
        $this->trader->getScheduler()->cancelTask($this->countdownTaskId);

        for ($i = 9; $i < 54; ++$i) {
            if (3 < $i % 9) {
                continue;
            }

            $item = $this->getItem($i);
            if ($item->getId() == Item::AIR) {
                continue;
            }
            $this->target->getInventory()->addItem($item);
        }
        
        for ($i = 9; $i < 54; ++$i) {
            if (3 < $i % 9) {
                continue;
            }

            $item = $this->inventory->getItem($i);
            if ($item->getId() == Item::AIR) {
                continue;
            }
            $this->player->getInventory()->addItem($item);
        }

        $this->clearAll(false);
        $this->inventory->clearAll(false);

        $this->player->removeWindow($this);

        $this->player->sendMessage("§bトレードが完了しました");
        $this->target->sendMessage("§bトレードが完了しました");
    }

    private function setDefaultItem(): void {
        $barrier = Item::get(-161, 0, 1);
        for ($i = 4; $i < 50; $i += 9) {
            $this->setItem($i, $barrier);
        }

        $glass = Item::get(Item::STAINED_GLASS_PANE, 7, 1);
        for ($i = 14; $i < 54; ++$i) {
            if (4 < $i % 9) {
                $this->setItem($i, $glass);
            }
        }

        $this->setItem(0, Item::get(Item::WOOL, 5, 1)->setCustomName("§aトレードを成立させる"));
        $this->setItem(1, Item::get(Item::WOOL, 14, 1)->setCustomName("§4トレードをキャンセルする"));
        $this->setItem(2, Item::get(Item::PAPER, 0, 1));
        $this->setItem(3, Item::get(Item::DYE, 8, 1)->setCustomName("§b準備中"));
        
        $this->setItem(5, Item::get(Item::DYE, 8, 1)->setCustomName("§b準備中"));
        $this->setItem(6, Item::get(Item::PAPER, 0, 1));
        $this->setItem(7, Item::get(Item::PAPER, 0, 1));
        $this->setItem(8, Item::get(Item::PAPER, 0, 1));

        for ($i = 0; $i < 54; ++$i) {
            $item = $this->getItem($i);
            $id = $item->getId();
            if ($id == Item::AIR || $id == Item::WOOL || $id == Item::DYE) {
                continue;
            }

            $item->setCustomName(" ");
            $this->setItem($i, $item);
        }
    }
}