<?php
include 'config.php';
include 'functions.php';

ini_set('memory_limit', '2M');
ignore_user_abort(true);
set_time_limit(60);
// ignore_user_abort(false);
ob_start();
// // do initial processing here
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

        if ($historyRequest->httpCode > 400) {
            break;
        }

        $summonerHistory = $historyRequest->body;
        $rankedGames = getRankedGameIdsBySummonerHistory($summonerHistory);

        $rankedGame = current($rankedGames);
        $count = 0;
        foreach ($rankedGames as &$rankedGame) {
            $count++;
            if($count > 5) {
                break;
            }
            $matchRequest = makeRequest(matchByMatchIdUrl($rankedGame->gameId));

            if ($matchRequest->httpCode > 400) {
                break;
            }

            $match = $matchRequest->body;

            if ($match->gameId) {
                dbStoreSummonerMatch($match);
                ob_flush();
                flush();
                sleep(6);
            }
        }
    }

    echo 'done crawling';
}

crawl();
