<?php /**
 * Created by PhpStorm.
 * User: mupisiri
 * Date: 4/24/2015
 * Time: 2:20 PM
 *
 */

include 'functions.php';

?>

<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" type="text/css" href="style.css">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="viewport" content="height=device-height, initial-scale=1.0">

</head>

<body>
  <?php
    include 'header.inc';
    include 'edit-buttons.inc';
  ?>

  <div id="content">
    <?php

    if ($_POST['copy']) {
        $op = "COPY";
        $id = 'copy';
        $value = 'Copy';
    } else if ($_POST['copy-ns']) {
        $op = "COPY with No Selection";
        $id = 'copy';
        $value = 'Copy';
    } else if ($_POST['delete']) {
      $op = "DELETE";
      $id = 'delete';
      $value = 'Delete';
    }

    // var_dump($_POST);
    echo $op.' specified...<p/>';

    // Make sure the DESTINATION path ends with a slash!
    $destinationDir = $dest = rtrim($_POST['destinationPath'], " /").'/';
    $assetMapPath = $map = $_POST['assetMapPath'];
    $deletePath = $del = $_POST['deletePath'];

    $parts = pathinfo($assetMapPath);
    $originDir = $parts['dirname'];

    echo 'The Copy From ASSETMAP file is: '.($map ? $map : "NOT Specified").'<br/>';
    echo 'The Copy To TARGET directory path is: '.$dest.'<br/>';
    echo 'The Delete From ASSETMAP file is: '.($del ? $del : "NOT Specified").'<br/>';
    echo '<hr/>';

    // If DELETE...set $map = $del
    if ($op === 'DELETE') { $map = $del; }

    // Form logic...
    // If $map is not empty, read PKLs from it.
    // If $map is empty and $auto is FALSE, read PKLs from $dest/ASSETMAP.
    // If $map is empty and $auto is TRUE, read PKLs from each of the auto dirs in $functions.

    if (!empty($map)) {
      $pkls = array( );
      if (!$pkls = readASSETMAP($map)) {
        echo "<div class='error-message'>Error - No packages found in '$map'.  Perhaps it is not a valid ASSETMAP file?</div>";
      } else if (!presentPKLs($pkls)) {
        echo "<div class='error-message'>Error - You did not choose a valid ASSETMAP path OR no assets were found.</div>";
      }
    }

    echo '<p>Choose one or more packages from the list below and click "'.$value.'" to take action on the selections.</p>';

    // Initialize the session packages array and sort the PKLs
    $packages = array( );
    if (isset($pkls)) { usort($pkls, 'mam_sort'); }

    // Set the form.
    echo '<form action="copyDeleteRebuild.php" id="pklForm" method="POST" enctype="multipart/form-data">';

    // Now the TOP form submit buttons...
    echo '<div class="openFormButtons">
      <input type="submit" name="'.$id.'" id="'.$id.'" class="button" value="'.$value.'"/>
    </div>';

    // Populate the form with checkboxes representing each PKL
    foreach ($pkls as $pkl) {
      $color = $pkl['#color'];
      $wt = $pkl['#weight'];

      $name = '<span style="color:' . $color . '; font-weight:' . $wt . ';">' . $pkl['#name'] .
          '; ' . $pkl['#date'] . $pkl['#count'] . ' assets; ' . $pkl['#size'] . '</span>';

      if ($op === 'COPY' && ($color === 'blue' || $color === 'green') && $wt === 'bolder') {
        $checked = "checked";
      } else {
        $checked = "";
      }

      echo '<input name="selectedPKL[]" class="options" type="checkbox" id="list-entry" value="'.
          $pkl['#name'].'" ' .$checked. '> '.$name.'</option><br />';
      $packages[$pkl['#name']] = $pkl['assets'];
    }

    // Now the BOTTOM form submit buttons...
    echo '<div class="openFormButtons">
      <input type="submit" name="'.$id.'" id="'.$id.'" class="button" value="'.$value.'"/></div></form>';

    // Store parameters for later retieval 
    store('/tmp/packages.data', $packages);
    $controls = array( 'destinationDir' => $destinationDir, 
        'assetMapPath' => $assetMapPath, 
        'deletePath' => $deletePath,
        'originDir' => $originDir );
    store('/tmp/controls.data', $controls); 

    ?>
  </div>

</body>
</html>




