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
        <div class="text-center">
            <h2 class="page-header">
            Most Influential Champions for Patch Version:
            </h2>
            <select>
                <?php
                    $patchVersions = patchVersions();
                    foreach($patchVersions as $patchVersion) {
                        echo "<option value='$patchVersion'>$patchVersion</option>";
                    }
                ?>
            </select>
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