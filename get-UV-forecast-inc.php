<?php
//import UV Forecast
// Script by Ken True - saratoga-weather.org
// Version 1.00 - 25-Feb-2008 - Initial Release
// Version 1.01 - 01-Mar-2008 - fixed logic to skip header row
// Version 1.02 - 03-Mar-2008 - added logic to adjust return arrays so [0] is always 'today's forecast
//                              based on timezone setting in $ourTZ (or $SITE['tz'] for templates
// Version 1.03 - 03-Jul-2009 - PHP5 support for timezone set
// Version 1.04 - 26-Jan-2011 - added support for $cacheFileDir for cache file
// Version 1.05 - 15-Feb-2011 - fixed undefined index Notice: errata
// Version 1.06 - 18-Feb-2011 - added support for comma decimal point in returned UVfcstUVI array
// Version 1.07 - 30-Mar-2011 - added support for date formatting
// Version 1.08 - 11-Nov-2017 - switch to curl for fetch, fix notice errata
// Version 1.09 - 12-Feb-2021 - switch to https for temis.nl access
//
// error_reporting(E_ALL); // uncomment for error checking
// this script is designed to be used by
//   include("get-UV-forecast-inc.php");
//
// the script does no printing other than the HTML comments on status and the
// required copyright info.
//
//  to print values in your page, just:
//
//   echo "UV Forecast $UVfcstDate[0] is $UVfcstUVI[0]\n";
//
//  Returns Arrays:
//
//  $UVfcstDate[n]  -- date of forecast in dd Mon yyyy format
//  $UVfcstUVI[n]   -- forecast UVI in dd.d format
//                     n=0...8 (usually)
//  $UVfcstDOW[n]   -- forecast DayOfWeek ('Sunday' ... 'Saturday') from date('l',time());
//  $UVfcstISO[n]   -- forecast date in YYYYMMDD format.
//  will return $UVfcstUVI[n] = 'n/a' if forecast is not available.
// 
// -------------Settings ---------------------------------
  $cacheFileDir = './';      // default cache file directory
  $myLat = '37.27153397';    //North=positive, South=negative decimal degrees
  $myLong = '-122.02274323';  //East=positive, West=negative decimal degrees
  $ourTZ = "America/Los_Angeles";  //NOTE: this *MUST* be set correctly to
// translate UTC times to your LOCAL time for the displays.
//  http://us.php.net/manual/en/timezones.php  has the list of timezone names
//  pick the one that is closest to your location and put in $ourTZ like:
//   $ourTZ = "Europe/Paris";  
//   $ourTZ = "Pacific/Auckland";
  $commaDecimal = false;     // =true to use comma as decimal point in UVfcstUVI
  $dateOnlyFormat = 'd M Y'; // dd MON YYYY
// -------------End Settings -----------------------------
//
$UVversion = 'get-UV-forecast-inc.php V1.09 - 12-Feb-2021';
// the following note is required by agreement with the authors of the website www.temis.nl
/* -----------------------------------------------------------------------------------------
Date: Wed, 20 Feb 2008 11:30:43 +0100
From: Ronald van der A <avander@knmi.nl>
Organization: KNMI
To: webmaster@saratoga-weather.org
CC: Ronald.van.der.A@knmi.nl, Jos.van.Geffen@knmi.nl
Subject: Re: Request to use data

Dear Ken,

If you change the line into

<p>UV forecast courtesy of and <a href="http://www.temis.nl/">Copyright
&copy; KNMI/ESA</a>. Used with permission.</p>

then it is ok for us. In this way KNMI is acknowledged, who have done
the major part of the UV product development.

Best regards,
Ronald van der A
 ----------------------------------------------------------------------------------------- */
$requiredNote = 'UV forecast courtesy of and Copyright &copy; KNMI/ESA (http://www.temis.nl/). Used with permission.';
//
// overrides from Settings.php if available
global $SITE;
if (isset($SITE['latitude'])) 	{$myLat = $SITE['latitude'];}
if (isset($SITE['longitude'])) 	{$myLong = $SITE['longitude'];}
if (isset($SITE['tz'])) {$ourTZ = $SITE['tz']; }
if(isset($SITE['cacheFileDir']))     {$cacheFileDir = $SITE['cacheFileDir']; }
if(isset($SITE['commaDecimal']))     {$commaDecimal = $SITE['commaDecimal']; }
if(isset($SITE['dateOnlyFormat']))   {$dateOnlyFormat = $SITE['dateOnlyFormat']; }
// end of overrides from Settings.php

$myLat = round($myLat,4);
$myLong = round($myLong,4);
//
$UV_URL = "https://www.temis.nl/uvradiation/nrt/uvindex.php?lon=$myLong&lat=$myLat";
//
// create a 'uv-forecast.txt' file in the same directory as the script.
// you may have to set the permissions on the file to '666' so it is writable
// by the webserver.
$UVcacheName = $cacheFileDir."uv-forecast.txt";
$UVrefetchSeconds = 3600;
// ---------- end of settings -----------------------

