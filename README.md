# Eigen-Wijzer CMS

Eenvoudig CMS (PHP + MySQLi + vanilla JS/HTML, geen frameworks of Composer)
om de website eigen-wijzer.be te beheren: pagina's opgebouwd uit eenvoudige
content-blokken (tekst, foto, quote, lijst, knoppen, kalender, evenementen —
met eigen afbeeldingsupload, een zelfbedieningsafsprakenkalender en
evenementen met capaciteit-beperkte inschrijving), 3 kiesbare
frontend-varianten op basis van Wendy's ontwerp met rustige
scroll/klik-animatie, een contactformulier waarvan de inzendingen in het
beheerpaneel terechtkomen, en ingebouwde SEO-optimalisatie inclusief
vindbaarheid voor AI-zoekfuncties (zie "SEO & vindbaarheid" hieronder).

## Projectstructuur

```
config/                     configuratie (config.php bevat echte databasegegevens — niet in git)
database/schema.sql          database-structuur, eenmalig importeren (nieuwe installatie)
database/migrations/         wijzigingen op een bestaande database (zie hieronder)
includes/                    gedeelde PHP-code (db-connectie, helpers, auth, blok-rendering, publieke layout)
index.php                   homepagina
pagina.php                  toont een individuele pagina op basis van ?slug=
contact.php                 contactformulier
admin/                      beheerpaneel (login, pagina's + blokkenbouwer, contactberichten)
```

De website draait volledig vanuit de projectroot — die stel je in als
document root van je hosting. `config/`, `database/` en `includes/` staan
dus **naast** de publieke bestanden in plaats van er fysiek buiten; ze
worden expliciet afgeschermd via een `.htaccess`-bestand in elk van die
mappen (`Require all denied`), zodat ze nooit rechtstreeks via de browser
opvraagbaar zijn. Zie "Beveiliging" hieronder.

## Lokaal opzetten (testen)

1. Zorg voor PHP 8.1+ met de mysqli-extensie, en een MySQL/MariaDB-server.
2. Maak een database aan en importeer `database/schema.sql` (nieuwe
   installatie). Draai je al een oudere versie van deze database, importeer
   dan in plaats daarvan de ontbrekende bestanden uit `database/migrations/`
   op volgnummer — `002_blocks_and_theme.sql` (lees de opmerking bovenaan,
   want bestaande paginainhoud wordt daarbij geleegd), `003_menu_visibility.sql`,
   `004_calendar_slots.sql`, `005_events.sql`, `006_site_settings.sql`,
   `007_contact_content.sql`, `008_analytics_cache.sql`,
   `009_contact_seo_fields.sql` en `010_ai_summary.sql`.
3. `cp config/config.example.php config/config.php` en vul je lokale
   databasegegevens in.
4. Start de ingebouwde PHP-server vanaf de projectroot, met `router.php`
   zodat `config/`, `database/` en `includes/` ook lokaal afgeschermd zijn,
   én zodat de nette URL's (`/contact`, `/pagina/...`, ...) lokaal hetzelfde
   werken als op je uiteindelijke hosting (PHP's ingebouwde server negeert
   `.htaccess`, in tegenstelling tot Apache):
   ```
   php -S localhost:8000 router.php
   ```
5. Open `http://localhost:8000/admin/` — omdat er nog geen beheerder bestaat,
   kom je automatisch op de installatiepagina terecht om het eerste account
   aan te maken.
6. (Optioneel, eenmalig) `php database/seed-homepage.php` maakt de homepage
   aan met Wendy's echte tekst en de echte praktijkfoto — enkel als er nog
   geen homepagina bestaat. Zie "Homepage-content" hieronder voor wat daarna
   nog manueel moet.

## Deployen op gedeelde hosting (cPanel-achtig)

1. Maak in je hostingpaneel een MySQL-database en -gebruiker aan, en
   importeer `database/schema.sql` via phpMyAdmin.
2. Upload de volledige projectmap rechtstreeks naar `public_html` (de
   website draait vanuit de projectroot, dus die moet je document root zijn
   — er is geen aparte `public/`-submap meer).
3. Controleer dat `config/.htaccess`, `database/.htaccess` en
   `includes/.htaccess` mee geüpload zijn en dat je hosting `.htaccess`
   effectief toepast (Apache met `mod_authz_core`, de standaard op zo goed
   als elke gedeelde hosting). Dit is wat `config/`, `database/` en
   `includes/` afschermt van de browser nu ze naast de publieke bestanden
   staan — test dit na deploy door bv. `jouwdomein.be/config/config.php`
   te bezoeken: dat moet een 403 Forbidden geven, nooit de bestandsinhoud.
4. Maak `config/config.php` aan op basis van `config/config.example.php` met
   de echte databasegegevens van je hosting.
5. Bezoek `jouwdomein.be/admin/` om het eerste beheerdersaccount aan te
   maken via de installatiepagina.
6. Zorg dat de website via HTTPS draait (meestal gratis Let's Encrypt via
   het hostingpaneel) — logingegevens mogen nooit over onversleuteld http.
7. **Test de nette URL's** (`jouwdomein.be/pagina/een-slug`, niet enkel de
   homepage): als die altijd op de homepage uitkomen in plaats van de juiste
   pagina, staat `MultiViews` vermoedelijk aan op je hosting (vaak de
   standaard). `.htaccess` zet dit zelf al uit (`Options -Indexes
   -MultiViews`), maar als je hosting die regel niet toepast (bv. via een
   losse `Options`-instelling in het hostingpaneel die voorrang krijgt), zet
   `MultiViews` dan handmatig uit via het hostingpaneel of vraag het na bij
   support.

### Nadien: code-updates via GitHub Actions (optioneel)

Na deze eerste, handmatige opzet kan je latere code-wijzigingen automatisch
laten uploaden via FTP, zonder zelf een FTP-client te gebruiken
(`.github/workflows/deploy.yml`):

1. Zet in de repository-instellingen op GitHub (Settings → Secrets and
   variables → Actions) twee secrets: `FTP_USERNAME` en `FTP_PASSWORD`, met
   de inloggegevens van `ftp.dcube-resource.be`.
2. Ga naar het tabblad "Actions" → "Deploy naar FTP (dcube-resource)" →
   "Run workflow" om een deploy te starten. Dit gebeurt **nooit
   automatisch** bij een merge — bewust een apart, manueel moment, zodat er
   altijd een controlemoment is vlak voor iets live gaat.
