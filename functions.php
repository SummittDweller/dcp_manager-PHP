<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 10/19/15
 * Time: 2:29 PM
 */

function store($file, $data) {
  $info = serialize($data);  // var_export($data, true);
  // print_r($info);
  file_put_contents($file, $info);
}

/**
 * fetch_PKL_assets($path, &$assets) extracts lists of assets from PKL files.
 *
 * @param $path
 *   The PKL file path.
 * @param $assets
 *   Returned array of assets found.  Each asset is another array with an
 *   index equal to its 'id' with a 'file' component and optional 'annotation'
 *   Note that the PKL file itself MUST be prepended to the list of assets!
 * @return boolean|string
 *   AnnotationText value from the PKL or FALSE if there was an error.
 */
function fetch_PKL_assets($path, &$assets) {
  if (!$xml = simplexml_load_file($path)) { return FALSE; }

  echo "<hr/>";

  // Get the PKL Id.
  if (isset($xml->Id)) {
    $id = (string) $xml->Id;
  } else {
    return FALSE;
  }

  // Get the PKL name from its AnnotationText.
  if (isset($xml->AnnotationText)) {
    $namePKL = (string) $xml->AnnotationText;
  } else {
    return FALSE;
  }

  // Seed the $assets list with the PKL file itself.
  $assets[$id] = array( );
  $assets[$id]['file'] = $path;
  $assets[$id]['annotation'] = $namePKL;

  $count = 0;

  // Logic for PKLs.
  if (isset($xml->AssetList)) {
    foreach ($xml->AssetList->Asset as $a) {
      list($seedDir, $file) = mam_split_path($path);
      $asset = array( );

      // MAM...looks like the asset filename is held in the OriginalFileName.
      // Id and AnnotationText may also be significant so grab them!

      if (!isset($a->Id)) {
        echo "No Id for the file asset '$file'.  It is being skipped.<br>";
        mam_log_message(__FUNCTION__, "No Id for the file asset '$file'.  It is being skipped.");
        continue;
      } else {
        $id = (string) $a->Id;
      }

      if (isset($a->OriginalFileName)) {
        $f = (string) $a->OriginalFileName;
        $asset['file'] = $seedDir.$f;
        $count++;
      } else {
        unset($asset[$id]);
        echo "No OriginalFileName for the asset '$file'.  It is being skipped.<br>";
        mam_log_message(__FUNCTION__, "No OriginalFileName for the asset '$file'.  It is being skipped.");
        continue;
      }

      if (isset($a->AnnotationText)) {
        $asset['annotation'] = (string) $a->AnnotationText;
      }

      echo "Found valid $f with an ID of $id.<br>";
      mam_log_message(__FUNCTION__, "Found valid $f in PKL $file.");
      $assets[$id] = $asset;
    }
  }

  // Count the $assets for $id.  If less than 3 exist then the package is incomplete and should be deleted.
  if ($count < 3) {
    $script = "/tmp/dcp_manager.sh";
    echo "<br><b>Asset count for $namePKL is only $count.  This PKL is incomplete and should be removed!</b><br>";
    $cmd = "unlink $path";
    file_put_contents($script, $cmd."\n", FILE_APPEND | LOCK_EX);
  }

  return $namePKL;
}

/**
 * rsync one asset (file) to the destination directory.
 *
 * @param $from
 *   The path to the asset to be rsync'd.
 * @param $dest
 *   The destination directory.
 * @param bool|FALSE $move
 *   If true, a move is performed rather than copy.
 */
function rsyncAsset($from, $dest, $move=FALSE) {
  static $previous;
  static $script = "/tmp/dcp_manager.sh";

  $rsync = "rsync";
  if (empty($rsync)) {
    $rsync = "/usr/local/bin/rsync";   // so we need this!
  } 

  // Build the rsync command and submit it for batch processing.
  if ($move) {
    $cmd = "$rsync -atvz --remove-source-files --ignore-existing '$from' '$dest'";
  } else {
    $cmd = "$rsync -atvzP --ignore-existing '$from' '$dest'";
  }

  // If $previous is set and this is the same command...SKIP IT!
  if (isset($previous) && $previous === $cmd) {
    mam_log_message(__FUNCTION__, "Skipping a redundant command.");
    return;
  } else {
    $previous = $cmd;
  }

  mam_log_message(__FUNCTION__, "Command -> $cmd");
  echo "Building: $cmd<br/>";
  file_put_contents($script, $cmd."\n", FILE_APPEND | LOCK_EX);

  return;
}

