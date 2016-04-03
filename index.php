<?php
  /******************************************************************************\
  *                                                                              *
  *  Artikelskördaren Copyright (c) 2008, 2009 Landstinget Dalarna               *
  *  Bibliotek och informationscentral                                           *
  *                                                                              *
  *  Version 1.00 rc1                                                            *
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
  * along with this program.  If not, see <http://www.gnu.org/licenses/>.        *
  *                                                                              *
  *                                                                              * 
  *  Utvecklad av Tony Mattsson <tony.mattsson@ltdalarna.se>                     *
  *                                                                              *
  *  Syfte: Administration av prenumeranter och tidskrifter som                  *
  *  används för att skicka ut email med artiklar.                               *
  *                                                                              *
  \******************************************************************************/
// Rapportera bara allvarligare fel
error_reporting(5);

// Börja session
session_start();
if ($_GET['atgard'] == 'loggaut') {
  session_destroy();
  header('Location: index.php');
}

// Inkludera viktiga parametrar
include ('./config/installningar.php');

// Möjligtvis begränsa via IP
if($BegransaAnroparIP)
  if (!inom_iprange($IPStart, $IPSlut)) die($IPFelMeddelande);

// Läs in värden i variabler
$tab            = frisera($_REQUEST['tab']);
if (empty($tab)) $tab = "anvandare"; 
$atgard         = frisera($_REQUEST['atgard']);
$varde          = frisera($_REQUEST['varde']);
$namn           = frisera($_POST['namn']);
$issn           = frisera($_POST['issn']);
$rssurl         = frisera($_POST['rssurl']);
$url            = frisera($_POST['url']);
$arbetsplats    = frisera($_POST['arbetsplats']);
$email          = frisera($_POST['email']);
$fornamn        = frisera($_POST['fornamn']);
$efternamn      = frisera($_POST['efternamn']);
$kommentar      = strip_tags($_POST['kommentar'], '<b><i><a><li><ol><ul>');
$period         = frisera($_POST['period']);
$utsdag         = frisera($_POST['utsdag']);
$anvandarnamn   = frisera($_POST['anvandarnamn']);
$losenord       = frisera($_POST['losenord']);

