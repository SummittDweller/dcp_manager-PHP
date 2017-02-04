<?php
/**
 * Created by PhpStorm.
 * User: Mark McFate
 * Date: 10/21/15
 * Time: 9:21 PM
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

  $str = file_get_contents('/tmp/packages.data');
  $packages = unserialize($str);
  $str = file_get_contents('/tmp/controls.data');
  $controls = unserialize($str);

  echo 'This is copyDeleteRebuild.php.<br/>';

  if ($_POST['copy']) {
    $word = 'DESTINATION';
    $op = "COPY";
  } else if ($_POST['delete']) {
    $word = "DELETE";
    $op = "DELETE";
  } else {
    $word = "TARGET";
    $op = "REBUILD";
  }
  echo "<div>$op operation specified.</div><br/>";

  echo 'Selected packages include:<p/>';
  $count = 0;
  $pkls = array( );

  foreach ($_POST['selectedPKL'] as $pkl) {
    echo '<div id="selectedPKL">' . $pkl . '</div>';
    $pkls[] = $pkl;
    $count++;
  }
  echo '<hr/>';

  // Copy ---------------------------------------------------------------------
  // If this is a copy operation... build a list of rsync commands
  // from all of the $_POST['selectedPKL'] package assets.

  if ($op === "COPY") {
    $count = count($_POST['selectedPKL']);
    if ($count < 1) {
      echo "Nothing to do here.  No packages were selected.";
    } else {

      $destinationDir = $dest = $controls['destinationDir'];
      $originDir = $controls['originDir'];

      echo "Destination directory is: '$dest'.<br/>";
      echo "Origin directory is: '$originDir'.<p/>";

      if (count($packages) < 1) {
        echo "Nothing to do here.  No packages were provided in the session.";
      } else {
        foreach ($_POST['selectedPKL'] as $pkl) {
          if (!isset($packages[$pkl])) {
            echo "Warning - Selected package '$pkl' was not found in /$packages.</br>";

          // Less than three assets...don't bother, this package is INCOMPLETE.
          } else if (($a = count($packages[$pkl]) < 3)) {
            echo "Warning - Selected package '$pkl' is INCOMPLETE. Its asset count is: $a!</br>";
          } else {

            foreach ($packages[$pkl] as $asset) {
              echo "copyDeleteRebuild.php - Asset is $asset.</br>";
              $dir = dirname($asset);
              $target = $originDir.'/'.$asset;
              $mkdir = strcmp($dir, ".");

              // echo "dir is: '$dir'<br/>";
              // echo "target is: $target<br/>";

              // If the asset is in a sub-directory, issue a command to make the
              // directory first.  Then prepend the destination asset name with the directory.
              if ($mkdir != 0) {
                $destDir = $dest.$dir;
                $cmd = "mkdir ".$destDir;
                echo "Building: $cmd<br/>";
                file_put_contents($script, $cmd."\n", FILE_APPEND | LOCK_EX);
                rsyncAsset($originDir.$asset, $destDir);

              // If the asset is found in the $originDir, fetch it.
              } else if (is_file($target)) {
                rsyncAsset($target, $dest);

              // Sometimes the file is in a subdirectory of $originDir
              // but the subdir is NOT with the asset.  Find the
              // file using glob and right this wrong!
              } else if ($subDir = globForAsset($originDir, $asset)) {
                $destDir = $dest.$subDir;
                $cmd = "mkdir ".$destDir;
                echo "Building: $cmd<br/>";
                file_put_contents($script, $cmd."\n", FILE_APPEND | LOCK_EX);
                $target = $originDir.'/'.$subDir.'/'.$asset;
                rsyncAsset($target, $destDir);

              // The asset could not be found.  Report this.
              } else {
                echo "<div class='error-message'>Error - Asset $asset could not be found for PKL $pkl.</div>";
              }
            }
          }
        }
      }
    }
    echo "<br/><hr/><div class='prompt'>A copy script has been constructed at $script.  Use the 'Run!' button to execute it.</div>";
  }

  // Delete ---------------------------------------------------------------------
  // If this is a delete operation... build a list of unlink commands
  // from all of the $_POST['selectedPKL'] package assets.

  if ($op === "DELETE") {
    $dest = "/mnt/1terrabyte/TRAILERS/";
    if ($count < 1) {
      echo "Nothing to do here.  No packages were selected.<br/>";
    } else {

      if (count($packages) < 1) {
        echo "Nothing to do here.  No packages were provided in the session.<br/>";
      } else {
        foreach ($pkls as $pkl) {
        if (!isset($packages[$pkl])) {
            echo "Warning - Selected package '$pkl' was not found in \$packages.<br/>";
          } else {
            foreach ($packages[$pkl] as $asset) {
              $target = $dest.$asset;
              echo "Target for deletion is: $target.<br/>";

              if (is_file($target)) {
                $cmd = "unlink $target";
                echo "Building: $cmd<br/>";
                file_put_contents($script, $cmd."\n", FILE_APPEND | LOCK_EX);
              } else {
                echo "Warning - Deletion target $target NOT FOUND.<br/>\n";
              }
            }
          }
        }
      }
    }
    echo "<br/><hr/><div class='prompt'>A delete script has been constructed at $script.  Use the 'Run!' button to execute it.</div>";
  }

  // Rebuild ------------------------------------------------------------------
  // If this is a rebuild operation... examine the contents of the DESTINATION
  // directory, remove any ASSETMAP file found there, and build a rudimentary new one.
  //
  // Use the 'Catalog' button instead!

  if ($op === 'REBUILD') {
    echo "<div class='error-message'>This function is disabled.  Use the CATALOG button instead!.</div>";
  }

  ?>
</div>

</body>
</html>

<?php

