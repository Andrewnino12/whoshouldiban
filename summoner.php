<!DOCTYPE  HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"  "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <meta  http-equiv="Content-Type" content="text/html;  charset=iso-8859-1">
    <?php include "header.php"?>
    <body>
	<h2 class="page-header text-center">
        Summoner Statistics
    </h2>
    <div class="row">
            <div class="col-md-12" style="text-align: center;">
                <?php

set_time_limit(60);

function getSummonerStats($sumoner_name, $conn)
{
    $summoner_request = mysqli_query($conn, "SELECT id, name, summoner_level, profile_icon_id, summoner_id FROM summoners where name = '$sumoner_name'");
    $dbSummoner = new dbSummoner();
    while ($row = mysqli_fetch_assoc($summoner_request)) {
        $dbSummoner->id = $row['id'];
        $dbSummoner->name = $row['name'];
        $dbSummoner->summoner_level = $row['summoner_level'];
        $dbSummoner->profile_icon_id = $row['profile_icon_id'];
        $dbSummoner->summoner_id = $row['summoner_id'];
    }

    var_dump($dbSummoner);

    $champion_influences = array();
    if ($dbSummoner->id > -1) {

        $queryString = 'SELECT match_id FROM summoner_matches where summoner_id = "' . $dbSummoner->summoner_id . '"';
        echo "<br>";
        echo "<br>";
        echo $queryString;
        $match_id_request = mysqli_query($conn, $queryString);
        $match_ids = array();
        while ($row = mysqli_fetch_assoc($match_id_request)) {
            array_push($match_ids, $row['match_id']);
        }

        echo "<br>";
        echo "<br>";
        var_dump($match_ids);

        $joinedIds = implode(',', $match_ids);

        $limit = sizeof($match_ids) * 10;
        $queryString2 = "select
        innodb.summoner_matches.summoner_id,
        innodb.summoner_matches.champ_pick,
        innodb.summoner_matches.champ_ban,
        innodb.summoner_matches.team_a,
        innodb.matches.match_id,
        innodb.matches.team_a_won from innodb.summoner_matches
        inner join innodb.matches on innodb.summoner_matches.match_id = innodb.matches.match_id
        where innodb.matches.match_id in ($joinedIds)
        LIMIT $limit
        ";

        echo "<br>";
        echo "<br>";
        echo $queryString2;

        $summoner_influence_request = mysqli_query($conn, $queryString2);

        class picksAndBans
        {
            public $summoner_on_team_a = false;
            public $team_a_won = false;
            public $team_a_bans = [];
            public $team_b_bans = [];
            public $team_a_picks = [];
            public $team_b__picks = [];
        }

        $picksAndBansArray = array();

        foreach ($matchIds as $matchId) {
            $picksAndBans[$matchId] = new picksAndBans();
        }

        while ($row = mysqli_fetch_assoc($summoner_influence_request)) {
            echo "<br>";
            echo "<br>";
            var_dump($row);

            $matchId = $row['match_id'];
            if ($row['team_a'] == 'True') {
                if ($row['summoner_id'] == $dbSummoner->summoner_id) {
                    $picksAndBans[$matchId]->summoner_on_team_a = true;
                }
                array_push($picksAndBans[$matchId]->team_a_picks, $row['champ_pick']);
                array_push($picksAndBans[$matchId]->team_a_bans, $row['champ_ban']);
            } else {
                if ($row['summoner_id'] == $dbSummoner->summoner_id) {
                    $picksAndBans[$matchId]->summoner_on_team_a = false;
                }
                array_push($picksAndBans[$matchId]->team_b_picks, $row['champ_pick']);
                array_push($picksAndBans[$matchId]->team_b_bans, $row['champ_ban']);
            }
            $picksAndBans[$matchId]->team_a_won = $row['team_a_won'] == 'True';
        }

        echo "<br>";
        echo "<br>";
        var_dump($picksAndBans);
    } else {
        echo "SUMMONER NOT FOUND IN DATABASE";
    }
    return $champion_influences;
}

if (isset($_GET['name'])) {
    $sumoner_name = $_GET['name'];

    global $conn;
    $champion_influences = getSummonerStats($sumoner_name, $conn);

    // if (sizeof($champion_influences) > 0) {
    //     $games_in_tier_request = mysqli_query($conn, "select tier, SUM(champ_wins) from innodb.champ_influences group by tier");
    //     $games_in_tier = array();
    //     while ($row = mysqli_fetch_assoc($games_in_tier_request)) {
    //         $games_in_tier[$row['tier']] = $row['SUM(champ_wins)'] / 5;
    //     }

    //     echo "<br>";
    //     echo '<div class="col-md-12" style="text-align: center;">';
    //     echo "<b>$champion_name</b>";
    //     echo "<br>";
    //     echo "<img src='/champ_icons/" . $champion_name . "Square.png' alt='error' />";
    //     echo "</div>";
    //     $tiers = ["IRON", "BRONZE", "SILVER", "GOLD", "PLATINUM", "DIAMOND", "MASTER", "GRANDMASTER", "CHALLENGER"];
    //     foreach ($tiers as $tier) {
    //         $champion_influence = $champion_influences[$tier];
    //         echo '<div class="col-md-4" style="text-align: center; display: inline-block; margin-bottom: 20px;">';
    //         echo "<img src='/emblems/" . $champion_influence->tier . "_Emblem.png' alt='error' style='width: 45px; margin:5px'>";
    //         echo '<p class="help-block" style="font-weight:bold">' . $champion_influence->tier . '</p>';
    //         $games = $games_in_tier[$tier];
    //         echo "Out of $games games in Patch $champion_influence->game_version:";
    //         echo "<br>";
    //         echo "Won $champion_influence->wins games";
    //         echo "<br>";
    //         echo "Lost $champion_influence->losses games";
    //         echo "<br>";
    //         echo "And was banned $champion_influence->bans";
    //         echo "<br>";
    //         echo "Chance of losing to: $champion_influence->chanceOfLosingTo";
    //         echo "<br>";
    //         echo "Chance of winning against: $champion_influence->chanceOfWinningAgainst";
    //         echo "<br>";
    //         echo "</div>";
    //     }
    // } else {
    //     echo "Champion with name: $champion_name does not exist";
    // }
    mysqli_close($conn);
} else {
    echo "NOT SET";
}
?>
            </div>
        </div>
    <!-- Footer -->
    <?php include "footer.php"?>
    </body>
</html>