/**
 * Sometimes an asset name does not specify the subdirectory that the asset is
 * found in. This function attempts to find it.
 *
 * @param $dir
 *   The root directory to search in.
 * @param $name
 *   The file name to search for.
 *
 * @return  boolean|string
 *   FALSE or the subdirectory name with a trailing slash.
 */
function globForAsset($dir, $name) {
  $target = $dir.$name;
  if ($list = glob($dir.'*/'.$name)) {
    $found = dirname($list[0]);
    if (substr($found, 0, strlen($dir)) == $dir) {
      $subDir = substr($found, strlen($dir));
    }
    $subDir .= '/';
    return $subDir;
  } else {
    return FALSE;
  }
}



/**
 * Returns the destination directory for operations based
 * on $_SESSION settings and a three-drive ROTATION.
 *
 */
function destination($junk=NULL) {
  static $next;
  $n = (empty($next) ? 0 : $next);

  // If automatic, determine destination based on a three-drive ROTATION.
  if ($_SESSION['autoDestination']) {
    $autoDirs = array(0 => '/mnt/ada0Data/', 1 => '/mnt/ada1Data/', 2 => '/mnt/ada2Data/');
    $dir = $autoDirs[$n];
    $next = ($n+1) % 3;
    return $dir;

  // Not automatic...return the specified path.
  } else {
    return $_SESSION['destinationDir'];
  }
}

/**
 * Open and read an ASSETMAP file and return an array of all CPL packages
 * and assets found within.
 *
 * @param $path
 *   Path to the ASSETMAP file.
 */
function readASSETMAP($aPath) {
  $packages = array( );

  // Load the specified file into a new SimpleXMLElement
  if (!$xml = simplexml_load_file($aPath)) { return FALSE; }

  $dir = dirname($aPath) . '/';
  $_SESSION['originDir'] = $dir;

  // Parse it for annotations and file paths looking for a CPL path.
  foreach ($xml->AssetList->Asset as $asset) {
    $id = (string) $asset->Id;
    $annotation = (string) $asset->AnnotationText;
    $path = (string) $asset->ChunkList->Chunk->Path;

    // If we found a PKL...
    if (stristr($path, "pkl") != FALSE) {
      $assets = array( );
      if ($assets = mam_process_package($dir, $path)) {
        $packages[] = $assets;
      }
    }
  }

  return $packages;
}

/**
 * Present the list of PKLs found.
 *
 * @param $pkls
 *   An array of PKL file paths.
 */
function presentPKLs($pkls)
{

// If there are no packages...show only the Catalog prompt and return FALSE.
  if (count($pkls) === 0) {
    echo '<form action="copyDeleteRebuild.php" id="pklForm" method="POST" enctype="multipart/form-data"></form>';
    //   <div class="openFormButtons">
    //     <input type="submit" name="rebuild" id="rebuild" class="button" value="Rebuild ASSETMAP"/>
    //   </div>
    // </form>';
    return FALSE;
  } else {
    return TRUE;
  }

}

/* -------------------------------------------------------------
     Old functions from phpFM follow here.
--------------------------------------------------------------- */

/**
 * mam_log_message($msg) - Write timestamped message to
 *   /var/log/dcp_manager.log
 *
 * @param $from - The function calling for the message.
 * @param $msg - The text of the message.
 * @param $dir - The directory containing the log file to be written.
 */
function mam_log_message($from, $msg, $dir=NULL) {

  $dir = NULL;     // This disables the redirection of the log.

  if ($dir) {
    $logFile = "/$dir/dcp_manager.log";
  } else {
    $logFile = "/tmp/dcp_manager.log";
  }
  $timeStamp = date('r');
  file_put_contents($logFile, ">>> $timeStamp | $from:\n$msg\n", FILE_APPEND);
  // echo "<pre>$msg</pre>";
}

