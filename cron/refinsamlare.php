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

// Inkludera nödvändiga beroenden
require("./midom.php");

// Anslut till databasen
$db = kontakta_databasen($dbnamn, $dbhost, $dbanv, $dblosen);

// Hämta den fil $url pekar på
function hamta_fil($url) {
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_HEADER, 0);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  $resultat = curl_exec($curl);
  if(curl_exec($curl) === FALSE)
    echo 'Curl fel: '.skriv_log(curl_error($curl))." (".
    skriv_log(curl_errno($curl)).")\n";
  curl_close($curl);
  return($resultat);
}

// Ansluter till databasen
function kontakta_databasen($dbnamn, $dbhost, $dbanv, $dblosen) {
$db = mysql_connect($dbhost, $dbanv, $dblosen) or die
  ("<p>Kunde inte kontakta MYSQL</p><p>Försäkra dig om att du skapat".
  " en databasanvändare och har fyllt i rätt lösenord!</p>");
mysql_select_db($dbnamn) or die ('Fel vid anslutning till databasen!');
return ($db);
}

// Skriv till en logfil
function skriv_log($data) {
  file_put_contents('../log/refinsamlare.log', $data, FILE_APPEND);
}

// Omvandla till ren UTF8 i fälten
function rena($data) {
  return(addslashes(trim(html_entity_decode($data, ENT_QUOTES, "UTF-8"))));
}

skriv_log(date('Y-m-d (H:i)')." startar körning\n".str_repeat("-=", 30)."\n");

// Kör länktest
skriv_log("** Startar länktest **\n");
$sql = "SELECT * FROM `$dbnamn`.`rss_tidskrift`";
$resultat = mysql_query($sql) or die ('Fel vid databasförfrågan!');
while ($rad = mysql_fetch_assoc($resultat)) {
  if (($fp = @fopen($rad['rssurl'], 'r')) === FALSE) {
    skriv_log("FEL!".$rad['namn']."\n");
    $sql = "UPDATE `$dbnamn`.`rss_tidskrift` SET `rssok` = NULL ".
    "WHERE `rss_tidskrift`.`rss_tidskrift_id` = '".$rad['rss_tidskrift_id'].
    "' LIMIT 1 ;";
    mysql_query($sql) or die ('Fel vid databasförfrågan!');
  } else {
    skriv_log("OK ".$rad['namn']."\n");
    $sql = "UPDATE `$dbnamn`.`rss_tidskrift` SET `rssok` = now() ".
    "WHERE `rss_tidskrift`.`rss_tidskrift_id` = '".$rad['rss_tidskrift_id'].
    "' LIMIT 1 ;";
    mysql_query($sql) or die ('Fel vid databasförfrågan!');
  }
  @fclose($fp);
}
skriv_log("** Slut på länktest **\n");

