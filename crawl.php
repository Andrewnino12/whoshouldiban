<?php
include 'functions.php';

// Immediately sends a response to the cron-job
ini_set('memory_limit', '2M');
ignore_user_abort(true);
set_time_limit(60);
ob_start();
header('Connection: close');
header('Content-Length: ' . ob_get_length());
ob_end_flush();
ob_flush();
flush();
function crawl()
{
    // Get random summoner from database
    $dbSummoners = dbGetSummoners();

    // Loop through summoners and get their match history
    foreach ($dbSummoners as &$dbSummoner) {
        echo "CRAWLING: $dbSummoner->name";
        echo '<br>';
        // Get summoner's match history
        $historyRequest = makeRequest(summonerHistoryByAccountIdUrl($dbSummoner->account_id));

        if ($historyRequest->httpCode > 400) {
            break;
        }

        $summonerHistory = $historyRequest->body;
        // Filters out games that aren't ranked solo queue or flex queue
        $rankedGames = getRankedGameIdsBySummonerHistory($summonerHistory);

        $count = 0;
        foreach ($rankedGames as $rankedGame) {
            $count++;
            // Limit to four games because of rate limits
            if($count > 4) {
                break;
            }
            $matchRequest = makeRequest(matchByMatchIdUrl($rankedGame->gameId));

            if ($matchRequest->httpCode > 400) {
                break;
            }

            $match = $matchRequest->body;

            if ($match->gameId) {
                // Got match info, store in database
                dbStoreSummonerMatch($match);
                // Flush output immediately
                ob_flush();
                flush();
                // Sleep for eight seconds to help with rate limiting
                sleep(8);
            }
        }
    }

    echo 'done crawling';
}

crawl();