3. Enkel de werkende site-bestanden worden geüpload (niet `database/`,
   `README.md` of de workflow zelf) — `config/config.php` en `uploads/`
   staan niet in git en worden dus nooit aangeraakt of overschreven.
   Database-migraties (`database/migrations/`) worden hierdoor **niet**
   automatisch uitgevoerd; die blijf je zelf handmatig via phpMyAdmin
   draaien, zoals voorheen.

## Functionaliteit (MVP)

- Login voor beheerders (wachtwoorden gehasht met `password_hash`,
  sessie-gebaseerd, met een eenvoudige brute-force-vertraging na 5 mislukte
  pogingen).
- Pagina's aanmaken/bewerken/verwijderen, publiceren/concept, een
  instelbare homepagina en een handmatige menuvolgorde. Automatische,
  unieke URL-slugs afgeleid van de titel (aanpasbaar).
- **Aanpasbaar hoofdmenu**: elke pagina heeft een schakelaar "Tonen in
  hoofdmenu". Staat die uit, dan blijft de pagina gewoon gepubliceerd en
  bereikbaar via haar eigen URL of een knop/link elders op de site — ze
  krijgt alleen geen plaats in de navigatie. Zo kan je bv. een
  privacybeleid of een campagnepagina maken die niet in het menu hoeft te
  staan. De volgorde in het menu stel je in door pagina's te verslepen in
  het overzicht bij "Pagina's" — de nieuwe volgorde wordt meteen
  opgeslagen, geen aparte opslaanknop nodig.
- **Blokkenbouwer**: elke pagina bestaat uit een lijst eenvoudige blokken
  die je toevoegt, herschikt (verslepen aan het handvat ⠿, of ↑/↓ als
  toetsenbord-/geen-JS-alternatief) en verwijdert in het beheerpaneel —
  geen vrije HTML-editor meer, dus geen manier om per ongeluk kapotte
  opmaak of scripts in te voegen:
  - **Tekst** — optionele eyebrow (klein label boven de titel, bv.
    "Aanbod"), optionele titel, en platte tekst (alinea's gescheiden door
    een lege regel) — titel en tekst mogen niet allebei leeg zijn, maar één
    van de twee volstaat (handig voor een kale sectiekop boven bv. een
    lijstblok). Eenvoudige opmaak mogelijk — zie "Opmaak in tekst en
    lijsten" hieronder.
  - **Foto** — kies "Bestand kiezen…" om een JPG/PNG/GIF/WEBP te uploaden
    (max. 5 MB, direct herbekeken als miniatuur), of vul zelf een
    afbeeldings-URL in. Plus alt-tekst (verplicht, toegankelijkheid) en
    optioneel bijschrift. JPG/PNG/WEBP worden bij upload automatisch
    verkleind tot max. 1600px op de langste zijde en herschaald voor een
    kleinere bestandsgrootte (EXIF-rotatie van telefoonfoto's wordt daarbij
    gerespecteerd) — GIF blijft ongemoeid, om een eventuele animatie niet
    te breken.
  - **Quote** — citaat + optionele bron.
  - **Lijst** — titel, stijl (opsomming/vinkjes) en items (één per regel,
    met dezelfde eenvoudige opmaak als een tekstblok).
  - **Knoppen** — tot 3 knoppen, één per regel als `Tekst | link`, met
    uitlijning links/gecentreerd/rechts.
  - **Kalender** — een zelfbedieningsafsprakenkalender (zie "Kalenderblok"
    hieronder) met een optionele titel; de beschikbare tijdsloten zelf
    beheer je los via "Kalender" in het beheerpaneel, niet per blok.
  - **Evenementen** — toont automatisch de eerstkomende, gepubliceerde
    evenementen met inschrijfformulier (zie "Evenementenblok" hieronder);
    de evenementen zelf beheer je los via "Evenementen" in het
    beheerpaneel, niet per blok.
  - **Kaart** — een gratis Google Maps-kaart op basis van een adres (geen
    API-key nodig), met een titel en een weergave-optie: binnen de
    tekstkolom ("box") of over de volle paginabreedte ("stretch").
  - **Foto + tekst** — een foto naast een stuk tekst, met de foto links of
    rechts instelbaar; stapelt op mobiel (foto boven tekst).
  - **Kolommen** — 2 of 3 kolommen naast elkaar, elk met een eigen mini-lijst
    van blokken (tekst, foto, quote, lijst, knoppen — geen kalender,
    evenementen, kaart of geneste kolommen, om het behapbaar te houden).
    Elke kolom heeft zijn eigen toolbar om blokken toe te voegen; stapelt
    verticaal op mobiel. Vanaf 640px lijnen titels, tekst en knoppen
    automatisch rij voor rij uit over de kolommen heen (CSS subgrid) — een
    langere titel in één kolom duwt de tekst daaronder in **alle** kolommen
    netjes mee naar dezelfde hoogte, zonder dat je iets hoeft in te stellen.
  - **Achtergrond** — elk blok (en, binnen een kolommenblok, elke kolom
    afzonderlijk) kan een "Accentkleur"- of "Zachte kaart"-achtergrond
    krijgen in plaats van de standaard, transparante achtergrond. Beide
    gebruiken de kleurtokens van de actieve frontend-variant, dus ze passen
    automatisch mee met A/B/C.
  - Links/afbeeldings-URL's worden serverside gevalideerd (enkel `/...`,
    `http(s)://`, `mailto:` of `tel:` — geen `javascript:`-injectie
    mogelijk).
- **Opmaak in tekst en lijsten**: de tekst van een tekstblok, foto+tekst-blok
  en de items van een lijstblok ondersteunen een kleine, veilige opmaaksyntax
  — `**vet**`, `*cursief*`, `[linktekst](url)` — via knoppen boven het
  veld of door de syntax gewoon zelf te typen. In een tekst- of
  foto+tekst-blok (en in de tekst van een kolom) kan je bovendien, via
  datzelfde principe, een opsomming toevoegen: elke regel die begint met
  `- ` wordt een bullet-lijst (dezelfde schuine naald-bullet als het
  lijstblok) — typ de regels, selecteer ze, en klik op de knop "•", of
  typ `- ` gewoon zelf vooraan elke regel. Een alinea waarin slechts een
  deel van de regels met `- ` begint, blijft gewone tekst (inclusief het
  streepje) — zo verandert bestaande inhoud met een letterlijk
  liggend streepje nooit onbedoeld van uiterlijk. Dit is bewust **geen**
  vrije HTML-editor: wat je typt wordt eerst volledig geëscaped en pas
  daarna omgezet naar `<strong>`/`<em>`/`<a>`/`<ul><li>`-tags, dus
  letterlijke `<script>`- of andere HTML-tags kunnen nooit als echte
  opmaak terechtkomen — enkel deze bewust beperkte stijlen zijn mogelijk.
  Links volgen dezelfde `is_safe_url()`-controle als overal elders
  (geen `javascript:`).