if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain,charset=ISO-8859-1");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}
global $Status;

$Status = "<!-- $UVversion -->\n<!-- $requiredNote -->\n";
// Establish timezone offset for time display
# Set timezone in PHP5/PHP4 manner
if (!function_exists('date_default_timezone_set')) {
	putenv("TZ=" . $ourTZ);
#	$Status .= "<!-- using putenv(\"TZ=$ourTZ\") -->\n";
    } else {
	date_default_timezone_set("$ourTZ");
#	$Status .= "<!-- using date_default_timezone_set(\"$ourTZ\") -->\n";
}
$TZ = date('T',time()); // get our timezone abbr

// You can now force the cache to update by adding ?force=1 to the end of the URL

if ( empty($_REQUEST['force']) ) 
        $_REQUEST['force']="0";

$Force = $_REQUEST['force'];

if ($Force==1) {
      $html = UVF_fetchUrlWithoutHanging($UV_URL,false); 
      $Status .= "<!-- force reload from URL $UV_URL -->\n";
      $fp = fopen($UVcacheName, "w"); 
	  if($fp) {
        $write = fputs($fp, $html); 
        fclose($fp);
	  } else {
	    $Status .= "<!--Unable to write cache $UVcacheName -->\n";
	  }
} 


// refresh cached copy of page if needed
// fetch/cache code by Tom at carterlake.org

if (file_exists($UVcacheName) and filemtime($UVcacheName) + $UVrefetchSeconds > time()) {
      $WhereLoaded = "from cache $UVcacheName";
      $html = implode('', file($UVcacheName));
    } else {
      $WhereLoaded = "from URL $UV_URL";
      $html = UVF_fetchUrlWithoutHanging($UV_URL,false);
      $fp = fopen($UVcacheName, "w"); 
	  if($fp) {
        $write = fputs($fp, $html); 
        fclose($fp);
	  } else {
	    $Status .=  "<!--Unable to write cache $UVcacheName -->\n";
	  }
	}
$Status .=  "<!-- UV data load from $WhereLoaded -->\n";

/*$UVfcstDate = array_fill(0,9,'');   // initialize the return arrays
$UVfcstUVI  = array_fill(0,9,'n/a');
$UVfcstDOW  = array_fill(0,9,'');
$UVfcstYMD  = array_fill(0,9,'');
$UVfcstISO  = array_fill(0,9,'');
*/
$UVfcstDate = array();   // initialize the return arrays
$UVfcstUVI  = array();
$UVfcstDOW  = array();
$UVfcstYMD  = array();
$UVfcstISO  = array();

if(strlen($html) < 50 ) {
  $Status .=  "<!-- data not available -->\n";
	print $Status;
  return;
}
// now slice it up
// Get the table to use:
  preg_match_all('|<dl><dd>\s*<table(.*?)</table>|is',$html,$betweenspan);
//  print "<!-- betweenspan \n" . print_r($betweenspan[1],true) . " -->\n";

// slice the table into rows
  preg_match_all('|<tr>(.*)</tr>|Uis',$betweenspan[1][0],$uvsets);
  $uvsets = $uvsets[1];
//  print "<!-- uvsets \n" . print_r($uvsets,true) . " -->\n";
/*
<!-- uvsets 
Array
(
    [0] => <td align=left ><i>&nbsp;<br>&nbsp; Date</i> </td>
    <td align=right><i>UV <br>&nbsp; index</i> </td>
    <td align=right><i>ozone <br>column</i> </td>
    [1] => <td align=right nowrap>&nbsp; 25 Feb 2008 </td>
    <td align=right nowrap> 4.2 </td>
    <td align=right nowrap>&nbsp;  303.4 DU </td>

    [2] => <td align=right nowrap>&nbsp; 26 Feb 2008 </td>
    <td align=right nowrap> 4.5 </td>
    <td align=right nowrap>&nbsp;  291.8 DU </td>

    [3] => <td align=right nowrap>&nbsp; 27 Feb 2008 </td>
    <td align=right nowrap> 4.0 </td>
    <td align=right nowrap>&nbsp;  328.0 DU </td>
	...
*/  
// $headings = array_shift($uvsets);  // lose the headings row	