// Logga in användaren om användarnamn och lösenord stämmer
if ($atgard == "loggain" AND !empty($anvandarnamn)
  AND !empty($losenord)) {
    if ($AdminAnvandarnamn == $anvandarnamn AND
      $AdminLosenord == $losenord) {
    $_SESSION["tidbev_inloggad"]     = "japp";
    $_SESSION["tidbev_inloggad_som"] = $anvandarnamn;
  } else {
        $atgard = "";
        sleep(1); // För minska effektiviteten i brute-force attacker
        skriv_minisida("<h4>Fel vid inloggning</h4>
      <p>Du kunde inte loggas in.
      Lösenordet och användarnamnet stämmer inte.</p>", TRUE);
        }
   } elseif (($atgard == "loggain" AND empty($anvandarnamn)) OR
  ($atgard == "loggain" AND empty($losenord))) {
  skriv_minisida("<h4>Fel vid inloggning</h4>
      <p>Du kunde inte loggas in.
      Du måste mata in både användarnamn och lösenord.</p>", TRUE);
  } elseif ($atgard == "loggain" AND empty($anvandarnamn) AND
    empty($losenord )) {
  skriv_minisida("<h4>Fel vid inloggning</h4>
      <p>Du kunde inte loggas in.
      Du matade inte in varken användarnamn eller lösenord.</p>", TRUE);
}

// Funktion för att ta bort skadlig kod i strängar, för att undvika injection
function frisera($strang) {
  if (empty($strang))
    return $strang; // Skippa funktioner om de inte behövs (snabbar upp)
  $strang = htmlspecialchars(strip_tags(trim($strang)));
  return $strang;
}

// Ansluter till databasen
function kontakta_databasen($dbnamn, $dbhost, $dbanv, $dblosen) {
$db = mysql_connect($dbhost, $dbanv, $dblosen) or die
  ("<p>Kunde inte kontakta MYSQL</p><p>Försäkra dig om att du skapat".
  " en databasanvändare och har fyllt i rätt lösenord!</p>");
// Kontrollera att rätt tabellstruktur finns
  mysql_select_db($dbnamn) or die ('Fel vid anslutning till databasen!');
$resultat = mysql_query("SHOW TABLES LIKE 'rss_arbetsplats';", $db)
  or die ('<p>Fel vid läsning av databasen</p>');
// Om databasen ej finns, skapa den och fyll den med tabeller
if (mysql_num_rows($resultat) < 1) {
  $sql = "CREATE TABLE IF NOT EXISTS `rss_arbetsplats` (
  `rss_arbetsplats_id` int(10) unsigned NOT NULL auto_increment,
  `namn` tinytext collate utf8_swedish_ci NOT NULL,
  PRIMARY KEY  (`rss_arbetsplats_id`)
  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=0;";
  mysql_query($sql, $db) or die("<p>Kunde ej skapa tabell!</p>");
  }
$resultat = mysql_query("SHOW TABLES LIKE 'rss_artiklar';", $db)
  or die ('<p>Fel vid läsning av databasen</p>');
// Om databasen ej finns, skapa den och fyll den med tabeller
if (mysql_num_rows($resultat) < 1) {
  $sql = "CREATE TABLE IF NOT EXISTS `rss_artiklar` (
  `rss_artiklar_id` int(10) unsigned NOT NULL auto_increment,
  `titel` varchar(512) collate utf8_swedish_ci NOT NULL,
  `forfattare` varchar(512) collate utf8_swedish_ci NOT NULL,
  `tid` int(10) unsigned NOT NULL,
  `url` text collate utf8_swedish_ci NOT NULL,
  `sfxurl` text collate utf8_swedish_ci NOT NULL,
  `rss_tidskrift_ref` int(10) unsigned NOT NULL,
  `beskrivning` text collate utf8_swedish_ci NOT NULL,
  `tidtyp` enum('post','genererad') collate utf8_swedish_ci NOT NULL default 'post',
  `volym` tinytext collate utf8_swedish_ci NOT NULL,
  `nummer` tinytext collate utf8_swedish_ci NOT NULL,
  `startsida` tinytext collate utf8_swedish_ci NOT NULL,
  `slutsida` tinytext collate utf8_swedish_ci NOT NULL,
  `identifierare` tinytext collate utf8_swedish_ci NOT NULL,
  `guid` tinytext collate utf8_swedish_ci NOT NULL,
  `pmid` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`rss_artiklar_id`)
  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=0;";
  mysql_query($sql, $db) or die("<p>Kunde ej skapa tabell!</p>");
  }
$resultat = mysql_query("SHOW TABLES LIKE 'rss_prenumeranter';", $db)
  or die ('<p>Fel vid läsning av databasen</p>');
// Om databasen ej finns, skapa den och fyll den med tabeller
if (mysql_num_rows($resultat) < 1) {
  $sql = "CREATE TABLE IF NOT EXISTS `rss_prenumeranter` (
  `rss_prenumeranter_id` int(10) unsigned NOT NULL auto_increment,
  `fornamn` tinytext collate utf8_swedish_ci NOT NULL,
  `efternamn` tinytext collate utf8_swedish_ci NOT NULL,
  `arbetsplats` int(10) unsigned NOT NULL,
  `email` tinytext collate utf8_swedish_ci NOT NULL,
  `period` enum('manad','vecka') collate utf8_swedish_ci NOT NULL default 'manad',
  `utsdag` tinyint(4) NOT NULL default '1',
  PRIMARY KEY  (`rss_prenumeranter_id`)
  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=0;";
  mysql_query($sql, $db) or die("<p>Kunde ej skapa tabell!</p>");
  }
  $resultat = mysql_query("SHOW TABLES LIKE 'rss_prenumerationer';", $db)
  or die ('<p>Fel vid läsning av databasen</p>');
// Om databasen ej finns, skapa den och fyll den med tabeller
if (mysql_num_rows($resultat) < 1) {
  $sql = "CREATE TABLE IF NOT EXISTS `rss_prenumerationer` (
  `rss_prenumerationer_id` int(10) unsigned NOT NULL auto_increment,
  `rss_prenumeranter_ref` int(10) unsigned NOT NULL,
  `rss_tidskrift_ref` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`rss_prenumerationer_id`)
  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=0;";
  mysql_query($sql, $db) or die("<p>Kunde ej skapa tabell!</p>");
  }
  $resultat = mysql_query("SHOW TABLES LIKE 'rss_tidskrift';", $db)
  or die ('<p>Fel vid läsning av databasen</p>');
// Om databasen ej finns, skapa den och fyll den med tabeller
if (mysql_num_rows($resultat) < 1) {
  $sql = "CREATE TABLE IF NOT EXISTS `rss_tidskrift` (
  `rss_tidskrift_id` int(10) unsigned NOT NULL auto_increment,
  `namn` tinytext collate utf8_swedish_ci NOT NULL,
  `rssurl` text collate utf8_swedish_ci NOT NULL,
  `issn` char(9) collate utf8_swedish_ci NOT NULL,
  `url` varchar(512) collate utf8_swedish_ci NOT NULL,
  `kommentar` text collate utf8_swedish_ci NOT NULL,
  `rssok` timestamp NULL default NULL,
  PRIMARY KEY  (`rss_tidskrift_id`)
  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=0;";
  mysql_query($sql, $db) or die("<p>Kunde ej skapa tabell!</p>");
  }
return ($db);
}

// Kontrollerar om anroparen befinner sig inom LD
function inom_iprange($IPStart, $IPSlut) {
  $ip = ip2long($_SERVER['REMOTE_ADDR']);
  if (($ip >= ip2long($IPStart)) && ($ip <= ip2long($IPSlut))) return TRUE;
return FALSE;
}

function skriv_tillbakaknapp($lank, $text = 'Gå tillbaka') {
  return('<p style="text-align:center;padding:10px;">
  <a href="./index.php'.$lank.
    '">&laquo;&nbsp;'.$text.'&nbsp;&raquo;</a></p>');
}

// Funktion för att kapa av en sträng efter ett visst antal tecken,
// och lägga till ... efter. Exempelvis för att utskriften skall bli snygg
function trunkera($strang, $maxlangd = 20) {
  if (strlen($strang) > $maxlangd) {
    $strang = substr($strang, 0, $maxlangd);
    return ($strang.'<span style="color:blue;">&hellip;</span>');
  }
  return ($strang);
}

function skriv_sida($medd, $tab) {
global $version;
$anvandare = $tidskrifter = "toppmeny";
if ($tab == "anvandare")
  $anvandare = "toppmenyvald";
  else
  $tidskrifter = "toppmenyvald";
echo '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"  
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="sv">
    <head>
        <title>Tidskriftsbevakning</title>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <link rel="stylesheet" type="text/css" href="./css/as.css" />
    </head>
    <body>
    <div id="innehall">
    <div id="top">
    <div id="logo">
    <img src="./bilder/as.png" class="logo" />
    </div>
    <div id="valjare">
    <ul class="toppmeny">
    <li class="toppmeny"><a class="'.
  $anvandare.'" href="./index.php?tab=anvandare">Användare</a></li>
    <li class="toppmeny"><a class="'.
  $tidskrifter.'" href="./index.php?tab=tidskrifter">Tidskrifter</a></li>
    </ul>
    </div>
    </div>
    <div id="loggaut">
    <p class="loggaut">Inloggad som: '.$_SESSION["tidbev_inloggad_som"].
      '&nbsp;<img class="bild"
    src="./bilder/loggaut.gif" /><a
    href="./index.php?atgard=loggaut">Logga ut</a></p>
    </div>
    <div id="huvudinnehall">'.$medd.'
    </div>
    </div>
<div id="sidfot">
<p class="sidfot">Artikelskördaren v'.$version.'&nbsp;&copy;&nbsp;'.
  date('Y').'&nbsp;Landstinget Dalarna Bibliotek och informationscentral</p>
</div>
</body>
</html>';
}

if ($_SESSION["tidbev_inloggad"] != "japp") {
  $html = '<form action="index.php" name="inloggning" method="post">
<table>
<tr>
<td><strong>Användarnamn</strong></td>
<td><input class="inloggning" type="text" name="anvandarnamn" /></td>
</tr>
<tr>
<td><strong>Lösenord</strong></td>
<td><input class="inloggning" type="password" name="losenord" /></td>
</tr>
  <tr>
  <td>&nbsp;</td>
  <td>
  <input type="submit" name="subknapp" value="Logga in" />
  <input type="hidden" name="atgard" value="loggain" />
  </td>
  </tr>
</table>
</form>';
skriv_minisida($html);
}

//
// Funktionsdeklarationer
//

function skriv_minisida($html, $tillbakaknapp = FALSE) {
echo '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"  
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="sv">
    <head>
        <title>Tidskriftsbevakning</title>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <link rel="stylesheet" type="text/css" href="./css/asmini.css" />
    </head>
    <body>
    <div id="innehall">
      <div id="logo">
      <img src="./bilder/as.png" class="logo" />
      </div>';
if ($tillbakaknapp) $html .= "<a href=\"./index.php\">&lt;-- Gå tillbaka</a>";
echo $html;
echo '    </div>
    </body>
</html>';
exit();
}

function skriv_prenumerationslista($varde) {
  $sql = "SELECT *
  FROM `rss_tidskrift`
  ORDER BY `namn` ASC";
  $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
  $medd = '<form action="./index.php" method="post">
  <table class="lista" cellspacing="0">
  <tr>
  <th class="lista">Tidskrift</th>
  <th class="lista">ISSN</th>
  <th class="lista">Pren.</th>
  </tr>';
  while ($rad = mysql_fetch_assoc($resultat)) {
  $sql2 = "SELECT * FROM `rss_prenumerationer`
    WHERE `rss_tidskrift_ref` = '".$rad['rss_tidskrift_id']."'
    AND `rss_prenumeranter_ref` = '".$varde."'";
  $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");

  if (mysql_num_rows($resultat2) > 0) { 
  $checkbox = '<input type="checkbox" name="issn#'.$rad['issn']
    .'" value="'.$rad['rss_tidskrift_id'].'" checked="checked" />';
  } else {
  $checkbox = '<input type="checkbox" name="issn#'.$rad['issn']
    .'" value="'.$rad['rss_tidskrift_id'].'" />';
  }
  $tnr .= $rad['rss_tidskrift_id']."#";

  $medd .= '<tr>
  <td class="lista"><img class="bild"
  src="./bilder/tidskrift.gif" /><a
  href="./index.php?atgard=redigtidskrift&amp;varde='.
  $rad['rss_tidskrift_id'].'&amp;tab=tidskrifter">'.$rad['namn'].'</a></td>
  <td class="lista">'.$rad['issn'].'</td>
  <td class="lista">'.$checkbox.'</td>
  </tr>';
  }
  $medd .= '</table>';
  $medd .= '<p class="hogerjustering">
  <input type="submit" value="Skicka in ändringar" />
  </p>
  <input type="hidden" name="atgard" value="anvtidpren" />
  <input type="hidden" name="tab" value="anvandare" />
  <input type="hidden" name="tnr" value="'.
    substr($tnr, 0, (strlen($tnr)-1)).'" />
  <input type="hidden" name="varde" value="'.$varde.'" />
  </form>';
  return($medd);
}

function skriv_tidskriftslista() {
  $sql = "SELECT *
  FROM `rss_tidskrift`
  ORDER BY `namn` ASC";
  $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
  $medd = '<table class="lista" cellspacing="0">
  <tr>
  <th class="lista">Tidskrift</th>
  <th class="lista"><abbr title="Visar om länken är OK">L</abbr></th>
  <th class="lista">ISSN</th>
  <th class="lista"><abbr title="Antal prenumeranter">Pren</abbr></th>
  </tr>';
  while ($rad = mysql_fetch_assoc($resultat)) {
  $sql2 = "SELECT * FROM `rss_prenumerationer`
    WHERE `rss_tidskrift_ref` = '".$rad['rss_tidskrift_id']."'";
  $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");
  $prenantal = mysql_num_rows($resultat2);
  $medd .= '<tr>
  <td class="lista"><img class="bild"
  src="./bilder/tidskrift.gif" /><a
  href="./index.php?atgard=redigtidskrift&amp;varde='.
  $rad['rss_tidskrift_id'].'&amp;tab=tidskrifter">'.$rad['namn'].'</a></td>
  <td class="lista">';
  if(is_null($rad['rssok']))
    $medd .= '<img class="bild" title="Det är fel på RSS-länken" '.
    'src="./bilder/lankfel.png" />';
  else
    $medd .= '<img class="bild" title="RSS-länken är OK" '.
    'src="./bilder/lankok.png" />';
  $medd .= '</td>
  <td class="lista">'.$rad['issn'].'</td>
  <td class="lista">'.$prenantal.'</td>
  </tr>';
  }
  $medd .= '<tr>
  <td class="undermeny"><img class="bild"
  src="./bilder/ny.gif" /><a
    href="./index.php?atgard=laggtilltidskrift&amp;tab=tidskrifter">Lägg till
    ny tidskrift</a></td>
  <td class="undermeny">&nbsp;</td>
  <td class="undermeny">&nbsp;</td>
  <td class="undermeny">&nbsp;</td>
  </tr>';
  $medd .= '</table>';
  return($medd);
}

function skriv_anvandarlista() {
  $sql = "SELECT *
    FROM `rss_prenumeranter`
    ORDER BY `efternamn` ASC";
  $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
  $medd = '<table class="lista" cellspacing="0">
  <tr>
  <th class="lista">Namn</th>
  <th class="lista">Arbetsplats</th>
  <th class="lista"><abbr title="Antal prenumeranter">Pren</abbr></th>
  <th class="lista">Period</th>
  <th class="lista">Utskick</th>
  </tr>';
  while ($rad = mysql_fetch_assoc($resultat)) {
  $sql2 = "SELECT `namn`
    FROM `rss_arbetsplats`
    WHERE `rss_arbetsplats_id` = '".$rad['arbetsplats']."'";
  $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");
  $arbetsplats = mysql_fetch_row($resultat2);
  $sql3 = "SELECT * FROM `rss_prenumerationer`
    WHERE `rss_prenumeranter_ref` = '".$rad['rss_prenumeranter_id']."'";
  $resultat3 = mysql_query($sql3) or die ("<p>Fel vid databasförfrågan!</p>");
  $prenantal = mysql_num_rows($resultat3);
  $peruts = ($rad['period'] == 'manad') ? "månad" : "vecka";
  $medd .= '<tr>
  <td class="lista"><img class="bild"
  src="./bilder/anvandare.gif" /><a
  href="./index.php?atgard=rediganvandare&amp;varde='.
  $rad['rss_prenumeranter_id'].'&amp;tab=anvandare">'.$rad['fornamn'].'&nbsp;'.
  $rad['efternamn'].'</a></td>
  <td class="lista">'.$arbetsplats[0].'</td>
  <td class="lista">'.$prenantal.'&nbsp;<img class="bild" '.
  'src="./bilder/andra.gif" /><a href='.
  '"./index.php?atgard=andpren&amp;varde='.$rad['rss_prenumeranter_id'].
  '&amp;tab=anvandare">Ändra</a></td>
  <td class="lista">'.$peruts.' ('.$rad['utsdag'].')</td>
  <td class="lista"><img class="bild" src="./bilder/skicka.png" /><a
  href="./index.php?atgard=skickanu&amp;varde='.
  $rad['rss_prenumeranter_id'].'&amp;tab=anvandare">Skapa</td>
  </tr>';
  }
  $medd .= '<tr>
  <td class="undermeny"><img class="bild"
  src="./bilder/ny.gif" /><a
    href="./index.php?atgard=laggtillanvandare&amp;tab=anvandare">Lägg till
    ny användare</a></td>
  <td class="undermeny"><img class="bild"
  src="./bilder/arbete.png" /><a
    href="./index.php?atgard=arbetsplatser&amp;tab=anvandare">'.
  'Arbetsplatser</a></td>
  <td class="undermeny">&nbsp;</td>
  <td class="undermeny">&nbsp;</td>
  <td class="undermeny">&nbsp;</td>
  </tr>';
  $medd .= '</table>';
  return($medd);
}

$db = kontakta_databasen($dbnamn, $dbhost, $dbanv, $dblosen);  // Anslut till databasen

  switch($tab) {
  case "tidskrifter":
    switch($atgard) {
    case "laggtilltidskrift":
    $medd = '<h2 class="tabellrubrik">Lägg till ny tidskrift</h2>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td class="vtrubrik">Namn</td>
    <td class="vtinneh"><input class="redigering" type="text" name="namn" value="" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">ISSN</td>
    <td class="vtinneh"><input class="redigering" type="text" name="issn" value="" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">RSS&nbsp;URL</td>
    <td class="vtinneh"><input class="redigering" type="text" name="rssurl" value="" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Tidskrift&nbsp;URL</td>
    <td class="vtinneh"><input class="redigering" type="text" name="url" value="" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Kommentar *</td>
    <td class="vtinneh"><textarea rows="5" cols="48"
      class="vt" name="kommentar"></textarea></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td><input type="submit" value="Lägg till tidskrift" /></td>
    </tr>
    </table>
    <input type="hidden" value="nytidskrift" name="atgard" />
    <input type="hidden" value="tidskrifter" name="tab" />
    </form>';
    $medd .= skriv_tillbakaknapp('?tab=tidskrifter');
    $medd .= '<p class="tagbeskrivning">* Tillåtna tags i kommentaren
  är: <strong>&lt;b&gt;</strong> (fetstil), <strong>&lt;i&gt;</strong> (kursiv),
  <strong>&lt;a&gt;</strong> (länk) och <strong>&lt;ul&gt;, &lt;ol&gt;,
   &lt;li&gt;</strong> (listor)</p>';
    break;
    case "nytidskrift":
    if(empty($namn)) {
    $html = "\n<h1>Fel vid inmatning</h1>\n<p>Ny tidskrift kunde ej matas in ".
    "på grund av att namnfältet är tomt</p>".
    skriv_tillbakaknapp('?atgard=laggtilltidskrift&tab=tidskrifter', 'OK');
    echo skriv_minisida($html);
    }
  $rssok = "now()";
  $sql = "INSERT INTO `$dbnamn`.`rss_tidskrift` (
    `rss_tidskrift_id`, `namn`, `rssurl`, `issn`, `url`, `kommentar`, `rssok`)
    VALUES (
    NULL , '".$namn."', '".$rssurl."', '".$issn."', '".$url."', '".$kommentar.
    "', ".$rssok.");";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd = '<h2 class="tabellrubrik">Lägg till ny tidskrift</h2>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td class="vtrubrik">Namn</td>
    <td class="vtinneh">'.trunkera($namn, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">ISSN</td>
    <td class="vtinneh">'.trunkera($issn, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">RSS&nbsp;URL</td>
    <td class="vtinneh">'.trunkera($rssurl, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Tidskrift&nbsp;URL</td>
    <td class="vtinneh">'.trunkera($url, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Kommentar</td>
    <td class="vtinneh">'.nl2br($kommentar).'</td>
    </tr>
    </table>
    </form>
    <p style="font-size:1.2em;"><span
    style="font-weight:bold;color:green;"
    >&radic;</span>&nbsp;Ny tidskrift har lagts till!</p>';
    $medd .= skriv_tillbakaknapp('?tab=tidskrifter', 'OK');
    break;
    case "raderatid":
    $medd = '<h2 class="tabellrubrik">Radera tidskrift</h2>';
    $medd .= '<p>Är du säker på att du vill radera tidskriften?</p>'.
    '<p>Tidskriften och alla prenumerationer på den kommer att raderas permanent.</p>';
    $medd .= '<p><img src="./bilder/tabort.gif" class="bild" />'.
    '<a href="./index.php?atgard=bkraderatid&amp;tab=tidskrifter'.
    '&amp;varde='.$varde.'">Radera tidskriften</a></p>';
    $medd .= skriv_tillbakaknapp('?tab=tidskrifter');
    break;
    case "bkraderatid":
    $medd = '<h2 class="tabellrubrik">Radera tidskrift</h2>';
    $sql = "DELETE FROM `$dbnamn`.`rss_artiklar`
    WHERE `rss_tidskrift_ref` = '".$varde."';";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $sql = "DELETE FROM `$dbnamn`.`rss_prenumerationer`
    WHERE `rss_tidskrift_ref` = '".$varde."';";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $sql = "DELETE FROM `$dbnamn`.`rss_tidskrift`
    WHERE `rss_tidskrift`.`rss_tidskrift_id` = '".$varde."' LIMIT 1";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd .= '<p>Tidskriften och all data associerad med tidskriften har'.
    ' tagits bort.</p>';
    $medd .= skriv_tillbakaknapp('?tab=tidskrifter', 'OK');
    break;
    case "redigtidskrift":
    $sql = "SELECT * FROM `rss_tidskrift` WHERE `rss_tidskrift_id` = '".$varde."'";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $rad = mysql_fetch_assoc($resultat);
    $medd = '<h2 class="tabellrubrik">Redigera tidskrift</h2>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td>&nbsp;</td>
    <td><p class="hogerjustering"><img src="./bilder/tabort.gif" class="bild" />'.
    '<a href="./index.php?atgard=raderatid&amp;tab=tidskrifter&amp;varde='.
    $varde.'">Radera tidskrift</a></p></td>
    </tr>
    <tr>
    <td class="vtrubrik">Namn</td>
    <td class="vtinneh"><input class="redigering" type="text" name="namn" value="'.
      $rad['namn'].'" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">ISSN</td>
    <td class="vtinneh"><input class="redigering" type="text" name="issn" value="'.
      $rad['issn'].'" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">RSS&nbsp;URL</td>
    <td class="vtinneh"><input class="redigering" type="text" name="rssurl" value="'.
      $rad['rssurl'].'" />';
  if(is_null($rad['rssok']))
    $medd .= "\n<br /><span style=\"font-size:0.8em;\">RSS-filen kunde inte verifieras vid senaste körningen!</span>";
  else
    $medd .= "\n<br /><span style=\"font-size:0.8em;\">RSS-filen testad OK: ".$rad['rssok']."</span>";
    $medd .= '</td>
    </tr>
    <tr>
    <td class="vtrubrik">Tidskrift&nbsp;URL</td>
    <td class="vtinneh"><input class="redigering" type="text" name="url" value="'.
      $rad['url'].'" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Kommentar *</td>
    <td class="vtinneh"><textarea rows="5" cols="48"
      class="vt" name="kommentar">'.stripslashes($rad['kommentar']).
      '</textarea></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td><input type="submit" value="Ändra" /></td>
    </tr>
    </table>
    <input type="hidden" value="'.$varde.'" name="varde" />
    <input type="hidden" value="posttidskrift" name="atgard" />
    <input type="hidden" value="tidskrifter" name="tab" />
    </form>';
    $medd .= skriv_tillbakaknapp('?tab=tidskrifter');
    $medd .= '<p class="tagbeskrivning">* Tillåtna tags i kommentaren
  är: <strong>&lt;b&gt;</strong> (fetstil), <strong>&lt;i&gt;</strong> (kursiv),
  <strong>&lt;a&gt;</strong> (länk) och <strong>&lt;ul&gt;, &lt;ol&gt;,
   &lt;li&gt;</strong> (listor)</p>';
    break;
    case "posttidskrift":
    $sql = "UPDATE `$dbnamn`.`rss_tidskrift`
      SET `namn` = '".$namn."',
          `rssurl` = '".$rssurl."',
          `issn` = '".$issn."',
          `url` = '".$url."',
          `kommentar` = '".$kommentar."'
      WHERE `rss_tidskrift`.`rss_tidskrift_id` = '".$varde."' LIMIT 1;";
      mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd = '<h2 class="tabellrubrik">Redigera tidskrift</h2>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td class="vtrubrik">Namn</td>
    <td class="vtinneh">'.trunkera($namn, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">ISSN</td>
    <td class="vtinneh">'.trunkera($issn, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">RSS&nbsp;URL</td>
    <td class="vtinneh">'.trunkera($rssurl, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Tidskrift&nbsp;URL</td>
    <td class="vtinneh">'.trunkera($url, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Kommentar</td>
    <td class="vtinneh">'.nl2br(stripslashes($kommentar)).'</td>
    </tr>
    </table>
    </form>
    <p style="font-size:1.2em;"><span
    style="font-weight:bold;color:green;"
    >&radic;</span>&nbsp;Ändringar har sparats!</p>';
    $medd .= skriv_tillbakaknapp('?tab=tidskrifter', 'OK');
    break;
    default:
      $medd = '<h2 class="tabellrubrik">Tidskrifter</h2>';
      $medd .= skriv_tidskriftslista();
    break;
    }
  break;
  default:
  switch($atgard) {
    case "anvtidpren":
    $issn = array();
    foreach($_POST as $variabel => $innehall) {
      if (substr($variabel, 0, 4) == "issn")
        $issn[] = $innehall;
    }
    $tnr = explode('#', $_POST['tnr']);
    foreach($tnr as $nummer) {
      if(in_array($nummer, $issn)) {
        $sql = "SELECT `rss_prenumerationer_id` FROM `rss_prenumerationer`
        WHERE `rss_prenumeranter_ref` = '".$varde."'
        AND `rss_tidskrift_ref` = '".$nummer."'";
        $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
        $rad = mysql_fetch_assoc($resultat);
        $sql2 = "SELECT * FROM `rss_tidskrift` WHERE `rss_tidskrift_id` = '".
          $nummer."'";
        $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");
        $rad2 = mysql_fetch_assoc($resultat2);
        if(mysql_num_rows($resultat) < 1) {
          $andrlogg .= "<li>Lägger till &quot;".$rad2['namn']."&quot;</li>";
          $sql4 = "INSERT INTO `$dbnamn`.`rss_prenumerationer` (
          `rss_prenumerationer_id`, `rss_prenumeranter_ref`, `rss_tidskrift_ref`)
          VALUES (NULL , '".$varde."', '".$nummer."');";
          mysql_query($sql4) or die ("<p>Fel vid databasförfrågan!</p>");  
      }
    } else {
        $sql = "SELECT `rss_prenumerationer_id` FROM `rss_prenumerationer`
        WHERE `rss_prenumeranter_ref` = '".$varde."'
        AND `rss_tidskrift_ref` = '".$nummer."'";
        $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
        $rad = mysql_fetch_assoc($resultat);
        $sql2 = "SELECT * FROM `rss_tidskrift` WHERE `rss_tidskrift_id` = '".
          $nummer."'";
        $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");
        $rad2 = mysql_fetch_assoc($resultat2);
        if(mysql_num_rows($resultat) > 0) {
          $andrlogg .= "<li>Drar ifrån &quot;".$rad2['namn']."&quot;</li>";
          $sql4 = "DELETE FROM `rss_prenumerationer` WHERE
          `rss_prenumerationer`.`rss_prenumerationer_id` = '".
          $rad['rss_prenumerationer_id']."' LIMIT 1";
          mysql_query($sql4) or die ("<p>Fel vid databasförfrågan!</p>");
      }
    }
  }
$sql3 = "SELECT `fornamn`, `efternamn` FROM `rss_prenumeranter`
  WHERE `rss_prenumeranter_id` = '".$varde."'";
$resultat3 = mysql_query($sql3) or die ("<p>Fel vid databasförfrågan!</p>");
$rad3 = mysql_fetch_assoc($resultat3);
$medd = '<h2 class="tabellrubrik">Prenumerationer för '.
$rad3['fornamn'].' '.$rad3['efternamn'].'</h2>';
$medd .= (empty($andrlogg)) ? "<p>Inga ändringar gjordes.</p>" : 
  "<p>Följande ändringar utfördes:</p><ul>".$andrlogg."</ul>";
$medd .= skriv_tillbakaknapp('?tab=anvandare', 'OK');
    break;
    case "skickanu":
    $sql = "SELECT * FROM `rss_prenumeranter` WHERE `rss_prenumeranter_id` = '".
      $varde."'";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $rad = mysql_fetch_assoc($resultat);
    $medd = '<h2 class="tabellrubrik">Utskick</h2>';
    $medd .= "<p>Du kan skapa en tidskriftsbevakning till ".$rad['fornamn'].
      ' '.$rad['efternamn']." som du antingen kan titta på här på skärmen".
      " eller skicka till ".$rad['email'].".</p>";
    $medd .= "\n<ul>\n<li><a href=\"".
    "./cron/skrivemail.php?atgard=skickaenskild&amp;varde=".
    $varde."&amp;utmatning=skarm\">Visa på skärmen utan att skicka</a></li>\n".
    "<li><a href=\"./cron/skrivemail.php?atgard=skickaenskild&amp;varde=".
    $varde."&amp;utmatning=epost\">Skicka till prenumeranten via epost</li></ul>";
    $medd .= skriv_tillbakaknapp('?tab=anvandare');
    break;
    case "andpren":
    $sql = "SELECT * FROM `rss_prenumeranter` WHERE `rss_prenumeranter_id` = '".
      $varde."'";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $rad = mysql_fetch_assoc($resultat);
    $medd = '<h2 class="tabellrubrik">Prenumerationer för '.
      $rad['fornamn'].' '.$rad['efternamn'].'</h2>';
    $medd .= skriv_prenumerationslista($varde);
    $medd .= skriv_tillbakaknapp('?tab=anvandare');
    break;

    case "arbetsplatser":
    $medd = "\n<h2 class=\"tabellrubrik\">Arbetsplatser</h2>";
    $sql = "SELECT * FROM `rss_arbetsplats` WHERE `rss_arbetsplats_id` !=0 ".
      "ORDER BY `namn` ASC";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd .= "\n<table class=\"lista\" cellspacing=\"0\">";
    $medd .= "\n<tr>\n<th class=\"lista\">Arbetsplats</th>\n<th".
    " class=\"lista\">Medlemmar</th>\n</tr>";
    while ($rad = mysql_fetch_assoc($resultat)) {
    $medd .= "\n<tr>\n<td class=\"lista\"><img class=\"bild\" ".
    "src=\"./bilder/arbete.png\" /><a href=\"./index.php?tab=anvandare".
    "&amp;atgard=redigarbpl&amp;varde=".$rad['rss_arbetsplats_id'].
    "\">".$rad['namn']."</a></td>";
    $sql2 = "SELECT * FROM `rss_prenumeranter` WHERE `arbetsplats` = '".
      $rad['rss_arbetsplats_id']."'";
    $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd .= "\n<td class=\"lista\">".mysql_num_rows($resultat2)."</td>\n</tr>";
    }
  $medd .= '<tr>
  <td class="undermeny"><img class="bild"
  src="./bilder/ny.gif" /><a
    href="./index.php?atgard=laggtillarbetsplats&amp;tab=anvandare">Lägg till
    ny arbetsplats</a></td>
  <td class="undermeny">&nbsp;</td>
  </tr>';
    $medd .= "\n</table>";
    $medd .= skriv_tillbakaknapp('?tab=anvandare', 'Gå tillbaka');
    break;

    case "redigarbpl":
    $medd = "\n<h2 class=\"tabellrubrik\">Redigera arbetsplats</h2>";
    $sql = "SELECT * FROM `rss_arbetsplats` WHERE `rss_arbetsplats_id` = '".
      $varde."'";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $rad = mysql_fetch_assoc($resultat);

    $medd .= "\n<form action=\"./index.php\" method=\"post\">\n<table class=\"vt\">".
    "\n<tr>\n<td class=\"vtrubrik\">&nbsp;</td>".
    "\n<td class=\"vtinneh\"><p class=\"hogerjustering\">".
    "<img src=\"./bilder/tabort.gif\" class=\"bild\" />".
    "<a href=\"./index.php?atgard=tabortarbpl".
    "&amp;tab=anvandare&amp;varde=".$varde."\">Ta bort arbetsplats</a></td>\n</tr>".
    "\n<tr>\n<td class=\"vtrubrik\">Arbetsplats</td>".
    "\n<td class=\"vtinneh\"><input type=\"text\" name=\"namn\" value=\"".
    $rad['namn']."\" class=\"redigering\" /></td>\n</tr>".
    "\n<tr>\n<td class=\"vtrubrik\">&nbsp;</td>".
    "\n<td class=\"vtrubrik\"><input type=\"submit\" value=\"Ändra\" /></td>\n</tr>".
    "\n</table>\n".
    "\n<input type=\"hidden\" name=\"varde\" value=\"$varde\" />".
    "\n<input type=\"hidden\" name=\"atgard\" value=\"andraarbpl\" />".
    "\n<input type=\"hidden\" name=\"tab\" value=\"anvandare\" />".
    "\n</form>";
    $medd .= skriv_tillbakaknapp('?tab=anvandare&amp;atgard=arbetsplatser',
      'Gå tillbaka');
    break;

    case "andraarbpl":
    $sql = "UPDATE `$dbnamn`.`rss_arbetsplats` SET `namn` = '".
    $namn."' WHERE `rss_arbetsplats`.`rss_arbetsplats_id` = '".$varde."' LIMIT 1;";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd = "\n<h2 class=\"tabellrubrik\">Redigera arbetsplats</h2>";
    $medd .= "\n<p>Arbetsplatsen har ändrat namn till &quot;$namn&quot;.</p>";
    $medd .= skriv_tillbakaknapp('?tab=anvandare&amp;atgard=arbetsplatser',
      'OK'); 
   break;

    case "tabortarbpl":
    $medd = "\n<h2 class=\"tabellrubrik\">Ta bort arbetsplats</h2>";
    $medd .= "\n<p>Är du säker på att du vill ta bort arbetsplatsen? ".
    "Användarna som tillhörde arbetsplatsen, kommer att få arbetsplatsen".
    " &quot;Ej valt&quot;.</p>";
    $medd .= "\n<p><img src=\"./bilder/tabort.gif\" class=\"bild\" />".
    "<a href=\"index.php?tab=anvandare&amp;atgard=arbetsplatsbo".
      "rttagning&amp;varde=$varde\">Ja, ta bort arbetsplats</a></p>";
    $medd .= skriv_tillbakaknapp('?tab=anvandare&amp;atgard=arbetsplatser',
      'Gå tillbaka');
    break;

    case "arbetsplatsborttagning":
    $sql = "UPDATE `$dbnamn`.`rss_prenumeranter` SET ".
    "`arbetsplats` = '0' WHERE ".
    "`rss_prenumeranter`.`arbetsplats` = '".$varde."';";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $sql = "DELETE FROM `rss_arbetsplats` WHERE ".
    "`rss_arbetsplats`.`rss_arbetsplats_id` = '".$varde."' LIMIT 1";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd = "\n<h2 class=\"tabellrubrik\">Ta bort arbetsplats</h2>";
    $medd .= "\n<p>Arbetsplatsen är borttagen.</p>";
    $medd .= skriv_tillbakaknapp('?tab=anvandare&amp;atgard=arbetsplatser',
      'OK');
    break;

    case "rediganvandare":
    $sql = "SELECT * FROM `rss_prenumeranter` WHERE `rss_prenumeranter_id` = '".
      $varde."'";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $rad = mysql_fetch_assoc($resultat);
    $medd = "\n<h2 class=\"tabellrubrik\">Redigera användare</h2>";
    if ($rad['period'] == "manad")
    $peruts = '<input type="radio" name="period" checked="checked" value="manad">Månad'.
      '&nbsp;&nbsp;<input type="radio" name="period" value="vecka" />Vecka';
    else
    $peruts = '<input type="radio" name="period" value="manad">Månad'.
      '&nbsp;&nbsp;<input type="radio" checked="checked" name="period" value="vecka" />Vecka';
    $arbuts = '<select name="arbetsplats">';
    $sql2 = "SELECT * FROM `rss_arbetsplats` ORDER BY `namn` ASC";
    $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");
    while ($rad2 = mysql_fetch_assoc($resultat2)) {
    if ($rad2['rss_arbetsplats_id'] == $rad['arbetsplats'])
      $arbuts .= "<option selected=\"selected\" ";
      else
      $arbuts .= "<option ";
    $arbuts .= 'value="'.$rad2['rss_arbetsplats_id'].'">'.
      $rad2['namn'].'</option>';
    }
    $arbuts .= '</select>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td>&nbsp;</td>
    <td><p class="hogerjustering"><img src="./bilder/tabort.gif" class="bild" />'.
    '<a href="./index.php?atgard=raderaanv&amp;tab=anvandare&amp;varde='.
    $varde.'">Radera användare</a></p></td>
    </tr>
    <tr>
    <td class="vtrubrik">Föramn</td>
    <td class="vtinneh"><input class="redigering" type="text" name="fornamn" value="'.
      $rad['fornamn'].'" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Efternamn</td>
    <td class="vtinneh"><input class="redigering" type="text" name="efternamn" value="'.
      $rad['efternamn'].'" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Abetsplats</td>
    <td class="vtinneh">'.$arbuts.'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Email</td>
    <td class="vtinneh"><input class="redigering" type="text" name="email" value="'.
      $rad['email'].'" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Period</td>
    <td class="vtinneh">'.$peruts.'&nbsp;<strong>Dag:</strong>
    <input class="redigkort" type="text" name="utsdag" value="'.
      $rad['utsdag'].'" /></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td><input type="submit" value="Ändra" /></td>
    </tr>
    </table>
    <input type="hidden" value="'.$varde.'" name="varde" />
    <input type="hidden" value="postanvandare" name="atgard" />
    <input type="hidden" value="anvandare" name="tab" />
    </form>';
    $medd .= skriv_tillbakaknapp('?tab=anvandare');
    break;
    case "raderaanv":
    $medd = '<h2 class="tabellrubrik">Radera användare</h2>';
    $medd .= '<p>Är du säker på att du vill radera användaren?</p>'.
    '<p>Användaren och alla användarens data kommer att raderas permanent.</p>';
    $medd .= '<p><img src="./bilder/tabort.gif" class="bild" />'.
    '<a href="./index.php?atgard=bkraderaanv&amp;tab=anvandare'.
    '&amp;varde='.$varde.'">Radera användaren</a></p>';
    $medd .= skriv_tillbakaknapp('?tab=anvandare');
    break;
    case "bkraderaanv":
    $medd = '<h2 class="tabellrubrik">Radera användare</h2>';
    $sql = "DELETE FROM `$dbnamn`.`rss_utskick`
    WHERE `rss_prenumeranter_ref` = '".$varde."';";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $sql = "DELETE FROM `$dbnamn`.`rss_prenumerationer`
    WHERE `rss_prenumeranter_ref` = '".$varde."';";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $sql = "DELETE FROM `$dbnamn`.`rss_prenumeranter`
    WHERE `rss_prenumeranter`.`rss_prenumeranter_id` = '".$varde."' LIMIT 1";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd .= '<p>Användaren och all data associerad med användaren har'.
    ' tagits bort.</p>';
    $medd .= skriv_tillbakaknapp('?tab=anvandare', 'OK');
    break;
    case "postanvandare":
    $sql = "UPDATE `$dbnamn`.`rss_prenumeranter`
      SET `fornamn` = '".$fornamn."',
          `efternamn` = '".$efternamn."',
          `arbetsplats` = '".$arbetsplats."',
          `email` = '".$email."',
          `period` = '".$period."',
          `utsdag` = '".$utsdag."'
      WHERE `rss_prenumeranter`.`rss_prenumeranter_id` = '".$varde."' LIMIT 1;";
      mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $sql = "SELECT `namn` FROM `rss_arbetsplats` WHERE `rss_arbetsplats_id` = '".
      $arbetsplats."'";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $arbuts = mysql_fetch_row($resultat);
    $peruts = ($period == 'manad') ? "Månad" : "Vecka";
    $medd = '<h2 class="tabellrubrik">Redigera användare</h2>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td class="vtrubrik">Förnamn</td>
    <td class="vtinneh">'.trunkera($fornamn, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Efternamn</td>
    <td class="vtinneh">'.trunkera($efternamn, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Arbetsplats</td>
    <td class="vtinneh">'.trunkera($arbuts[0], 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Email</td>
    <td class="vtinneh">'.trunkera($email, 60).'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Period</td>
    <td class="vtinneh">'.trunkera($peruts, 60).', dag <strong>'.
      $utsdag.'</strong></td>
    </tr>
    </table>
    </form>
    <p style="font-size:1.2em;"><span
    style="font-weight:bold;color:green;"
    >&radic;</span>&nbsp;Ändringar har sparats!</p>';
    $medd .= skriv_tillbakaknapp('?tab=anvandare', 'OK');
    break;

    case "laggtillanvandare":
    $medd = '<h2 class="tabellrubrik">Lägg till användare</h2>';
    $arbuts = '<select name="arbetsplats">';
    $sql2 = "SELECT * FROM `rss_arbetsplats` ORDER BY `namn` ASC";
    $resultat2 = mysql_query($sql2) or die ("<p>Fel vid databasförfrågan!</p>");
    while ($rad2 = mysql_fetch_assoc($resultat2)) {
    $arbuts .= '<option value="'.$rad2['rss_arbetsplats_id'].'">'.
      $rad2['namn'].'</option>';
    }
    $arbuts .= '</select>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td class="vtrubrik">Föramn</td>
    <td class="vtinneh"><input class="redigering" type="text" name="fornamn" value="" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Efternamn</td>
    <td class="vtinneh"><input class="redigering" type="text" name="efternamn" value="" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Abetsplats</td>
    <td class="vtinneh">'.$arbuts.'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Email</td>
    <td class="vtinneh"><input class="redigering" type="text" name="email" value="" /></td>
    </tr>
    <tr>
    <td class="vtrubrik">Period</td>
    <td class="vtinneh"><input type="radio" checked="checked" name="period" value="manad">Månad'.
      '&nbsp;&nbsp;<input type="radio" name="period"'.
      ' value="vecka" />Vecka&nbsp;<strong>Dag:</strong>
    <input class="redigkort" type="text" name="utsdag" value="1" /></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td><input type="submit" value="Lägg till användare" /></td>
    </tr>
    </table>
    <input type="hidden" value="'.$varde.'" name="varde" />
    <input type="hidden" value="nyanvandare" name="atgard" />
    <input type="hidden" value="anvandare" name="tab" />
    </form>';
    $medd .= skriv_tillbakaknapp('?tab=anvandare');
    break;

    case "laggtillarbetsplats":
    $medd = '<h2 class="tabellrubrik">Lägg till arbetsplats</h2>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td class="vtrubrik">Arbetsplats</td>
    <td class="vtinneh"><input class="redigering" type="text" name="namn" value="" /></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td><input type="submit" value="Lägg till arbetsplats" /></td>
    </tr>
    </table>
    <input type="hidden" value="'.$varde.'" name="varde" />
    <input type="hidden" value="nyarbetsplats" name="atgard" />
    <input type="hidden" value="anvandare" name="tab" />
    </form>';
    $medd .= skriv_tillbakaknapp('?tab=anvandare&amp;atgard=arbetsplatser');
    break;

    case "nyarbetsplats":
    $medd = '<h2 class="tabellrubrik">Lägg till arbetsplats</h2>';
    $sql = "INSERT INTO `$dbnamn`.`rss_arbetsplats` (".
    "`rss_arbetsplats_id`, `namn`) VALUES (NULL , '".$namn."');";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $medd .= "\n<p>Nu har &quot;$namn&quot; lagts till i listan över".
    " arbetsplatser.</p>";
    $medd .= skriv_tillbakaknapp('?tab=anvandare&amp;atgard=arbetsplatser', 
      'OK');
    break;

    case "nyanvandare":
    $sql = "INSERT INTO `$dbnamn`.`rss_prenumeranter` (
    `rss_prenumeranter_id`, `fornamn`, `efternamn`, `arbetsplats`,
    `email`, `period`, `utsdag`)
    VALUES (NULL , '".$fornamn."', '".$efternamn."', '".$arbetsplats.
    "', '".$email."', '".$period."', '".$utsdag."');";
    mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $sql = "SELECT `namn` FROM `rss_arbetsplats` WHERE `rss_arbetsplats_id` = '".
      $arbetsplats."'";
    $resultat = mysql_query($sql) or die ("<p>Fel vid databasförfrågan!</p>");
    $arbuts = mysql_fetch_row($resultat);
    $peruts = ($period == 'manad') ? "Månad" : "Vecka";
    $medd = '<h2 class="tabellrubrik">Lägg till användare</h2>';
    $medd .= '<form action="./index.php" method="post">
    <table class="vt">
    <tr>
    <td class="vtrubrik">Föramn</td>
    <td class="vtinneh">'.$fornamn.'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Efternamn</td>
    <td class="vtinneh">'.$efternamn.'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Abetsplats</td>
    <td class="vtinneh">'.$arbuts[0].'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Email</td>
    <td class="vtinneh">'.$email.'</td>
    </tr>
    <tr>
    <td class="vtrubrik">Period</td>
    <td class="vtinneh">'.$peruts.', dag <strong>'.$utsdag.'</strong></td>
    </tr>
    </table>
    </form>
    <p style="font-size:1.2em;"><span
    style="font-weight:bold;color:green;"
    >&radic;</span>&nbsp;Ny användare har lagts till!</p>';
    $medd .= skriv_tillbakaknapp('?tab=anvandare', 'OK');
    break;
    default:
    $medd = '<h2 class="tabellrubrik">Användare</h2>';
  $medd .= skriv_anvandarlista();
    break;
  }
  break;
}
skriv_sida($medd, $tab);
?>
