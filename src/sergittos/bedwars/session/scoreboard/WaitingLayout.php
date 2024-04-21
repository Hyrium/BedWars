<?php
/*
* Copyright (C) Sergittos - All Rights Reserved
* Unauthorized copying of this file, via any medium is strictly prohibited
* Proprietary and confidential
*/

declare(strict_types=1);


namespace sergittos\bedwars\session\scoreboard;


use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\utils\ConfigGetter;
use sergittos\bedwars\utils\GameUtils;
use sergittos\hyrium\session\scoreboard\Layout;
use sergittos\hyrium\session\Session;
use sergittos\bedwars\session\SessionFactory;

class WaitingLayout implements Layout {

    public function build(Session $session): array {
        $session = SessionFactory::getSession($session->getPlayer());

        $game = $session->getGame();
        $map = $game->getMap();
        $stage = $game->getStage();
        return [
            " ",
            "{WHITE}Map: {GREEN}" . $map->getName(),
            "{WHITE}Players: {GREEN}" . $game->getPlayersCount() . "/" . $map->getMaxCapacity(),
            "  ",
            !$stage instanceof StartingStage ? "{WHITE}Waiting..." : "{WHITE}Starting in {GREEN}" . $stage->getCountdown() . "s",
            "   ",
            "{WHITE}Mode: {GREEN}" . GameUtils::getMode($map->getPlayersPerTeam()),
            "{WHITE}Version: {GRAY}v" . ConfigGetter::getVersion()
        ];
    }

}