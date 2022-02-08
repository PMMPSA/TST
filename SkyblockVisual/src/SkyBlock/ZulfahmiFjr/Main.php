<?php

namespace SkyBlock\ZulfahmiFjr;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\entity\Entity;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\level\Position;
use pocketmine\event\level\ChunkLoadEvent;
use pocketmine\tile\Tile;
use pocketmine\tile\Chest;
use pocketmine\level\Level;
use pocketmine\event\Event;
use pocketmine\item\Item;
use pocketmine\utils\UUID;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\math\Vector3;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;

use SkyBlock\ZulfahmiFjr\Entity\KingSlime;
use SkyBlock\ZulfahmiFjr\Task\Scoreboard;
use SkyBlock\ZulfahmiFjr\Form\SimpleForm;
use SkyBlock\ZulfahmiFjr\Form\CustomForm;

class Main extends PluginBase implements Listener{

    private static $instance;

    public $index = array();
    public $eid = array();
    public $mode = array();
    public $prefix = "§9§l§oSkyBlock§r§f: §6§o";

    public function onEnable(){
     $this->getLogger()->info("SkyBlock được làm bởi ZulfahmiFjr (ImHotep) và việt hóa bởi Hoangviphb99");
     self::$instance = $this;
     $this->saveDefaultConfig();
     $this->reloadConfig();
     $this->data = new Config($this->getDataFolder()."skyblock.yml", Config::YAML, array());
     GeneratorManager::addGenerator(SkyBlockGenerator::class, "basic", true);
     $this->getServer()->getPluginManager()->registerEvents($this, $this);
     Entity::registerEntity(KingSlime::class, true);
     $this->getScheduler()->scheduleRepeatingTask(new Scoreboard($this), 20);
    }

    public static function getInstance():Main{
     return self::$instance;
    }

    public function onCommand(CommandSender $p, Command $command, string $label,array $args):bool{
     if($command->getName() !== "skyblock") return false;
     if(!$p instanceof Player){
      $p->sendMessage($this->prefix."Please use this command in game!§r§f!");
      return false;
     }
     if(isset($args[0]) && $args[0] === "leaderboard"){
      if(!$p->isOp()){
       $p->sendMessage($this->prefix."Bạn không có quyền để sử dụng lệnh này!§r§f!");
       return false;
      }
      $this->mode[$p->getName()][$p->getLevel()->getFolderName()] = 3;
      $p->sendMessage($this->prefix."Bạn không có quyền để sử dụng lệnh này!§r§f!");
      return true;
     }
     if(!$this->hasSkyBlockIsland($p)){
      $this->createSkyBlock($p);
     }else{
      $this->menuSkyBlock($p);
     }
     return true;
    }

    public function createSkyBlock(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->chooseNewIsland($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eSKYBLOCK");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eBạn chưa có đảo nào§r§f, hãy tạo đảo để chơi nhé :D§r§f\n".str_repeat("=", 33));
     $form->addButton("§lTẠO ĐẢO\n§l§e»» §r§f§oNhấn để xác nhận", "textures/ui/icon_recipe_nature");
     $p->sendForm($form);
    }

    public function chooseNewIsland(Player $p){
     $models = $this->getConfig()->get("models-island");
     $options = [];
     foreach($models as $name => $data){
      $options[] = [["§l".ucwords(str_replace("-", " ", $name))." Kiểu đảo\n§l§9»» §r§f§oNhấn để tạo", $data["image"]], [$name, $data]];
     }
     $form = new SimpleForm(function(Player $p, $result) use ($options){
      if($result === null) return;
      $datas = [];
      foreach($options as $option){
       $datas[] = $option[1];
      }
      $this->confirmMenu($p, "create", [$datas[$result][0], $datas[$result][1]]);
     });
     $form->setTitle("§l§eChọn đảo hiện có");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eVui lòng chọn mô hình đảo Skyblock mà bạn sẽ tạo§r§f!\n".str_repeat("=", 33));
     $islands = [];
     foreach($options as $option){
      $islands[] = $option[0];
     }
     foreach($islands as $island){
      $form->addButton($island[0], $island[1], "url");
     }
     $p->sendForm($form);
    }

    public function menuSkyBlock(Player $p){
     if(!empty(($data = $this->data->get($p->getName())))){
      $form = new SimpleForm(function(Player $p, $result) use ($data){
       if($result === null) return;
       switch($result){
        case 0:{
         if(!$this->teleportToIsland($p, $p->getName(), $data)){
          $p->sendMessage($this->prefix."Không thể tải thế giới của bạn§r§f, §exin hãy tạo lại§r§f!");
          break;
         }
         $p->sendMessage($this->prefix."Bạn đã dịch chuyển thành công đến hòn đảo SkyBlock của riêng mình§r§f.");
         break;
        }
        case 1:{
         if(!$this->infoIslandMenu($p)) $p->sendMessage($this->prefix."Không tìm thấy dữ liệu SkyBlock Island của bạn!§r§f!");
         break;
        }
        case 2:{
         $this->friendsMenu($p);
         break;
        }
        case 3:{
         $this->settingMenu($p);
         break;
        }
        case 4:{
         $this->confirmMenu($p, "delete");
         break;
        }
       }
      });
      $form->setTitle("§l§eSkyBlock Menu");
      $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eXin hãy chọn trong menu§r§f!\n".str_repeat("=", 33));
      $form->addButton("§lDỊCH CHUYỂN ĐẾN ĐẢO\n§l§6»» §r§fNhấp để dịch chuyển", "textures/ui/icon_recipe_item");
      $form->addButton("§lTHÔNG TIN ĐẢO\n§l§6»» §r§fNhấp để xem", "textures/ui/creative_icon");
      $form->addButton("§lBẠN BÈ\n§l§6» §r§fNhấp để xem bạn bè", "textures/ui/FriendsIcon");
      $form->addButton("§lCÀI ĐẶT ĐẢO\n§l§6»» §r§fNhấp để cài đặt", "textures/ui/accessibility_glyph_color");
      $form->addButton("§lXÓA ĐẢO\n§l§6»» §r§fNhấp để xóa", "textures/ui/trash");
      $p->sendForm($form);
     }else{
      $p->sendMessage($this->prefix."Dữ liệu đảo SkyBlock của bạn dường như bị hỏng§r§f, §6Vui lòng đảo lại hoặc Làm lại đảo của bạn§r§f!");
     }
    }

