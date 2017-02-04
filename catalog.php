<?php
/**
 * Created by PhpStorm.
 * User: Mark McFate
 * Date: 10/21/15
 * Time: 9:21 PM
 */

include 'functions.php';
session_start( );

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

  $op = "CATALOG";
  echo "<div class='prompt'>$op operation specified.</div><br/>";

  $dest = "/mnt/1terrabyte/TRAILERS/";

  echo 'The TARGET directory path is: ' . $dest . '<br/>';
  echo '<hr/>';

  $packages = array();

  $target = $dest.'ASSETMAP';
  // $target = '/tmp/ASSETMAP';
  echo "<div class='prompt'>Current TARGET is: $target.</div>";

  if (is_file($target)) {
    echo "Warning - Be advised that the target ASSETMAP file already exists.  It will be overwritten.";
    unlink($target);
  } else {
    echo "No target ASSETMAP file found at $target so a new one will be created.";
  }

  copy('/var/www/html/dcp_manager/Empty_ASSETMAP', $target);

  if ($file = fopen($target, 'r')) {
    echo "<div>Hurray!  $target is open.</div>";
    fclose($file);

    $packages[$dest] = array( );

    echo "<br/><hr/>";
    $now = date('c');

    // Load the ASSETMAP document
    $xml = new DOMDocument;
    if ($xml->load($target)) {
      echo "<div>$target successfully loaded as a DOMDocument!</div>";
    }

    // Modify the AnnotationText and IssueDate elements
    $xpath = new DOMXPath($xml);
    $xpath->query("/AssetMap/AnnotationText")
      ->item(0)->nodeValue = 'DCP_Manager_Build_' . $now;
    $xpath->query("/AssetMap/IssueDate")->item(0)->nodeValue = $now;

    if ($bytes = $xml->save($target)) {
      echo "<div>xml->save wrote $bytes bytes to $target.</div>";
    } else {
      echo "<div class='error-message'>Could not write to $target!</div>";
    }

    // Find all the PKL files in the destination directory and the AssetList element of $temp.
    $files = glob($dest . '{pkl,PKL}*.xml', GLOB_BRACE);
    $assetList = $xpath->query("/AssetMap/AssetList")
      ->item(0);  // There should be only one.

    $countPKL = $countAssets = 0;

    // Loop through the PKLs --------------------------------------------------------------------
    foreach ($files as $file) {
      $assets = array();
      $countPKL++;

      // Fetch the assets for each PKL found.
      $name = fetch_PKL_assets($file, $assets);
      echo "<div style='text-indent:0.5em;' class='prompt'>$name</div>";
      echo "<div style='text-indent:0; margin-top:1em;'>Found PKL file '$file' for examination.</div>";

      $Assets = array( );

      // Pre-scan the list of PKL assets.  If any files are missing, issue a warning.

      $complete = TRUE;
      foreach ($assets as $id => $asset) {
        $Assets[$id] = $asset;
        $file = $asset['file'];
        $basename = basename($file);
        if (!file_exists($file)) {
          echo "<div style='text-indent:3em;'
            class='warning-message'>Could not find asset '$file'! This package may be incomplete!</div>";
          if (!$path = globForAsset($dest, $basename)) {
            echo "<div style='text-indent:3em;'
              class='error-message'>Could not find asset file '$basename'! This package IS incomplete!</div>";
          } else {
            echo "<div style='text-indent:1em;'>Found target file $basename in $path!</div>";
            $Assets[$id]['file'] = $path.$basename;
          }
        } else {
          echo "<div style='text-indent:1em;'>Found target file $basename in $dest.</div>";
          $Assets[$id]['file'] = $dest.$basename;
        }
      }

      // We have a package.  Process it.
      $packages[$dest][] = $name;

      foreach ($Assets as $id => $asset) {
        $aPath = $asset['file'];
        $basename = basename($aPath);
        echo "<div style='text-indent:2em; line-height:1.25em;'>Found asset $aPath inside the PKL with an ID of $id.</div>";

        if (substr($aPath, 0, strlen($dest)) == $dest) {
          $localAsset = substr($aPath, strlen($dest));
        } else {
          $localAsset = $aPath;
        }

        echo "<div style='text-indent:3em; line-height:1.25em;'>$aPath trimmed to $localAsset.</div>";
        $hasAnnotation = (isset($asset['annotation']) ? $asset['annotation'] : FALSE);

        // Build ASSETMAP element for this found asset file.
        $a = $xml->createElement('Asset');

        if ($hasAnnotation) {
          $annotation = $xml->createElement('AnnotationText');
          $annotationText = $xml->createTextNode($hasAnnotation);
        }

        $idElement = $xml->createElement('Id');
        $idText = $xml->createTextNode($id);

        $chunkList = $xml->createElement('ChunkList');
        $chunk = $xml->createElement('Chunk');

        $path = $xml->createElement('Path');
        $pathText = $xml->createTextNode($localAsset);

       if (stripos("_$basename", "pkl")) {
          $packingList = $xml->createElement('PackingList');
        } else {
          unset($packingList);
        }

        $aNode = $assetList->appendChild($a);
        $aNode->appendChild($idElement)->appendChild($idText);
        if (isset($packingList)) {
          $aNode->appendChild($packingList);
        }
        if (isset($annotation)) {
          $aNode->appendChild($annotation)->appendChild($annotationText);
        }
        $aNode->appendChild($chunkList)
          ->appendChild($chunk)
          ->appendChild($path)
          ->appendChild($pathText);

        if ($xml->save($target)) {
          echo "<div style='text-indent:3em; line-height:1.25em;'>The $localAsset asset was successfully written to '$target'.</div>";
          $assetList = $xpath->query("/AssetMap/AssetList")
            ->item(0);  // There should be only one.
          $countAssets++;
        }
        else {
          echo "<div class='error-message'>Error - Unable to save the asset map in '$target'.</div>";
        }
      }

    }
    echo "<div class='prompt'>Done. $countPKL PKL files found in the '$dest' directory. They yielded $countAssets assets.</div>";
  }

  // Report the packages and their directories, in order.
  foreach ($packages as $a => $dir) {
    echo "<hr/><div class='prompt'>Catalog of: $a</div>";
    asort($dir);
    foreach($dir as $name) {
      echo "<div style='line-height:1.5em;'>$name</div>";
    }
  }

  // Done.  Now, tidy up the new ASSETMAP file.
  $command = "tidy -xml -i -m -w 600 $target";
  $status = exec($command);

  ?>
</div>

</body>
</html>

