<?php

declare(strict_types=1);


namespace sergittos\bedwars\session;


use pocketmine\entity\effect\EffectInstance;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\BedWars;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\stage\EndingStage;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\scoreboard\LobbyScoreboard;
use sergittos\bedwars\session\scoreboard\Scoreboard;
use sergittos\bedwars\session\settings\GameSettings;
use sergittos\bedwars\session\settings\SpectatorSettings;
use sergittos\bedwars\utils\ColorUtils;
use sergittos\bedwars\utils\ConfigGetter;
use function in_array;
use function strtoupper;
use function time;

class Session {

    public const RESPAWN_DURATION = 5;

    private Player $player;
    private Scoreboard $scoreboard;

    private GameSettings $game_settings;
    private SpectatorSettings $spectator_settings;

    private ?Game $game = null;
    private ?Team $team = null;

    private ?Session $last_session_hit = null;
    private ?Session $tracking_session = null;

    private ?int $respawn_time = null;
    private ?int $last_session_hit_time = null;

    private int $coins;
    private int $kills;
    private int $wins;

    public function __construct(Player $player) {
        $this->player = $player;
        $this->game_settings = new GameSettings($this);
        $this->load();
    }

    public function getPlayer(): Player {
        return $this->player;
    }

    public function getUsername(): string {
        return $this->player->getName();
    }

    public function getColoredUsername(): string {
        $username = $this->getUsername();
        if($this->hasTeam()) {
            return $this->team->getColor() . $username;
        }
        return $username;
    }

    public function getGameSettings(): GameSettings {
        return $this->game_settings;
    }

    public function getSpectatorSettings(): SpectatorSettings {
        return $this->spectator_settings;
    }

    public function getGame(): ?Game {
        return $this->game;
    }

    public function getTeam(): ?Team {
        return $this->team;
    }

    public function getLastSessionHit(): ?Session {
        if($this->last_session_hit_time === null) {
            return null;
        }
        if(time() - $this->last_session_hit_time <= 10) {
            return $this->last_session_hit;
        }
        return null;
    }

    public function getTrackingSession(): ?Session {
        return $this->tracking_session;
    }

    public function getCoins(): int {
        return $this->coins;
    }

    public function getKills(): int {
        return $this->kills;
    }

    public function getWins(): int {
        return $this->wins;
    }

    public function resetSettings(): void {
        $this->game_settings = new GameSettings($this);
        $this->respawn_time = 0;
    }

    public function setSpectatorSettings(SpectatorSettings $spectator_settings): void {
        $this->spectator_settings = $spectator_settings;
    }

    public function setScoreboard(Scoreboard $scoreboard): void {
        $this->scoreboard = $scoreboard;
        $this->updateScoreboard();
    }

    public function setGame(?Game $game): void {
        $this->game = $game;
    }

    public function setTeam(?Team $team): void {
        $this->team = $team;
    }

    public function setLastSessionHit(?Session $last_session_hit): void {
        $this->last_session_hit = $last_session_hit;
        $this->last_session_hit_time = time();
    }

    public function setTrackingSession(?Session $tracking_session): void {
        $this->tracking_session = $tracking_session;
        $this->updateCompassDirection();
    }

    public function updateCompassDirection(): void {
        $this->player->setSpawn(
            $this->tracking_session !== null ? $this->tracking_session->getPlayer()->getPosition() : Vector3::zero()
        );
    }

    public function updateScoreboard(): void {
        $this->scoreboard->show($this);
    }

    public function attemptToRespawn(): void {
        if($this->respawn_time <= 0) {
            $this->respawn_time = null;
            $this->respawn();
            return;
        }

        if($this->respawn_time < 5) {
            $message = "{YELLOW}You will respawn in {RED}" . $this->respawn_time . " {YELLOW}" . ($this->respawn_time === 1 ? "second" : "seconds") . "!";
            $this->title("{RED}YOU DIED!", $message);
            $this->message($message);
        }

        $this->respawn_time--;
    }

    private function respawn(): void {
        $this->message("{YELLOW}You have respawned!");
        $this->title("{GREEN}RESPAWNED!", "", 7, 21, 7);

        $this->game_settings->apply();
        $this->player->setGamemode(GameMode::SURVIVAL());
        $this->player->setHealth($this->player->getMaxHealth());
        $this->player->teleport($this->team->getSpawnPoint());
    }

    public function setCoins(int $coins): void {
        $this->coins = $coins;
    }

    public function addCoins(int $coins): void {
        $this->coins += $coins;
    }

    public function setKills(int $kills): void {
        $this->kills = $kills;
    }

    public function addKill(): void {
        $this->kills++;
    }

    public function setWins(int $wins): void {
        $this->wins = $wins;
    }

    public function addWin(): void {
        $this->wins++;
    }

    public function isPlaying(): bool {
        return $this->hasGame() and $this->game->isPlaying($this);
    }

    public function isSpectator(): bool {
        return $this->hasGame() and $this->game->isSpectator($this);
    }

    public function hasGame(): bool {
        return $this->game !== null;
    }

    public function hasTeam(): bool {
        return $this->team !== null;
    }

    public function isRespawning(): bool {
        return $this->respawn_time !== null;
    }

    public function isOnline(): bool {
        return $this->player->isOnline();
    }

