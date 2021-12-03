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

use pocketmine\command\{Command, CommandSender, PluginCommand};
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\{Enchantment, EnchantmentInstance, StringToEnchantmentParser};
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\{Config, TextFormat as TF};
use ReflectionClass;

/**
 * Class MaxEnchants
 * @package Xenophilicy\MaxEnchants
 */
class MaxEnchants extends PluginBase implements Listener {
    
    /** @var string*/
    private string $pluginVersion;
    /** @var array*/
    private array $customMaxLevels;
    /** @var mixed|null*/
    private mixed $cmdName;
    /** @var Config*/
    private Config $config;
    /** @var mixed*/
    private mixed $maxLevel;
    /** @var array*/
    private array $vanillaEnchants;
    
    public function onEnable(): void{
        $pluginManager = $this->getServer()->getPluginManager();
        $pluginManager->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $version = $this->config->get("VERSION");
        $this->pluginVersion = $this->getDescription()->getVersion();
        if($version < "1.2.0"){
            $this->getLogger()->warning("You have updated MaxEnchants to v" . $this->pluginVersion . " but have a config from v$version! Please delete your old config for new features to be enabled and to prevent unwanted errors! Plugin will remain disabled...");
            $pluginManager->disablePlugin($this);
        }
        $include = $this->config->getNested("Broadcast.SendTo");
        if($include !== "" && !is_null($include)){
            foreach($include as $inclusion){
                if(strtolower($inclusion) == "console" || strtolower($inclusion) == "sender" || strtolower($inclusion) == "target"){
                    continue;
                }else{
                    $this->getLogger()->critical("Invalid message recipient list, allowed recipients are console, sender, and target. Plugin will remain disabled...");
                    $pluginManager->disablePlugin($this);
                    return;
                }
            }
        }
        $this->buildVanillaEnchantArray();
        $maxLevels = $this->config->get("Custom-Max-Levels");
        if($maxLevels !== false){
            if($maxLevels == "" || !is_array($maxLevels)){
                $this->getLogger()->critical("Invalid custom max levels array found, disabling plugin...");
                $pluginManager->disablePlugin($this);
                return;
            }else{
                foreach($maxLevels as $id => $level){
                    if(!is_int($id) || !is_int($level) || !$this->isValidEnchant($id)){
                        $this->getLogger()->warning("Invalid max level found at $id, it will not be included!");
                    }else{
                        $enchantment = EnchantmentIdMap::getInstance()->fromId($id);
                        $this->customMaxLevels[$enchantment->getName()] = $level;
                    }
                }
            }
        }
        $this->maxLevel = $this->config->get("Max-Level") >= 32767 ? 32767 : $this->config->get("Max-Level");
        $this->cmdName = str_replace("/", "", $this->config->getNested("Command.Name"));
        if($this->cmdName == ""){
            $this->getLogger()->critical("Invalid enchant command string found, disabling plugin...");
            $pluginManager->disablePlugin($this);
        }else{
            if(($cmdInstance = $this->getServer()->getCommandMap()->getCommand($this->cmdName)) instanceof Command){
                if($this->config->getNested("Command.Override")){
                    $cmdInstance->setLabel("_" . $cmdInstance->getName());
                    $this->getServer()->getCommandMap()->unregister($cmdInstance);
                }else{
                    $this->getLogger()->critical("Command override disabled in config but command name is set to a default command, please change it to prevent interference with other commands! Plugin will remain disabled...");
                    $pluginManager->disablePlugin($this);
                    return;
                }
            }
            $cmd = new PluginCommand($this->cmdName, $this, $this);
            $cmd->setDescription($this->config->getNested("Command.Description"));
            if($this->config->getNested("Command.Permission.Enabled")){
                $cmd->setPermission($this->config->getNested("Command.Permission.Node"));
            }
            $this->getServer()->getCommandMap()->register("MaxEnchants", $cmd, $this->cmdName);
        }
    }
    
    private function buildVanillaEnchantArray(): void{
        $this->vanillaEnchants = [];
        $reflection = new ReflectionClass(Enchantment::class);
        $lastId = -1;
        foreach($reflection->getConstants() as $name => $id){
            $lastId++;
            if($id !== $lastId){
                break;
            }
            $enchantment = EnchantmentIdMap::getInstance()->fromId($id);
            if($enchantment instanceof Enchantment){
                $this->vanillaEnchants[$enchantment->getName()] = [ucwords(strtolower(str_replace("_", " ", $name))), $lastId];
            }
        }
    }
    
