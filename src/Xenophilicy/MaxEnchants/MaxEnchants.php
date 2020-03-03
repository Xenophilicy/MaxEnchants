<?php
# MADE BY:
#  __    __                                          __        __  __  __                     
# /  |  /  |                                        /  |      /  |/  |/  |                    
# $$ |  $$ |  ______   _______    ______    ______  $$ |____  $$/ $$ |$$/   _______  __    __ 
# $$  \/$$/  /      \ /       \  /      \  /      \ $$      \ /  |$$ |/  | /       |/  |  /  |
#  $$  $$<  /$$$$$$  |$$$$$$$  |/$$$$$$  |/$$$$$$  |$$$$$$$  |$$ |$$ |$$ |/$$$$$$$/ $$ |  $$ |
#   $$$$  \ $$    $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |$$ |$$ |$$ |      $$ |  $$ |
#  $$ /$$  |$$$$$$$$/ $$ |  $$ |$$ \__$$ |$$ |__$$ |$$ |  $$ |$$ |$$ |$$ |$$ \_____ $$ \__$$ |
# $$ |  $$ |$$       |$$ |  $$ |$$    $$/ $$    $$/ $$ |  $$ |$$ |$$ |$$ |$$       |$$    $$ |
# $$/   $$/  $$$$$$$/ $$/   $$/  $$$$$$/  $$$$$$$/  $$/   $$/ $$/ $$/ $$/  $$$$$$$/  $$$$$$$ |
#                                         $$ |                                      /  \__$$ |
#                                         $$ |                                      $$    $$/ 
#                                         $$/                                        $$$$$$/

namespace Xenophilicy\MaxEnchants;

use pocketmine\command\{Command,CommandSender,PluginCommand};
use pocketmine\item\enchantment\{Enchantment,EnchantmentInstance};
use pocketmine\utils\{Config,TextFormat as TF};
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;

class MaxEnchants extends PluginBase implements Listener{

    public function onEnable(){
        $pluginManager = $this->getServer()->getPluginManager();
        $pluginManager->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        $this->config->getAll();
        $version = $this->config->get("VERSION");
        $this->pluginVersion = $this->getDescription()->getVersion();
        if($version < "1.0.0"){
            $this->getLogger()->warning("You have updated MaxEnchants to v".$this->pluginVersion." but have a config from v$version! Please delete your old config for new features to be enabled and to prevent unwanted errors! Plugin will remain disabled...");
            $pluginManager->disablePlugin($this);
        }
        $this->maxLevel = $this->config->get("Max-Level") >= 32767 ? 32767:$this->config->get("Max-Level");
        $this->cmdName = str_replace("/","",$this->config->getNested("Command.Name"));
        if($this->cmdName == null || $this->cmdName == ""){
            $this->getLogger()->critical("Invalid enchant command string found, disabling plugin...");
            $pluginManager->disablePlugin($this);
            return;
        } else{
            if(($cmdInstance = $this->getServer()->getCommandMap()->getCommand($this->cmdName)) instanceof Command){
                if($this->config->getNested("Command.Override")){
                    $cmdInstance->setLabel("_".$cmdInstance->getName());
                    $this->getServer()->getCommandMap()->unregister($cmdInstance);
                    $this->getLogger()->notice("Unregistered default command: /".$cmdInstance->getName());
                } else{
                    $this->getLogger()->critical("Command override disabled in config but command name is set to a default command, please change it to prevent interference with other commands! Plugin will remain disabled...");
                    $pluginManager->disablePlugin($this);
                    return;
                }
            }
            $cmd = new PluginCommand($this->cmdName , $this);
            $cmd->setDescription($this->config->getNested("Command.Description"));
            if($this->config->getNested("Command.Permission.Enabled")){
                $cmd->setPermission($this->config->getNested("Command.Permission.Node"));
            }
            $this->getServer()->getCommandMap()->register("MaxEnchants", $cmd, $this->cmdName);
            $this->getLogger()->notice("Registered MaxEnchants command: /".$this->cmdName);
        }
        $this->buildVanillaEnchantArray();
    }

    private function buildVanillaEnchantArray() : void{
        $this->vanillaEnchants = [];
        $reflection = new \ReflectionClass(Enchantment::class);
		$lastId = -1;
        foreach($reflection->getConstants() as $name => $id){
            $lastId++;
            if($id !== $lastId){
                break;
            }
            $enchantment = Enchantment::getEnchantment($id);
            if ($enchantment instanceof Enchantment) {
                $this->vanillaEnchants[$enchantment->getName()] = ucwords(strtolower(str_replace("_", " ", $name)));
            }
        }
        return;
    }

    private function enchantItem(CommandSender $sender, array $args) : void{
        if(count($args) < 2 || count($args) > 3){
            $sender->sendMessage(TF::RED."Usage: /".$this->cmdName." <player> <enchantment ID> [level]");
            return;
        }
        if(($player = $sender->getServer()->getPlayer($args[0])) === null){
            $sender->sendMessage(TF::RED."Player not found");
            return;
        }
        if(($item = $player->getInventory()->getItemInHand())->isNull()){
            $sender->sendMessage(TF::RED."Player must be holding an item");
            return;
        }
        if(is_numeric($args[1])){
            $enchantment = Enchantment::getEnchantment((int) $args[1]);
        } else{
            $enchantment = Enchantment::getEnchantmentByName($args[1]);
        }
        if(!($enchantment instanceof Enchantment)){
            $sender->sendMessage(TF::RED."There is no such enchantment with ID ".TF::YELLOW.$args[1]);
            return;
        }
        if(isset($args[2])){
            if(!is_numeric($args[2])){
                $sender->sendMessage(TF::RED."Enchantment level must be numeric");
                return;
            } elseif(($desiredLevel = (int) $args[2]) > $this->maxLevel){
                $sender->sendMessage(TF::RED."Enchantment level must be less than ".TF::YELLOW.$this->maxLevel);
                return;
            } elseif($desiredLevel < 1){
                $sender->sendMessage(TF::RED."Enchantment level must be greater than ".TF::YELLOW."1");
                return;
            } else{
                $level = $args[2];
            }
        } else{
            $level = 1;
        }
        $item->addEnchantment(new EnchantmentInstance($enchantment, $level));
        $player->getInventory()->setItemInHand($item);
        $enchantmentName = $this->vanillaEnchants[$enchantment->getName()] ?? $enchantment->getName();
        $this->getServer()->broadcastMessage(TF::GREEN."Successfully added ".TF::BLUE.$enchantmentName.TF::GREEN." level ".TF::BLUE.$level.TF::GREEN." for ".$player->getName());
        return;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() == "maxenchants"){
            $sender->sendMessage(TF::GRAY."---".TF::GOLD." MaxEnchants ".TF::GRAY."---");
            $sender->sendMessage(TF::YELLOW."Version: ".TF::AQUA.$this->pluginVersion);
            $sender->sendMessage(TF::YELLOW."Description: ".TF::AQUA."Enable enchants up to level 255");
            $sender->sendMessage(TF::YELLOW."Command: ".TF::BLUE."/".$this->cmdName." <player> <enchantment ID> [level]");
            $sender->sendMessage(TF::GRAY."-------------------");
        }
        if($command->getName() == $this->cmdName){
            if($sender instanceof Player){
                $this->enchantItem($sender, $args);
            } else{
                $sender->sendMessage(TF::RED."This is an in-game command only!");
            }
        }
        return true;
    }
}