/**
 * Custom sort function.
 *
 * @param $a
 *   Asset array element including a #sort element.
 * @param $b
 *   Asset array element including a #sort element.
 *
 * @return int
 *   Zero if equal, -1 if a < b or 1 if a > b.
 */
function mam_sort($a, $b) {
  if ($a['#sort'] == $b['#sort']) {
    if (!isset($a['#name']) || !isset($b['#name'])) { return 0; }
    if ($a['#name'] < $b['#name']) {
      return -1;
    } else if ($a['#name'] === $b['name']) {
      return 0;
    } else {
      return 1;
    }
  } else {
    return ($a['#sort'] < $b['#sort']) ? -1 : 1;
  }
}

/**
 * Process a PKL package file.
 *
 * @param $dir
 *   Package directory path.
 * @param $fname
 *   The PKL (package) file name.
 *
 * @return boolean|array
 *   Returned array of asset file names and attributes including...
 *      asset = Array of asset names
 *      #name = The package name from AnnotationText
 *      #color = The color code to use when printing the package name (based on date)
 *      #count = The number of assets returned
 *      #date = The creation date of the package
 *      #size = The total package size in GB
 */
function mam_process_package($dir, $fname) {
  $info = array( );
  $path = $dir.$fname;
  mam_log_message(__FUNCTION__, "Called with dir=$dir and fname=$fname.", $dir);

  if (!$file = fopen($path, "r")) {
    mam_log_message(__FUNCTION__, "[fopen Error] $path");
    return FALSE;
  } else {
    fclose($file);
  }

  // Ok, we have a readable PKL.

  $size = 0;
  if (!$xml = simplexml_load_file($path)) { return FALSE; }
  if (!isset($xml->AnnotationText)) { return FALSE; }

  // PKL logic.

  $pName = (string) $xml->AnnotationText;

  $oneYearAgo = strtotime("-1 year", time());
  $twoYearsAgo = strtotime("-2 years", time());
  $twoWeeksAgo = strtotime("-2 weeks", time());
  $oneMonthAgo = strtotime("-1 month", time());
  $dateColor = "#656565";  // "grey";
  $sort = 2;

  if (isset($xml->AssetList)) {
    if (isset($xml->IssueDate)) {
      $iDate = substr($xml->IssueDate, 0, 10);
      $issued = strtotime($iDate);
      if ($issued < $twoYearsAgo) {
        $sort = 4;
        $dateColor = "red";
      } else if ($issued < $oneYearAgo) {
        $sort = 3;
        $dateColor = "magenta";
      }
      if ($issued > $oneMonthAgo) {
        $sort = 1;
        $dateColor = "blue"; // "#005A00";    // dark green
      }
      if ($issued > $twoWeeksAgo) {
        $sort = 0;
        $dateColor = "green"; // "#339933";    // lighter green
      }
      $iDate .= '; ';
    } else {
      $iDate = "";
    }

    $info['#sort'] = $sort;
    $info['#name'] = $pName;
    $info['#count'] = count($xml->AssetList->Asset) + 1;
    $info['#date'] = $iDate;
    $info['#color'] = $dateColor;
    $info['#weight'] = 'normal';
    $info['assets'] = array( );

    // Tag OCAP trailers in red.
    if (stristr($pName, 'OCAP')) {
      $info['#color'] = 'red';
      $info['#weight'] = 'lighter';
    }

    // Tag Flat, English trailers in very bold print.
    if (stristr($pName, '_F_EN-')) { $info['#weight'] = 'bolder'; }

    foreach ($xml->AssetList->Asset as $a) {
      $bsize = floatval($a->Size);
      $size += floatval($bsize) / 1000000000.0;
      if (isset($a->OriginalFileName)) {
        $info['assets'][] = (string) $a->OriginalFileName;
      } else if (isset($a->AnnotationText)) {
        $info['assets'][] = (string) $a->AnnotationText;
      } else {  // No OriginalFileName or AnnotationText... look for file of specified size.
        $find = "find $dir -type f -size ${bsize}c 2>&1";
        if ($found = shell_exec($find)) {
          $files = explode("\n", $found);
          $id = str_replace("urn:uuid:", "", (string) $a->Id);
          $found = FALSE;
          foreach ($files as $fname) {
            if (strpos($fname, $id) > 1) {
              $info['assets'][] = str_replace($dir, "", $fname);
              $found = TRUE;
              break;
            }
          }
          if (!$found) { $info['assets'][] = str_replace($dir, "", $files[0]); }
        }
      }
    }
    $info['#size'] = sprintf("%4.2f GB", $size);
    $info['assets'][] = $fname;  // add the PKL itself to the assets
    return $info;
  }

  return FALSE;
}