$indx = 0;
foreach ($uvsets as $n => $uvtext) { // take each row forecast and slice it up

// extract the data from the current table row
   $uvtext = preg_replace('|&nbsp;|is','',$uvtext);
   preg_match_all('|<td.*?>(.*?)</td>|is',$uvtext,$matches);
   
   //$Status .=  "<!-- $indx : matches \n" . print_r($matches,true) . " -->\n";
   if (isset($matches[1][1]) and is_numeric(trim($matches[1][1]))) {
	 $t = strtotime(trim($matches[1][0]));
     $UVfcstDate[$indx] = date($dateOnlyFormat,$t);  // save the values found
	 $UVfcstDOW[$indx] = date('l',$t); // sets to 'Sunday' thru 'Saturday'
	 $UVfcstYMD[$indx] = date('Ymd',$t);  // sets to YYYYMMDD
	 $UVfcstUVI[$indx] = trim($matches[1][1]);   // save UV index
	 $indx++;
   }

}

foreach ($UVfcstDate as $i => $val) {
  $Status .=  "<!-- Date='$val', UV='" . $UVfcstUVI[$i] . "' DOW='".$UVfcstDOW[$i]. "' YMD='".$UVfcstYMD[$i]."' -->\n";
}
// now fix up the array so 'today' is the [0] entry
$YMD = date('Ymd',time());
$shifted = 0;
foreach ($UVfcstYMD as $i => $uvYMD ) {
  if ($uvYMD < $YMD) {
    $junk = array_shift($UVfcstDate);
	$junk = array_shift($UVfcstUVI);
	$junk = array_shift($UVfcstDOW);
    $shifted++; 
  }
}
for ($i=0;$i<$shifted;$i++) { // clean up the YMD array after shifting
  $junk = array_shift($UVfcstYMD);
}
if ($shifted) {
  $Status .=  "<!-- after date=$YMD processing, shifted $shifted entries -->\n";
  foreach ($UVfcstDate as $i => $val) {
    $Status .=  "<!-- Date='$val', UV='" . $UVfcstUVI[$i] . "' DOW='".$UVfcstDOW[$i]. "' YMD='".$UVfcstYMD[$i]."' -->\n";
  }
}

if($commaDecimal) {
	foreach ($UVfcstUVI as $i => $uvi) {
		$UVfcstUVI[$i] = preg_replace('|\.|',',',$UVfcstUVI[$i]);
	}
   $Status .=  "<!-- UVfcstUVI entries now use decimal comma format -->\n";
}
print $Status;
return; // printing is left to the including page

// ----------------------------functions ----------------------------------- 
 
function UVF_fetchUrlWithoutHanging($url,$useFopen) {
// get contents from one URL and return as string 
  global $Status, $needCookie;
  
  $overall_start = time();
  if (! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=6;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Status .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (get-UV-forecast-inc.php - saratoga-weather.org)');

  curl_setopt($ch,CURLOPT_HTTPHEADER,                          // request LD-JSON format
     array (
         "Accept: text/html,text/plain"
     ));

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, true);                      // include header information
//  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
//  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Status .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Status .= "<!-- curl Error: ". curl_error($ch) ." -->\n";        //  display error notice
  }
  $cinfo = curl_getinfo($ch);                                  // get info on curl exec.
/*
curl info sample
Array
(
[url] => http://saratoga-weather.net/clientraw.txt
[content_type] => text/plain
[http_code] => 200
[header_size] => 266
[request_size] => 141
[filetime] => -1
[ssl_verify_result] => 0
[redirect_count] => 0
  [total_time] => 0.125
  [namelookup_time] => 0.016
  [connect_time] => 0.063
[pretransfer_time] => 0.063
[size_upload] => 0
[size_download] => 758
[speed_download] => 6064
[speed_upload] => 0
[download_content_length] => 758
[upload_content_length] => -1
  [starttransfer_time] => 0.125
[redirect_time] => 0
[redirect_url] =>
[primary_ip] => 74.208.149.102
[certinfo] => Array
(
)

[primary_port] => 80
[local_ip] => 192.168.1.104
[local_port] => 54156
)
*/
  $Status .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'];
	if(isset($cinfo['primary_ip'])) {
		$Status .= " dest=".$cinfo['primary_ip'] ;
	}
	if(isset($cinfo['primary_port'])) { 
	  $Status .= " port=".$cinfo['primary_port'] ;
	}
	if(isset($cinfo['local_ip'])) {
	  $Status .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Status .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Status .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Status .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Status .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Status .= "<!-- raw data\n".$data."\n -->\n"; 
  $i = strpos($data,"\r\n\r\n");
  $headers = substr($data,0,$i);
  $content = substr($data,$i+4);
  if($cinfo['http_code'] <> '200') {
    $Status .= "<!-- headers returned:\n".$headers."\n -->\n"; 
  }
  return $data;                                                 // return headers+contents

 } else {
//   print "<!-- using file_get_contents function -->\n";
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (get-UV-forecast-inc.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (get-UV-forecast-inc.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = UVF_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = UVF_fetch_microtime();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Status .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Status .= "<-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Status .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

}    // end UVF_fetchUrlWithoutHanging
// ------------------------------------------------------------------

function UVF_fetch_microtime()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}
   
// ----------------------------------------------------------
      
?>