- **3 frontend-varianten**, per pagina instelbaar (dropdown in de
  pagina-editor), gebaseerd op Wendy's eigen ontwerp: A "Crème, salie &
  terracotta" (het nieuwe ontwerp), B "Koraal" en C "Salie-groen" (de twee
  eerdere kleurstudies). Alle drie delen dezelfde rustige, ruime opbouw —
  enkel kleuren, typografie en afronding wisselen; de contactpagina volgt
  automatisch de variant van de homepagina voor een consistente
  uitstraling. De lettertypes uit Wendy's ontwerp (Source Serif 4/Figtree
  voor A, Lora/Work Sans voor B en C) zijn benaderd met stevige
  systeem-fallbackstacks — geen Google Fonts-CDN, dezelfde privacy-afweging
  als de rest van dit project. Zelf hosten van de echte lettertypebestanden
  kan als vervolgstap voor pixel-exacte typografie.
- **Subtiele animatie**: blokken faden rustig in bij scroll (één keer,
  IntersectionObserver, met een no-JS/`prefers-reduced-motion`-fallback
  zodat content altijd zichtbaar blijft), en knoppen/links geven een
  zachte terugkoppeling bij hover/klik. Bewust ingetogen — geen bounces of
  herhaalde animaties, om de rustige uitstraling niet te verstoren.
- **Mobile-first CSS**: basisstijlen zijn geschreven voor kleine schermen,
  met `min-width`-media queries die layout (nav, knoppenrij, contentbreedte)
  geleidelijk verrijken voor tablet/desktop.
- **Logo & navigatie** (Wendy's "Kompasnaald"-ontwerp): een SVG-logo (ring +
  naald) met wordmerk, waarvan alle kleuren via `var(--text)`/`var(--accent)`/
  `var(--bg)` lopen — past dus automatisch mee met de frontend-variant.
  Speelse, bewust subtiele logo-animatie: de naald zwaait uit en komt tot
  rust bij het laden van de pagina (eenmalig), en draait een volle toer bij
  hover/focus. Op mobiel (<640px) wordt het menu een ronde knop die opent
  tot een kruis-in-ring (CSS, geen library) met een uitklappaneel en
  gestaffelde item-reveal; vanaf 640px blijft het de horizontale balk.
  Volledig `prefers-reduced-motion`-bewust. Lijstblokken in "opsomming"-stijl
  gebruiken dezelfde schuine naald-bullet (-38°, rechttrekt bij hover) als
  het logo — "vinkjes"-stijl blijft ongewijzigd.
- Contactformulier op de site met validatie, CSRF-bescherming en
  meerlagige spambeveiliging; inzendingen zijn zichtbaar en
  markeerbaar/verwijderbaar in het beheerpaneel, en worden ook meteen
  per e-mail doorgestuurd — zie "Spambeveiliging & e-mailnotificatie"
  hieronder.
- **Google Analytics** (optioneel): bezoekstatistieken, met een
  toestemmingsbanner die voldoet aan de EU-cookieregels — en optioneel
  bezoekerscijfers rechtstreeks in het Dashboard. Zie "Google Analytics"
  hieronder.
- Consistente output-escaping (XSS) en prepared statements overal (SQL
  injection) — zie "Beveiliging" hieronder.
- **Opruiming van uploads**: verwijder je een fotoblok of vervang je de
  afbeelding, dan wordt het oude bestand in `uploads/` automatisch
  verwijderd — maar alleen als geen andere pagina het nog gebruikt (er
  wordt telkens over alle pagina's gecontroleerd, niet enkel de pagina die
  je net bewerkte).

## Kalenderblok (afspraken boeken)

Een nieuw, zesde bloktype: een zelfbedieningskalender waarmee bezoekers
zelf een afspraak inplannen, zonder heen-en-weer e-mailen.

- **Beschikbare tijdsloten** beheer je los van paginainhoud via "Kalender"
  in het beheerpaneel: datum + tijd toevoegen, en een overzicht van
  aankomende sloten (beschikbaar of al geboekt, met naam/e-mail/bericht van
  wie geboekt heeft). Een boeking annuleren zet het tijdslot weer open; een
  nog-niet-geboekt tijdslot kan je gewoon verwijderen.
- **Op de site** toont het kalenderblok een maandkalender; een dag met
  beschikbare momenten is aanklikbaar, waarna de tijdstippen verschijnen en
  je naam/e-mailadres/bericht invult om te bevestigen.
- **Race-condition-veilig**: boeken gebeurt via één atomaire
  `UPDATE ... WHERE status = 'available'`-query. Proberen twee bezoekers
  tegelijk hetzelfde moment te boeken, dan wint er maar één — de andere
  krijgt meteen te zien dat het moment net ingenomen is en de kalender
  ververst automatisch.
- Zelfde beveiligingspatroon als het contactformulier: CSRF-token,
  honeypot-veld, serverside validatie van datum/tijd/e-mailadres.
- **Nog niet ingebouwd**: e-mailbevestiging naar de bezoeker of Wendy bij
  een nieuwe boeking (zie "Mogelijke volgende stappen").

## Evenementenblok (lezingen/workshops aankondigen)

Een zevende bloktype, los van het kalenderblok: voor eenmalige evenementen
(lezingen, workshops, infosessies) met optionele, capaciteit-beperkte
inschrijving — geen individuele tijdsloten zoals bij een afspraak, gewoon
één datum en (optioneel) een maximum aantal plaatsen.

- **Evenementen beheer je los van paginainhoud** via "Evenementen" in het
  beheerpaneel: titel, beschrijving, datum, optionele tijd/locatie, en een
  optioneel maximum aantal plaatsen (leeg = onbeperkt). Per evenement zie
  je wie ingeschreven is, met de mogelijkheid een inschrijving te
  verwijderen (bv. na een telefonische afmelding) — dat maakt meteen weer
  een plaats vrij.
- **Op de site** toont het evenementenblok automatisch de eerstkomende
  6 gepubliceerde evenementen, elk met een "Schrijf je in"-uitklapper
  (`<details>`/`<summary>`, geen JavaScript nodig) met een naam/e-mailveld.
  Is een evenement volzet, dan verschijnt een "Volzet"-label in plaats van
  het formulier.
