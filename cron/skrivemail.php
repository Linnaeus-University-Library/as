<?php
/******************************************************************************\
*                                                                              *
*  Detta program är en del av Artikelskördaren                                 *
*                                                                              *
* This program is free software: you can redistribute it and/or modify         *
* it under the terms of the GNU Affero General Public License as published by  *
* the Free Software Foundation, either version 3 of the License, or            *
* (at your option) any later version.                                          *
*                                                                              *
* This program is distributed in the hope that it will be useful,              *
* but WITHOUT ANY WARRANTY; without even the implied warranty of               *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                *
* GNU Affero General Public License for more details.                          *
*                                                                              *
* You should have received a copy of the GNU Affero General Public License     *
* along with Artikelskördaren.  If not, see <http://www.gnu.org/licenses/>.    *
*                                                                              *
*                                                                              * 
*  Utvecklad av Tony Mattsson <tony.mattsson@ltdalarna.se>                     *
*                                                                              *
*  Syfte: Skickar ut email till prenumeranter baserat på deras                 *
*  prenumerationer och vilka dagar de vill ha utskick                          *
*                                                                              *
\******************************************************************************/

// Inkludera viktiga parametrar
include ('../config/installningar.php');

// Anslut till databasen
$db = kontakta_databasen($dbnamn, $dbhost, $dbanv, $dblosen);

$atgard     = $_GET['atgard'];
$utmatning  = $_GET['utmatning'];
$varde      = (is_numeric($_GET['varde'])) ? $_GET['varde'] : FALSE;

// Ansluter till databasen
function kontakta_databasen($dbnamn, $dbhost, $dbanv, $dblosen) {
$db = mysql_connect($dbhost, $dbanv, $dblosen) or die
  ("<p>Kunde inte kontakta MYSQL</p><p>Försäkra dig om att du skapat".
  " en databasanvändare och har fyllt i rätt lösenord!</p>");
mysql_select_db($dbnamn) or die ('Fel vid anslutning till databasen!');
return ($db);
}

// Funktion för att skicka HTML-mail
// Från http://code.web-max.ca/misc_htmlemail.php
function sendHTMLemail($HTML,$from,$to,$subject)
{
// First we have to build our email headers
// Set out "from" address

    $headers = "From: $from\r\n"; 

// Now we specify our MIME version

    $headers .= "MIME-Version: 1.0\r\n"; 

// Create a boundary so we know where to look for
// the start of the data

    $boundary = uniqid("HTMLEMAIL"); 
    
// First we be nice and send a non-html version of our email
    
    $headers .= "Content-Type: multipart/alternative;".
                "boundary = $boundary\r\n\r\n"; 

    $headers .= "This is a MIME encoded message.\r\n\r\n"; 

    $headers .= "--$boundary\r\n".
                "Content-Type: text/plain; charset=UTF-8\r\n".
                "Content-Transfer-Encoding: base64\r\n\r\n"; 
                
    $headers .= chunk_split(base64_encode(strip_tags($HTML))); 

// Now we attach the HTML version

    $headers .= "--$boundary\r\n".
                "Content-Type: text/html; charset=UTF-8\r\n".
                "Content-Transfer-Encoding: base64\r\n\r\n"; 
                
    $headers .= chunk_split(base64_encode($HTML)); 

// And then send the email ....

    if (mail($to,$subject,"",$headers))
      return TRUE;
  return FALSE;
}

// Funktion för att kapa av en sträng efter ett visst antal tecken,
// och lägga till ... efter.
// Exempelvis för att utskriften skall bli snygg
function trunkera($strang, $maxlangd = 65) {
  if (strlen($strang) > $maxlangd) {
    $strang = substr($strang, 0, $maxlangd);
    return ($strang.' ...');
  }
  return ($strang);
}

// Skriv till en logfil
function skriv_log($data) {
  file_put_contents('../log/skrivemail.log', $data, FILE_APPEND);
}

