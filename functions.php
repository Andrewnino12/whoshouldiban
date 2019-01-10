<?php
include 'config.php';

class ChampionWinsLossesAndBans
{
    public $id = 0;
    public $name = "";
    public $wins = 0;
    public $losses = 0;
    public $matchesBanned = array();
}

class ChampionStatistics
{
    public $matches = array();
    public $champions = array(); //ChampionWinsLossesAndBans[]
}

class dbMatch
{
    public $id = 0;
    public $match_id = 0;
    public $season_id = 0;
    public $platform_id = '';
    public $game_version = '';
    public $game_creation = 0;
    public $game_duration = 0;
    public $team_a_won = false;
    public $solo_queue = false;
    public $tier = '';
}

class dbSummonerMatch
{
    public $id = 0;
    public $match_id = 0;
    public $summoner_id = 0;
    public $team_a = false;
    public $role = '';
    public $champ_pick = 0;
    public $champ_ban = 0;
}

class dbSummoner
{
    public $id = 0;
    public $account_id = '';
    public $name = '';
    public $profile_icon_id = 0;
    public $revision_date = 0;
    public $summoner_level = 0;
    public $summoner_id = '';
}

class httpResponse
{
    public $httpCode = 404;
    public $body = '';
}

function mapIds($object)
{
    return $object->match_id;
}

function getChampionWinsAndLossesForTier($tier)
{
    $dbMatches = dbGetTierMatches($tier);
    $matchIds = array_keys($dbMatches);
    $dbSummonerMatches = dbGetSummonerMatchesFromMatchIds($matchIds);

    $championStatistics = new ChampionStatistics();
    $championStatistics->matches = $matchIds;
    $championStatistics->champions = dbGetChampions();

    $count = 0;
    foreach ($dbSummonerMatches as &$dbSummonerMatch) {
        $dbMatch = $dbMatches[$dbSummonerMatch->match_id];

        if (($dbSummonerMatch->team_a && $dbMatch->team_a_won) || (!$dbSummonerMatch->team_a && !$dbMatch->team_a_won)) {
            $championStatistics->champions[$dbSummonerMatch->champ_pick]->wins++;
        } else {
            $championStatistics->champions[$dbSummonerMatch->champ_pick]->losses++;
        }

        if ($dbSummonerMatch->champ_ban > 0) {
            if (strlen($championStatistics->champions[$dbSummonerMatch->champ_ban]->matchesBanned[$dbMatch->match_id]) <= 0) {
                array_push($championStatistics->champions[$dbSummonerMatch->champ_ban]->matchesBanned, $dbMatch->match_id);
            }
        }
    }

    return $championStatistics;
}

function frontPageCards()
{
    $tiers = ["IRON", "BRONZE", "SILVER", "GOLD", "PLATINUM", "DIAMOND", "GRANDMASTER", "CHALLENGER"];

    foreach ($tiers as &$tier) {
        echo '<div class="col-md-6" style="text-align: center;"><p class="help-block">' . $tier . '</p>';
        $winsAndLosses = getChampionWinsAndLossesForTier($tier);
        getMostInfluentialChampions($winsAndLosses);
        echo "</div>";
    }
}

function getMostInfluentialChampions($championWinsAndLosses, $numberToReturn = 5)
{
    $highestInfluenceRate = 0;
    $highestInfluenceChampion = new ChampionStatistics();
    $championInfluences = array();

    foreach ($championWinsAndLosses->champions as &$champion) {
        if ($champion->wins + $champion->losses > 0) {
            $winRate = $champion->wins / ($champion->wins + $champion->losses);
            $lossRate = $champion->losses / ($champion->wins + $champion->losses);
            $pickRateWhenAvailable = ($champion->wins + $champion->losses) / (sizeof($championWinsAndLosses->matches) - sizeof($champion->matchesBanned));
            $banRate = sizeof($champion->matchesBanned) / sizeof($championWinsAndLosses->matches);

            $chanceOfLosingTo = $pickRateWhenAvailable * $winRate;
            $chanceOfWinningAgainst = $pickRateWhenAvailable * $lossRate;
            if ($chanceOfLosingTo > $highestInfluenceRate) {
                $championInfluences["$champion->id"] = $chanceOfLosingTo;
            }
        }
    }
    $count = 0;
    echo "Out of " . sizeof($championWinsAndLosses->matches) . " matches<br>";
    arsort($championInfluences);
    foreach ($championInfluences as $key => $value) {
        if ($count >= $numberToReturn) {
            break;
        }
        $count++;
        $champion = $championWinsAndLosses->champions[$key];
        $value = round($value * 100, 2);
        echo "$champion->name had an influence rate of $value%<br>";
        echo "With $champion->wins wins, $champion->losses losses, and " . sizeof($champion->matchesBanned) . " matches banned<br>";
    }
}

