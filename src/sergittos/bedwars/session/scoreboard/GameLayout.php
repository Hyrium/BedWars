<?php
/*
* Copyright (C) Sergittos - All Rights Reserved
* Unauthorized copying of this file, via any medium is strictly prohibited
* Proprietary and confidential
*/

declare(strict_types=1);


namespace sergittos\bedwars\session\scoreboard;


use sergittos\bedwars\game\stage\PlayingStage;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\session\SessionFactory;
use sergittos\bedwars\utils\ColorUtils;
use sergittos\hyrium\session\scoreboard\Layout;
use sergittos\hyrium\session\Session;
use sergittos\bedwars\session\Session as BedwarsSession;
use function array_merge;
use function date;
use function gmdate;

class GameLayout implements Layout {

    public function build(Session $session): array {
        $session = SessionFactory::getSession($session->getPlayer());
        if(!$session->hasGame()) {
            return [];
        }

        $stage = $session->getGame()->getStage();
        if(!$stage instanceof PlayingStage) {
            return [];
        }
        $event = $stage->getNextEvent();

        return array_merge([
            "{GRAY}" . date("m/d/y"),
            " ",
            "{WHITE}" . $event->getName() . " in: {GREEN}" . gmdate("i:s", $event->getTimeRemaining()) . "   ",
            "  ",
        ], $this->getTeams($session));
    }

    private function getTeams(BedwarsSession $session): array {
        $teams = [];
        foreach($session->getGame()->getTeams() as $team) {
            $teams[] = ColorUtils::translate(
                $team->getColor() . $team->getFirstLetter() . " {WHITE}" . $team->getName() . ": " .
                $this->getBedStatus($team) . ($team->hasMember($session) ? " {GRAY}YOU" : " ")
            );
        }

        return $teams;
    }

    private function getBedStatus(Team $team): string {
        return !$team->isAlive() ? "{RED}X" : "{GREEN}" . ($team->isBedDestroyed() ? $team->getMembersCount() : "Alive");
    }

}