- **Aantal vrije plaatsen pas zichtbaar bij oplopende bezetting**
  (`event_capacity_status()` in `functions.php`, gedeeld door het
  website-evenementenblok en het compacte nieuwsbriefblok hieronder): bij
  een vers gepubliceerd evenement toont "10 van de 10 plaatsen vrij" vooral
  dat er nog niemand ingeschreven is — dus blijft dat cijfer verborgen
  zolang 75% of meer van de plaatsen nog vrij is, en verschijnt het pas
  (of "Volzet") vanaf dat punt. Dit geldt enkel voor de publieke weergave;
  "Evenementen" in het beheerpaneel toont altijd de exacte aantallen.
- **Race-condition-veilig**: de capaciteitscheck gebeurt binnen één
  databasetransactie met `SELECT ... FOR UPDATE` op het evenement, dus ook
  hier kunnen twee bezoekers nooit allebei de laatste plaats bemachtigen.
- Zelfde beveiligingspatroon als het contactformulier en het kalenderblok:
  CSRF-token, honeypot-veld, serverside validatie — inclusief een check dat
  de "terug naar de pagina"-redirect na het inschrijven altijd een eigen,
  relatieve pagina is (nooit een externe URL).
- **Nog niet ingebouwd**: e-mailbevestiging bij inschrijving (zie
  "Mogelijke volgende stappen").

## Nieuwsbrief

Een eigen nieuwsbriefmodule — geen koppeling met een extern platform
(Mailchimp e.d.), alles via de bestaande SMTP-mailclient en de MySQL-
database van de site zelf.

- **Abonnees** (`database/migrations/014_newsletter.sql`,
  `016_newsletter_first_name.sql`) komen op drie manieren binnen: het
  opt-in-vinkje ("Ja, ik wil graag de nieuwsbrief ontvangen", standaard
  uitgevinkt) op het contactformulier en bij een evenementinschrijving, of
  handmatig/via CSV-import door de beheerder op "Nieuwsbrief → Abonnees
  beheren". Een CSV-import voegt enkel écht nieuwe adressen toe — een eerder
  uitgeschreven adres wordt daarbij nooit stilzwijgend heringeschreven; dat
  kan enkel via een expliciete, individuele actie.
- **Voornaam**: optioneel per abonnee, voor persoonlijke aanspreking via de
  `{{voornaam}}`-merge-tag (zie hieronder). Via het contactformulier/
  evenementinschrijving wordt dit automatisch afgeleid uit het bestaande
  "Naam"-veld (eerste woord) — geen apart veld nodig. Bij handmatig
  toevoegen apart invulbaar; bij CSV-import optioneel als tweede kolom
  (`e-mail,voornaam`). Onbekend blijft gewoon onbekend, nooit verplicht.
- **Opmaak**: dezelfde blokkenbouwer als bij een pagina, maar met een
  kleinere toegestane set (tekst, foto, quote, lijst, knoppen, evenementen-
  overzicht) — geen kalender, kaart, foto+tekst of kolommen. E-mailclients
  (vooral Outlook desktop) ondersteunen geen CSS Grid/Flexbox of externe
  stylesheets, dus een nieuwsbrief wordt via een eigen, met inline-stijlen
  opgebouwde HTML-sjabloon gerenderd — niet dezelfde opmaak-code als de
  website zelf, die zou in een inbox gewoon niet weergeven. De "Achtergrond"-
  optie (Accentkleur/Zachte kaart) werkt ook hier, als inline-stijl in
  plaats van de CSS-klasse van de website. Zowel het onderwerp als de
  inhoud ondersteunen `{{voornaam}}` als merge-tag, die bij verzending per
  abonnee wordt ingevuld (onbekende voornaam valt terug op "daar").
- **Evenementenblok (compact)**: toont automatisch de eerstkomende,
  gepubliceerde evenementen (datum, titel, locatie, en — vanaf 75%
  bezetting — het aantal vrije plaatsen of "Volzet", zie hierboven) — een
  apart, beknopt blok naast het volledige evenementenblok van de website,
  zonder inline inschrijfformulier: inschrijven gebeurt altijd via de
  website, optioneel via een configureerbare knop (tekst + link) onderaan
  het blok.
- **Nieuwsbrief dupliceren**: elke nieuwsbrief — ook een al verzonden of
  geannuleerde — kan via het kopieer-icoon in het overzicht gedupliceerd
  worden naar een nieuw concept met dezelfde inhoud (onderwerp voorafgegaan
  door "Kopie van"), zodat een periodieke nieuwsbrief niet elke keer van nul
  opgebouwd moet worden.
- **Versturen gebeurt in batches**, niet in één keer: op gedeelde hosting
  zonder cron zou één verzoek dat honderden losse SMTP-verbindingen opzet,
  simpelweg de PHP-uitvoeringslimiet overschrijden. Een klik op "Verstuur"
  zet alle abonnees in een wachtrij; de pagina roept daarna zelf herhaaldelijk
  een klein batchje (20) af totdat iedereen een mail heeft, met een
  voortgangsbalk. Een nieuwsbrief kan niet meer bewerkt worden eens het
  verzenden gestart is. Elke verzonden mail wordt meteen als dusdanig
  opgeslagen (niet pas na een volledig batchje) — een trage of niet-
  reagerende mailserver kan dus nooit al gemaakte voortgang ongedaan maken.
  Loopt een batch vast (geen antwoord binnen 45 seconden), dan toont de
  pagina dat zichtbaar met een "Opnieuw proberen"-knop, in plaats van stil
  te blijven hangen. Een verzending die nog bezig is, kan ook altijd
  geannuleerd worden ("Annuleren") — reeds verzonden mails blijven verzonden,
  de rest van de wachtrij wordt niet meer aangeschreven. Een nieuwsbrief
  verwijderen kan in elke status behalve "Bezig met verzenden" (eerst
  annuleren); bij een verzonden/geannuleerde nieuwsbrief verdwijnen dan ook
  de bijhorende open-/klikgegevens.
- **Foutmeldingen van de mailserver zelf** zijn zichtbaar, niet enkel een
  generieke "het is mislukt": elke verzending die de mailserver weigert
  (verkeerde login, geweigerde afzender, ...) toont het échte antwoord van
  de server (bv. "535 5.7.8 Authentication failed"), zowel live tijdens het
  verzenden als nadien op de nieuwsbrief zelf — want elke verzending wordt
  sowieso als verzonden geregistreerd zodra ze geprobeerd is, ook als de
  mailserver ze weigerde (zie hierboven), dus zonder dit zou zo'n mislukking
  onzichtbaar blijven. Inloggegevens verschijnen nooit in deze meldingen.