function skapa_utskrift($prenumerant, $fran_tidpunkt, $till_tidpunkt, $dbnamn) {
global $ansvarig, $ansvemail, $biblmedd, $AktiveraSFX, $OURL_resolver;
$sql = "SELECT * FROM `$dbnamn`.`rss_prenumerationer`
  WHERE `rss_prenumeranter_ref` = '".$prenumerant."'";
$resultat = mysql_query($sql) or die ('Fel vid databasförfrågan!'.mysql_error($db));
while ($rad = mysql_fetch_assoc($resultat)) {
$sql2 = "SELECT * FROM `$dbnamn`.`rss_tidskrift` WHERE
  `rss_tidskrift_id` = '".$rad['rss_tidskrift_ref']."'";
$resultat2 = mysql_query($sql2) or die ('Fel vid databasförfrågan!'.mysql_error($db));
$rad2 = mysql_fetch_assoc($resultat2);
$reflista .= "\n<li><a href=\"#".$rad2['issn']."\">".$rad2['namn']."</a></li>";
$medd .= "\n<hr>\n<h2>\n<a name=\"".$rad2['issn']."\" href=\"".
  $rad2['url']."\">".$rad2['namn']."</a>\n</h2>";

$kommentar = str_replace("\n", "<br>", 
  htmlentities($rad2['kommentar'], ENT_NOQUOTES, "UTF-8"));
$kommentar = str_replace("&lt;", "<", $kommentar);
$kommentar = str_replace("&gt;", ">", $kommentar);

  $sql3 = "SELECT * FROM `$dbnamn`.`rss_artiklar` WHERE
    `rss_tidskrift_ref` = '".$rad2['rss_tidskrift_id']."'
    AND `tid` > '".$fran_tidpunkt."'
    AND `tid` < '".$till_tidpunkt."'
    ORDER BY `tid` ASC";
  $resultat3 = mysql_query($sql3) or die ('Fel vid databasförfrågan!'.mysql_error($db));
  $traffar = mysql_num_rows($resultat3);
  if ($traffar != 0) {
    if(!empty($kommentar))
      $medd .= "\n<blockquote>\n<p>".$kommentar."</p>\n</blockquote>";
  $nynya = ($traffar > 1) ? "nya artiklar" : "ny artikel";
  $medd .= "\n<p>".$traffar." ".$nynya."</p><ol>";
  while ($rad3 = mysql_fetch_assoc($resultat3)) {
    $medd .= "\n<li>(".date("Y-m-d", $rad3['tid']).
    ') <b>'.htmlentities($rad3['titel'], ENT_QUOTES, "UTF-8").'</b>';
  if($AktiveraSFX) $medd .= " <a href=\"$OURL_resolver".
  htmlspecialchars($rad3['sfxurl']).'">[SFX]</a>';
   $medd .= ' <a href="'.htmlspecialchars($rad3['url']).'">[RSS]</a>';

  if (!empty($rad3['forfattare'])) {
    $medd .= "<br>".htmlentities(trunkera($rad3['forfattare'], 60),
      ENT_QUOTES, "UTF-8");
  }
  $medd .= "</li>";
    }
  $medd .= "</ol>";
  }  else {
    $medd .= "\n\n<p>Inga nya artiklar har publicerats.</p>";
    }
}
$medd .= "\n<hr>\n<p><b>Utmatningen slutar här</b></p>";
$huvud = "\n<h1>Tidskriftsbevakning</h1>".
  "\n<p><em>Artiklar publicerade ".date('Y-m-d', $fran_tidpunkt).
  " &mdash; ".date('Y-m-d', $till_tidpunkt)."</em></p>".$biblmedd.
  "<h2>Innehållsförteckning</h2>\n<ul>".$reflista."\n</ul>".
  "<p>Om du vill ändra din bevakning kan ".
  "du kontakta ".$ansvarig." &lt;<a href=\"mailto:".$ansvemail."\">".
  $ansvemail."</a>&gt;.</p>";

return($huvud.$medd);
}
// Slut på funktionsdefinitioner