function dbGetMatches($matchId)
{
    global $conn;
    $matches_request = mysqli_query($conn, "SELECT * FROM matches where match_id = $matchId");
    $match = array();
    while ($row = mysqli_fetch_assoc($matches_request)) {
        $match = $row;
    }
    return $match;
}

function patchVersion()
{
    global $conn;
    $patch_request = mysqli_query($conn, "select max(game_version) from matches");
    $patchVersion = "UNKNOWN";
    while ($row = mysqli_fetch_assoc($patch_request)) {
        $patchVersion = $row['max(game_version)'];
    }
    return $patchVersion;
}

function dbGetTierMatches($tier)
{
    global $conn;
    $matches_request = mysqli_query($conn, "SELECT * FROM matches where (tier, game_version) in
    ( select tier, game_version
    from innodb.matches
    where tier = '$tier' && game_version in (select max(game_version) from innodb.matches)
    )");
    $matches = array();
    while ($row = mysqli_fetch_assoc($matches_request)) {
        $dbMatch = new dbMatch();
        $dbMatch->id = $row['id'];
        $dbMatch->match_id = $row['match_id'];
        $dbMatch->season_id = $row['season_id'];
        $dbMatch->platform_id = $row['platform_id'];
        $dbMatch->game_version = $row['game_version'];
        $dbMatch->game_creation = $row['game_creation'];
        $dbMatch->game_duration = $row['game_duration'];
        $dbMatch->team_a_won = $row['team_a_won'] == 'True';
        $dbMatch->solo_queue = $row['solo_queue'] == 'True';
        $dbMatch->tier = $row['tier'];

        $matches[$row['match_id']] = $dbMatch;
    }
    return $matches;
}

function dbGetSummonerMatchesFromMatchIds($matchIds)
{
    $joinedIds = implode(',', $matchIds);
    global $conn;
    $summoner_matches_request = mysqli_query($conn, "SELECT * FROM summoner_matches where match_id in ($joinedIds)");
    $summoner_matches = array();
    while ($row = mysqli_fetch_assoc($summoner_matches_request)) {
        $summoner_match = new dbSummonerMatch();
        $summoner_match->id = $row['id'];
        $summoner_match->summoner_id = $row['summoner_id'];
        $summoner_match->champ_pick = $row['champ_pick'];
        $summoner_match->champ_ban = $row['champ_ban'];
        $summoner_match->team_a = $row['team_a'] == 'True';
        $summoner_match->role = $row['role'];
        $summoner_match->match_id = $row['match_id'];

        $summoner_matches[$row['match_id']] = $summoner_match;
    }
    return $summoner_matches;
}

function dbGetChampions($championId = -1)
{
    global $conn;
    $queryString = $championId > 0 ? "SELECT * FROM champions WHERE id = $championId" : "SELECT * FROM champions ORDER BY name";

    $champions_request = mysqli_query($conn, $queryString);
    $champions = array();
    while ($row = mysqli_fetch_assoc($champions_request)) {
        $championWinsLossesAndBans = new ChampionWinsLossesAndBans();
        $championWinsLossesAndBans->id = $row['id'];
        $championWinsLossesAndBans->name = $row['name'];
        $championWinsLossesAndBans->wins = 0;
        $championWinsLossesAndBans->losses = 0;
        $championWinsLossesAndBans->matchesBanned = array();
        $champions[$championWinsLossesAndBans->id] = $championWinsLossesAndBans;
    }
    return $champions;
}

function dbGetSummoners($accountId = '', $limit = 1)
{
    global $conn;
    $queryString = strlen($accountId) > 0 ? "SELECT * FROM summoners WHERE account_id = '$accountId'" : "SELECT * FROM summoners ORDER BY rand() desc limit $limit";

    $summoners_request = mysqli_query($conn, $queryString);
    $summoners = array();
    while ($row = mysqli_fetch_assoc($summoners_request)) {
        $summoner = new dbSummoner();
        $summoner->id = $row['id'];
        $summoner->account_id = $row['account_id'];
        $summoner->name = $row['name'];
        $summoner->profile_icon_id = $row['profile_icon_id'];
        $summoner->revision_date = $row['revision_date'];
        $summoner->summoner_level = $row['summoner_level'];
        $summoner->summoner_id = $row['summoner_id'];
        $summoners[$row['id']] = $summoner;
    }

    if (sizeof($summoners) < 1) {
        $summonerRequest = makeRequest(summonerByAccountIdUrl($accountId));
        $summoner = $summonerRequest->body;

        $summoners = dbStoreSummoner($summoner);
    }
    return $summoners;
}

function dbGetSummonerByName($name)
{
    global $conn;

    $summoners_request = mysqli_query($conn, "SELECT * FROM summoners WHERE name = '$name'");
    $dbSummoner = new dbSummoner();
    $dbSummoner->id = -1;
    while ($row = mysqli_fetch_assoc($summoners_request)) {
        $dbSummoner->id = $row['id'];
        $dbSummoner->account_id = $row['account_id'];
        $dbSummoner->name = $row['name'];
        $dbSummoner->profile_icon_id = $row['profile_icon_id'];
        $dbSummoner->revision_date = $row['revision_date'];
        $dbSummoner->summoner_level = $row['summoner_level'];
        $dbSummoner->summoner_id = $row['summoner_id'];
    }

    return $dbSummoner;
}

function dbStoreSummoner($summoner)
{
    global $conn;
    $queryString = "SELECT * FROM summoners WHERE account_id = '$summoner->accountId'";

    $dbSummoners_request = mysqli_query($conn, $queryString);
    $dbSummoner = new dbSummoner();
    $dbSummoner->id = -1;
    while ($row = mysqli_fetch_assoc($dbSummoners_request)) {
        $dbSummoner->id = $row['id'];
        $dbSummoner->account_id = $row['account_id'];
        $dbSummoner->name = $row['name'];
        $dbSummoner->profile_icon_id = $row['profile_icon_id'];
        $dbSummoner->revision_date = $row['revision_date'];
        $dbSummoner->summoner_level = $row['summoner_level'];
        $dbSummoner->summoner_id = $row['summoner_id'];
    }

    if ($dbSummoner->id < 0) {
        $queryString = "INSERT INTO summoners (name, account_id, summoner_level, revision_date, summoner_id, profile_icon_id) VALUES" . getSummonerValuesString($summoner);
        $dbSummoners_request = mysqli_query($conn, $queryString);
        $queryString = "SELECT * FROM summoners WHERE account_id = '$summoner->accountId'";

        $dbSummoners_request = mysqli_query($conn, $queryString);
        while ($row = mysqli_fetch_assoc($dbSummoners_request)) {
            $dbSummoner->id = $row['id'];
            $dbSummoner->account_id = $row['account_id'];
            $dbSummoner->name = $row['name'];
            $dbSummoner->profile_icon_id = $row['profile_icon_id'];
            $dbSummoner->revision_date = $row['revision_date'];
            $dbSummoner->summoner_level = $row['summoner_level'];
            $dbSummoner->summoner_id = $row['summoner_id'];
        }
    }
    return $dbSummoner;
}

function summonerHistoryByAccountIdUrl($accountId)
{
    $encodedAccountId = rawurlencode($accountId);
    return "https://na1.api.riotgames.com/lol/match/v4/matchlists/by-account/$encodedAccountId";
}

function summonerByAccountIdUrl($accountId)
{
    $encodedAccountId = rawurlencode($accountId);
    return "https://na1.api.riotgames.com/lol/summoner/v4/summoners/by-account/$encodedAccountId";
}

function matchByMatchIdUrl($matchId)
{
    return "https://na1.api.riotgames.com/lol/match/v4/matches/$matchId";
}

function get_headers_from_curl_response($response)
{
    $headers = array();

    $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

    foreach (explode("\r\n", $header_text) as $i => $line) {
        if ($i === 0) {
            $headers['http_code'] = $line;
        } else {
            list($key, $value) = explode(': ', $line);

            $headers[$key] = $value;
        }
    }

    return $headers;
}

function makeRequest($requestUrl)
{
    global $api_key;
    $encodedUrl = $requestUrl . "?api_key=$api_key";

    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, $encodedUrl);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $retryCount = 0;
    $httpCode = 404;

    while ($httpCode > 400 && $retryCount < 1) {
        $retryCount++;
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("Error: " . curl_error($ch));
            echo "Error: " . curl_error($ch);
        } else {
            if ($response) {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headers = get_headers_from_curl_response($response);
                $body = substr($response, strpos($response, "{"));
                if (strpos($requestUrl, "positions") !== false) {
                    $body = substr($response, strpos($response, "["));
                }
                $decodedJSON = json_decode($body);

                if ($httpCode === 429) {
                    error_log("Retrying after " . $headers['Retry-After'] . " seconds");
                    echo "Retrying after " . $headers['Retry-After'] . " seconds";
                    // sleep(intval($headers['Retry-After']));
                }
            } else {
                error_log("NO DATA FROM RESPONSE: " . $encodedUrl, 0);
                echo "NO DATA FROM RESPONSE: " . $encodedUrl, 0;
            }
        }

    }

    $httpResponse = new httpResponse();
    $httpResponse->httpCode = $httpCode;
    $httpResponse->body = $decodedJSON;

    return $httpResponse;

}

