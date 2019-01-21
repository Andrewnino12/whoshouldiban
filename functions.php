<?php
include 'config.php';

class ChampionInfluence
{
    public $wins = -1;
    public $losses = -1;
    public $bans = -1;
    public $chanceOfLosingTo = 0;
    public $chanceOfWinningAgainst = 0;
    public $game_version = '';
    public $tier = '';
}

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
    $dbMatches = dbGetTierMatches($tier); // Get matches within tier
    $matchIds = array_keys($dbMatches); // Get matchIds only
    $dbSummonerMatches = dbGetSummonerMatchesFromMatchIds($matchIds); // Get summonerMatches from matchIds

    $championStatistics = new ChampionStatistics();
    $championStatistics->matches = $matchIds;
    $championStatistics->champions = dbGetChampions(); // Initialize list of champions

    foreach ($dbSummonerMatches as $dbSummonerMatch) {
        $dbMatch = $dbMatches[$dbSummonerMatch->match_id];

        // Count number of wins and losses for each champ
        if (($dbSummonerMatch->team_a && $dbMatch->team_a_won) || (!$dbSummonerMatch->team_a && !$dbMatch->team_a_won)) {
            $championStatistics->champions[$dbSummonerMatch->champ_pick]->wins++;
        } else {
            $championStatistics->champions[$dbSummonerMatch->champ_pick]->losses++;
        }

        // Keep track of which matches each champ was banned
        if ($dbSummonerMatch->champ_ban > 0) {
            if (!array_key_exists($dbMatch->match_id, $championStatistics->champions[$dbSummonerMatch->champ_ban]->matchesBanned)) {
                array_push($championStatistics->champions[$dbSummonerMatch->champ_ban]->matchesBanned, $dbMatch->match_id);
            }
        }
    }

    return $championStatistics;
}

function frontPageCards()
{
    // Main output for index.php
    $tiers = ["IRON", "BRONZE", "SILVER", "GOLD", "PLATINUM", "DIAMOND", "MASTER", "GRANDMASTER", "CHALLENGER"];
    $patchVersion = patchVersion();
    foreach ($tiers as $tier) {
        echo '<div class="col-md-4" style="text-align: center; display: inline-block; margin-bottom: 20px;">';
        echo "<img src='/emblems/" . $tier . "_Emblem.png' alt='error' style='width: 45px; margin:5px'>";
        echo '<p class="help-block" style="font-weight:bold">' . $tier . '</p>';
        getHighestInfluenceChampions($tier, $patchVersion);
        echo "</div>";
    }
}

function getHighestInfluenceChampions($tier, $patchVersion)
{
    global $conn;

    $champion_influence_request = mysqli_query($conn, "SELECT * FROM champ_influences where tier = '$tier' AND game_version = '$patchVersion' ORDER BY chance_of_losing_to DESC");
    $champ_array = array();
    $wins = 0;
    $losses = 0;
    while ($row = mysqli_fetch_assoc($champion_influence_request)) {
        $wins += $row['champ_wins'];
        $losses += $row['champ_losses'];
        // Only care about champions with positive win rates
        if ($row['chance_of_losing_to'] > $row['chance_of_winning_against']) {
            $champ = new ChampionInfluence();
            $champ->wins = $row['champ_wins'];
            $champ->losses = $row['champ_losses'];
            $champ->bans = $row['champ_bans'];
            $champ->chanceOfLosingTo = $row['chance_of_losing_to'];
            $champ->chanceOfWinningAgainst = $row['chance_of_winning_against'];
            $champ_array[$row["champ_id"]] = $champ;
        }
    }

    echo "Out of " . $wins / 5 . " matches<br>";
    $dbChampions = dbGetChampions();

    $count = 0;
    foreach ($champ_array as $key => $value) {
        // Only display 5 champions for each tier
        if ($count > 4) {
            break;
        }
        $count++;
        $champion = $champ_array[$key];
        $influenceRate = round($champion->chanceOfLosingTo * 100, 2);
        $name = $dbChampions[$key]->name;
        echo "<img src='/champ_icons/" . $name . "Square.png' alt='error' style='width: 45px; margin:5px'>";
        echo "<a href='/champion.php?name=$name' style='font-weight: bold'>$name</a> had an influence rate of $influenceRate%<br>";
        echo "With $champion->wins wins, $champion->losses losses, and " . $champion->bans . " matches banned<br>";
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
    // Get most recent patch version
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
    // Get matches for tier on the most recent patch
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

        $summoner_matches[$row['id']] = $summoner_match;
    }
    return $summoner_matches;
}

function dbGetChampions($championId = -1)
{
    global $conn;
    // Get all champions if $championId isn't specified
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

function dbGetChampionNames($championId = -1)
{
    global $conn;
    // Get all champions if $championId isn't specified
    $queryString = $championId > 0 ? "SELECT name FROM champions WHERE id = $championId" : "SELECT name FROM champions ORDER BY name";

    $champions_request = mysqli_query($conn, $queryString);
    while ($row = mysqli_fetch_assoc($champions_request)) {
        echo $row['name'] . ',';
    }
}

function dbGetSummoners($accountId = '', $limit = 1)
{
    global $conn;
    // Get random summoner if accountId isn't specified
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

    // Summoner wasn't found, add them to database
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
        // Add summoner to database and then get the automatically generated id
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
                    // In theory this shouldn't be hit because crawl rate is slow enough
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
    // Return true if Ranked Solo (420) or Ranked Flex (440)
    return $match->queue === 420 || $match->queue === 440;
}

function getRankedGameIdsBySummonerHistory($summonerHistory)
{
    // Filter out everything that isn't a ranked game
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
            // Get user's current ranked position for the given queue
            if (strpos($position->queueType, 'RANKED_FLEX') !== false && $match->queueId === 440
                || strpos($position->queueType, 'RANKED_SOLO') !== false && $match->queueId === 420) {
                $tier = $position->tier;
            }
        }

        array_push($tiers, $tier);
    }

    // Get mode of summoner's ranks to guess ELO for match
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

            // Summoner doesn't exist in the database yet
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
                // Determine role
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

        // Only add match if all 10 summonerMatches can be added as well
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
            // Not all users could be added to database
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
