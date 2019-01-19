<?php
include 'functions.php';

ini_set('memory_limit', '20M');
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
function dbUpdateChampionInfluence($champion, $numberOfMatches, $tier, $patchVersion, $conn)
{
    $champion_influence_request = mysqli_query($conn, "SELECT * FROM champ_influences where champ_id = $champion->id AND tier = '$tier' AND game_version = '$patchVersion'");
    $wins = -1;
    $losses = -1;
    $bans = -1;
    while ($row = mysqli_fetch_assoc($champion_influence_request)) {
        $wins = $row['champ_wins'];
        $losses = $row['champ_losses'];
        $bans = $row['champ_bans'];
    }

    $matchesBanned = sizeof($champion->matchesBanned);

    if ($champion->wins > $wins || $champion->losses > $losses || $matchesBanned > $bans) { // NEW DATA
        $winRate = $champion->wins + $champion->losses > 0 ? $champion->wins / ($champion->wins + $champion->losses) : 0;
        $lossRate = $champion->wins + $champion->losses > 0 ? $champion->losses / ($champion->wins + $champion->losses) : 0;
        $pickRateWhenAvailable = ($champion->wins + $champion->losses) / ($numberOfMatches - $matchesBanned);

        $chanceOfLosingTo = $pickRateWhenAvailable > 0 ? $pickRateWhenAvailable * $winRate : 0;
        $chanceOfWinningAgainst = $pickRateWhenAvailable > 0 ? $pickRateWhenAvailable * $lossRate : 0;
        if ($wins + $losses + $bans >= 0) { // IN DATABASE ALREADY, DO UPDATE
            $queryString = "UPDATE champ_influences set champ_id = $champion->id, game_version = '$patchVersion', tier = '$tier', champ_wins = $champion->wins, champ_losses = $champion->losses, champ_bans = $matchesBanned, chance_of_losing_to = $chanceOfLosingTo, chance_of_winning_against = $chanceOfWinningAgainst where champ_id = $champion->id AND tier = '$tier' AND game_version = '$patchVersion'";
        } else { // NOT IN DATABASE, DO INSERT
            $queryString = "INSERT INTO champ_influences (champ_id, game_version, tier, champ_wins, champ_losses, champ_bans, chance_of_losing_to, chance_of_winning_against) VALUES($champion->id, '$patchVersion', '$tier', $champion->wins, $champion->losses, $matchesBanned, $chanceOfLosingTo, $chanceOfWinningAgainst)";
        }

        if ($conn->query($queryString) === true) {
            echo $queryString;
            echo "<br>";
        } else {
            echo "Error: " . $queryString . "<br>" . $conn->error;
        }
    }
}

function storeMostInfluentialChampions($tier, $patchVersion)
{
    $championWinsAndLosses = getChampionWinsAndLossesForTier($tier);

    $wins = 0;
    $losses = 0;
    $bans = 0;
    foreach ($championWinsAndLosses->champions as $champion) {
        $wins += $champion->wins;
        $losses += $champion->losses;
        $bans += sizeof($champion->matchesBanned);
    }

    $highestInfluenceChampion = new ChampionStatistics();
    $championInfluences = array();
    global $conn;
    foreach ($championWinsAndLosses->champions as $champion) {
        dbUpdateChampionInfluence($champion, sizeof($championWinsAndLosses->matches), $tier, $patchVersion, $conn);
    }
    mysqli_close($conn);
}

if (isset($_POST['tier'])) {
    $tier = $_POST['tier'];
    $tiers = ["IRON", "BRONZE", "SILVER", "GOLD", "PLATINUM", "DIAMOND", "MASTER", "GRANDMASTER", "CHALLENGER"];
    if ($tier && in_array($tier, $tiers)) {
        $patchVersion = patchVersion();
        storeMostInfluentialChampions($tier, $patchVersion);
    }
}
