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

function mapIds($object)
{
    return $object->match_id;
}

function getChampionWinsAndLossesForTier($tier)
{
    $dbMatches = dbGetTierMatches($tier);
    // var_dump($dbMatches);
    // echo '<br>';
    // echo '<br>';

    $matchIds = array_keys($dbMatches);

    // var_dump($matchIds);
    // echo '<br>';
    // echo '<br>';
    $dbSummonerMatches = dbGetSummonerMatchesFromMatchIds($matchIds);

    // var_dump($dbSummonerMatches);

    // echo '<br>';
    // echo '<br>';

    // echo 'summonermatches: ';
    // var_dump($dbSummonerMatches);
    // $championStatistics = array(
    //     'matches' => array(),
    //     'champions' => dbGetChampions(),
    // );

    $championStatistics = new ChampionStatistics();
    $championStatistics->matches = $matchIds;
    $championStatistics->champions = dbGetChampions();

    $count = 0;
    foreach ($dbSummonerMatches as &$dbSummonerMatch) {
        $dbMatch = $dbMatches[$dbSummonerMatch->match_id];
        // echo '<br>dbSummonerMatch: ';
        // var_dump($dbSummonerMatch);
        // echo '<br>match_id: ' . $dbSummonerMatch->match_id;
        // echo '<br>';

        // echo '<br>match: ';
        // var_dump($dbMatch);
        // echo '<br>match_id:' . $dbMatch->match_id;
        // echo '<br>';

        // if ($championStatistics->matches[$dbMatch['match_id']]) {
        //     array_push($championStatistics->matches, $dbMatch['match_id']);
        // }

        // if (championStatistics.matches.indexOf(dbMatch.match_id) < 0) championStatistics.matches.push(dbMatch.match_id)

        if (($dbSummonerMatch->team_a && $dbMatch->team_a_won) || (!$dbSummonerMatch->team_a && !$dbMatch->team_a_won)) {
            // if ((dbSummonerMatch.team_a && dbMatch.team_a_won) || (!dbSummonerMatch.team_a && !dbMatch.team_a_won)) {
            //   // console.log("champ pick: " + dbSummonerMatch.champ_pick)

            $championStatistics->champions[$dbSummonerMatch->champ_pick]->wins++;
            //   championStatistics.champions.find(champion => champion.id === dbSummonerMatch.champ_pick).wins++
        } else {
            //   // console.log(dbSummonerMatch.champ_pick)
            $championStatistics->champions[$dbSummonerMatch->champ_pick]->losses++;
            //   championStatistics.champions.find(champion => champion.id === dbSummonerMatch.champ_pick).losses++

        }

        if ($dbSummonerMatch->champ_ban > 0) {
            // if (dbSummonerMatch.champ_ban > 0) {
            // echo "BANNED CHAMP: " . strlen($championStatistics->champions[$dbSummonerMatch->champ_ban]->matchesBanned[$dbMatch->match_id]);
            // var_dump($championStatistics->champions[$dbSummonerMatch->champ_ban]);
            if (strlen($championStatistics->champions[$dbSummonerMatch->champ_ban]->matchesBanned[$dbMatch->match_id]) <= 0) {
                //   if (championStatistics.champions.find(champion => champion.id === dbSummonerMatch.champ_ban).matchesBanned.indexOf(dbMatch.match_id) < 0) {
                array_push($championStatistics->champions[$dbSummonerMatch->champ_ban]->matchesBanned, $dbMatch->match_id);
                //     championStatistics.champions.find(champion => champion.id === dbSummonerMatch.champ_ban).matchesBanned.push(dbMatch.match_id)
            }
        }
    }

    return $championStatistics;
}

function frontPageCards()
{
    $tiers = ["IRON", "BRONZE", "SILVER", "GOLD"];

    foreach ($tiers as &$tier) {
        echo '<div class="col-md-6" style="text-align: center;"><p class="help-block">' . $tier . '</p>';
        $winsAndLosses = getChampionWinsAndLossesForTier($tier);
        getMostInfluentialChampion2($winsAndLosses);
        echo "</div>";
    }
}

