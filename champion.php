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
function getChampionStats($champion_name) {
    global $conn;

    $champion_request = mysqli_query($conn, "SELECT * FROM champions where name = '$champion_name'");
    $id = -1;
    while ($row = mysqli_fetch_assoc($champion_request)) {
        $id = $row['id'];
    }
    echo "Champion ID is $id";
    echo "<br>";

    $champion_influence_request = mysqli_query($conn, "SELECT * FROM champ_influences where champ_id = $id");
    $champion_influences = array();
    while ($row = mysqli_fetch_assoc($champion_influence_request)) {
        $champion_influence = new ChampionInfluence();
        $champion_influence->wins = $row['champ_wins'];
        $champion_influence->losses = $row['champ_losses'];
        $champion_influence->bans = $row['champ_bans'];
        $champion_influence->game_version = $row['game_version'];
        $champion_influence->tier = $row['tier'];
        $champion_influence->chanceOfLosingTo = $row['chance_of_losing_to'];
        $champion_influence->chanceOfWinningAgainst = $row['chance_of_winning_against'];
        array_push($champion_influences, $champion_influence);
    }
    echo "<br>";
    return $champion_influences;
    mysqli_close($conn);
}


if (isset($_GET['name'])) {
    $champion_name = $_GET['name'];
    echo "Champion name is $champion_name";
    echo "<br>";
    $champion_influences = getChampionStats($champion_name);

    foreach ($champion_influences as $champion_influence) {
        echo "<br>";
        echo "In $champion_influence->tier for Patch $champion_influence->game_version:";
        echo "<br>";
        echo "Won $champion_influence->wins games";
        echo "<br>";
        echo "Lost $champion_influence->losses games";
        echo "<br>";
        echo "And was banned $champion_influence->bans";
        echo "<br>";
        echo "For a total influence rate of $champion_influence->chanceOfLosingTo";
        echo "<br>";
    }
} else {
    echo "NOT SET";
}
?>
            </div>
        </div>
    <!-- Footer -->
    <?php include("footer.php") ?>
    </body>
</html>