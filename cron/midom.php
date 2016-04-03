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
*  Utvecklad av Mikael Mikaelsson <mikael.mikaelsson@ltdalarna.se>             *
*                                                                              *
*  Syfte: Underlätta läsning av xml-dokument i artikelskördaren                *
*                                                                              *
\******************************************************************************/

/* filnamn: midom.php
    Utvecklad av Mikael Mikaelsson, Falu lasaretts bibliotek och informationscentral
    Primärt syfte: Lära mig begripa och använda klassen DOMDocument samt göra den bekvämare att använda för mina syften

En liten utbyggnad av DOMDocument samt supportfunktioner för DOMNode
Namnkonventioner: jag använder kid och kids istället för child och children för att namnge barn i trädet, varför? 
kid och kids är kortare. 
Försöker genomgående använda prefixet mi_ på alla globala identifierare för att slippa namnkollisioner
*/

class midom extends  DOMDocument
{
	public function mi_addroot($rootname)
	{
		// skapar en rotnod endast om den saknas
		$theroot = $this->mi_root();
		if (!$theroot) 
		{
			$theroot = $this->mi_nodnew($this,$rootname);
		}
	}
	public function mi_cdatanew($par,$contents = "")
	{
		// skapar ny cdata-nod under noden $par, ev med innehåll enl $contents
		$result = $this->createCDATASection($contents);
		$par->appendChild($result);
		return $result;
	}
	public function mi_findnode($nodename,$occurr = 0)
	{
		$result = false;
		$occurred = -1;
		$nlst = $this->getElementsByTagName($nodename);
		$antal = $nlst->length;
		for ($i = 0; (($i < $antal) && ($occurred < $occurr)); $i++)
		{
			$occurred++;
			if ($occurred == $occurr)
			{
				$result = $nlst->item($i);
			}
		}
		return $result;
	}
	public function mi_findparentof($nodename,$occurr = 0)
	{
		$result = false;
		$noden = $this->mi_findnode($nodename,$occurr);
		if ($noden)
		{
			$result = $noden->parentNode;
		}
		return $result;
	}
	public function mi_nodnew($par,$nodename,$contents = "")
	{
		// Skapar en ny nod under noden $par med nodnamnet $nodename och innehållet $contents
		$result =  $this->createElement($nodename);
		$par->appendChild($result);
		if (strlen($contents) > 0)
		{
			$tx = $this->createTextNode($contents);
			$result->appendChild($tx);
		}
		return $result;		
	}	
	public function mi_root()

	{
		// egentligen inte nödvändig, men gör det lättare för klienten att begripa i synnerhet för den som 
		// arbetat med NativeXml tidigare
		$result = false;
		if ($this->hasChildNodes()) 
		{
			// första barnnoden behöver inte vara roten. Det kan vara någon obskyr deklaraltione eller kommentar eller annat elände
			// alltså säger vi att första noden av typen XML_ELEMENT_NODE är roten
			$found = false;
			$nl = $this->childNodes;
			$antal = $nl->length;
			for ($i = 0; (($i < $antal) && (! $found)) ; $i++)
			{
				$nod = $nl->item($i);
				if ($nod->nodeType == XML_ELEMENT_NODE)
				{
					$found = true;
					$result = $nod;
				}
			}
				
		}
			
		return $result;
	}

} // eof class midom

/* *******************************************************************************************************

Stödfunktioner för hantering av objekt skapade med midom. Det mesta här nedan borde egentligen ligga som utbyggnader på en nedärvd klass av DOMNode
men eftersom DOMDocument ärver från DOMNode, blir det alltför komplext att extendera DOMNode med en egen klass

******************************************************************************************************** */
// mi_kidvalue($parent,$kids_node_name) returnerar ev värde i textnod eller första cdatanod under $par

function mi_findchild($par,$nodename,$occurr = 0)
{
	// returnerar $occurr förekomst av nod med $nodename eller false om inget hittas
	$result = false;
	//$kids = $par->getElementsByTagName($nodename);
	// getElementsByTagName inte lämplig här eftersom den söker rekursivt på djupet. Vi vill begränsa till de omedelbara barnen här
	$kids = $par->childNodes;
	$len = $kids->length;
	$occurred = -1;
	$found = false;
	for ($i = 0;(($i < $len) && (!$found));$i++)
	{
		$kid = $kids->item($i);
		if ($kid->nodeName == $nodename)
		{
			$occurred++;
			if ($occurred == $occurr)
			{
				$found = true;
				$result = $kid;
			}
		}
	}
	return $result;
}
function mi_countchild($par,$nodename)
{
	$result = 0;
	$kids = $par->childNodes;
	$len = $kids->length;
	for ($i = 0; $i < $len; $i++)
	{
		$kid = $kids->item($i);
		if ($kid->nodeName == $nodename) $result++;
	}
	return $result;
}

function mi_kidvalue($par,$nodename)
{
	$result = "";
	$kid = mi_findchild($par,$nodename);
	if ($kid)
	{
		if ($kid->hasChildNodes())
		{
			$grandkid = $kid->firstChild;
			$typ = $grandkid->nodeType;
			// Håller isär textnod och cdatanod fast det kanske är onödigt, men textnod-innehåll konverteras automatiskt men
			// hur det är med cdata är jag osäker på
			if ($typ == XML_TEXT_NODE) $result = $grandkid->textContent;
			else if ($typ == XML_CDATA_SECTION_NODE) $result = $grandkid->textContent;
		}
	}
	return $result;
}