function getMostInfluentialChampion($championWinsAndLosses)
{
    $highestInfluenceRate = 0;
    $highestInfluenceChampion = new ChampionStatistics();

    foreach ($championWinsAndLosses->champions as &$champion) {
        $winRate = $champion->wins / ($champion->wins + $champion->losses);
        $lossRate = $champion->losses / ($champion->wins + $champion->losses);
        $pickRateWhenAvailable = ($champion->wins + $champion->losses) / (sizeof($championWinsAndLosses->matches) - sizeof($champion->matchesBanned));
        $banRate = sizeof($champion->matchesBanned) / sizeof($championWinsAndLosses->matches);

        $chanceOfLosingTo = $pickRateWhenAvailable * $winRate;
        $chanceOfWinningAgainst = $pickRateWhenAvailable * $lossRate;
        // echo "$champion->name: chanceOfLosingTo = ${chanceOfLosingTo * 100}%, chanceOfWinningAgainst = ${chanceOfWinningAgainst * 100}% `)
        // echo "pickRateWhenAvailable: ${pickRateWhenAvailable * 100}%, winRate: ${winRate * 100}%, lossRate: ${lossRate * 100}% `)
        if ($chanceOfLosingTo > $highestInfluenceRate) {
            $highestInfluenceRate = $chanceOfLosingTo;
            $highestInfluenceChampion = $champion;
        }
    }
    echo "Out of " . sizeof($championWinsAndLosses->matches) . " matches<br>";
    echo "$highestInfluenceChampion->name had an influence rate of $highestInfluenceRate%<br>";
    echo "With $highestInfluenceChampion->wins wins, $highestInfluenceChampion->losses losses, and " . sizeof($highestInfluenceChampion->matchesBanned) . " matches banned";
}

