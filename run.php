<?php /**
 * Created by PhpStorm.
 * User: Mark McFate
 * Date: 4/24/2015
 * Time: 2:20 PM
 */

include 'functions.php';

include 'head.inc';
include 'header.inc';
include 'edit-buttons.inc';
?>

<div id="content">
  <?php

  $script = '/tmp/dcp_manager.sh';
  // unlink($script);

  if (!file_exists($script)) {
    echo "<div class='error-message'>Error - There is no '$script' script to run!</div>";
  
  // This popen is the bad boy that does the heavy lifting!

  } else if ($proc = popen("source $script", 'r')) {
    $order = array("\r\n", "\n");
    $reportProgress = FALSE;

    while ($progress = fgets($proc, 200)) {
      ob_flush(); flush();
      $update = str_replace($order,'<br />', $progress);

      if (strstr($update, 'building file list')) {
        $style = "<br/><div style='color:green;'>";
        $reportProgress = "Building file list...";
        echo $style . "Building file list...</div>";
        $update = "";
      } else if (strstr($update, 'total size is')) {
        $style = "<div style='color:blue;'>";
        echo $style . $update . "</div><hr/>";
        $reportProgress = 'Done';
        $update = "";
      } else if (strstr($update, 'file to consider')) {
        $style = "<div style='color:blue;'>";
        echo $style . $update . "</div><strong>";
        $reportProgress = 'Starting...';
        $update = "";
      } else {
        $reportProgress = getInnerProgress($update);
        $style = "<div>";
      }

      if ($reportProgress) {
        echo "<div id='status' class='absolute'>Progress: ".$reportProgress."</div>";
      } else {
        echo $style . $update . "</div></strong>";
      }

    ?>
    <script type="text/javascript">
      $(document).ready(function() {
        var update = "<?php echo $update; ?>";
        $("#content").html(update);
      });
    </script>

    <?php
      ob_flush(); flush();
      sleep(5);                  // slow the updates down a bit
    }

    pclose($proc);
    // unlink($script);   // All done, delete the script so it is not run again!
  
  } else {
   echo "<div class='error-message'>Error - popen returned FALSE!</div>";
  }

  ?>

</div>
</body>
</html>

<?php
function getInnerProgress($string, $delim="\r") {
  if (strpos("_$string", "%") > 0) {
    $string = explode($delim, $string, 3); // also, we only need 2 items at most
    return isset($string[1]) ? $string[1] : FALSE;
  } else {
    return FALSE;
  }
}
?>
