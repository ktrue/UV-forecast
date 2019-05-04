<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>UV Forecast</title>
<?php
$UVscript		= 'get-UV-forecast-inc.php'; // worldwide forecast script for UV Index
$ourTZ = "America/Los_Angeles";  

$maxIcons = 7;  // maximum number of icons to display

if (isset($UVscript) and ! isset($UVfcstDate[0])) { // load up the UV forecast script
  $UVfcstDate = array_fill(0,9,'');   // initialize the return arrays
  $UVfcstUVI  = array_fill(0,9,'n/a');
}
  include_once($UVscript);
  
  $maxIcons = min($maxIcons,count($UVfcstUVI));  // use lesser of number of icons available

# Set timezone in PHP5/PHP4 manner
  if (!function_exists('date_default_timezone_set')) {
	  if (! ini_get('safe_mode') ) {
		 putenv("TZ=$ourTZ");  // set our timezone for 'as of' date on file
	  }  
    } else {
	date_default_timezone_set("$ourTZ");
   }

?>
</head>
<body style="font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; background-color:#FFFFFF; width: 680px; margin: 0 auto;">
<div id="main">
  
  <h1>UV Index Forecast</h1>
  <p>&nbsp;</p>
  <table width="99%" style="border: none">
  <tr>
    <td align="center">
  <?php if($UVfcstUVI[0] <> 'n/a') {  // forecast is available .. do normal page ?>
      <table width="620" style="border: none" cellspacing="3" cellpadding="3">
        <tr class="table-top">
        <?php for ($i = 0;$i < $maxIcons;$i++) { ?>
          <td align="center"><strong><?php
		    if($UVfcstDate[$i]<>'') {
			    print date('D',strtotime($UVfcstDate[$i])); 
		    } ?></strong></td>
        <?php } // end for
		?>
        </tr>
        <tr class="column-light">
          <?php for ($i=0;$i < $maxIcons;$i++) {  ?>
          <td align="center"><?php echo gen_uv_icon($UVfcstUVI[$i]); ?></td>
          <?php } // end for
		  ?>
        </tr>
        <tr class="column-dark">
          <?php for ($i=0;$i < $maxIcons;$i++) {  ?>
          <td align="center"><strong><?php echo $UVfcstUVI[$i]; ?></strong></td>
          <?php } // end for
		  ?>
        </tr>
        <tr class="column-light">
          <?php for ($i=0;$i < $maxIcons;$i++) {  ?>
          <td align="center"><?php echo get_uv_word(round($UVfcstUVI[$i],0)); ?></td>
          <?php } // end for
		  ?>
        </tr>
      </table>
    <p>
    <a href="<?php echo htmlspecialchars($UV_URL); ?>"><small>
	<?php print 
		'UV forecast courtesy of and Copyright &copy; KNMI/ESA (http://www.temis.nl/). Used with permission.'; ?>
     </small></a>
    </p>
    <img src="./ajax-images/uv_image.jpg" alt="UV Index Legend" style="border: none" /><br />
    <img src="./ajax-images/UVI_maplegend_H.gif" alt="UV Index Scale" style="border: none" />
  <?php } else { // forecast wasn't available.. use alternate text ?>
  <h2><?php print 'The UV Index Forecast is not currently available'; ?></h2>
  <?php } // end of forecast wasn't available ?>
    </td>
    </tr>
    </table>

</div><!-- end main -->
<?php 
function gen_uv_icon($uv) {
	global $SITE;
	if($uv == 'n/a') { return( ''); }
	$ourUVrounded = round($uv,0);
	if ($ourUVrounded > 11) {$ourUVrounded = 11; }
	if ($ourUVrounded < 1 ) {$ourUVrounded = 1; }
	$ourUVicon = "uv" . sprintf("%02d",$ourUVrounded) . ".gif";
	
	return( '<img src="./ajax-images/'. $ourUVicon . 
	  '" height="76" width="40"  alt="UV Index" title="UV Index" />');
}
//=========================================================================
//  decode UV to word+color for display

function get_uv_word ( $uv ) {
	global $SITE;
// figure out a text value and color for UV exposure text
//  0 to 2  Low
//  3 to 5     Moderate
//  6 to 7     High
//  8 to 10 Very High
//  11+     Extreme
   switch (TRUE) {
	 case ($uv == 'n/a'):
	   $uv = '';
	 break;
     case ($uv == 0):
       $uv = langtransstr('None');
     break;
     case (($uv > 0) and ($uv < 3)):
       $uv = '<span style="border: solid 1px; background-color: #A4CE6a;">&nbsp;Low&nbsp;</span>';
     break;
     case (($uv >= 3) and ($uv < 6)):
       $uv = '<span style="border: solid 1px;background-color: #FBEE09;">&nbsp;Medium&nbsp;</span>';
     break;
     case (($uv >=6 ) and ($uv < 8)):
       $uv = '<span style="border: solid 1px; background-color: #FD9125;">&nbsp;High&nbsp;</span>';
     break;
     case (($uv >=8 ) and ($uv < 11)):
       $uv = '<span style="border: solid 1px; color: #FFFFFF; background-color: #F63F37;">&nbsp;Very&nbsp;High&nbsp;</span>';
     break;
     case (($uv >= 11) ):
       $uv = '<span style="border: solid 1px; color: #FFFF00; background-color: #807780;">&nbsp;Extreme&nbsp;</span>';
     break;
   } // end switch
   return $uv;
} // end getUVword

//=========================================================================

?>
</body>
</html>