function getMostInfluentialChampion2($championWinsAndLosses, $numberToReturn = 5)
{
    $highestInfluenceRate = 0;
    $highestInfluenceChampion = new ChampionStatistics();
    $championInfluences = array();

    foreach ($championWinsAndLosses->champions as &$champion) {
        $winRate = $champion->wins / ($champion->wins + $champion->losses);
        $lossRate = $champion->losses / ($champion->wins + $champion->losses);
        $pickRateWhenAvailable = ($champion->wins + $champion->losses) / (sizeof($championWinsAndLosses->matches) - sizeof($champion->matchesBanned));
        $banRate = sizeof($champion->matchesBanned) / sizeof($championWinsAndLosses->matches);

        $chanceOfLosingTo = $pickRateWhenAvailable * $winRate;
        $chanceOfWinningAgainst = $pickRateWhenAvailable * $lossRate;
        // echo "$champion->name: chanceOfLosingTo = ${chanceOfLosingTo * 100}%, chanceOfWinningAgainst = ${chanceOfWinningAgainst * 100}% `)
        // echo "pickRateWhenAvailable: ${pickRateWhenAvailable * 100}%, winRate: ${winRate * 100}%, lossRate: ${lossRate * 100}% `)
        if ($chanceOfLosingTo > $highestInfluenceRate) {
            // $highestInfluenceRate = $chanceOfLosingTo;
            // $highestInfluenceChampion = $champion;
            $championInfluences["$champion->id"] = $chanceOfLosingTo;
        }
    }
    // var_dump($championInfluences);
    $count = 0;
    echo "Out of " . sizeof($championWinsAndLosses->matches) . " matches<br>";
    arsort($championInfluences);
    foreach ($championInfluences as $key => $value) {
        if($count >= $numberToReturn) {
            break;
        }
        $count++;
        // echo "KEY IS: $key";
        // echo "<br>";
        // echo "VALUE IS: $value";
        // echo "<br>";
        $champion = $championWinsAndLosses->champions[$key];
        $value = $value * 100;
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
    ( select tier, max(game_version)
    from innodb.matches
    where tier = '$tier'
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
    // var_dump($joinedIds);
    global $conn;
    $summoner_matches_request = mysqli_query($conn, "SELECT * FROM summoner_matches where match_id in ($joinedIds)");
    $summoner_matches = array();
    while ($row = mysqli_fetch_assoc($summoner_matches_request)) {
        $summoner_match = new dbSummonerMatch();
        // $summoner_match = json_encode($row);
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

function dbGetSummoners($accountId = '', $limit = 5)
{
    global $conn;
    $queryString = strlen($accountId) > 0 ? "SELECT * FROM summoners WHERE account_id = '$accountId'" : "SELECT * FROM summoners ORDER BY id desc limit $limit";

    // echo"<br>";
    // echo"<br>";
    // echo $queryString;

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
        $summoner = json_decode($summonerRequest);
        $summoners = dbStoreSummoner($summoner);
    }
    return $summoners;
}

function dbStoreSummoner($summoner)
{
    global $conn;
    $queryString = "SELECT * FROM summoners WHERE account_id = '$summoner->accountId'";

    // echo $queryString;

    $dbSummoners_request = mysqli_query($conn, $queryString);
    $dbSummoners = array();
    while ($row = mysqli_fetch_assoc($dbSummoners_request)) {
        // echo 'dbStoreSummoner';
        $dbSummoner = new dbSummoner();
        $dbSummoner->id = $row['id'];
        $dbSummoner->account_id = $row['account_id'];
        $dbSummoner->name = $row['name'];
        $dbSummoner->profile_icon_id = $row['profile_icon_id'];
        $dbSummoner->revision_date = $row['revision_date'];
        $dbSummoner->summoner_level = $row['summoner_level'];
        $dbSummoner->summoner_id = $row['summoner_id'];
        $dbSummoners[$row['id']] = $dbSummoner;
    }

    if (sizeof($summoners) < 1) {
        $queryString = "INSERT INTO summoners (name, account_id, summoner_level, revision_date, summoner_id, profile_icon_id) VALUES" . getSummonerValuesString($summoner);
        // echo "insert $queryString";
        // echo "<br>";
        $dbSummoners_request = mysqli_query($conn, $queryString);
        $queryString = "SELECT * FROM summoners WHERE account_id = '$summoner->accountId'";
        // echo "select $queryString";
        // echo "<br>";

        $dbSummoners_request = mysqli_query($conn, $queryString);
        $dbSummoners = array();
        while ($row = mysqli_fetch_assoc($dbSummoners_request)) {
            // echo 'dbStoreSummoner';
            $dbSummoner = new dbSummoner();
            $dbSummoner->id = $row['id'];
            $dbSummoner->account_id = $row['account_id'];
            $dbSummoner->name = $row['name'];
            $dbSummoner->profile_icon_id = $row['profile_icon_id'];
            $dbSummoner->revision_date = $row['revision_date'];
            $dbSummoner->summoner_level = $row['summoner_level'];
            $dbSummoner->summoner_id = $row['summoner_id'];
            $dbSummoners[$row['id']] = $dbSummoner;
        }
    }
    return $dbSummoners;
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

function makeRequest($requestUrl)
{
    global $api_key;
    // echo '<br>';
    // echo $requestUrl . "?api_key=$api_key";
    // echo '<br>';
    return file_get_contents($requestUrl . "?api_key=$api_key");

}

function rankedGames($match)
{
    return $match->queue === 420 || $match->queue === 440 > $match;
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
        $positionsRequest = makeRequest(leaguePositionsBySummonerIdUrl($summonerId));
        $tier = 'UNRANKED';

        $positions = json_decode($positionsRequest);
        // var_dump($positions);
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
    // return "match_id = $match->gameId, season_id = $match->seasonId, platform_id = '$match->platformId', game_version = '$match->gameVersion', game_creation = $match->gameCreation, game_duration = $match->gameDuration, team_a_won = '$win', solo_queue = '$solo_queue', tier = '$matchTier'";
    return "($match->gameId, $match->seasonId, '$match->platformId', '$match->gameVersion', $match->gameCreation, $match->gameDuration, '$win', '$solo_queue', '$matchTier')";
}

function getUpdateMatchValuesString($match)
{
    $win = strpos($match->teams[0]->win, 'Win') !== 0 ? "True" : "False";
    $solo_queue = $match->queueId === 420 ? "True" : "False";
    $matchTier = getMatchTier($match);
    return "match_id = $match->gameId, season_id = $match->seasonId, platform_id = '$match->platformId', game_version = '$match->gameVersion', game_creation = $match->gameCreation, game_duration = $match->gameDuration, team_a_won = '$win', solo_queue = '$solo_queue', tier = '$matchTier'";
    // return "($match->gameId, $match->seasonId, '$match->platformId', '$match->gameVersion', $match->gameCreation, $match->gameDuration, '$win', '$solo_queue', '$matchTier')";
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
        // $text = "UPDATE matches set (match_id, season_id, platform_id, game_version, game_creation, game_duration, team_a_won, solo_queue, tier) = $matchValuesString where match_id = $match_id";
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
    // echo "matchId is " . $match_id;

    return $match_id;
}

function dbStoreSummonerMatch($match)
{
    global $conn;
    $dbSummonerMatches = dbGetSummonerMatchesFromMatchIds($match->gameId);

    if (sizeof($dbSummonerMatches) < 1) {
        $teamId = 0;

        foreach ($match->participantIdentities as &$participantIdentity) {
            $participantId = $participantIdentity->participantId - 1;
            if ($participantId > 4) {
                $teamId = 1;
            }

            $summonerRequest = makeRequest(summonerByNameUrl($participantIdentity->player->summonerName));
            $summoner = json_decode($summonerRequest);
            // echo '<br>Summoner: ';
            // var_dump($summoner);

            $dbSummoner = dbGetSummoners($summoner->accountId);
            // echo '<br>dbSummoner: ';
            // var_dump($dbSummoner);

            if (sizeof($dbSummoner) > 0) {
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

                // echo '<br>dbSummoner: ';
                // var_dump($dbSummoner);
                // echo '<br>';

                // echo '<br>dbSummoner ID: ';
                // var_dump(array_pop(array_reverse($dbSummoner))->summoner_id);
                // echo '<br>';

                $dbSummonerMatch = new dbSummonerMatch();
                $dbSummonerMatch->id = -1;
                $dbSummonerMatch->summoner_id = array_pop(array_reverse($dbSummoner))->summoner_id;
                $dbSummonerMatch->champ_pick = $participant->championId;
                $dbSummonerMatch->champ_ban = $match->teams[$teamId]->bans[$participantId % 5]->championId;
                $dbSummonerMatch->team_a = $teamId < 1;
                $dbSummonerMatch->role = $lane;
                $dbSummonerMatch->match_id = -1;

                // var_dump($dbSummonerMatch);

                array_push($dbSummonerMatches, $dbSummonerMatch);
            } else {
                echo "Couldn't store and get dbSummoner with name: $participantIdentity->player->summonerName";
            }
        }

        if (sizeof($dbSummonerMatches) === 10) {
            $dbMatchId = dbStoreMatch($match);
            // echo '<br>';
            // echo "dbMatchId: ";
            // echo '<br>';
            // var_dump($dbMatchId);
            if ($dbMatchId > 0) {
                $values = array();
                foreach ($dbSummonerMatches as &$dbSummonerMatch) {
                    // var_dump($dbSummonerMatch);
                    $team_a = $dbSummonerMatch->team_a ? "True" : "False";
                    $text = "('$dbSummonerMatch->summoner_id', $dbSummonerMatch->champ_pick, $dbSummonerMatch->champ_ban, '$team_a', '$dbSummonerMatch->role', $match->gameId)";
                    array_push($values, $text);
                }
                echo '<br>';
                // echo implode(';', $queryStrings);

                // $summoner_match_request = mysqli_query($conn, implode(';', $queryStrings));
                // $match_id = -1;
                // while ($row = mysqli_fetch_assoc($summoner_match_request)) {
                //     echo $row;
                // }

                $queryString = "INSERT INTO summoner_matches(summoner_id, champ_pick, champ_ban, team_a, role, match_id) VALUES " . implode(',', $values);

                echo $queryString;
                echo '<br>';

                if ($conn->query($queryString) === true) {
                    echo 'Inserted match with all 10 summonerMatches';
                    echo "<br>";
                } else {
                    echo "Error: " . $queryString . "<br>" . $conn->error;
                }

            } else {
                echo "Couldn't store match, won't be storing summonerMatches";
            }
        } else {
            echo "Couldn't create all 10 summonerMatches, only got " . sizeof($dbSummonerMatches);
        }

    } else if (sizeof($dbSummonerMatches) < 10) {
        echo "dbSummonerMatches.length was less than 10: " . sizeof($dbSummonerMatches);
    } else {
        echo "dbSummonerMatches already existed";
    }

    return $dbSummonerMatches;
}

// $winsAndLosses = getChampionWinsAndLossesForTier('DIAMOND');
// $champion = getMostInfluentialChampion($winsAndLosses);

// var_dump($winsAndLosses);
// var_dump($champion);

// $match = dbGetMatches(2939823480);
// echo "match is " . $match['tier'];

// $matches = dbGetTierMatches('GOLD');
// echo 'matches are: ' . var_dump($matches);

// $champions = dbGetChampions();
// var_dump($champions);

// $summoners = dbGetSummoners();
// var_dump($summoners);
