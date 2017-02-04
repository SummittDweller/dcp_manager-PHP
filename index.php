<?php /**
 * Created by PhpStorm.
 * User: Mark McFate
 * Date: 4/24/2015
 * Time: 2:20 PM
 */

// session_start( );

include 'head.inc';
include 'header.inc';
include 'edit-buttons.inc';
?>

  <div id="content">
    <form method="POST" action="open.php">

      <div class="path-title">ASSETMAP to Copy From...</div>
      <div class="path-instructions">You must specify an ASSETMAP file to Copy from. It is NOT required for Delete or Catalog operations.</div>
      <div class="path">
        <input type="text" name="assetMapPath" class="pathBox" id="assetmap-path" value="/mnt/usb/ASSETMAP" />
      </div>

      <div class="path-title">TARGET Copy Directory</div>
      <div class="path-instructions">You must specify a valid TARGET directory in order to use the Copy command button.</div>
      <div class="path">
        <input type="text" class="pathBox" name="destinationPath" id="destination-path" value="/mnt/1terrabyte/TRAILERS/"/>
      </div>

      <div class="path-title">ASSETMAP to Delete From</div>
      <div class="path-instructions">You must specify a valid ASSETMAP to Delete from in order to use the Delete command button.</div>
      <div class="path">
        <input type="text" class="pathBox" name="deletePath" id="delete-path" value="/mnt/1terrabyte/TRAILERS/ASSETMAP"/>
      </div>

        <input type="submit" name='copy-ns' class="button" id="select-button" value="Copy with No Selection"/>
        <input type="submit" name='copy' class="button" id="select-button" value="Copy"/>
        <input type="submit" name='delete' class="button" id="select-button" value="Delete"/>
    </form><br />
  </div>

</body>
</html>

