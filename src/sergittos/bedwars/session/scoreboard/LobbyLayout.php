<?php
/*
* Copyright (C) Sergittos - All Rights Reserved
* Unauthorized copying of this file, via any medium is strictly prohibited
* Proprietary and confidential
*/

declare(strict_types=1);


namespace sergittos\bedwars\session\scoreboard;


use sergittos\bedwars\session\SessionFactory;
use sergittos\hyrium\session\scoreboard\Layout;
use sergittos\hyrium\session\Session;

class LobbyLayout implements Layout {

    public function build(Session $session): array {
        $session = SessionFactory::getSession($session->getPlayer());
        return [
            " ",
            "{WHITE}Coins: {GREEN}" . $session->getCoins(),
            "  ",
            "{WHITE}Total Kills: {GREEN}" . $session->getKills(),
            "{WHITE}Total Wins: {GREEN}" . $session->getWins(),
        ];
    }

}