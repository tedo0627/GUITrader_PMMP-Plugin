<?php

namespace trader;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;

use trader\listener\EventListener;
use trader\listener\InventoryListener;

class GUITrader extends PluginBase {

    private $request;

    public function onEnable() {
        $this->request = new RequestManager($this);

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new InventoryListener(), $this);
    }

    public function getRequest(): RequestManager {
        return $this->request;
    }
}