$sql = "SELECT * FROM `$dbnamn`.`rss_tidskrift`";
$resultat = mysql_query($sql) or die ('Fel vid databasförfrågan!');
while ($rad = mysql_fetch_assoc($resultat)) {
// Hoppa över felaktiga XML-filer
if(!is_null($rad['rssok'])) {
$data = hamta_fil($rad['rssurl']);
$sparaxml = '../cache/'.$rad['issn'].'.xml';

// Spara senaste XML-er för senare kontroll
if ($RSSlogg) {
  if (file_put_contents($sparaxml, $data)) {
    exec("rm -f $sparaxml".'.gz');
    exec("gzip $sparaxml");
    exec("rm -f $sparaxml");
  }
}

// Skapa DOM-objektet
$xml = new midom();

// ladda in xml-strängen
$xml->loadXML($data);
$roten = $xml->mi_root();

// Vilken nod har item som förälder?
  $itempar = $xml->mi_findparentof("item");
  if ($itempar)
  {
// Skapa en array av items. Läser in alla item-referenser under föräldern, till en array
    $items = mi_kids_all_bytag($itempar,"item");

// Nollställ räknare
$rakn_nyartikel = $rakn_totalt = 0;

// Plocka lite data från items
foreach ($items as $item)
{

// Totalt antal artiklar
$rakn_totalt++;

// Nollställ variabler
unset ($forfattare, $titel, $datum, $lank, $volym, $nummer, $startsida,
$identifierare, $pmid, $bestlank, $aulast, $datumid, $beskrivning, $slutsida,
$pmidsidor, $pmid_data, $guid);

// Länk
if(mi_kidvalue($item,"link") != "")
  $lank = mi_kidvalue($item,"link");

// Titel
if(mi_kidvalue($item,"title") != "")
  $titel = rena(strip_tags(mi_kidvalue($item,"title")));

$striptitel = preg_split("[\[.(.*?)\]]", $titel, 2);
$striptitel = (!empty($striptitel[1]) AND (substr($titel, -1) != "]")) ?
  trim($striptitel[1]) : $titel;

// Författare
if(mi_kidvalue($item,"dc:creator") != "")
  $forfattare = rena(mi_kidvalue($item,"dc:creator"));
elseif(mi_kidvalue($item,"author") != "")
  $forfattare = rena(mi_kidvalue($item,"author"));

// Datum
if(mi_kidvalue($item,"dc:date") != "") {
  $datum = strtotime(mi_kidvalue($item,"dc:date"));
  $datumid = "post";
} elseif(mi_kidvalue($item,"pubDate") != "") {
  $datum = strtotime(mi_kidvalue($item,"pubDate"));
  $datumid = "post";
} elseif(mi_kidvalue($item,"prism:publicationDate") != "") {
  $datum = strtotime(mi_kidvalue($item,"prism:publicationDate"));
  $datumid = "post";
}
else {
  $datum = time();
  $datumid = "genererad";
}

// Beskrivning
if(mi_kidvalue($item,"description") != "") {
  $beskrivning = rena(mi_kidvalue($item,"description"));
// Ta bort överflödig formattering
  $beskrivning = strip_tags($beskrivning,
  '<p><a><h1><h2><h3><h4><h5><h6><br /><li><ol><ul><strong><em>');
  if(mb_substr(strtolower($beskrivning), 0, 2, "UTF-8") != "<p")
    $beskrivning = "<p>".$beskrivning."</p>";
}

// Volym
if(mi_kidvalue($item,"prism:volume") != "")
  $volym = mi_kidvalue($item,"prism:volume");

// Nummer
if(mi_kidvalue($item,"prism:number") != "")
  $nummer = mi_kidvalue($item,"prism:number");

// Startsida
if(mi_kidvalue($item,"prism:startingPage") != "")
  $startsida = mi_kidvalue($item,"prism:startingPage");

// Slutsida
if(mi_kidvalue($item,"prism:endingPage") != "")
  $slutsida = mi_kidvalue($item,"prism:endingPage");

// Identifierare
if(mi_kidvalue($item,"dc:identifier") != "")
  $identifierare = mi_kidvalue($item,"dc:identifier");

// Guid
if(mi_kidvalue($item,"guid") != "")
  $guid = mi_kidvalue($item,"guid");

// PMID
if(!empty($guid)) {
  $explodpmid = explode("PubMed:", $guid);
  if((count($explodpmid) > 1) AND is_numeric($explodpmid[1])) {
    $pmid = trim($explodpmid[1]);
  }
}

// Gör alla sidor till typen 9101-9155 istället för typen 9101-55
if ((!empty($slutsida) AND !empty($startsida)) AND
    (is_numeric($slutsida) AND is_numeric($startsida)) AND
    ($slutsida < $startsida))
      $slutsida = (substr($startsida, 0,
      (strlen($startsida) - strlen($slutsida))).$slutsida);

// Skapa beställningslänk för SFX
$bestlank = $OURL_resolver."?issn=".$rad['issn']."&date=".date("Y", $datum);
$bestlank .= "&title=".rawurlencode ($rad['namn']);
if (!empty($pmid)) $bestlank .= "&id=pmid:".$pmid;
if (!empty($volym)) $bestlank .= "&volume=".$volym;
if (!empty($nummer) AND is_numeric($nummer)) $bestlank .= "&issue=".$nummer;
if (!empty($startsida)) $bestlank .= "&spage=".$startsida;
if (!empty($slutsida)) $bestlank .= "&epage=".$slutsida;
if (!empty($striptitel)) $bestlank .= "&atitle=".rawurlencode ($striptitel);
if (!empty($identifier)) $bestlank .= "&id=".rawurlencode ($identifier);
if (!empty($forfattare)) $bestlank .= "&aulast=".rawurlencode ($forfattare);

// Skapa SQL-fråga
$sql2 = "SELECT * FROM `rss_artiklar`
  WHERE `titel` = '".$titel."'
  AND `forfattare` = '".$forfattare."'
  AND `rss_tidskrift_ref` = '".$rad['rss_tidskrift_id']."'";
$resultat2 = mysql_query($sql2);
if (!$resultat2) skriv_log("SQL-error: ".mysql_error()."\n");
if (mysql_num_rows($resultat2) < 1) {
  $sql3 = "INSERT INTO `$dbnamn`.`rss_artiklar` (
  `rss_artiklar_id`, `titel`, `forfattare`, `tid`, `url`, `sfxurl`,
  `rss_tidskrift_ref`, `beskrivning`, `tidtyp`, `volym`, `nummer`,
  `startsida`, `slutsida`, `identifierare`, `guid`, `pmid`)
  VALUES (
  NULL , '".$titel."', '".$forfattare."', '".$datum."', '".$lank.
  "', '".$bestlank."', '".$rad['rss_tidskrift_id']."', '".$beskrivning."',
  '".$datumid."', '".$volym."', '".$nummer."', '".$startsida."', '".$slutsida."',
  '".$identifierare."', '".$guid."', '".$pmid."');";

  $resultat3 = mysql_query($sql3);
  if (!$resultat3) {
    skriv_log("\nSQL-fel: ".mysql_error()."\n");
    skriv_log("Felaktig SQL: ".$sql3."\n\n");
        }
  $rakn_nyartikel++;
      } elseif($dublettlogg) {
        $logg = date('Y-m-d (H:i)').": Dublett hittad (tidskrift id ".
        $rad['rss_tidskrift_id'].")\n".
        "Titel: $titel\nFörfattare: $forfattare\n\n";
        file_put_contents('../log/dubletter.log', $logg, FILE_APPEND);
        }
      }
    }
  }
skriv_log($rakn_nyartikel." nya av ".$rakn_totalt." för ".$rad['namn']."\n");
}
skriv_log(str_repeat("-=", 30)."\n\n\n");

?>