    public function infoIslandMenu(Player $p):bool{
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->menuSkyBlock($p);
        break;
       }
      }
     });
     if(!empty(($data = $this->data->get($p->getName())))){
      $form->setTitle("§l§eThông tin đảo");
      $friend = "empty";
      if(!empty($data["friends"]) && is_array($data["friends"])){
       $friend = "";
       $i = 1;
       foreach($data["friends"] as $name){
        $friend .= "\n  §r§f".$i."). §7§o".$name."§r";
        $i++;
       }
      }
      $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r§f\n                ".str_repeat("-", 13)."\n§l§6»» §r§eĐây là thông tin đảo của bạn§r§f:\n".str_repeat("=", 33)."\n- §e§oTin nhắn khi thăm quan§r§f:§7\n  ".$data["welcome"]."§r§f\n- §e§oKhóa đảo:§r§f:§7 ".($data["lock"] ? "on" : "off")."§r§f\n- §e§oPvP§r§f:§7 ".($data["pvp"] ? "on" : "off")."§r§f\n- §e§oPoints§r§f:§7 ".$data["points"]."§r§f\n- §e§oFriends§r§f:§7 ".$friend."\n§r§f".str_repeat("=", 33));
      $form->addButton("§lVỀ MENU CHÍNH\n§l§6»» §r§f§oNhấn để trở lại", "textures/ui/refresh_light");
      $p->sendForm($form);
      return true;
     }
     return false;
    }

    public function friendsMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->addFriendMenu($p);
        break;
       }
       case 1:{
        $this->removeFriendMenu($p);
        break;
       }
       case 2:{
        $this->visitFriendMenu($p);
        break;
       }
       case 3:{
        $this->menuSkyBlock($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eMenu Bạn Bè");
     $form->setContent(str_repeat("=", 33)."\n                   §9§l§oSky Block§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§eVui lòng chọn từ menu§r§f, §6hoặc quay lại menu trước§r§f!\n".str_repeat("=", 33));
     $form->addButton("§lTHÊM BẠN\n§l§6»» §r§f§oNhấp để thêm", "textures/ui/confirm");
     $form->addButton("§lXÓA BẠN\n§l§6»» §r§f§oNhấp để xóa", "textures/ui/cancel");
     $form->addButton("§lTHĂM QUAN ĐẾN ĐẢO BẠN\n§l§6»» §r§f§oNhấp để thăm quan", "textures/ui/conduit_power_effect");
     $form->addButton("§lVỀ MENU CHÍNH\n§l§6»» §r§f§oNhấp để quay lại", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function addFriendMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->addFriendViaInputName($p);
        break;
       }
       case 1:{
        $this->addFriendViaOnlineList($p);
        break;
       }
       case 2:{
        $this->friendsMenu($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eThêm bạn");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eVui lòng chọn xem có tìm kiếm tên người chơi mà bạn sẽ kết bạn hay không§r§f! §6hoặc quay lại menu chính§r§f.\n".str_repeat("=", 33));
     $form->addButton("§lTHÔNG QUA TÊN\n§l§6»» §r§f§o Nhấp để xác nhận", "textures/ui/confirm");
     $form->addButton("§lQUA HỆ THỐNG ONLINE\n§l§9»» §r§f§oNhấp để xác nhận", "textures/ui/confirm");
     $form->addButton("§lQUAY LẠI\n§l§9»» §r§f§oNhấn để quay lại", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function addFriendViaInputName(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."Hãy nhập tên mà bạn muốn thêm vào!§r§f!");
       return;
      }
      $this->addFriend($p, $result[0]);
     });
     $form->setTitle("§e§lThêm bạn");
     $form->addInput(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eVui lòng nhập tên của người chơi mà bạn sẽ kết bạn§r§f! §6đảm bảo rằng người chơi bạn sẽ kết bạn không ngoại tuyến§r§f!\n".str_repeat("=", 33)."\n§l§e»» §r§6New Friend Name§r§f:");
     $p->sendForm($form);
    }

    public function addFriendViaOnlineList(Player $p){
     $players = [];
     foreach($this->getServer()->getOnlinePlayers() as $player){
      if($player->getName() !== $p->getName()){
       $players[] = $player->getName();
      }
     }
     $form = new CustomForm(function(Player $p, $result) use ($players){
      if($result === null) return;
      if(empty($players)) return;
      $this->addFriend($p, $players[$result[0]]);
     });
     $form->setTitle("§e§lThêm bạn");
     if(empty($players)){
      $form->addLabel(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eCó vẻ như không có người chơi nào khác trực tuyến nên không thể thêm gì làm bạn của bạn§r§f!\n".str_repeat("=", 33));
     }else{
      $form->addDropdown(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eVui lòng chọn người chơi mà bạn sẽ kết bạn§r§f! §6đảm bảo rằng người chơi bạn sẽ kết bạn không ngoại tuyến§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oSelect New Friend§r§f:", $players);
     }
     $p->sendForm($form);
    }

    public function removeFriendMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->removeFriendViaInputName($p);
        break;
       }
       case 1:{
        $this->removeFriendViaFriendList($p);
        break;
       }
       case 2:{
        $this->friendsMenu($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eXóa bạn");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Vui lòng chọn xem có tìm kiếm tên bạn bè mà bạn muốn xóa khỏi danh sách bạn bè của mình hay không§r§f! §6hoặc quay lại menu trước§r§f.\n".str_repeat("=", 33));
     $form->addButton("§l THÔNG QUA TÊN\n§l§6»» §r§f§oNhấp để xác nhận", "textures/ui/cancel");
     $form->addButton("§lTHÔNG QUA DANH SÁCH\n§l§6»» §r§f§oNhấp để xác nhận", "textures/ui/cancel");
     $form->addButton("§lQUAY LẠI\n§l§6»» §r§f§oNhấp để quay lại", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function removeFriendViaInputName(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."Vui lòng nhập tên người bạn mà bạn muốn xóa làm bạn bè§r§f!");
       return;
      }
      $this->removeFriend($p, $result[0]);
     });
     $form->setTitle("§e§lXóa bạn bè");
     $form->addInput(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§eVui lòng nhập tên người bạn mà bạn muốn xóa làm bạn bè§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oRemove Friend Name§r§f:");
     $p->sendForm($form);
    }

    public function removeFriendViaFriendList(Player $p){
     $friends = [];
     if(!empty(($data = $this->data->get($p->getName())))){
      foreach($data["friends"] as $friend){
       $friends[] = $friend;
      }
     }
     $form = new CustomForm(function(Player $p, $result) use ($friends){
      if($result === null) return;
      if(empty($friends)) return;
      $this->removeFriend($p, $friends[$result[0]]);
     });
     $form->setTitle("§e§lXóa bạn bè");
     if(empty($friends)){
      $form->addLabel(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eCó vẻ như bạn không có bất kỳ người bạn nào để xóa khỏi bạn của mình§r§f!\n".str_repeat("=", 33));
     }else{
      $form->addDropdown(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eVui lòng chọn người bạn muốn xóa làm bạn§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oSelect Remove Friend§r§f:", $friends);
     }
     $p->sendForm($form);
    }

    public function visitFriendMenu(Player $p){
     $form = new SimpleForm(function(Player $p, $result){
      if($result === null) return;
      switch($result){
       case 0:{
        $this->visitFriendViaInputName($p);
        break;
       }
       case 1:{
        $this->visitFriendViaFriendList($p);
        break;
       }
       case 2:{
        $this->friendsMenu($p);
        break;
       }
      }
     });
     $form->setTitle("§l§eThăm đảo bạn bè");
     $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Vui lòng chọn xem có tìm kiếm tên của người bạn có hòn đảo SkyBlock mà bạn muốn đến thăm hay không§r§f! §6hoặc quay lại menu chính§r§f.\n".str_repeat("=", 33));
     $form->addButton("§lTHÔNG QUA TÊN\n§l§6»» §r§f§oNhấp để xác nhận", "textures/ui/conduit_power_effect");
     $form->addButton("§lTHÔNG QUA DANH SÁCH\n§l§6»» §r§f§oNhấp để xác nhận", "textures/ui/conduit_power_effect");
     $form->addButton("§lQUAY LẠI\n§l§6»» §r§f§oNhấp quay lại", "textures/ui/refresh_light");
     $p->sendForm($form);
    }

    public function visitFriendViaInputName(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."§c§lVui lòng nhập tên của người bạn mà bạn muốn đến thăm đảo SkyBlock§r§f!");
       return;
      }
      $this->visitFriend($p, $result[0]);
     });
     $form->setTitle("§e§lThăm đảo bạn bè");
     $form->addInput(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Vui lòng nhập tên của người bạn mà bạn muốn đến thăm đảo SkyBlock§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oĐảo của bạn bè bạn§r§f:");
     $p->sendForm($form);
    }

    public function visitFriendViaFriendList(Player $p){
     $friends = [];
     if(!empty(($data = $this->data->get($p->getName())))){
      foreach($data["friends"] as $friend){
       $friends[] = $friend;
      }
     }
     $form = new CustomForm(function(Player $p, $result) use ($friends){
      if($result === null) return;
      if(empty($friends)) return;
      $this->visitFriend($p, $friends[$result[0]]);
     });
     $form->setTitle("§e§lThăm đảo bạn bè");
     if(empty($friends)){
      $form->addLabel(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eCó vẻ như bạn không có bạn bè để bạn đến thăm đảo SkyBlock§r§f!\n".str_repeat("=", 33));
     }else{
      $form->addDropdown(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eXin hãy chọn đảo của bạn bè mà bạn muốn đến§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oChọn để xem bạn§r§f:", $friends);
     }
     $p->sendForm($form);
    }

    public function settingMenu(Player $p){
     if(!empty(($data = $this->data->get($p->getName())))){
      $form = new SimpleForm(function(Player $p, $result) use ($data){
       if($result === null) return;
       switch($result){
        case 0:{
         $this->setWelcomeMenu($p);
         break;
        }
        case 1:{
         if(!$data["lock"]){
          $this->data->setNested($p->getName().".lock", true);
          $this->data->save();
          $p->sendMessage($this->prefix."Bạn đã khóa thành công Đảo SkyBlock của mình§r§f.");
         }else{
          $this->data->setNested($p->getName().".lock", false);
          $this->data->save();
          $p->sendMessage($this->prefix."Bạn đã mở khóa thành công đảo SkyBlock của mình§r§f.");
         }
         $this->settingMenu($p);
         break;
        }
        case 2:{
         if(!$data["pvp"]){
          $this->data->setNested($p->getName().".pvp", true);
          $this->data->save();
          $p->sendMessage($this->prefix."Bạn đã kích hoạt thành công pvp trên đảo SkyBlock của mình§r§f.");
         }else{
          $this->data->setNested($p->getName().".pvp", false);
          $this->data->save();
          $p->sendMessage($this->prefix."Bạn đã vô hiệu hóa thành công pvp trên đảo SkyBlock của mình§r§f.");
         }
         $this->settingMenu($p);
         break;
        }
        case 3:{
         $this->menuSkyBlock($p);
         break;
        }
       }
      });
      if(!$data["lock"]){
       $lockText = "§cOFF";
      }else{
       $lockText = "§aON";
      }
      if(!$data["pvp"]){
       $pvpText = "§cOFF";
      }else{
       $pvpText = "§aON";
      }
      $form->setTitle("§l§eSetting Menu");
      $form->setContent(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6Please choose which one you want to change from your SkyBlock island data§r§f! §6or return to the SkyBlock menu§r§f.\n".str_repeat("=", 33));
      $form->addButton("§lTẠO TIN NHẮN CHÀO\n§l§6»» §r§f§oTap to set", "textures/ui/comment");
      $form->addButton("§lKHÓA ĐẢO: ".$lockText."\n§l§6»» §r§f§oTap to set", "textures/ui/lock");
      $form->addButton("§lPVP TRÊN ĐẢO: ".$pvpText."\n§l§6»» §r§f§oTap to set", "textures/ui/strength_effect");
      $form->addButton("§lVỀ MENU\n§l§6»» §r§f§oTap to back", "textures/ui/refresh_light");
      $p->sendForm($form);
     }else{
      $p->sendMessage($this->prefix."Your SkyBlock island data appears to be corrupted§r§f, §6please re-island§r§f!");
     }
    }

    public function setWelcomeMenu(Player $p){
     $form = new CustomForm(function(Player $p, $result){
      if($result === null) return;
      if(trim($result[0]) === ""){
       $p->sendMessage($this->prefix."Please enter the incoming message that you will modify§r§f!");
       return;
      }
      if(!empty(($data = $this->data->get($p->getName())))){
       $this->data->setNested($p->getName().".welcome", str_replace("§", "", $result[0]));
       $this->data->save();
       $p->sendMessage($this->prefix."You have successfully changed your message coming to your SkyBlock island§r§f.");
       $this->settingMenu($p);
      }else{
       $p->sendMessage($this->prefix."§cDữ liệu đảo SkyBlock của bạn dường như bị hỏng§r§f, §6hãy tạo lại đảo§r§f!");
      }
     });
     $form->setTitle("§e§lĐặt câu chào");
     $form->addInput(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§6»» §r§eVui lòng nhập một tin nhắn mới đến mà bạn muốn sửa đổi§r§f!\n".str_repeat("=", 33)."\n§l§9»» §r§e§oNew Welcome Message§r§f:");
     $p->sendForm($form);
    }

    public function confirmMenu(Player $p, string $type, array $datas = []){
     if($type === "create" || $type === "delete"){
      $form = new SimpleForm(function(Player $p, $result) use ($type, $datas){
       if($result === null) return;
       switch($result){
        case 0:{
         if($type === "create"){
          $this->data->setNested($p->getName().".model", $datas[0]);
          $this->data->setNested($p->getName().".welcome", "Welcome to SkyBlock Island");
          $this->data->setNested($p->getName().".friends", []);
          $this->data->setNested($p->getName().".lock", false);
          $this->data->setNested($p->getName().".pvp", false);
          $this->data->setNested($p->getName().".points", 0);
          $this->data->setNested($p->getName().".king-slime", $datas[1]["king-slime"]);
          $this->data->save();
          $settings = ["preset" => json_encode($datas[1])];
          $this->getServer()->generateLevel($p->getName(), null, SkyBlockGenerator::class, $settings);
          $this->getServer()->loadLevel($p->getName());
          $this->menuSkyBlock($p);
          $p->sendMessage($this->prefix."§r§eBạn đã tạo thành công một hòn đảo SkyBlock với một mô hình §r§f".ucwords(str_replace("-", " ", $datas[0])).". §eteleport now§r§f!");
         }else if($type === "delete"){
          if($p->getLevel()->getFolderName() === $p->getName()) $p->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
          if($this->getServer()->isLevelLoaded($p->getName())) $this->getServer()->unloadLevel($this->getServer()->getLevelByName($p->getName()));
          $this->data->remove($p->getName());
          $this->data->save();
          $this->removeDirectory("worlds/".$p->getName()."/region");
          $this->removeDirectory("worlds/".$p->getName());
          $p->sendMessage($this->prefix."Bạn đã xóa thành công đảo SkyBlock§r§f.");
         }
         break;
        }
        case 1:{
         if($type === "delete") $this->menuSkyBlock($p);
         if($type === "create") $this->chooseNewIsland($p);
         break;
        }
       }
      });
      $form->setTitle("§l§eConfirm ".ucwords($type)." Menu");
      $text = [];
      if($type === "create"){
       $text[] = "membuat";
       $text[] = " dengan model §r§f".ucwords(str_replace("-", " ", $datas[0]));
      }else if($type === "delete"){
       $text[] = "menghapus";
      }
      $form->setContent(str_repeat("=", 33)."\n                   §e§l§oSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§eAre you sure to".$text[0]."Skyblock island".(empty($text[1]) ? "" : $text[1])."§r§f? §eplease confirm§r§f!\n".str_repeat("=", 33));
      $form->addButton("§lCÓ, TÔI ĐỒNG Ý\n§l§6»» §r§f§oNhấn để xóa".$type, "textures/ui/confirm");
      $form->addButton("§lQUAY LẠI\n§l§6»» §r§f§oNhấn để quay lại", "textures/ui/refresh_light");
      $p->sendForm($form);
     }
    }

    public function onPlayerJoin(PlayerJoinEvent $e){
     $p = $e->getPlayer();
     $this->index[$p->getName()] = 1;
     $this->eid[$p->getName()] = Entity::$entityCount++;
     $this->setLeaderboardText($p, true);
     if(!empty(($this->data->get($p->getName())))){
      if(!$this->getServer()->isLevelLoaded($p->getName())) $this->getServer()->loadLevel($p->getName());
     }
     if($this->isSkyBlockLevel($p->getLevel())){
      if(!empty(($data = $this->data->get($p->getLevel()->getFolderName())))){
       $i = 0;
       foreach($p->getLevel()->getEntities() as $en){
        if($en instanceof KingSlime) $i++;
       }
       if($i <= 0) $this->spawnSlimeKing($p, $data);
      }
     }
    }

    public function onPlayerQuit(PlayerQuitEvent $e){
     $p = $e->getPlayer();
     unset($this->index[$p->getName()]);
     unset($this->eid[$p->getName()]);
     if(!empty(($this->data->get($p->getName())))){
      if($this->getServer()->isLevelLoaded($p->getName())) $this->getServer()->unloadLevel($this->getServer()->getLevelByName($p->getName()));
     }
    }

    public function onPlayerInteract(PlayerInteractEvent $e){
     $this->doInteractEvent($e);
     if($e::RIGHT_CLICK_BLOCK === $e->getAction()){
      $p = $e->getPlayer();
      $block = $e->getBlock();
      $button = $this->isButton($block);
      $name = $p->getName();
      $world = $p->getLevel()->getFolderName();
      if(isset($this->mode[$name][$world])){
       if($this->mode[$name][$world] === 3){
        $this->getConfig()->setNested("leaderboard-points.board", array($block->getX() + 0.5, $block->getY() + 3, $block->getZ() + 0.5));
        $this->getConfig()->save();
        $p->sendMessage($this->prefix."Bạn đã xác định thành công vị trí của bảng xếp hạng các điểm đảo§r§f.");
        $p->sendMessage($this->prefix."Please specify the location of the next button§r§f!");
        --$this->mode[$name][$world];
       }else if($this->mode[$name][$world] === 2){
        $this->getConfig()->setNested("leaderboard-points.next", array($block->getX(), $block->getY(), $block->getZ()));
        $this->getConfig()->save();
        $p->sendMessage($this->prefix."You have successfully determined the location of the next button§r§f.");
        $p->sendMessage($this->prefix."Please specify the location of the back button§r§f!");
        --$this->mode[$name][$world];
       }else if($this->mode[$name][$world] === 1){
        $this->getConfig()->setNested("leaderboard-points.back", array($block->getX(), $block->getY(), $block->getZ()));
        $this->getConfig()->save();
        $p->sendMessage($this->prefix."You have successfully determined the location of the back button§r§f.");
        unset($this->mode[$name][$world]);
       }
      }else if($block->getId() === 77){
       if($button !== null){
        if(empty($this->index[$name])) $this->index[$name] = 1;
        switch($button){
         case "next":{
          if($this->getRankings($this->index[$name] + 1) === null){
           $p->sendMessage($this->prefix."The next page is no longer found§r§f!");
           return false;
          }
          $this->index[$name] = $this->index[$name] + 1;
          $this->setLeaderboardText($p);
          break;
         }
         case "back":{
          if($this->index[$name] <= 1){
           $p->sendMessage($this->prefix."The previous page is no longer found§r§f!");
           return false;
          }
          $this->index[$name] = $this->index[$name] - 1;
          $this->setLeaderboardText($p);
          break;
         }
        }
       }
      }
     }
    }

    public function onBlockPlace(BlockPlaceEvent $e){
     $this->doInteractEvent($e);
    }

    public function onBlockBreak(BlockBreakEvent $e){
     $this->doInteractEvent($e);
    }

    public function onEntityDamage(EntityDamageEvent $e){
     if($e instanceof EntityDamageByEntityEvent){
      $damager = $e->getDamager();
      $victim = $e->getEntity();
      if($damager instanceof Player && $victim instanceof Player){
       if($damager->getLevel()->getFolderName() === $this->getServer()->getDefaultLevel()->getFolderName()){
        $e->setCancelled(true);
        if(!empty(($this->data->get($victim->getName())))){
         $form = new SimpleForm(function(Player $p, $result) use ($victim){
          if($result === null) return;
          switch($result){
           case 0:{
            $this->visitFriend($p, $victim->getName(), true);
            break;
           }
          }
         });
         $form->setTitle("§l§eVisit ".$victim->getName()." Island");
         $form->setContent(str_repeat("=", 33)."\n                   §e§lSkyBlock§r\n                ".str_repeat("-", 13)."\n§l§9»» §r§6§oApakah anda ingin untuk berkunjung ke pulau SkyBlock milik §r§f".$victim->getName()."? §6§osilahkan konfirmasi§r§f!\n".str_repeat("=", 33));
         $form->addButton("§lCÓ, TÔI MUỐN\n§l§9»» §r§f§oNhấn để dịch chuyển", "textures/ui/confirm");
         $damager->sendForm($form);
        }else{
         $damager->sendMessage($this->prefix."§r§f".$victim->getName()." §ehiện không có hoặc chưa tạo đảo§r§f!");
        }
       }else if(!empty(($data = $this->data->get($victim->getName())))){
        if(!$data["pvp"]){
         $e->setCancelled(true);
         $damager->addTitle("", "§c§oPVP ĐÃ TẮT§r§f!", 20, 20, 20);
        }
       }
      }
     }
     $fallPlayer = $e->getEntity();
     if($fallPlayer instanceof Player){
      if($e->getCause() === EntityDamageEvent::CAUSE_VOID && $this->isSkyBlockLevel($fallPlayer->getLevel())){
       $e->setCancelled();
       $fallPlayer->teleport(new Position(8.5, 36, 9.5, $this->getServer()->getLevelByName($fallPlayer->getLevel()->getFolderName())));
      }
     }
    }

    public function onChunkLoad(ChunkLoadEvent $e){
     $level = $e->getLevel();
     if(!$this->isSkyBlockLevel($level)) return;
     $data = $this->getConfig()->getNested("models-island.".($this->data->getNested($level->getFolderName().".model")));
     foreach($data["fill-chest"] as $chestPos){
      $position = new Position($chestPos[0][0], $chestPos[0][1], $chestPos[0][2]);
      if($level->getChunk($position->x >> 4, $position->z >> 4) === $e->getChunk() && $e->isNewChunk()){
       $chest = Tile::createTile(Tile::CHEST, $level, Chest::createNBT($position));
       $inventory = $chest->getInventory();
       unset($chestPos[0]);
       foreach($this->parseItems($chestPos) as $item){
        $inventory->addItem($item);
       }
      }
     }
    }

    public function hasSkyBlockIsland(Player $p):bool{
     foreach($this->data->getAll() as $name => $data){
      if($name === $p->getName()) return true;
     }
     return false;
    }

    public function isSkyBlockLevel(Level $level):bool{
     foreach($this->data->getAll() as $name => $data){
      if($name === $level->getFolderName()) return true;
     }
     return false;
    }

    public function addFriend(Player $p, string $friendName):void{
     if(strtolower($friendName) === strtolower($p->getName())){
      $p->sendMessage($this->prefix."§c§lBạn không thể thêm chính bạn là bạn§r§e!");
      return;
     }
     $friend = $this->getServer()->getPlayerExact($friendName);
     if(!$friend instanceof Player){
      $p->sendMessage($this->prefix."Người chơi §r§f".$friendName." §6§okhông được tìm thấy hoặc không trực tuyến vào lúc này§r§f!");
      return;
     }
     if(!empty(($data = $this->data->get($p->getName())))){
      $findFriend = false;
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $friendName){
         $findFriend = true;
         break;
        }
       }
      }
      if($findFriend){
       $p->sendMessage($this->prefix."Người chơi §r§f".$friendName." §eđã là bạn bè§r§f!");
       return;
      }
      $friends = $data["friends"];
      $friends[] = $friendName;
      $this->data->setNested($p->getName().".friends", $friends);
      $this->data->save();
      $friend->sendMessage($this->prefix."Bạn đã được thêm vào bởi§r§f".$p->getName().".");
      $p->sendMessage($this->prefix."Thêm bạn thành công§r§f".$friend->getName()." §eas a friend§r§f.");
     }else{
      $p->sendMessage($this->prefix."Dữ liệu đảo SkyBlock của bạn dường như bị hỏng§r§f, §ehãy tạo lại đảo§r§f!");
     }
     return;
    }

    public function removeFriend(Player $p, string $friendName):void{
     if(!empty(($data = $this->data->get($p->getName())))){
      $findFriend = false;
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $friendName){
         $findFriend = true;
         break;
        }
       }
      }
      if(!$findFriend){
       $p->sendMessage($this->prefix."Người chơi §r§f".$friendName." §ekhông phải là bạn của bạn§r§f!");
       return;
      }
      $friends = [];
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name !== $friendName){
         $friends[] = $name;
        }
       }
      }
      $this->data->setNested($p->getName().".friends", $friends);
      $this->data->save();
      $friend = $this->getServer()->getPlayerExact($friendName);
      if($friend instanceof Player){
       $friend->sendMessage($this->prefix."Bây giờ bạn không còn là bạn nữa §r§f".$p->getName().".");
      }
      $p->sendMessage($this->prefix."Bạn đã xóa bạn thành công §r§f".$friendName." §eas a friend§r§f.");
     }else{
      $p->sendMessage($this->prefix."Dữ liệu đảo SkyBlock của bạn dường như bị hỏng§r§f, §ehãy tạo lại đảo§r§f!");
     }
     return;
    }

    public function visitFriend(Player $p, string $friendName, $mustNotFriend = false):void{
     if(!empty(($data = $this->data->get($p->getName())))){
      $findFriend = false;
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $friendName){
         $findFriend = true;
         break;
        }
       }
      }
      if(!$findFriend && !$mustNotFriend){
       $p->sendMessage($this->prefix."Người chơi §r§f".$friendName." §ekhông phải là bạn của bạn§r§f!");
       return;
      }
      if(!$this->teleportToIsland($p, $friendName, $data)){
       $p->sendMessage($this->prefix."Đảo SkyBlock thuộc về thế giới §r§f".$friendName." §ekhông thể tải được§r§f!");
       return;
      }
      $friend = $this->getServer()->getPlayerExact($friendName);
      if($friend instanceof Player){
       $friend->sendMessage($this->prefix."§r§f".$p->getName()." §eđã thăm quan đảo của bạn§r§f!.");
      }
      $p->sendMessage($this->prefix.$data["welcome"]."§r§f.");
     }else{
      $p->sendMessage($this->prefix."Dữ liệu thuộc về §r§f".$friendName." §evà có vẻ như bị hỏng§r§f!");
     }
     return;
    }

    public function removeDirectory(string $at){
     $dir = $this->getServer()->getDataPath().$at;
     $dir = rtrim($dir, "/\\")."/";
     foreach(scandir($dir) as $file){
      if($file === "." || $file === "..") continue;
      $path = $dir.$file;
      if(!is_dir($path)) unlink($path);
     }
     rmdir($dir);
    }

    public function isAsFriend(string $nameOfCheck, string $nameOfOwner):bool{
     if(!empty(($data = $this->data->get($nameOfOwner)))){
      if(!empty($data["friends"]) && is_array($data["friends"])){
       foreach($data["friends"] as $name){
        if($name === $nameOfCheck){
         return true;
         break;
        }
       }
      }
     }
     return false;
    }

    public function doInteractEvent(Event $e){
     $p = $e->getPlayer();
     if($this->isSkyBlockLevel($p->getLevel()) && $p->getPlayer()->getLevel()->getFolderName() !== $this->getServer()->getDefaultLevel()->getFolderName()){
      if(!empty(($data = $this->data->get($p->getLevel()->getFolderName())))){
       if($p->getName() !== $p->getLevel()->getFolderName()){
        if(!$data["lock"]){
         if(!$this->isAsFriend($p->getName(), $p->getLevel()->getFolderName())){
          $p->sendMessage($this->prefix."Bạn không phải là bạn của §r§f".$p->getLevel()->getFolderName()."!");
          $e->setCancelled(true);
         }
        }else{
         $p->sendMessage($this->prefix."Đảo đã bị khóa bởi§r§f".$p->getLevel()->getFolderName()."!");
         $e->setCancelled(true);
        }
       }
       if(!$e->isCancelled()){
        $points = [57 => 4, 133 => 4, 41 => 3, 42 => 2, 22 => 1, 152 => 1];
        if($e instanceof BlockPlaceEvent){
         foreach($points as $id => $point){
          if($e->getBlock()->getId() === $id){
           $this->data->setNested($p->getName().".points", ($this->data->getNested($p->getName().".points") + $point));
           $this->data->save();
          }
         }
        }
        if($e instanceof BlockBreakEvent){
         foreach($points as $id => $point){
          if($e->getBlock()->getId() === $id){
           $result = ($this->data->getNested($p->getName().".points") - $point);
           if($result > 0){
            $this->data->setNested($p->getName().".points", $result);
           }else{
            $this->data->setNested($p->getName().".points", 0);
           }
           $this->data->save();
          }
         }
        }
       }
      }
     }
    }

    public function parseItems($items){
     $result = [];
     foreach($items as $parts){
      foreach($parts as $key => $value){
       $parts[$key] = (int) $value;
      }
      if(isset($parts[0])){
       $result[] = Item::get($parts[0], $parts[1] ?? 0, $parts[2] ?? 1);
      }
     }
     return $result;
    }

    public function teleportToIsland(Player $p, string $levelName, array $data):bool{
     if(!$this->getServer()->loadLevel($levelName)) return false;
     $level = $this->getServer()->getLevelByName($levelName);
     $p->teleport(new Position(8.5, 36, 9.5, $level));
     $i = 0;
     foreach($level->getEntities() as $en){
      if($en instanceof KingSlime) $i++;
     }
     if($i <= 0){
      $this->spawnSlimeKing($p, $data);
     }
     return true;
    }

    public function spawnSlimeKing(Player $p, array $data){
     $nbt = Entity::createBaseNBT(new Vector3($data["king-slime"][0] + 0.5, $data["king-slime"][1] + 1, $data["king-slime"][2] + 0.5));
     $kingslime = new KingSlime($p->getLevel(), $nbt);
     $kingslime->setNameTag("§e§lVua Slime");
     $kingslime->setNameTagAlwaysVisible(true);
     $kingslime->setNameTagVisible(true);
     $kingslime->spawnToAll();
    }

    public function getRankings(int $index){
     $allPoints = [];
     foreach($this->data->getAll() as $islandOwner => $islandData){
      $allPoints[$islandOwner] = $islandData["points"];
     }
     $allkeys = array_keys($allPoints);
     $i = 0;
     $text = "";
     arsort($allPoints, SORT_NUMERIC);
     foreach($allPoints as $name => $money){
      $i++;
      $allkeys[$i] = $name;
     }
     $count = $index * 10;
     for($i = ($index * 10) - 10; $i < $count;){
      $i++;
      if(empty($allkeys[$i])){
       $text = $text."-\n";
      }else{
       $text = $text."§f§l(§r§e".$i."§f§l) §r§6§o".$allkeys[$i]."§r§f: ".$this->data->getNested($allkeys[$i].".points")." §r§7§opoints§r\n";
      }
     }
     if($index !== 1){
      if($text === str_repeat("-\n", 10)){
       return null;
      }
     }
     return $text;
    }

    public function isButton($block){
     if(!empty(($posData = $this->getConfig()->get("leaderboard-points")))){
      $posNext = $posBack = null;
      if(!empty($posData["next"])) $posNext = $posData["next"];
      if(!empty($posData["back"])) $posBack = $posData["back"];
      $next = $back = "";
      if($posNext !== null) $next = $posNext[0]." ".$posNext[1]." ".$posNext[2];
      if($posBack !== null) $back = $posBack[0]." ".$posBack[1]." ".$posBack[2];
      $pos = $block->x." ".$block->y." ".$block->z;
      if($next === $pos){
       return "next";
      }elseif($back === $pos){
       return "back";
      }
     }
     return null;
    }

    public function setLeaderboardText(Player $p, $join = false){
     $to = 1;
     if($join) $to = 3;
     $posData = $this->getConfig()->get("leaderboard-points");
     for($i = 0; $i < $to; $i++){
      if($i === 0){
       $text = "§l§a» §9ĐIỂM ĐẢO SKYBLOCK §a«\n".$this->getRankings($this->index[$p->getName()]);
       $eid = $this->eid[$p->getName()];
       $type = "board";
      }else if($i === 1){
       $text = "§l§9>§f>§a>";
       $eid = 114514;
       $type = "next";
      }else if($i === 2){
       $text = "§l§a<§f<§9<";
       $eid = 114515;
       $type = "back";
      }
      if(!empty($posData[$type])){
       $uuid = UUID::fromRandom();
       $add = new PlayerListPacket();
       $add->type = PlayerListPacket::TYPE_ADD;
       $add->entries = [PlayerListEntry::createAdditionEntry($uuid, $eid, $text, SkinAdapterSingleton::get()->toSkinData(new Skin("Standard_Custom", str_repeat("\x00", 8192))))];
       $p->sendDataPacket($add);
       $pk = new AddPlayerPacket();
       $pk->uuid = $uuid;
       $pk->username = $text;
       $pk->entityRuntimeId = $eid;
       if($type === "next" || $type === "back"){
        $pk->position = new Vector3($posData[$type][0] + 0.5, $posData[$type][1], $posData[$type][2] + 0.5);
       }else{
        $pk->position = new Vector3($posData[$type][0], $posData[$type][1], $posData[$type][2]);
       }
       $pk->item = ItemFactory::get(Item::AIR, 0, 0);
       $flags = (1 << Entity::DATA_FLAG_IMMOBILE);
       $pk->metadata = [
        Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
        Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.01]
       ];
       $p->sendDataPacket($pk);
       $remove = new PlayerListPacket();
       $remove->type = PlayerListPacket::TYPE_REMOVE;
       $remove->entries = [PlayerListEntry::createRemovalEntry($uuid)];
       $p->sendDataPacket($remove);
      }
     }
    }

    public function setScoreboardEntry(Player $p, int $score, string $msg, string $objName){
     $entry = new ScorePacketEntry();
     $entry->objectiveName = $objName;
     $entry->type = 3;
     $entry->customName = "$msg";
     $entry->score = $score;
     $entry->scoreboardId = $score;
     $pk = new SetScorePacket();
     $pk->type = 0;
     $pk->entries[$score] = $entry;
     $p->sendDataPacket($pk);
    }

    public function createScoreboard(Player $p, string $title, string $objName, string $slot = "sidebar", $order = 0){
     $pk = new SetDisplayObjectivePacket();
     $pk->displaySlot = $slot;
     $pk->objectiveName = $objName;
     $pk->displayName = $title;
     $pk->criteriaName = "dummy";
     $pk->sortOrder = $order;
     $p->sendDataPacket($pk);
    }

    public function removeScoreboard(Player $p, string $objName){
     $pk = new RemoveObjectivePacket();
     $pk->objectiveName = $objName;
     $p->sendDataPacket($pk);
    }

} 