/**
 * mam_fetch_assets($path, &$assets, $dir)
 *
 * @param $path
 *   The file path.
 * @param $assets
 *   Returned array of assets found.  Each asset is another complete file path,
 *   or a directory.  Assets that have a directory prefix in the OriginalFileName->Id
 *   or AnnotationText should return only the directory!
 * @return
 *   Count of $assets returned.  Zero if none.
 *
 */
function mam_fetch_assets($path, &$assets, $dir=NULL) {
  $ret = 0;

  // Check if the file is XML.  If yes, determine if it is a CPL, a PKL, or not.

  if (stripos($path,".xml") > 1) {
    mam_log_message(__FUNCTION__, "Package file $path is an XML (package) file.", $dir);
    $test = "_".$path;

    if (stripos($test, "PKL_") || stripos($test, "_pkl.") || stripos($test, "CPL_") || stripos($test, "_cpl.")) {
      mam_log_message(__FUNCTION__, "Found $path to be a PKL or CPL.", $dir);
      if (!$xml = simplexml_load_file($path)) { return $ret; }

      // Logic for PKLs.

      if (isset($xml->AssetList)) {
        foreach ($xml->AssetList->Asset as $a) {
          list($seedDir,$file) = mam_split_path($path);

          // MAM...looks like the asset filename can take on one
          // of two forms.  OriginalFileName or AnnotationText.

          if (isset($a->OriginalFileName)) {
            list($newDir, $aFile) = mam_split_path((string)$a->OriginalFileName);
          } else if (isset($a->AnnotationText)) {
            list($newDir, $aFile) = mam_split_path((string)$a->AnnotationText);
          }

          if ($newDir) {
            $asset = $seedDir.rtrim($newDir,'/');
          } else {
            $asset = $seedDir.$aFile;
          }
          $assets[] = $asset;
          mam_log_message(__FUNCTION__, "Found $asset in the PKL.", $dir);
          $ret++;
        }

      // Logic for CPLs.  But there are no assets inside a CPL...skip it!

      } /* else if (isset($xml->ReelList)) {
        foreach ($xml->ReelList->Reel as $r) {
          if (isset($r->AssetList)) {
            foreach ($r->AssetList as $a) {
              $lastSlash = strrpos($path,"/");
              if ($nameOnly) {
                $dir = "";
              } else {
                $dir = substr($path,0,$lastSlash)."/";
              }
              if (isset($a->MainPicture->Id)) { $asset = $dir.mam_strip_prefix($a->MainPicture->Id); $ret++; }
              if (isset($a->MainSound->Id)) { $asset = $dir.mam_strip_prefix($a->MainSound->Id); $ret++; }
              if (isset($asset)) {
                $assets[] = $asset;
                mam_log_message(__FUNCTION__, "Found asset $asset in CPL.", $dir);
              }
            }
          }
        }
      } */
    }
  }

  return $ret;
}

function mam_strip_prefix($id) {
  if ($colon = strrpos($id,":")) {
    return substr($id,$colon);
  }
}

/**
 * mam_delete_package($path)
 *
 * @param $path - The file path.
 */
function mam_delete_package($path) {
  $assets = array();

  // Check if the file is XML.  If yes, determine if it is a CPL, PKL or other.

  if (strpos($path,".xml")) {
    list($dir,$file) = mam_split_path($path);
    if ($num = mam_fetch_assets($path, $assets)) {
      foreach ($assets as $a) {
        if ($count = mam_delete($a)) {
          mam_log_message("mam_delete_package", "Deleted file '$a'.",
            $dir);
        } else if ($count === 0) {
          mam_log_message("mam_delete_package", "Attempt to delete '$a' failed.", $dir);
        } else {
          mam_log_message("mam_delete_package". "'$a' not found so it cannot be deleted.", $dir);
        }
      }
    }
  }
  return;
}