    public function showBossBar(string $title): void {
        $this->hideBossBar();
        $this->sendDataPacket(
            BossEventPacket::show($this->player->getId(), ColorUtils::translate($title), 10, false, 0, BossBarColor::BLUE)
        );
    }

    public function hideBossBar(): void {
        $this->sendDataPacket(BossEventPacket::hide($this->player->getId()));
    }

    public function sendDataPacket(ClientboundPacket $packet): void {
        $this->player->getNetworkSession()->sendDataPacket($packet);
    }

    public function playSound(string $sound): void {
        $location = $this->player->getLocation();
        $this->sendDataPacket(PlaySoundPacket::create(
            $sound,
            $location->getX(),
            $location->getY(),
            $location->getZ(),
            1,
            1
        ));
    }

    public function clearInventories(): void {
        $this->player->getCursorInventory()->clearAll();
        $this->player->getOffHandInventory()->clearAll();
        $this->player->getEnderInventory()->clearAll();
        $this->player->getArmorInventory()->clearAll();
        $this->player->getInventory()->clearAll();
    }

    public function giveWaitingItems(): void {
        $this->clearInventories();
        $this->player->getInventory()->setItem(8, BedwarsItems::LEAVE_GAME()->asItem());
    }

    public function giveSpectatorItems(): void {
        $this->clearInventories();

        $inventory = $this->player->getInventory();
        $inventory->setItem(0, BedwarsItems::TELEPORTER()->asItem());
        $inventory->setItem(4, BedwarsItems::SPECTATOR_SETTINGS()->asItem());
        $inventory->setItem(7, BedwarsItems::PLAY_AGAIN()->asItem());
        $inventory->setItem(8, BedwarsItems::RETURN_TO_LOBBY()->asItem());
    }

    public function addEffect(EffectInstance $effect_instance): void {
        $this->player->getEffects()->add($effect_instance);
    }

    public function teleportToWaitingWorld(): void {
        $world = $this->game->getMap()->getWaitingWorld();
        foreach($world->getPlayers() as $player) {
            if(SessionFactory::getSession($player)->getGame()?->getId() !== $this->game?->getId()) {
                $this->player->hidePlayer($player);
            } elseif(!$this->player->canSee($player)) {
                $this->player->showPlayer($player);
            }
        }

        $this->player->teleport($world->getSafeSpawn());
    }

    public function teleportToHub(): void {
        $this->player->getEffects()->clear();
        $this->player->setGamemode(GameMode::ADVENTURE());
        $this->player->setHealth($this->player->getMaxHealth());
        $this->player->setNameTag($this->player->getDisplayName());
        $this->player->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSafeSpawn());

        $this->clearInventories();
        $this->setScoreboard(new LobbyScoreboard());
        $this->showBossBar("{DARK_GREEN}You are playing on {AQUA}" . strtoupper(ConfigGetter::getIP()));
    }

    public function kill(int $cause): void {
        $killer_session = $this->getLastSessionHit();
        $session_username = $this->getColoredUsername();

        if($killer_session !== null) {
            $killer_session->addCoins(8); // TODO: Check for final kill
            $killer_session->addKill();
            $killer_session->playSound("random.orb");

            $killer_username = $killer_session->getColoredUsername();
            if($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
                $this->game->broadcastMessage($session_username . " {GRAY}was killed by " . $killer_username . "{GRAY}.");
            } elseif($cause === EntityDamageEvent::CAUSE_VOID) {
                $this->game->broadcastMessage($session_username . " {GRAY}was knocked into the void by " . $killer_username . "{GRAY}.");
            }

            foreach($this->player->getInventory()->getContents() as $item) {
                if(in_array($item->getTypeId(), [ItemTypeIds::IRON_INGOT, ItemTypeIds::GOLD_INGOT, ItemTypeIds::DIAMOND, ItemTypeIds::EMERALD])) {
                    $killer_session->getPlayer()->getInventory()->addItem($item);
                }
            }
        }

        if($cause === EntityDamageEvent::CAUSE_VOID and $killer_session === null) {
            $this->game->broadcastMessage($session_username . " {GRAY}fell to the void.");
        }

        $this->player->teleport($this->game->getMap()->getSpectatorSpawnPosition());
        $this->player->setGamemode(GameMode::SPECTATOR());

        if($this->team->isBedDestroyed()) {
            $this->game->removePlayer($this, false, true);
            return;
        } elseif($this->game->getStage() instanceof EndingStage) {
            return;
        }
        $this->respawn_time = self::RESPAWN_DURATION;

        $this->clearInventories();
        $this->title(
            "{RED}YOU DIED!",
            $message = "{YELLOW}You will respawn in {RED}" . self::RESPAWN_DURATION . " {YELLOW}seconds!", 0, 41
        );
        $this->message($message);
    }

    public function load(): void {
        BedWars::getInstance()->getProvider()->loadSession($this);
    }

    public function save(): void {
        BedWars::getInstance()->getProvider()->saveSession($this);
    }

    public function title(string $title, string $subtitle = "", int $fade_in = 0, int $stay = 21, int $fade_out = 0): void {
        $this->player->sendTitle(
            ColorUtils::translate($title), ColorUtils::translate($subtitle), $fade_in, $stay, $fade_out
        );
    }

    public function message(string $message): void {
        $this->player->sendMessage(ColorUtils::translate($message));
    }

}