    /**
     * @param $id
     * @return bool
     */
    private function isValidEnchant($id): bool{
        foreach(array_values($this->vanillaEnchants) as $ench){
            if($ench[1] === $id){
                return true;
            }
        }
        return false;
    }
    
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        if($command->getName() == "maxenchants"){
            $sender->sendMessage(TF::GRAY . "---" . TF::GOLD . " MaxEnchants " . TF::GRAY . "---");
            $sender->sendMessage(TF::YELLOW . "Version: " . TF::AQUA . $this->pluginVersion);
            $sender->sendMessage(TF::YELLOW . "Description: " . TF::AQUA . "Enable enchants up to level 255");
            $sender->sendMessage(TF::YELLOW . "Command: " . TF::BLUE . "/" . $this->cmdName . " <player> <enchantment ID> [level]");
            $sender->sendMessage(TF::GRAY . "-------------------");
        }
        if($command->getName() == $this->cmdName){
            $this->enchantItem($sender, $args);
        }
        return true;
    }
    
    private function enchantItem(CommandSender $sender, array $args): void{
        if(count($args) < 2 || count($args) > 3){
            $sender->sendMessage(TF::RED . "Usage: /" . $this->cmdName . " <player> <enchantment ID> [level]");
            return;
        }
        if(($player = $sender->getServer()->getPlayerByPrefix($args[0])) === null){
            $sender->sendMessage(TF::RED . "Player not found");
            return;
        }
        if(($item = $player->getInventory()->getItemInHand())->isNull()){
            $sender->sendMessage(TF::RED . "Player must be holding an item");
            return;
        }
        if(is_numeric($args[1])){
            $enchantment = EnchantmentIdMap::getInstance()->fromId((int)$args[1]);
        }else{
            $enchantment = StringToEnchantmentParser::getInstance()->parse($args[1]);
        }
        if(!($enchantment instanceof Enchantment)){
            $sender->sendMessage(TF::RED . "There is no such enchantment with ID " . TF::YELLOW . $args[1]);
            return;
        }
        if(isset($args[2])){
            if(!is_numeric($args[2])){
                $sender->sendMessage(TF::RED . "Enchantment level must be numeric");
                return;
            }elseif(($desiredLevel = (int)$args[2]) < 1){
                $sender->sendMessage(TF::RED . "Enchantment level must be greater than " . TF::YELLOW . "1");
                return;
            }
            if(in_array($enchantment->getName(), array_keys($this->customMaxLevels))){
                $max = $this->customMaxLevels[$enchantment->getName()];
            }else{
                $max = $this->maxLevel;
            }
            if($desiredLevel > $max){
                $sender->sendMessage(TF::RED . "Enchantment level can be at most " . TF::YELLOW . $max);
                return;
            }else{
                $level = $args[2];
            }
        }else{
            $level = 1;
        }
        $item->addEnchantment(new EnchantmentInstance($enchantment, $level));
        $player->getInventory()->setItemInHand($item);
        $enchantmentName = $this->vanillaEnchants[$enchantment->getName()][0] ?? $enchantment->getName();
        $this->broadcast($enchantmentName, $level, $player, $sender);
    }
    
    /**
     * @param string $name
     * @param string $level
     * @param Player $target
     * @param $sender
     */
    private function broadcast(string $name, string $level, Player $target, $sender): void{
        $msgString = $this->config->getNested("Broadcast.Message");
        $include = $this->config->getNested("Broadcast.SendTo");
        $msgString = str_replace("{name}", $name, $msgString);
        $msgString = str_replace("{level}", $level, $msgString);
        $msgString = str_replace("{target}", $target->getName(), $msgString);
        if($sender instanceof Player){
            $msgString = str_replace("{sender}", $sender->getName(), $msgString);
        }else{
            $msgString = str_replace("{sender}", "CONSOLE", $msgString);
        }
        $msgString = str_replace("&", "§", $msgString);
        if($include !== "" && !is_null($include) && $include !== []){
            $include = strtolower(implode(",", $include));
            if(str_contains($include, "console")){
                $this->getLogger()->info(TF::clean($msgString));
            }
            if(str_contains($include, "target")){
                $target->sendMessage($msgString);
            }
            if(str_contains($include, "sender") && $sender instanceof Player){
                $sender->sendMessage($msgString);
            }
        }
    }
}
