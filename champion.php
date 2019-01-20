<!DOCTYPE  HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"  "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <meta  http-equiv="Content-Type" content="text/html;  charset=iso-8859-1">
    <?php include "header.php"?>
    <body>
        <!-- <div class="row container-fluid">
            <h1 class="page-header text-center">
                Most Influential Champions
            </h1>
        </div> -->
	<h2 class="page-header text-center">
        Champion Statistics
    </h2>
    <div class="row">
            <div class="col-md-12" style="text-align: center;">
                <?php
function getChampionStats($champion_name, $conn)
{
    $champion_request = mysqli_query($conn, "SELECT * FROM champions where name = '$champion_name'");
    $id = -1;
    while ($row = mysqli_fetch_assoc($champion_request)) {
        $id = $row['id'];
    }

    $champion_influences = array();
    if ($id > -1) {
        $champion_influence_request = mysqli_query($conn, "SELECT * FROM champ_influences where champ_id = $id");
        while ($row = mysqli_fetch_assoc($champion_influence_request)) {
            $champion_influence = new ChampionInfluence();
            $champion_influence->wins = $row['champ_wins'];
            $champion_influence->losses = $row['champ_losses'];
            $champion_influence->bans = $row['champ_bans'];
            $champion_influence->game_version = $row['game_version'];
            $champion_influence->tier = $row['tier'];
            $champion_influence->chanceOfLosingTo = $row['chance_of_losing_to'];
            $champion_influence->chanceOfWinningAgainst = $row['chance_of_winning_against'];
            $champion_influences[$row['tier']] = $champion_influence;
        }
    }
    return $champion_influences;
}

if (isset($_GET['name'])) {
    $champion_name = $_GET['name'];

    global $conn;
    $champion_influences = getChampionStats($champion_name, $conn);
    if (sizeof($champion_influences) > 0) {
        $games_in_tier_request = mysqli_query($conn, "select tier, SUM(champ_wins) from innodb.champ_influences group by tier");
        $games_in_tier = array();
        while ($row = mysqli_fetch_assoc($games_in_tier_request)) {
            $games_in_tier[$row['tier']] = $row['SUM(champ_wins)'] / 5;
        }

        echo "<br>";
        echo '<div class="col-md-12" style="text-align: center;">';
        echo "<b>$champion_name</b>";
        echo "<br>";
        echo "<img src='/champ_icons/" . $champion_name . "Square.png' alt='error' />";
        echo "</div>";
        $tiers = ["IRON", "BRONZE", "SILVER", "GOLD", "PLATINUM", "DIAMOND", "MASTER", "GRANDMASTER", "CHALLENGER"];
        foreach ($tiers as $tier) {
            $champion_influence = $champion_influences[$tier];
            echo '<div class="col-md-4" style="text-align: center; display: inline-block; margin-bottom: 20px;">';
            echo "<img src='/emblems/" . $champion_influence->tier . "_Emblem.png' alt='error' style='width: 45px; margin:5px'>";
            echo '<p class="help-block" style="font-weight:bold">' . $champion_influence->tier . '</p>';
            $games = $games_in_tier[$tier];
            echo "Out of $games games in Patch $champion_influence->game_version:";
            echo "<br>";
            echo "Won $champion_influence->wins games";
            echo "<br>";
            echo "Lost $champion_influence->losses games";
            echo "<br>";
            echo "And was banned $champion_influence->bans";
            echo "<br>";
            echo "Chance of losing to: $champion_influence->chanceOfLosingTo";
            echo "<br>";
            echo "Chance of winning against: $champion_influence->chanceOfWinningAgainst";
            echo "<br>";
            echo "</div>";
        }
    } else {
        echo "Champion with name: $champion_name does not exist";
    }
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