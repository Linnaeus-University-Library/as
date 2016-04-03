
ARTIKELSKÖRDAREN
================
Artikelskördaren är ett system för bevakning av tidskriftsartiklar.
Systemet består av tre delar:

  # Administration av tidskrifter och prenumeranter
  # Program som samlar in artiklar via RSS-strömmar
  # Program som skickar ut artiklar till prenumeranter

Programmet är utvecklat av bibliotekarier vid Landstinget Dalarna
Bibliotek och informationscentral i Falun som ett svar på verksamhetens
behov av bevakning av tidskriftsartiklar. Detta blev särskilt aktuellt
då landstinget beslutade säga upp tryckta prenumerationer.

Artikelskördaren är utvecklad av Tony Mattsson <tony.mattsson@ltdalarna.se>
och Mikael Mikaelsson <mikael.mikaelsson@ltdalarna.se>.


Systemkrav
----------
Artikelskördaren är skriven i PHP och använder MySQL som databas.
Systemet är testat, och fungerar med PHP 5, MySQL 5 under Linux.
Administrationsgränssnittet är testat att fungera med Mozilla Firefox 3+,
Internet explorer 7+ och Google Chrome.


Installation
------------
Att installera Artikelskördaren är relativt enkelt. Dock krävs det lite
grundläggande kunskaper i Linux.

  # Skapa en databas och en databasanvändare. Det kan du lämpligtvis
  göra med ett webbaserat verktyg hos ditt webbhotell.
  # Ladda upp as-version.tar.gz till din webbplats och packa upp arkivet
  # Redigera filen 'installningar.php' som finns i mappen 'config' och
  fyll i åtminstone:
    * administratörens lösenord i variabeln '$AdminLosenord'. Du ändrar
    alltså det som finns inom citationstecknen, så om du vill ha
    lösenordet 'superhemligt' skall raden se ut här,
    $AdminLosenord = "superhemligt";
    * Fyll i uppgifter för databasen, såsom lösenord, databasens
    namn och användarnamn
    Fyll i uppgifter för emailutskick. Du bör åtminstone fylla i
    ansvarig, den ansvariges email. Frånadressen kan vara något
    problematisk att veta ($emailfran), och kan ställa till problem
    om organisationens epostserver sorterar bort epost med en
    alternativ returadress. Fråga webbhotellet fråm vilken adress
    emailen skickas och fyll i. Oftast är det användarnamn@server.com.
  # Programmen 'refinsamlare.php' och 'skrivemail.php' skall köras
  om cronjobs. Det vill säga att systemet kör dem automatiskt, lämpligen
  en gång per dag, på natten. 'refinsamlare.php' skall köras först
  för att samla in referenser, och 'skrivemail.php' skall köras några
  timmar senare för att skicka ut email till lämpliga personer.

Oftast finns grafiska gränssnitt installerade hos webhotellet för att
konfigurera crontab. Exempelvis kan crontab se ut så här för dessa
program:

 0 0 * * * /usr/bin/php -q /home/sida/public_html/as/chron/refinsamlare.php
 0 0 * * * /usr/bin/php -q /home/sida/public_html/as/chron/skrivemail.php

Om organisationen har en SFX-server kan ni ändra '$AktiveraSFX' till
TRUE ($AktiveraSFX = TRUE;) och fylla i adressen till er SFX-server
i variabeln '$OURL_resolver'.


Licens
------
Artikelskördaren är licensierad under GPL v.3. Licensen finns inkluderad
i detta programpaket i filen Licensavtal.txt.





