<?php
include 'config.php';
include 'functions.php';

ini_set('memory_limit', '2M');
ignore_user_abort(true);
set_time_limit(3600);
ob_start();
// do initial processing here
//echo $response; // send the response
header('Connection: close');
header('Content-Length: ' . ob_get_length());
ob_end_flush();
ob_flush();
flush();
function crawl()
{
    $dbSummoners = dbGetSummoners();

    foreach ($dbSummoners as &$dbSummoner) {
        echo "CRAWLING: $dbSummoner->name";
        echo '<br>';
        $historyRequest = makeRequest(summonerHistoryByAccountIdUrl($dbSummoner->account_id));
        $summonerHistory = json_decode($historyRequest);
        // var_dump($summonerHistory);
        $gameIds = getRankedGameIdsBySummonerHistory($summonerHistory);
        // var_dump($gameIds);

        foreach ($gameIds as &$gameId) {
            // echo "gameId: $gameId";
            $matchRequest = makeRequest(matchByMatchIdUrl($gameId->gameId));
            $match = json_decode($matchRequest);
            // var_dump($match);
            dbStoreSummonerMatch($match);
        }
    }

    echo 'done crawling';
}

crawl();
