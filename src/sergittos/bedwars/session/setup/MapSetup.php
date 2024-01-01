<?php

declare(strict_types=1);


namespace sergittos\bedwars\session\setup;


use sergittos\bedwars\game\map\Map;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\setup\builder\MapBuilder;
use sergittos\bedwars\session\setup\step\PreparingMapStep;
use sergittos\bedwars\session\setup\step\Step;

class MapSetup {

    private Session $session;
    private MapBuilder $map_builder;
    private Step $step;

    public function __construct(Session $session, MapBuilder $map_builder) {
        $this->map_builder = $map_builder;
        $this->session = $session;

        $this->setStep(new PreparingMapStep());
    }

    public function getMapBuilder(): MapBuilder {
        return $this->map_builder;
    }

    public function getStep(): Step {
        return $this->step;
    }

    public function setStep(Step $step): void {
        $this->step = $step;
        $this->step->start($this->session);
    }

    public function createMap(): Map {
        return $this->map_builder->build();
    }

}