- **List-Unsubscribe-headers**: elke nieuwsbrief krijgt een `List-Unsubscribe`-
  en `List-Unsubscribe-Post`-header (RFC 8058), gekoppeld aan dezelfde
  afmeldlink als in de mail zelf. Gmail/Outlook/Yahoo gebruiken dit zowel
  als signaal dat het om een legitieme, correct beheerde verzendlijst gaat
  (relevant voor of een mail in de inbox dan wel het "Promoties"-tabblad
  terechtkomt) als om hun eigen "Uitschrijven"-knop naast de afzender te
  tonen. Dit alleen garandeert geen inbox-plaatsing — dat hangt ook af van
  SPF/DKIM/DMARC-configuratie op DNS-niveau en afzenderreputatie, buiten
  wat deze applicatie kan afdwingen.
- **Tracking**: elke verzonden mail bevat een onzichtbare 1×1-pixel (open-
  tracking) en elke link wordt herschreven via een eigen omleidings-URL
  (klik-tracking) — beide gekoppeld aan een uniek, willekeurig token per
  (nieuwsbrief, abonnee)-combinatie, nooit aan het e-mailadres zelf.
  Zichtbaar in het nieuwsbrievenoverzicht en op de detailpagina van een
  verzonden nieuwsbrief.
- **Afmelden is verplicht, niet optioneel**: elke verzonden mail krijgt
  automatisch een voettekst met een one-click-afmeldlink (geen login, geen
  bevestigingsstap) — wettelijk vereist voor commerciële e-mail, dus
  hiervoor is bewust geen instelling om dit uit te zetten.
- **Voorvertoning en testmail**: op de bewerkpagina van een opgeslagen
  concept staat een link "Voorvertoning" (`admin/newsletter-preview.php`,
  rendert de echte, opgemaakte e-mail — inclusief merge-tags met "daar" als
  fallback — in een iframe) en een veld om een testmail naar één
  e-mailadres te sturen (`admin/newsletter-test-send.php`): onderwerp
  voorafgegaan door `[Test]`, geen List-Unsubscribe-header (er is geen
  echte abonnee/token achter een losse test). Werkt ook na het effectief
  versturen, als extra controle.
- **Niet ingebouwd**: een geautomatiseerd, periodiek verzendschema — een
  nieuwsbrief wordt altijd met een bewuste klik verstuurd.

## E-mailinstellingen

Alle mail die de site verstuurt — contactmeldingen, wachtwoordherstel voor
het beheerpaneel, en de nieuwsbrief — gebruikt vanaf nu dezelfde SMTP-
configuratie, instelbaar via "Instellingen → E-mail"
(`admin/mail-settings.php`, tabel `mail_settings`,
`database/migrations/015_mail_settings.sql`): server, poort, encryptie,
gebruikersnaam, wachtwoord, afzenderadres/-naam, reply-to-adres en het
ontvangeradres voor contactmeldingen.

- **Terugval op `config/config.php`**: elk veld dat hier leeg is gelaten
  valt per veld terug op de overeenkomstige waarde in de `smtp`-sectie van
  `config/config.php` (de vroegere, enige manier). Zo blijft mail na deze
  update meteen werken zonder iets te moeten doen — eenmaal deze pagina
  opgeslagen is, heeft de database voorrang. Het wachtwoordveld toont nooit
  de huidige waarde (leeg laten bij opslaan behoudt het bestaande
  wachtwoord, uit de database of anders uit config.php).
- **Reply-to**: optioneel, wordt gebruikt als `Reply-To`-header op elke
  verzonden nieuwsbrief. Contactmeldingen gebruiken in plaats daarvan altijd
  het e-mailadres van de inzender zelf (al zo, en nuttiger dan een vast
  adres), dat gedrag blijft ongewijzigd.
