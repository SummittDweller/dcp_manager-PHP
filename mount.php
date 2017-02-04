<?php /**
 * Created by PhpStorm.
 * User: Mark McFate
 * Date: 4/24/2015
 * Time: 2:20 PM
 */

include 'functions.php';

?>

<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" type="text/css" href="style.css">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="viewport" content="height=device-height, initial-scale=1.0">

  <title>...:::: DCP Manager</title>

</head>

<body>
  <?php
    include 'header.inc';
    include 'edit-buttons.inc';
  ?>

  <div id="content">
    <?php
      $cmds = array('umount /mnt/stick', 'mount_msdosfs /dev/sdf1 /mnt/stick', 'umount /mnt/usb', 'mount -rt ext2fs /dev/sde1 /mnt/usb');
      foreach ($cmds as $cmd) {
        mam_log_message('mount.php', "Command -> $cmd");
        echo "Running: $cmd<br/>";
        $out = array( );
        if ($output = exec($cmd, $out, $status)) {
          echo "<div style='margin-left:1em;'>$output</div>";
        } else {
          echo "<div style='margin-left:1em;'>The command produced no output with a status code of '$status'.</div>";
        }
      }
    ?>
  </div>

</body>
</html>

