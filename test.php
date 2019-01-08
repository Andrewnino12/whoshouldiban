<?php 
include 'config.php';

try {
    $conn = new mysqli($host, $user, $password, $db);
    if($conn->connect_error){
        echo "failed";
        die("Connection failure: " . $conn->connect_error);
    }else{
        echo 'Server time is ' . idate('h');
        echo "<br>";
        $hosts = mysqli_query($conn, "SELECT * FROM summoners limit 25");
        while($row = mysqli_fetch_assoc($hosts)) {
            echo $row['name'];
            echo '<br>';
        }
    }
}
catch(PDOException $e)
{
    die($e);
}
?>