- **DKIM** (digitale ondertekening van elke uitgaande mail, `includes/
  mailer.php`'s `dkim_sign()`) is, in tegenstelling tot de rest van de
  e-mailinstellingen, bewust **niet** via het beheerpaneel instelbaar —
  het is een langlevend cryptografisch geheim, geen login die ooit via een
  webformulier wijzigt. Configureren kan enkel via een `'dkim' => [...]`-
  sectie in `config/config.php` (zie het commentaar in
  `config/config.example.php` voor de volledige opzet: sleutelpaar
  genereren, `domain`/`selector`/`private_key` invullen, en een
  bijhorend `<selector>._domainkey.<domain>` TXT-record toevoegen in DNS).
  Leeg laten (de standaard) verstuurt mail gewoon zonder DKIM-handtekening.
  Samen met het bestaande SPF-record is dit een van de signalen die
  Gmail/Outlook/Yahoo gebruiken om inbox- vs. Promoties-/spamplaatsing te
  bepalen — geen garantie op zich, maar wel een directe verbetering.

## Homepage-content

`database/seed-homepage.php` (eenmalig via de command line te draaien, zie
"Lokaal opzetten") zet Wendy's eigen tekst uit haar ontwerp meteen klaar als
homepage — titel, de vier diensten, de echte foto van de praktijkruimte
(`assets/images/praktijkruimte.jpg`), en een kalenderblok onderaan.
Bewust **niet** meegenomen, omdat ze ook in Wendy's eigen ontwerp nog als
placeholder stonden — vul zelf aan via het beheerpaneel zodra je ze hebt:

- Een portretfoto van Wendy voor de hero (nu leeg).
- Een echte cliëntreactie als quote-blok (het testimonial in het ontwerp
  was zelf een placeholder, dus niet overgenomen).
- Adres, telefoonnummer en e-mailadres — die staan nergens in Wendy's
  ontwerp en worden dus nergens verzonnen; vul ze aan via "Instellingen"
  in het beheerpaneel (zie "Site-instellingen" hieronder), anders toont de
  voettekst voorlopig enkel de sitenaam.

## Site-instellingen (footer + contactpagina)

Via "Instellingen" in het beheerpaneel (`admin/settings.php`) vul je adres
(straat, postcode en gemeente als aparte velden — zie hieronder),
telefoonnummer en e-mailadres in — die verschijnen dan automatisch in de
voettekst van elke pagina (telefoon/e-mail als klikbare `tel:`/`mailto:`-
links, adres samengevoegd tot één regel). Een leeg veld wordt gewoon niet
getoond; er wordt nergens iets verzonnen. Opgeslagen in de eenrijige tabel
`site_settings` (`database/migrations/006_site_settings.sql`,
uitgebreid in `019_schema_privacy_faq.sql`).
- Beschikbare tijdsloten voor het kalenderblok (anders toont dat blok niets).

Verderop op diezelfde pagina staat "Structuurdata (schema.org)": prijsklasse,
werkgebied, LinkedIn-/Google Business-link, en Wendy als persoon (naam,
functietitel, expertise, korte bio) — allemaal onzichtbaar op de site zelf,
enkel voor zoekmachines/AI, en elk veld apart optioneel. Zie
[SEO & vindbaarheid voor AI-zoekfuncties](#seo--vindbaarheid-voor-ai-zoekfuncties)
hieronder voor hoe dit precies in het JSON-LD terechtkomt.

Op diezelfde pagina staat ook "Inhoud contactpagina": dezelfde
blokkenbouwer als bij een gewone pagina (tekst, foto, kaart, ...), die op
`/contact` verschijnt bóven het vaste contactformulier. Het formulier zelf
(velden, validatie, opslag van inzendingen) blijft vast en is niet via
blokken aanpasbaar — enkel de inhoud ervoor is vrij in te vullen. Leeg =
enkel het formulier, zoals voorheen. Opgeslagen in dezelfde `site_settings`-
rij, kolom `content` (`database/migrations/007_contact_content.sql`). Het
formulier zelf kan wel een achtergrond krijgen ("Geen"/"Accentkleur"/
"Zachte kaart", dezelfde opties als op een content-blok) via "Achtergrond
contactformulier" op diezelfde pagina
(`database/migrations/011_contact_form_background.sql`).

Ook op die pagina: **bewaartermijn contactberichten & inschrijvingen**
("Voor altijd"/90/180/365/730 dagen) — contactberichten en evenement-
inschrijvingen ouder dan de gekozen termijn worden automatisch verwijderd
(naam, e-mailadres, bericht, IP-adres). Gebeurt bij het openen van het
Dashboard, niet onmiddellijk bij het wijzigen van de instelling
(`database/migrations/012_submission_retention.sql`).

Verder staan er twee eigen SEO/AI-velden op die pagina:
- **Titel/meta-omschrijving contactpagina**: `/contact` had tot nu toe een
  vaste, hardcoded titel en omschrijving. Leeg = die vaste tekst blijft
  gebruikt (`database/migrations/009_contact_seo_fields.sql`).
- **AI-samenvatting van de praktijk**: een eigen, uitgebreidere tekst over
  de praktijk (los van de korte meta-omschrijving), die verschijnt als
  "Over de praktijk"-sectie in `llms.txt` — zie
  [SEO & vindbaarheid voor AI-zoekfuncties](#seo--vindbaarheid-voor-ai-zoekfuncties)
  hieronder. Leeg = die sectie verschijnt simpelweg niet
  (`database/migrations/010_ai_summary.sql`).

## Privacypagina

Geen aparte, hardcoded privacypagina (zoals contact.php) — het is een
gewone pagina, aangemaakt zoals elke andere via "Pagina's → Nieuwe pagina",
die je via het vinkje "Als privacypagina instellen" markeert
(`database/migrations/019_schema_privacy_faq.sql`, kolom `is_privacy_page`
op `pages`, zelfde exclusief-vlag-patroon als "Als homepagina instellen" —
er kan er maar één tegelijk zijn). Die markering bepaalt automatisch:
- De link "Privacybeleid" in de voettekst van elke pagina.
- De korte vermelding "Door te verzenden ga je akkoord met ons
  privacybeleid" net boven de verstuurknop van het contactformulier, een
  evenementinschrijving en een afspraakboeking (`render_privacy_note()` in
  `functions.php`, en de JS-tegenhanger in `calendar-block.js` voor de
  kalenderboeking die geen paginaherlading gebruikt). Een informatieve
  link, geen verplicht aan te vinken vakje.

Is er nog geen enkele pagina als privacypagina gemarkeerd (of niet
gepubliceerd), dan verschijnt er simpelweg nergens een link of vermelding —
nooit een dode link naar een pagina die niet bestaat.

## Google Analytics

Volledig optioneel — alles hieronder blijft uitgeschakeld (geen script,
geen toestemmingsbanner) zolang `config.php`'s `google_analytics`-sectie
leeg is.

### Bezoektracking + toestemmingsbanner

1. Maak een GA4-property aan (of gebruik een bestaande) en noteer het
   "G-XXXXXXXXXX"-meet-ID uit GA4 Admin → Datastreams → je webstream.
2. Vul dat in als `google_analytics.measurement_id` in `config/config.php`.
3. Klaar — elke publieke pagina toont dan onderaan een eenvoudige
   toestemmingsbanner ("Akkoord" / "Weiger"). Het trackingscript laadt pas
   ná een klik op "Akkoord" (bewaard in `localStorage` van de bezoeker, dus
   de banner verschijnt daarna niet meer); bij "Weiger" of geen keuze
   wordt er niets geladen. Zo voldoet de site aan de EU-regels rond
   analytics-cookies, zonder een cookie-consent-dienst van een derde partij.

Het beheerpaneel zelf (`/admin/...`) toont nooit deze banner en laadt nooit
Analytics — enkel de publieke site wordt gemeten.

### Bezoekerscijfers in het Dashboard (optioneel, extra opzetwerk)

Bovenop de tracking hierboven kan je ook bezoekerscijfers (bezoekers en
paginaweergaven van de laatste 7 dagen, plus de 5 meest bekeken pagina's)
rechtstreeks op het admin-Dashboard tonen, via Google's Analytics Data API
— zonder een Google-SDK of Composer-package, met een zelfgeschreven
service-account-aanmelding. Opzet:

1. Open [Google Cloud Console](https://console.cloud.google.com/), maak
   een project aan (of kies een bestaand project) en schakel de **Google
   Analytics Data API** in voor dat project.
2. Maak een **service-account** aan in dat project, en genereer er een
   JSON-sleutel voor (IAM & Admin → Service Accounts → je account →
   Keys → Add key → JSON). Bewaar dat bestand veilig, het bevat een
   privésleutel.
3. In GA4 zelf: Admin → Property Access Management → voeg het e-mailadres
   van het service-account (uit de JSON, veld `client_email`) toe als
   **Viewer** op je property.
4. Noteer ook het numerieke GA4 **property-ID** (GA4 Admin → Property
   Settings — niet hetzelfde als het "G-"meet-ID hierboven).
5. Vul in `config/config.php` onder `google_analytics` aan:
   - `property_id` — het numerieke property-ID uit stap 4.
   - `service_account_email` — het `client_email`-veld uit de JSON-sleutel.
   - `service_account_private_key` — het `private_key`-veld uit de
     JSON-sleutel, inclusief de `-----BEGIN/END PRIVATE KEY-----`-regels.
     Of je de regeleindes als echte newlines of als letterlijke `\n`
     plakt maakt niet uit, beide vormen worden herkend.

Het Dashboard haalt bij weergave de cijfers op en cachet ze 30 minuten
(tabel `analytics_cache`,
`database/migrations/008_analytics_cache.sql`) om Google's API niet bij
elke paneelbezoek opnieuw te belasten; "nu vernieuwen" op het Dashboard
forceert een verse ophaling. Lukt die niet (verkeerde gegevens, Google
tijdelijk onbereikbaar, ...), dan toont het Dashboard de laatst gekende
cijfers met een duidelijke melding, in plaats van niets te tonen — inclusief
een "Reden:"-regel met Google's eigen foutmelding (bv. "Invalid grant:
account not found"), zodat je meteen weet wat er mis is zonder in het
PHP-foutenlogboek te moeten kijken.

## Spambeveiliging & e-mailnotificatie

Het contactformulier combineert meerdere, onafhankelijke lagen —
val één weg (bv. reCAPTCHA niet ingesteld), dan blijven de andere actief:

- **CSRF-token** (al aanwezig) — blokkeert vervalste inzendingen van
  andere sites.
- **Honeypot-veld** — een onzichtbaar veld dat enkel bots invullen; wordt
  het ingevuld, dan doet de site net alsof het bericht verzonden is
  (de bot leert niets), maar er wordt niets opgeslagen of gemaild.
- **Tijdscontrole** — de servertijd waarop het formulier getoond werd,
  wordt bijgehouden in de sessie (niet in een onzichtbaar veld dat een bot
  gewoon kan meesturen); een inzending binnen de 3 seconden wordt geweigerd.
- **IP-ratelimiet** — max. 3 inzendingen per IP-adres per 10 minuten.
- **Google reCAPTCHA v3** (optioneel) — een onzichtbare score-check van
  Google op basis van bezoekersgedrag (geen puzzeltjes voor de bezoeker).
  Vul `recaptcha.site_key` en `recaptcha.secret_key` in in `config.php` om
  dit te activeren (registreer je domein op
  [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin));
  laat beide leeg om deze laag over te slaan.

Een geweigerde inzending krijgt altijd dezelfde algemene foutmelding,
ongeacht welke laag precies toesloeg — zo leert een bot niet welke check
hij moet omzeilen.

### E-mailnotificatie

Elke geslaagde inzending wordt (bovenop het opslaan in het beheerpaneel)
automatisch gemaild, via een eigen, kleine SMTP-client (geen
Composer/PHPMailer — past bij de rest van dit project) die STARTTLS,
impliciete TLS en AUTH LOGIN ondersteunt, wat de meeste hosting- en
webmailproviders dekt. Opzet in `config/config.php` onder `smtp`:

- `host`, `port`, `encryption` (`tls` voor STARTTLS — meestal poort 587,
  `ssl` voor impliciete TLS — meestal poort 465, of `none` voor een
  onversleutelde lokale relay) en `username`/`password` van je
  mailaccount.
- `from_email`/`from_name` — het afzenderadres; veel providers eisen dat
  dit overeenkomt met (of dicht aanleunt bij) het ingelogde mailaccount,
  anders wordt het geweigerd of als spam gemarkeerd.
- `to_email` — waar de notificaties naartoe gaan. Leeg = valt terug op het
  e-mailadres onder Instellingen (zie "Site-instellingen" hierboven).

Laat `host` leeg om e-mailverzending uit te schakelen — inzendingen blijven
dan gewoon zichtbaar in het beheerpaneel, er wordt alleen niet gemaild.
Mislukt de mail (verkeerde SMTP-gegevens, provider tijdelijk onbereikbaar,
...), dan blijft de inzending wél gewoon opgeslagen; e-mail is best-effort
bovenop, nooit een voorwaarde om het bericht te bewaren.

## SEO & vindbaarheid voor AI-zoekfuncties

- **Schone URL's**: pagina's zijn bereikbaar via `/pagina/{slug}` en het
  contactformulier via `/contact` (geen `.php`/`?slug=` meer in de
  adresbalk) — beter voor zowel klassieke zoekmachines als AI-crawlers.
- **Canonical URL + Open Graph + Twitter cards** op elke pagina, automatisch
  ingevuld vanuit titel, meta-omschrijving en de eerste foto van de pagina
  (ook als die in een kolommen- of foto+tekst-blok staat). Heeft een pagina
  geen eigen foto, dan valt `og:image` terug op een vaste, echte foto van de
  praktijkruimte (`assets/images/praktijkruimte.jpg`) in plaats van helemaal
  geen afbeelding te tonen — zodat een gedeelde link altijd verzorgd oogt.
- **Meta-omschrijving: waarschuwing, geen blokkade**: bij het bewerken van
  een pagina (en van de contactpagina-velden in Instellingen) toont het veld
  live het aantal tekens, met een waarschuwing als het leeg is of langer dan
  ~160 tekens (het punt waarop zoekmachines vaak afkappen). De pagina blijft
  gewoon opslaanbaar — het is een hint, geen harde eis.
- **Structured data (JSON-LD)**:
  - `Organization`- en `WebPage`-schema.org-markup op elke pagina.
  - Zodra adres of telefoonnummer is ingevuld bij "Instellingen", wordt
    `Organization` automatisch `ProfessionalService` (een LocalBusiness-
    subtype, passend voor een therapie-/coachingpraktijk), met een
    gestructureerd `PostalAddress` (straat/postcode/gemeente apart, zie het
    adresveld hieronder) in plaats van één platte tekstregel. Elk
    afzonderlijk veld — adres, telefoon, prijsklasse, werkgebied,
    LinkedIn-/Google Business-link — wordt enkel toegevoegd als het effectief
    is ingevuld; leeg = gewoon weggelaten, nooit verzonnen bedrijfsgegevens.
  - **Behandelaar als Person** (`founder`): naam, functietitel, expertise
    (kommagescheiden lijst → `knowsAbout`) en een korte bio, ook volledig
    optioneel — verschijnt pas zodra minstens de naam is ingevuld.
  - **Adres**: straat, postcode en gemeente zijn drie aparte velden (i.p.v.
    één vrij tekstveld) zodat het schema.org-adres een echte `PostalAddress`
    kan zijn; de footer voegt ze automatisch weer samen tot één leesbare
    regel (`format_address()` in `functions.php`). `addressCountry` staat
    vast op `BE`.
  - **Openingsuren**: bewust niet ingebouwd — de praktijk werkt uitsluitend
    op afspraak via het bestaande kalenderblok, een vast urenschema zou dus
    niet kloppen.
  - Elk aankomend, gepubliceerd evenement met een locatie krijgt eigen
    `Event`-schema.org-markup (naam, beschrijving, datum/tijd, locatie) op
    de pagina waar het evenementenblok staat. Zonder locatie (nog niet
    ingevuld) verschijnt er geen `Event`-markup voor dat evenement — Google
    vereist een locatie, en die wordt nooit verzonnen.
  - **FAQ-blok** (`faq`, enkel beschikbaar in de paginablokkenbouwer, niet
    in de nieuwsbrief): een lijst vraag/antwoord, ingevoerd als platte tekst
    met een lichte conventie (elke vraag begint met "V: ", gevolgd door het
    antwoord op de volgende regel(s), een lege regel scheidt de volgende
    vraag — zelfde stijl als de "- "-conventie van het lijstblok). Rendert
    als een uitklapbare lijst (`<details>`/`<summary>`, geen JavaScript
    nodig) én genereert automatisch `FAQPage`-schema.org-markup, enkel op de
    pagina waar het blok effectief staat.
- Elk fotoblok (los, foto+tekst, of in een kolom) vereist een alt-tekst,
  zowel in de admin-UI als hard afgedwongen bij het opslaan — nooit een
  foto zonder beschrijving.
- **`sitemap.xml`** (dynamisch, `sitemap.php`) — lijst van alle
  gepubliceerde pagina's voor zoekmachines.
- **`robots.txt`** (dynamisch, `robots.php`) — sluit enkel
  `/admin/` uit; staat expliciet open voor de bekende AI-crawlers (GPTBot,
  ChatGPT-User, OAI-SearchBot, Google-Extended, CCBot, anthropic-ai,
  ClaudeBot, PerplexityBot, Applebot-Extended, Meta-ExternalAgent) zodat de
  site ook via AI-zoekfuncties gevonden en geciteerd kan worden.
- **`llms.txt`** (dynamisch, `llms.php`) — een opkomende, informele
  standaard: een platte-tekstsamenvatting van de site speciaal voor
  AI-systemen, naast de klassieke `sitemap.xml` voor zoekmachines. Bestaat
  uit secties:
  - **Over de praktijk** (optioneel) — de eigen AI-samenvatting uit
    Instellingen, enkel getoond als die is ingevuld.
  - **Aanbod** — alle gepubliceerde pagina's (incl. Home en Contact) met
    hun meta-omschrijving.
  - **Evenementen** (optioneel) — aankomende, gepubliceerde evenementen met
    datum, locatie en beschrijving; blijft weg zodra er geen zijn.
- De site is licht en snel (geen zware JS-frameworks, geen externe
  lettertypes) — laadsnelheid en mobielvriendelijkheid zijn zelf ook
  rankingfactoren.

## Beveiliging — belangrijk voor je gaat live

- **`config/`, `database/` en `includes/` zijn afgeschermd via `.htaccess`**
  (`Require all denied` in elke map). Omdat de site vanuit de projectroot
  draait, staan deze mappen naast de publieke bestanden in plaats van er
  fysiek buiten — zonder die `.htaccess`-bestanden zouden
  `config/config.php` (databasewachtwoord) en `database/schema.sql`
  (databasestructuur) gewoon via de browser opvraagbaar zijn. Test dit na
  elke deploy: `jouwdomein.be/config/config.php` moet een 403 geven.
- **Nooit** `config/config.php` in git committen (staat al in `.gitignore`).
- Gebruik een sterk, uniek wachtwoord voor het beheerdersaccount.
- Zet HTTPS aan zodra de site live staat.
- `admin/install.php` sluit zichzelf automatisch af zodra er één
  account bestaat — dat is de enige manier waarop nieuwe accounts kunnen
  ontstaan; er is bewust geen registratiepagina.
- **Wachtwoord vergeten**: "Wachtwoord vergeten?" op de inlogpagina stuurt
  een tijdelijke (1 uur geldige) herstellink naar het e-mailadres onder
  Instellingen — vereist dus zowel dat e-mailadres als een werkende SMTP-
  configuratie in `config/config.php`. Zonder die twee kan het wachtwoord
  enkel rechtstreeks in de database hersteld worden
  (`database/migrations/013_password_reset.sql`).
- Afbeeldingsuploads (`admin/upload-image.php`) zijn alleen
  bereikbaar als ingelogde beheerder, controleren het werkelijke
  bestandstype (niet enkel de extensie) via `finfo` + `getimagesize()`,
  slaan op onder een gegenereerde bestandsnaam (nooit de originele naam)
  en `uploads/.htaccess` verhindert dat er ooit iets in die map als
  script kan uitvoeren — zelfs als een bestand die controles ooit zou
  omzeilen.
- Als een upload op je hosting mislukt met een generieke foutmelding,
  controleer dan `upload_max_filesize` en `post_max_size` in de
  PHP-instellingen van je hostingpaneel (moeten minstens 5 MB toelaten).

## Mogelijke volgende stappen (niet in deze MVP)

- E-mailnotificatie bij een nieuwe kalenderboeking of evenementinschrijving
  (het contactformulier mailt al — zie "Spambeveiliging &
  e-mailnotificatie"; dezelfde `includes/mailer.php` is herbruikbaar).
- Meerdere beheerders met rollen, wachtwoord-reset via e-mail.
- Paginering als het aantal pagina's/berichten groot wordt.
- Zelf gehoste webfonts voor pixel-exacte typografie (zie "Functionaliteit").
- Een rijkere voettekst (adres/telefoon/e-mail/links) zodra die gegevens
  er zijn — nu bewust minimaal om niets te verzinnen.
- Het beheerpaneel is niet responsive voor mobiel (vaste zijbalk, geen
  inklapbaar hamburgermenu zoals de publieke site) — in de praktijk wordt
  het vrijwel altijd op desktop gebruikt, dus bewust nog niet gebouwd.
