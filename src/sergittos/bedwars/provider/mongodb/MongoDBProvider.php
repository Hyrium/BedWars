<?php
/*
* Copyright (C) Sergittos - All Rights Reserved
* Unauthorized copying of this file, via any medium is strictly prohibited
* Proprietary and confidential
*/

declare(strict_types=1);


namespace sergittos\bedwars\provider\mongodb;


use libasynCurl\Curl;
use pocketmine\utils\InternetRequestResult;
use sergittos\bedwars\provider\Provider;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\settings\SpectatorSettings;
use sergittos\hyrium\Hyrium;
use function json_decode;

class MongoDBProvider extends Provider {

    public function loadSession(Session $session): void {
        Curl::getRequest(Hyrium::API_URL . "users/" . $session->getXuid(), 10, [], function(?InternetRequestResult $result) use ($session): void {
            $data = json_decode($result->getBody(), true);

            $session->setCoins($data["coins"]);
            $session->setKills($data["kills"]);
            $session->setWins($data["wins"]);
            $session->setSpectatorSettings(SpectatorSettings::fromData($session, $data));
        });
    }

    public function updateCoins(Session $session): void {
        $this->updateSession($session);
    }

    public function updateKills(Session $session): void {
        $this->updateSession($session);
    }

    public function updateWins(Session $session): void {
        $this->updateSession($session);
    }

    private function updateSession(Session $session): void {
        Curl::putRequest(Hyrium::API_URL . "users/" . $session->getXuid(), [
            "coins" => $session->getCoins(),
            "kills" => $session->getKills(),
            "wins" => $session->getWins()
        ], 10, ["Content-Type: application/json"], function(?InternetRequestResult $result) use ($session): void {});
    }

    public function saveSession(Session $session): void {}

}