function rankedGames($match)
{
    return $match->queue === 420 || $match->queue === 440;
}

function getRankedGameIdsBySummonerHistory($summonerHistory)
{
    return array_filter($summonerHistory->matches, "rankedGames");
}

function getSummonerIdsFromMatch($match)
{
    $summonerIds = array();
    foreach ($match->participantIdentities as &$participantIdentity) {
        array_push($summonerIds, $participantIdentity->player->summonerId);
    }
    return $summonerIds;
}

function leaguePositionsBySummonerIdUrl($summonerId)
{
    $encodedSummonerId = rawurlencode($summonerId);
    return "https://na1.api.riotgames.com/lol/league/v4/positions/by-summoner/$encodedSummonerId";
}

function getMatchTier($match)
{
    $summonerIds = getSummonerIdsFromMatch($match);
    $tiers = array();
    foreach ($summonerIds as &$summonerId) {
        $positionsResponse = makeRequest(leaguePositionsBySummonerIdUrl($summonerId));
        $positions = $positionsResponse->body;
        $tier = 'UNRANKED';

        foreach ($positions as &$position) {
            if (strpos($position->queueType, 'RANKED_FLEX') !== false && $match->queueId === 440
                || strpos($position->queueType, 'RANKED_SOLO') !== false && $match->queueId === 420) {
                $tier = $position->tier;
            }
        }

        array_push($tiers, $tier);
    }

    $values = array_count_values($tiers);
    $mode = array_search(max($values), $values);

    return $mode;
}