function mi_nodval($nod)
{
	// en bekvämlighetsfunktion. Borde egentligen ligga som en egenskap el metod unde en arvd DOMNode klass men det blir för komplicerat efter som DOMDocument ärver från DOMNode
	// Denna funktion returnerar textvärde, dvs den innerText som ev finns i en normal nod, dvs den plockar fram firstchild och ser om 
    // det är en textnode och isåfall returneras dess värde
    // Om noden är en cdatasektion returnerar den nodens textvärde
	$result = "";
	if (($nod->nodeType == XML_ELEMENT_NODE) && ($nod->hasChildNodes()))
	{
		$fc = $nod->firstChild;
		if ($fc->nodeType == XML_TEXT_NODE) $result = $fc->textContent;
	}
	else if ($nod->nodeType = XML_CDATA_SECTION_NODE)
	{
		$result = $nod->textContent;
	}	
	return $result;
}
function mi_kidbytag_find($nod,$tagname,$fulltagname = true,$occurr = 0)
{
	// returnerar $occurr av barnnoden tagname eller false om ingen hittas
	// om fulltagname sätts till false ignoreras ev namnprefix på tagg
	$result = false;
	$occurred = -1;
	if ($nod->hasChildNodes())
	{
		$kids = $nod->childNodes;
		$antal = $kids->length;
		for ($i = 0; (($i < $antal) && ($occurred < $occurr)); $i++)
		{
			$candidate = $kids->item($i);
			$name2check = $fulltagname ? $candidate->nodName : $candidate->localName;
			if ($name2check == $tagname)
			{
				$occurred++;
				if ($occurred = $occurr) $result = $candidate;
			}
		}
	}
	return $result;
}
function mi_kids_all_bytag($par,$tagname,$fulltagname = true)
{	
	$result = array();
	
	if ($par->hasChildNodes())
	{
		$nl = $par->childNodes;
		$top = $nl->length;
		for ($i = 0; $i < $top; $i++)
		{
			$candidate = $nl->item($i);
			if ($fulltagname) $name2check = $candidate->nodeName;
			else $name2check = $candidate->localName;
			if ($name2check == $tagname) $result[] = $candidate;
		}
	}
	
	return $result;
}
function mi_traversera($node,$callbackfunction,$secondparam = "")
// Hur anropas denna funk? 
// - $node = det element som traverseringen skall starta i. Om man vill traversera ett helt
// xml-dokument, startar man alltså i rot-noden
// - $callbackfunction = namn på en funktion som man vill skall anropas för varje funnen nod.
// funktionen skall konstrueras så att den tar emot följande parametrar: $nod (vilken alltså är av typen DOMNode), $secondparam (vilken alltså är
// en valfri extra parameter. Om den utelämnas skickas en tom sträng.

// osäker på om denna blir till någon nytta. Dock användbart för mig för att komma ihåg den trixiga gången för 
// en traversering av trädet
{
	call_user_func($callbackfunction,$node,$secondparam);
	if ($node->hasChildNodes())
	{
		$kids = $node->childNodes;
		$antal = $kids->length;
		for ($i = 0;$i < $antal;$i++)
		{
			$kid = $kids->item($i);
			mi_traversera($kid,$callbackfunction,$secondparam);
		}
	}
}
/* hm... helt onödig. DOMElement.getAttribute och DOMElement.setAttribute är tillräckligt smidiga i sig
 om attributet saknas, returneras tom sträng vid getAttribute och om det saknas vid setAttribute skapas det.
function mi_getattr($node,$attribname)
{
	$result = "";
	if ($node->hasAttribute($attribname))
	{
		$result = $node->get_attribute($attribname);
	}
	return $result;
}
*/
?>
<?php
/* Här lägger vi testprogram för test av klasser och funktioner ovan. Skall vara
bortkommenterat utom under utvecklingsfasen av klassen. Exemplet kan tjäna som
anropsvägledning*/

/*
$xml = new midom();
$xml->formatOutput = true;
$xml->mi_addroot("rotaryta");
$r = $xml->mi_root();
$n = $xml->mi_nodnew($r,"grytor");
for ($i = 1; $i < 11; $i++)
{
	$nc = $xml->mi_nodnew($n,"gryta","Detta är gryta $i");
}
$cdkeeper = $xml->mi_nodnew($r,"cdkeeper");
$cdat = $xml->mi_cdatanew($cdkeeper,"Litta cdata\noch en ny rad!");
$cdat = $xml->mi_cdatanew($cdkeeper,"Mera data\nMycket mera\n<hej>");
$cdat = $xml->mi_cdatanew($cdkeeper,rawurlencode("En gång jag seglar i hamn\nÄ du me på den\n"));
$xmltext = $xml->saveXML();
//echo $xmltest;
//echo "Heja Klasse!";
//echo "Heja en gång till!";
echo $xmltext;
*/

?>
