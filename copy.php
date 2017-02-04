<?php
/**
 * Created by PhpStorm.
 * User: bazil
 * Date: 10/19/15
 * Time: 9:11 PM
 */


$charset = "UTF-8";
header("Pragma: no-cache");
header("Cache-Control: no-store");
header("Content-Type: text/html; charset=".$charset);

html_header( );

$output = array( );

echo "<p />The following rsync (copy) processes are still running on the system:<p/>";
exec("rsync ".$_GET["file"]." ".$_GET["destination"], $output);
foreach ($output as $line) { echo "<p>$line</p>"; }
echo "----------------------------------";
echo " End of List ";
echo "----------------------------------";

/*function to copy a file to desired destinion folder*
function copy(){
  echo "<p />The following rsync (copy) processes are still running on the system:<p/>";
  exec("pgrep rsync | xargs -s 200 ps", $output);
  foreach ($output as $line) { echo "<p>$line</p>"; }
}*/

//copy();

function html_header($header="") {
  global $charset;
  echo "
     <!DOCTYPE HTML PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
     <html xmlns=\"http://www.w3.org/1999/xhtml\">
     <head>
     <meta http-equiv=\"content-type\" content=\"text/html; charset=".$charset."\" />
	<meta http-equiv=\"refresh\" content=\"5\">
	<title>...:::: phpCPMonitor</title>
     $header
     </head> ";
  return;
}

echo "bazil"." ".$_GET["destination"]." ".$_GET["file"];

?>