function summonerByNameUrl($summonerName)
{
    $encodedName = rawurlencode($summonerName);
    return "https://na1.api.riotgames.com/lol/summoner/v4/summoners/by-name/$encodedName";
}

function getInsertMatchValuesString($match)
{
    $win = strpos($match->teams[0]->win, 'Win') !== 0 ? "True" : "False";
    $solo_queue = $match->queueId === 420 ? "True" : "False";
    $matchTier = getMatchTier($match);
    return "($match->gameId, $match->seasonId, '$match->platformId', '$match->gameVersion', $match->gameCreation, $match->gameDuration, '$win', '$solo_queue', '$matchTier')";
}

function getUpdateMatchValuesString($match)
{
    $win = strpos($match->teams[0]->win, 'Win') !== 0 ? "True" : "False";
    $solo_queue = $match->queueId === 420 ? "True" : "False";
    $matchTier = getMatchTier($match);
    return "match_id = $match->gameId, season_id = $match->seasonId, platform_id = '$match->platformId', game_version = '$match->gameVersion', game_creation = $match->gameCreation, game_duration = $match->gameDuration, team_a_won = '$win', solo_queue = '$solo_queue', tier = '$matchTier'";
}

function getSummonerValuesString($summoner)
{
    return "('$summoner->name', '$summoner->accountId', $summoner->summonerLevel, $summoner->revisionDate, '$summoner->id', $summoner->profileIconId)";
}

function dbStoreMatch($match)
{
    global $conn;
    $match_request = mysqli_query($conn, "SELECT id FROM matches WHERE match_id = '$match->gameId'");
    $match_id = -1;
    while ($row = mysqli_fetch_assoc($match_request)) {
        $match_id = $row['id'];
    }

    $matchValuesString = getInsertMatchValuesString($match);
    $text = "INSERT INTO matches (match_id, season_id, platform_id, game_version, game_creation, game_duration, team_a_won, solo_queue, tier) VALUES$matchValuesString";

    if ($match_id > 0) {
        $matchValuesString = getUpdateMatchValuesString($match);
        $text = "UPDATE matches set $matchValuesString where match_id = $match_id";
    }

    if ($conn->query($text) === true) {
        echo "New record created successfully";
        echo "<br>";
    } else {
        echo "Error: " . $text . "<br>" . $conn->error;
    }

    $match_request = mysqli_query($conn, "SELECT id FROM matches WHERE match_id = '$match->gameId'");
    $match_id = -1;
    while ($row = mysqli_fetch_assoc($match_request)) {
        $match_id = $row['id'];
    }
    echo '<br>';

    return $match_id;
}

