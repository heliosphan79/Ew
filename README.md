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
   `004_calendar_slots.sql`, `005_events.sql` en `006_site_settings.sql`.
3. `cp config/config.example.php config/config.php` en vul je lokale
   databasegegevens in.
4. Start de ingebouwde PHP-server vanaf de projectroot, met `router.php`
   zodat `config/`, `database/` en `includes/` ook lokaal afgeschermd zijn
   (PHP's ingebouwde server negeert `.htaccess`, in tegenstelling tot
   Apache op je uiteindelijke hosting):
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
    lijstblok).
  - **Foto** — kies "Bestand kiezen…" om een JPG/PNG/GIF/WEBP te uploaden
    (max. 5 MB, direct herbekeken als miniatuur), of vul zelf een
    afbeeldings-URL in. Plus alt-tekst (verplicht, toegankelijkheid) en
    optioneel bijschrift.
  - **Quote** — citaat + optionele bron.
  - **Lijst** — titel, stijl (opsomming/vinkjes) en items (één per regel).
  - **Knoppen** — tot 3 knoppen, één per regel als `Tekst | link`.
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
    verticaal op mobiel.
  - Links/afbeeldings-URL's worden serverside gevalideerd (enkel `/...`,
    `http(s)://`, `mailto:` of `tel:` — geen `javascript:`-injectie
    mogelijk).
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
- Contactformulier op de site met validatie, CSRF-bescherming en een
  honeypot-veld tegen spambots; inzendingen zijn zichtbaar en
  markeerbaar/verwijderbaar in het beheerpaneel.
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
- **Race-condition-veilig**: de capaciteitscheck gebeurt binnen één
  databasetransactie met `SELECT ... FOR UPDATE` op het evenement, dus ook
  hier kunnen twee bezoekers nooit allebei de laatste plaats bemachtigen.
- Zelfde beveiligingspatroon als het contactformulier en het kalenderblok:
  CSRF-token, honeypot-veld, serverside validatie — inclusief een check dat
  de "terug naar de pagina"-redirect na het inschrijven altijd een eigen,
  relatieve pagina is (nooit een externe URL).
- **Nog niet ingebouwd**: e-mailbevestiging bij inschrijving (zie
  "Mogelijke volgende stappen").

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

## Site-instellingen (footer)

Via "Instellingen" in het beheerpaneel (`admin/settings.php`) vul je adres,
telefoonnummer en e-mailadres in — die verschijnen dan automatisch in de
voettekst van elke pagina (telefoon/e-mail als klikbare `tel:`/`mailto:`-
links). Een leeg veld wordt gewoon niet getoond; er wordt nergens iets
verzonnen. Opgeslagen in de eenrijige tabel `site_settings`
(`database/migrations/006_site_settings.sql`).
- Beschikbare tijdsloten voor het kalenderblok (anders toont dat blok niets).

## SEO & vindbaarheid voor AI-zoekfuncties

- **Schone URL's**: pagina's zijn bereikbaar via `/pagina/{slug}` en het
  contactformulier via `/contact` (geen `.php`/`?slug=` meer in de
  adresbalk) — beter voor zowel klassieke zoekmachines als AI-crawlers.
- **Canonical URL + Open Graph + Twitter cards** op elke pagina, automatisch
  ingevuld vanuit titel, meta-omschrijving en (indien aanwezig) de eerste
  foto van de pagina — zodat een gedeelde link op social media/WhatsApp
  er verzorgd uitziet.
- **Structured data (JSON-LD)**: elke pagina krijgt `Organization`- en
  `WebPage`-schema.org-markup. Bewust minimaal — enkel site-naam en URL,
  nooit verzonnen bedrijfsgegevens (adres, telefoon, ...) die je nergens
  hebt ingevuld.
- **`sitemap.xml`** (dynamisch, `sitemap.php`) — lijst van alle
  gepubliceerde pagina's voor zoekmachines.
- **`robots.txt`** (dynamisch, `robots.php`) — sluit enkel
  `/admin/` uit; staat expliciet open voor de bekende AI-crawlers
  (GPTBot, ChatGPT-User, Google-Extended, ClaudeBot, PerplexityBot, ...)
  zodat de site ook via AI-zoekfuncties gevonden en geciteerd kan worden.
- **`llms.txt`** (dynamisch, `llms.php`) — een opkomende, informele
  standaard: een korte, platte-tekstsamenvatting van de site speciaal voor
  AI-systemen, naast de klassieke `sitemap.xml` voor zoekmachines.
- Elk fotoblok vereist een alt-tekst (toegankelijkheid **en** SEO), en de
  site is licht en snel (geen zware JS-frameworks, geen externe lettertypes)
  — laadsnelheid en mobielvriendelijkheid zijn zelf ook rankingfactoren.

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

- E-mailnotificatie bij een nieuw contactformulier, een nieuwe
  kalenderboeking of een nieuwe evenementinschrijving (bv. via PHP `mail()`
  of een transactionele e-maildienst).
- Meerdere beheerders met rollen, wachtwoord-reset via e-mail.
- Paginering als het aantal pagina's/berichten groot wordt.
- Zelf gehoste webfonts voor pixel-exacte typografie (zie "Functionaliteit").
- Een rijkere voettekst (adres/telefoon/e-mail/links) zodra die gegevens
  er zijn — nu bewust minimaal om niets te verzinnen.
