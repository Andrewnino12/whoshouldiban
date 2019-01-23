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
        <div class="text-center col-md-12">
            <h2 class="page-header">
            Most Influential Champions for Patch Version:
            </h2>
            <form  id="gameVersionForm">
                <select id="gameVersionSelect" onchange="this.form.submit()" name="gameVersion">
                    <?php
                        foreach($gameVersions as $gameVersion) {
                            if(isset($_GET['gameVersion']) && $_GET['gameVersion'] == $gameVersion) {
                                echo "<option selected='selected' value='$gameVersion'>$gameVersion</option>";
                            } else {
                                echo "<option value='$gameVersion'>$gameVersion</option>";
                            }
                        }
                    ?>
                </select>
            </form>
            <button type="button" class="btn btn-info" data-toggle="collapse" data-target="#explanation" style="margin-top:20px">How Does It Work?</button>
            <div id="explanation" class="collapse">
                <p class="col-md-10" style="display:inline-table; margin-top:20px;">A champion's influence rate is the <i>likelihood that you will lose to them in the next game if you leave them unbanned.</i> This is calculated using their pick rate, win rate, and ban rate, to determine how likely you are to face them in the next game, and how likely you are to lose when they do get picked.</p>
                <p class="col-md-10" style="display:inline-table; margin-top:20px;">For example: a champion with a 50% ban rate, and 10% play rate will be banned 50 out of 100 games, and then picked in 10 out of the remaining 50 games. If they have a 100% win rate, they will win all 10 of those games, for a total influence rate of 10%.</p>
                <p class="col-md-10" style="display:inline-table; margin-top:20px;">Now imagine a champion with a 50% ban rate, a 60% win rate, and a 100% play rate. They will be banned 50 out of 100 games, picked every game from the remaining 50, and then win 30 of those 50 games. This gives them a higher influence rate (30%) than the previous example (10%), because although their win rate is lower, they're much more likely to be encountered.</p>
            </div>
        </div>
    <div class="row">
            <div class="col-md-12" style="text-align: center;">
                <?php
frontPageCards();
?>
            </div>
        </div>
    <!-- Footer -->
    <?php include("footer.php") ?>
    </body>
</html>