<?php
/******************************************************************************\
*                                                                              *
*  Detta program �r en del av Artikelsk�rdaren                                 *
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
* along with Artikelsk�rdaren.  If not, see <http://www.gnu.org/licenses/>.    *
*                                                                              *
*                                                                              * 
*  Utvecklad av Mikael Mikaelsson <mikael.mikaelsson@ltdalarna.se>             *
*                                                                              *
*  Syfte: Underl�tta l�sning av xml-dokument i artikelsk�rdaren                *
*                                                                              *
\******************************************************************************/

/* filnamn: midom.php
    Utvecklad av Mikael Mikaelsson, Falu lasaretts bibliotek och informationscentral
    Prim�rt syfte: L�ra mig begripa och anv�nda klassen DOMDocument samt g�ra den bekv�mare att anv�nda f�r mina syften

En liten utbyggnad av DOMDocument samt supportfunktioner f�r DOMNode
Namnkonventioner: jag anv�nder kid och kids ist�llet f�r child och children f�r att namnge barn i tr�det, varf�r? 
kid och kids �r kortare. 
F�rs�ker genomg�ende anv�nda prefixet mi_ p� alla globala identifierare f�r att slippa namnkollisioner
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
		// skapar ny cdata-nod under noden $par, ev med inneh�ll enl $contents
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
		// Skapar en ny nod under noden $par med nodnamnet $nodename och inneh�llet $contents
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
		// egentligen inte n�dv�ndig, men g�r det l�ttare f�r klienten att begripa i synnerhet f�r den som 
		// arbetat med NativeXml tidigare
		$result = false;
		if ($this->hasChildNodes()) 
		{
			// f�rsta barnnoden beh�ver inte vara roten. Det kan vara n�gon obskyr deklaraltione eller kommentar eller annat el�nde
			// allts� s�ger vi att f�rsta noden av typen XML_ELEMENT_NODE �r roten
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

St�dfunktioner f�r hantering av objekt skapade med midom. Det mesta h�r nedan borde egentligen ligga som utbyggnader p� en ned�rvd klass av DOMNode
men eftersom DOMDocument �rver fr�n DOMNode, blir det alltf�r komplext att extendera DOMNode med en egen klass

******************************************************************************************************** */
// mi_kidvalue($parent,$kids_node_name) returnerar ev v�rde i textnod eller f�rsta cdatanod under $par

function mi_findchild($par,$nodename,$occurr = 0)
{
	// returnerar $occurr f�rekomst av nod med $nodename eller false om inget hittas
	$result = false;
	//$kids = $par->getElementsByTagName($nodename);
	// getElementsByTagName inte l�mplig h�r eftersom den s�ker rekursivt p� djupet. Vi vill begr�nsa till de omedelbara barnen h�r
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
			// H�ller is�r textnod och cdatanod fast det kanske �r on�digt, men textnod-inneh�ll konverteras automatiskt men
			// hur det �r med cdata �r jag os�ker p�
			if ($typ == XML_TEXT_NODE) $result = $grandkid->textContent;
			else if ($typ == XML_CDATA_SECTION_NODE) $result = $grandkid->textContent;
		}
	}
	return $result;
}

function mi_nodval($nod)
{
	// en bekv�mlighetsfunktion. Borde egentligen ligga som en egenskap el metod unde en arvd DOMNode klass men det blir f�r komplicerat efter som DOMDocument �rver fr�n DOMNode
	// Denna funktion returnerar textv�rde, dvs den innerText som ev finns i en normal nod, dvs den plockar fram firstchild och ser om 
    // det �r en textnode och is�fall returneras dess v�rde
    // Om noden �r en cdatasektion returnerar den nodens textv�rde
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
	// om fulltagname s�tts till false ignoreras ev namnprefix p� tagg
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
// xml-dokument, startar man allts� i rot-noden
// - $callbackfunction = namn p� en funktion som man vill skall anropas f�r varje funnen nod.
// funktionen skall konstrueras s� att den tar emot f�ljande parametrar: $nod (vilken allts� �r av typen DOMNode), $secondparam (vilken allts� �r
// en valfri extra parameter. Om den utel�mnas skickas en tom str�ng.

// os�ker p� om denna blir till n�gon nytta. Dock anv�ndbart f�r mig f�r att komma ih�g den trixiga g�ngen f�r 
// en traversering av tr�det
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
/* hm... helt on�dig. DOMElement.getAttribute och DOMElement.setAttribute �r tillr�ckligt smidiga i sig
 om attributet saknas, returneras tom str�ng vid getAttribute och om det saknas vid setAttribute skapas det.
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
/* H�r l�gger vi testprogram f�r test av klasser och funktioner ovan. Skall vara
bortkommenterat utom under utvecklingsfasen av klassen. Exemplet kan tj�na som
anropsv�gledning*/

/*
$xml = new midom();
$xml->formatOutput = true;
$xml->mi_addroot("rotaryta");
$r = $xml->mi_root();
$n = $xml->mi_nodnew($r,"grytor");
for ($i = 1; $i < 11; $i++)
{
	$nc = $xml->mi_nodnew($n,"gryta","Detta �r gryta $i");
}
$cdkeeper = $xml->mi_nodnew($r,"cdkeeper");
$cdat = $xml->mi_cdatanew($cdkeeper,"Litta cdata\noch en ny rad!");
$cdat = $xml->mi_cdatanew($cdkeeper,"Mera data\nMycket mera\n<hej>");
$cdat = $xml->mi_cdatanew($cdkeeper,rawurlencode("En g�ng jag seglar i hamn\n� du me p� den\n"));
$xmltext = $xml->saveXML();
//echo $xmltest;
//echo "Heja Klasse!";
//echo "Heja en g�ng till!";
echo $xmltext;
*/

?>
