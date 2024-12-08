<?php

namespace App\Services\RiotApiServices;

class ScoreServices
{
    private  $structureScore = array(
        'tier' => array(
            'IRON' => 1000,
            'BRONZE' => 2000,
            'SILVER' => 3000,
            'GOLD' => 4000,
            'PLATINUM' => 5000,
            'EMERALD' => 6000,
            'DIAMOND' => 7000,
            'MASTER' => 8000,
            'GRANDMASTER' => 9000,
            'CHALLENGER' => 10000
        ),
        'rank' => array(
            'IV' => 100,
            'III' => 200,
            'II' => 300,
            'I' => 400,
        ),
    );


    public function getScoreSummoner($dataSummoner)
    {
        $score = 0;
        foreach ($this->structureScore as $key => $rules) {
            $dataToTest = $dataSummoner->$key;
            $score += $this->structureScore[$key][$dataToTest];
        }
        $score += $dataSummoner->leaguePoints;
        return $score;
    }

}