/**
 * mam_copy_package($path, $dest)
 *
 * @param $path - The suspect XML (package) file path.
 * @param $dest - The intended destination folder/device.
 *
 */
function mam_copy_package($path, $dest) {
  list($dir, $file) = mam_split_path($dest);
  mam_log_message(__FUNCTION__, "Called with path=$path and dest=$dest.", $dir);

  // Check if the file is XML.  If yes, determine if it is a CPL, PKL or other.

  if (strpos($path,".xml") > 1) {
    $assets = array( );
    mam_log_message(__FUNCTION__, "Found $path as an XML (package) file.", $dir);
    if ($num = mam_fetch_assets($path, $assets, $dir)) {
      mam_log_message(__FUNCTION__, "Package file $path has $num assets.", $dir);
      list($destDir, $destFile) = mam_split_path($dest);
      foreach ($assets as $a) {
        list($orgDir, $orgFile) = mam_split_path($a);
        total_copy($a, $destDir);
      }
    }
  } else {
    mam_log_message(__FUNCTION_, "Package $path is NOT an XML (package) file!", $dir);
  }
  return;
}

function mam_split_path($path) {
  if ($slash = strrpos($path, "/")) {
    return array(substr($path, 0, $slash) . "/", substr($path, $slash + 1));
  }
  else {
    return array(NULL, $path);
  }
}

/**
 * Wrapper for batch_copy (directories) and mam_copy_package (files).
 *
 */
function total_copy($orig, $dest) {
  list($dir, $file) = mam_split_path((string) $dest);

  $ok = true;
  if (file_exists($orig)) {
    if (is_dir($orig)) {
      mam_log_message(__FUNCTION__, "Calling batch_copy with orig=$orig and dest=$dest.", $dir);
      $ok = batch_copy((string)$orig, (string)$dest);
    } else {
      mam_log_message(__FUNCTION__, "Calling mam_copy_package with orig=$orig and dest=$dest.", $dir);
      mam_copy_package($orig, $dest);
      list($dir, $file) = mam_split_path($orig);
      $ok = batch_copy((string)$orig, (string)$dest);
    }
  }
  return $ok;
}

// Concept lifted from http://stackoverflow.com/questions/45953/php-execute-a-background-process and
// http://stackoverflow.com/questions/3819398/php-exec-command-or-similar-to-not-wait-for-result
//
// If $move is TRUE the --remove_source option is attached so that the
// original files are deleted after a successful copy is made.

function batch_copy($from, $to, $move=FALSE) {
  static $previous;
  // $rsync = exec("whereis rsync");
  $rsync = "/usr/local/bin/rsync";

  // If $to ends with a file name, remove it!
  list($dir, $file) = mam_split_path($to);

  // Build the rsync command and submit it for batch processing.

  if ($move) {
    $cmd = "$rsync -atvz --remove-source-files --ignore-existing '$from' '$dir'";
  } else {
    $cmd = "$rsync -atvz --ignore-existing '$from' '$dir'";
  }

  // If $previous is set and this is the same command...SKIP IT!
  if (isset($previous) && $previous === $cmd) {
    mam_log_message(__FUNCTION__, "Skipping a redundant command.", $dir);
    return;
  } else {
    $previous = $cmd;
  }

  mam_log_message(__FUNCTION__, "Command -> $cmd", $dir);
  mam_log_message(__FUNCTION__, "  From = $from", $dir);
  $output = shell_exec($cmd.' > /dev/null 2> /dev/null &');
  if ($output) { mam_log_message(__FUNCTION__, "Output -> $output", $dir); }

  return;
}

/**
 * Function to delete package assets.
 *
 * @param $arg
 * @return bool|int
 */
function mam_delete($arg) {
  if (file_exists($arg)) {
    @chmod($arg,0755);
    if (is_dir($arg)) {
      $handle = opendir($arg);
      while($aux = readdir($handle)) {
        if ($aux != "." && $aux != "..") mam_delete($arg."/".$aux);
      }
      @closedir($handle);
      rmdir($arg);
    } else {
      mam_delete_package($arg);
      if (unlink($arg)) {
        return 1;
      } else {
        return 0;
      }
    }
  }
  return FALSE;
}