function dbStoreSummonerMatch($match)
{
    global $conn;
    $dbSummonerMatches = dbGetSummonerMatchesFromMatchIds(array($match->gameId));

    if (sizeof($dbSummonerMatches) < 1) {
        $teamId = 0;
        foreach ($match->participantIdentities as &$participantIdentity) {
            $participantId = $participantIdentity->participantId - 1;
            if ($participantId > 4) {
                $teamId = 1;
            }

            $summonerName = $participantIdentity->player->summonerName;

            $dbSummoner = dbGetSummonerByName($summonerName);

            if ($dbSummoner->id < 0) {
                $summonerRequest = makeRequest(summonerByNameUrl($summonerName));

                if ($summonerRequest->httpCode > 400) {
                    break;
                }

                $summoner = $summonerRequest->body;
                $dbSummoner = dbStoreSummoner($summoner);
            }

            if ($dbSummoner->id > 0) {
                $participant = $match->participants[$participantId];
                $lane = $participant->timeline->lane;
                switch ($lane) {
                    case 'MID':
                    case 'MIDDLE':
                        $lane = 'mid';
                        break;
                    case 'TOP':
                        $lane = 'top';
                        break;
                    case 'JUNGLE':
                        $lane = 'jungle';
                        break;
                    case 'BOT':
                    case 'BOTTOM':
                        if ($participant->timeline->role == 'DUO_SUPPORT') {
                            $lane = 'support';
                        } else {
                            $lane = 'bot';
                        }
                        break;
                    default:
                }

                $dbSummonerMatch = new dbSummonerMatch();
                $dbSummonerMatch->id = -1;
                $dbSummonerMatch->summoner_id = $dbSummoner->summoner_id;
                $dbSummonerMatch->champ_pick = $participant->championId;
                $dbSummonerMatch->champ_ban = $match->teams[$teamId]->bans[$participantId % 5]->championId;
                $dbSummonerMatch->team_a = $teamId < 1;
                $dbSummonerMatch->role = $lane;
                $dbSummonerMatch->match_id = -1;

                array_push($dbSummonerMatches, $dbSummonerMatch);
            } else {
                error_log("Couldn't store and get dbSummoner with name: $summonerName", 0);
                echo "Couldn't store and get dbSummoner with name: $summonerName";
            }
        }

        if (sizeof($dbSummonerMatches) === 10) {
            $dbMatchId = dbStoreMatch($match);
            if ($dbMatchId > 0) {
                $values = array();
                foreach ($dbSummonerMatches as &$dbSummonerMatch) {
                    $team_a = $dbSummonerMatch->team_a ? "True" : "False";
                    $text = "('$dbSummonerMatch->summoner_id', $dbSummonerMatch->champ_pick, $dbSummonerMatch->champ_ban, '$team_a', '$dbSummonerMatch->role', $match->gameId)";
                    array_push($values, $text);
                }
                echo '<br>';
                $queryString = "INSERT INTO summoner_matches(summoner_id, champ_pick, champ_ban, team_a, role, match_id) VALUES " . implode(',', $values);
                echo $queryString;
                echo '<br>';

                if ($conn->query($queryString) === true) {
                    error_log("Inserted match with all 10 summonerMatches", 0);
                    echo 'Inserted match with all 10 summonerMatches';
                    echo "<br>";
                } else {
                    echo "Error: " . $queryString . "<br>" . $conn->error;
                }

            } else {
                echo "Couldn't store match, won't be storing summonerMatches";
            }
        } else {
            echo "<br>";
            echo "<br>";
            echo "Couldn't create all 10 summonerMatches, only got " . sizeof($dbSummonerMatches);
            echo "<br>";
            var_dump($dbSummonerMatches);
            echo "<br>";
            echo "<br>";

        }

    } else if (sizeof($dbSummonerMatches) < 10) {
        echo "dbSummonerMatches.length was less than 10: " . sizeof($dbSummonerMatches);
    } else {
        echo "dbSummonerMatches already existed";
    }

    return $dbSummonerMatches;
}