// Antingen skickar man enstaka utskick (om exempelvis utskicket ej kommit
// fram till användaren, kan administratören gå in och skicka manuellt.)
if ($atgard != "skickaenskild") {

skriv_log(date('Y-m-d (H:i)')." startar körning\n".str_repeat("=", 60));

  $sql = "SELECT *
    FROM `rss_prenumeranter`
    ORDER BY `arbetsplats` ASC";
  $resultat = mysql_query($sql) or die ('Fel vid databasförfrågan!'.mysql_error($db));
  while ($rad = mysql_fetch_assoc($resultat)) {
  skriv_log("\n".$rad['fornamn']." ".$rad['efternamn'].":");
  if($rad['period'] == "manad" AND $rad['utsdag'] == date('j')) {
    $fran_tidpunkt = (time() - (4 * 7 * 24 * 60 * 60));
    $till_tidpunkt = time();
    skriv_log("\tmånadsutskick från ".date('Y-m-d (H:i)',
      $fran_tidpunkt)." till ".date('Y-m-d (H:i)'));
// Skicka email till prenumeranten
    $till         = $rad['email'];
    if (sendHTMLemail(skapa_utskrift($rad['rss_prenumeranter_id'], $fran_tidpunkt,
    $till_tidpunkt, $dbnamn), $emailfran, $till, $utskickamne)) {
      skriv_log("\tmånadsutskick från ".date('Y-m-d (H:i)',
      $fran_tidpunkt)." till ".date('Y-m-d (H:i)'));
    } else {
      skriv_log("\tmisslyckades skicka till ".$rad['email']);
    }
  } elseif($rad['period'] == "vecka" AND $rad['utsdag'] == date('N')) {
    $fran_tidpunkt = (time() - (7 * 24 * 60 * 60));
    $till_tidpunkt = time();
// Skicka email till prenumeranten
    $till         = $rad['email'];
    if (sendHTMLemail(skapa_utskrift($rad['rss_prenumeranter_id'], $fran_tidpunkt,
    $till_tidpunkt, $dbnamn), $emailfran, $till, $utskickamne)) {
      skriv_log("\tveckoutskick från ".date('Y-m-d (H:i)',
      $fran_tidpunkt)." till ".date('Y-m-d (H:i)'));
    } else {
      skriv_log("\tmisslyckades skicka till ".$rad['email']);
    }
  } else {
    skriv_log("\tingen utmatning");
  }
}

skriv_log("\n".str_repeat("=", 60)."\n\n\n");
} else {
  $sql = "SELECT * FROM `rss_prenumeranter` WHERE `rss_prenumeranter_id` = '".
  $varde."'";
  $resultat = mysql_query($sql) or die ('Fel vid databasförfrågan!'.mysql_error($db));
  $rad = mysql_fetch_assoc($resultat);
  if($rad['period'] == "manad") {
    $fran_tidpunkt = (time() - (4 * 7 * 24 * 60 * 60));
    $till_tidpunkt = time();
  } else {
    $fran_tidpunkt = (time() - (7 * 24 * 60 * 60));
    $till_tidpunkt = time();
  }
  $utskrift = skapa_utskrift($rad['rss_prenumeranter_id'],
    $fran_tidpunkt, $till_tidpunkt, $dbnamn);
  if ($utmatning == "epost" AND $varde == TRUE) {
  // Skicka email till prenumeranten
    $till         = $rad['email'];
    if(sendHTMLemail(skapa_utskrift($varde, $fran_tidpunkt,
    $till_tidpunkt, $dbnamn), $emailfran, $till, $utskickamne))
    echo "<html><head><title>Skickat via epost</title></head><body>".
    "<p>Epostmeddelande har skickats till $till.</p><p>".
    "<a href=\"../index.php?".
    "tab=anvandare\">G&aring; tillbaka till Artikelsk&ouml;rdaren</a>".
    "</p></body></html>";
    else
    echo "<html><head><title>Skickat via epost</title></head><body>".
    "<p>Epostmeddelande kunde <b>inte</b> skickas till $till.</p><p>".
    "<a href=\"../index.php?".
    "tab=anvandare\">G&aring; tillbaka till Artikelsk&ouml;rdaren</a>".
    "</p></body></html>";
    exit();
  } else {
  echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">
  <html>
  <head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
   <title>Utmatning av artiklar</title>
  </head>
  <body>';
  echo $utskrift;
  echo "</body>\n</html>";
  }
}
?>
