<?php
/****************************************************************************\
*                                                                            *
*  Detta program är en del av Artikelskördaren                               *
*                                                                            *
* This program is free software: you can redistribute it and/or modify       *
* it under the terms of the GNU General Public License as published by       *
* the Free Software Foundation, either version 3 of the License, or          *
* (at your option) any later version.                                        *
*                                                                            *
* This program is distributed in the hope that it will be useful,            *
* but WITHOUT ANY WARRANTY; without even the implied warranty of             *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the              *
* GNU General Public License for more details.                               *
*                                                                            *
* You should have received a copy of the GNU General Public License          *
* along with Artikelskördaren.  If not, see <http://www.gnu.org/licenses/>.  *
*                                                                            *
*                                                                            * 
*  Utvecklad av Tony Mattsson <tony.mattsson@ltdalarna.se>                   *
*                                                                            *
\****************************************************************************/

// ## ADMINISTRATÖR
// #################
// Administratörens användarnamn
$AdminAnvandarnamn = "admin";
// Lösenord. Ändra till något mycket svårgissat, helst slumpat
$AdminLosenord = "";

// ## DATABAS
// ###########
$dbnamn = ""; // Namn på databasen. Behöver ej ändras.
$dbhost = "localhost"; // Host. Troligvis localhost
$dbanv = ""; // Databasanvändaren
$dblosen = ""; // Lösenordet till databasen

// ## SÄKERHET
// ############
// IP-Begränsning: om du vill begränsa åtkomsten till applikatione
// till exempelvis ett landsting
$BegransaAnroparIP = FALSE; // TRUE / FALSE
$IPStart = ""; // Start på IP-Range
$IPSlut = ""; // Slut på IP-Range
$IPFelMeddelande = "Denna applikation har begränsad åtkomst.";

// ## EMAILUTSKICK
// ################
// Ansvarig för Artikelskördaren
$ansvarig = "";
// Den ansvariges email-adress
$ansvemail = "";
// Meddelande från biblioteket
$biblmedd = "\n<p>Automatiskt utskick från bibliotekets tjänst för ".
  "tidskriftsbevakning &mdash; &quot;Artikelskördaren&quot;.</p>";
// Epostadress från vilken utskicket skickas. Observera att många
// spamfilter sorterar bort epost som har en annan retur-adress
// än från-adress
$emailfran = "";
// Ämne på utskicket
$utskickamne = "Tidskriftsbevakning från biblioteket";

// ## REFERENSINSAMLING
// #####################
// Aktivera SFX-länk
$AktiveraSFX = FALSE; // TRUE / FALSE
// Adress till SFX-servern
$OURL_resolver = "";

// ## BUGG-INFORMATION
// ####################
// Logga alla dubletter, för att kontrollera buggar
$dublettlogg = FALSE; // TRUE / FALSE
// Spara RSS-filerna i foldern 'cache'
$RSSlogg = FALSE; // TRUE / FALSE

// ## Övrigt
// ##########
$version = "1.00 rc1";
?>
