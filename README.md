# >UV Index forecast by latitude/longitude

I've received permission from [KNMI/EMA](http://www.knmi.nl/) to use their handy [multi-day UV-Index forecast](http://www.temis.nl/uvradiation/nrt/uvindex.php) which is based on latitude and longitude of the location. There is a requirement that you credit them as copyright holders as shown in the example below. The **$requiredNote** string contains the required text. The program doesn't print anything (except HTML comments) when run and is designed to be included in your page (see below). The only required settings are:

```
// -------------Settings ---------------------------------
  $cacheFileDir = './';      // default cache file directory
  $myLat = '37.27153397';    //North=positive, South=negative decimal degrees
  $myLong = '-122.02274323';  //East=positive, West=negative decimal degrees
  $ourTZ = "America/Los_Angeles";  //NOTE: this *MUST* be set correctly to
// translate UTC times to your LOCAL time for the displays.
//  https://us.php.net/manual/en/timezones.php  has the list of timezone names
//  pick the one that is closest to your location and put in $ourTZ like:
//   $ourTZ = "Europe/Paris";  
//   $ourTZ = "Pacific/Auckland";
  $commaDecimal = false;     // =true to use comma as decimal point in UVfcstUVI
  $dateOnlyFormat = 'd M Y'; // dd MON YYYY
// -------------End Settings -----------------------------
```
The script returns values in two arrays:  
**$UVfcstDate[n]** contains the date of forecast in **dd Mon yyyy** format  
**$UVfcstUVI[n]** contains forecast UVI in dd.d format  
**$UVfcstDOW[n]** is the forecast Day Of Week ('Sunday' ... 'Saturday') from date('l',time()); (New in V1.02)  
**$UVfcstISO[n]** is the forecast date in YYYYMMDD format. (New in V1.02)  

where n=0...8 . The script will return $UVfcstUVI[n] = 'n/a' if the forecast is not available.  

New with Version 1.02 - the script will adjust the returned arrays so that the [0] entry is for today (based on the timezone setting).  

To use in your page, this code will show the UV forecast for one day

```
<?php
   include("get-UV-forecast-inc.php");
 print "UV Forecast $UVfcstDate[0] is $UVfcstUVI[0] <br/><small>($requiredNote)</small>\n";
?>
```
would show:

```
UV Forecast 03-May-2019 is 7.1  
<small>(UV forecast courtesy of and Copyright © KNMI/ESA (http://www.temis.nl/). Used with permission.)</small>
```

This code will show the available forecasts with text description and the required copyright note appears as a tooltip when you mouse over the UV values.

```
<?php
//=========================================================================
//  decode UV to word+color for display

function getUVword (  $inUV ) {
// figure out a text value and color for UV exposure text
//  0 to 2  Low
//  3 to 5  Moderate
//  6 to 7  High
//  8 to 10 Very High
//  11+     Extreme
   $uv = preg_replace('|,|','.',$inUV); // in case decimal comma option is selected
   switch (TRUE) {
     case ($uv == 0):
       $uv = 'None';
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

for ($i=0;$i < count($UVfcstUVI); $i++) { ?>
UV forecast: <?php echo $UVfcstDate[$i] ?> is
<a href="<?php echo htmlspecialchars($UV_URL); ?>" title="<?php echo strip_tags($requiredNote); ?>">
<b><?php echo $UVfcstUVI[$i]; ?></b></a>&nbsp;&nbsp;<?php echo getUVword($UVfcstUVI[$i]); ?><br/><br/>

<?php } // end for loop ?>  
```

(note: this script is included with the [AJAX/PHP](https://saratoga-weather.org/wxtemplates/index.php) website templates)
