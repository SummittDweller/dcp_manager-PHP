<?php /**
 * Created by PhpStorm.
 * User: mupisiri
 * Date: 4/24/2015
 * Time: 2:20 PM
 */

include 'functions.php';
session_start();

?>

<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" type="text/css" href="style.css">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="viewport" content="height=device-height, initial-scale=1.0">

  <meta http-equiv=\"refresh\" content=\"5\" />
  <title>...:::: phpCPMonitor</title>

</head>

<body>
<?php
  include 'header.inc';
  include 'edit-buttons.inc';
?>

<div id="content">
  <?php
    // Loop through all the active $_SESSION['process'] PIDs.
    foreach ($_SESSION['process'] as $pid) {
      echo "<div>Process $pid status is:</div>";
      passthru("ps -p $pid");
    }

    /*
    output = array( );
    echo "The following rsync (copy) processes are still running on the system:<p/>";
    shell_exec("pgrep rsync | xargs -s 200 ps");
    exec("pgrep rsync | xargs -s 200 ps", $output);
    foreach ($output as $line) { echo "<p>$line</p>"; } */

    echo "----------------------------------";
    echo " End of List ";
    echo "----------------------------------";

  ?>
</div>

</body>
</html>
