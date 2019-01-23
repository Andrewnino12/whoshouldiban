<?php
include 'functions.php';

// Need more memory to process large sets of games
ini_set('memory_limit', '20M');
// Immediately sends response to cron-job
ignore_user_abort(true);
set_time_limit(120);
ob_start();
header('Connection: close');
header('Content-Length: ' . ob_get_length());
ob_end_flush();
ob_flush();
flush();
function dbUpdateChampionInfluence($champion, $numberOfMatches, $tier, $gameVersion, $conn)
{
    $champion_influence_request = mysqli_query($conn, "SELECT * FROM champ_influences where champ_id = $champion->id AND tier = '$tier' AND game_version = '$gameVersion'");
    $wins = -1;
    $losses = -1;
    $bans = -1;
    while ($row = mysqli_fetch_assoc($champion_influence_request)) {
        $wins = $row['champ_wins'];
        $losses = $row['champ_losses'];
        $bans = $row['champ_bans'];
    }

    $matchesBanned = sizeof($champion->matchesBanned);

    // New data is different from what's in the database currently
    if ($champion->wins > $wins || $champion->losses > $losses || $matchesBanned > $bans) {
        $winRate = $champion->wins + $champion->losses > 0 ? $champion->wins / ($champion->wins + $champion->losses) : 0;
        $lossRate = $champion->wins + $champion->losses > 0 ? $champion->losses / ($champion->wins + $champion->losses) : 0;
        $pickRateWhenAvailable = ($champion->wins + $champion->losses) / ($numberOfMatches - $matchesBanned);

        $chanceOfLosingTo = $pickRateWhenAvailable > 0 ? $pickRateWhenAvailable * $winRate : 0;
        $chanceOfWinningAgainst = $pickRateWhenAvailable > 0 ? $pickRateWhenAvailable * $lossRate : 0;
        $queryString = '';
        // In database already, do update
        if ($wins + $losses + $bans >= 0) {
            // Prevents wild values from being stored
            if($chanceOfLosingTo <= 1 && $chanceOfWinningAgainst <= 1) {
                $queryString = "UPDATE champ_influences set champ_id = $champion->id, game_version = '$gameVersion', tier = '$tier', champ_wins = $champion->wins, champ_losses = $champion->losses, champ_bans = $matchesBanned, chance_of_losing_to = $chanceOfLosingTo, chance_of_winning_against = $chanceOfWinningAgainst where champ_id = $champion->id AND tier = '$tier' AND game_version = '$gameVersion'";
            } else {
                error_log("CHAMPION ID: $champion->id, TIER: $tier, NUMBER OF WINS: $champion->wins, NUMBER OF LOSSES: $champion->losses, NUMBER OF MATCHES: $numberOfMatches, MATCHES BANNED: $matchesBanned, PICKRATEWHENAVAILABLE: $pickRateWhenAvailable, CHANCE OF LOSING TO: $chanceOfLosingTo, CHANCE OF WINNING AGAINST: $chanceOfWinningAgainst", 0);
            }
        // Not yet in database, do insert
        } else {
            $queryString = "INSERT INTO champ_influences (champ_id, game_version, tier, champ_wins, champ_losses, champ_bans, chance_of_losing_to, chance_of_winning_against) VALUES($champion->id, '$gameVersion', '$tier', $champion->wins, $champion->losses, $matchesBanned, $chanceOfLosingTo, $chanceOfWinningAgainst)";
        }

        if (strlen($queryString) > 0) {
            if ($conn->query($queryString) === true) {
                echo $queryString;
                echo "<br>";
            } else {
                echo "Error: " . $queryString . "<br>" . $conn->error;
            }
        }
    }
}

function storeMostInfluentialChampions($tier, $gameVersion)
{
    $championWinsAndLosses = getChampionWinsAndLossesForTier($tier, $gameVersion);

    $highestInfluenceChampion = new ChampionStatistics();
    $championInfluences = array();
    global $conn;
    foreach ($championWinsAndLosses->champions as $champion) {
        $numberOfMatches = sizeof($championWinsAndLosses->matches);
        if($numberOfMatches > 0 ) {
            dbUpdateChampionInfluence($champion, $numberOfMatches, $tier, $gameVersion, $conn);
        } else {
            echo "NO MATCHES FOR $champion->id in $tier";
        }
    }
    echo "FINISHED $tier";
    echo "<br>";
    mysqli_close($conn);
}

if (isset($_POST['tier'])) {
    $tier = $_POST['tier'];
    $tiers = ["IRON", "BRONZE", "SILVER", "GOLD", "PLATINUM", "DIAMOND", "MASTER", "GRANDMASTER", "CHALLENGER"];
    if ($tier && in_array($tier, $tiers)) {
        storeMostInfluentialChampions($tier, $gameVersions[0]);
    }
}
