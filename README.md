# Eigen-Wijzer CMS

Eenvoudig CMS (PHP + MySQLi + vanilla JS/HTML, geen frameworks of Composer)
om de website eigen-wijzer.be te beheren: pagina's opgebouwd uit eenvoudige
content-blokken (tekst, foto, quote, lijst, knoppen), 4 kiesbare
frontend-varianten met rustige scroll/klik-animatie, en een contactformulier
waarvan de inzendingen in het beheerpaneel terechtkomen.

## Projectstructuur

```
config/                     configuratie (config.php bevat echte databasegegevens — niet in git)
database/schema.sql          database-structuur, eenmalig importeren (nieuwe installatie)
database/migrations/         wijzigingen op een bestaande database (zie hieronder)
includes/                    gedeelde PHP-code (db-connectie, helpers, auth, blok-rendering, publieke layout)
public/                      webroot — dit is wat je hosting als document root moet gebruiken
  index.php                   homepagina
  pagina.php                  toont een individuele pagina op basis van ?slug=
  contact.php                 contactformulier
  admin/                      beheerpaneel (login, pagina's + blokkenbouwer, contactberichten)
```

`config/`, `database/` en `includes/` staan **buiten** `public/` en zijn dus
niet rechtstreeks via de browser bereikbaar — enkel `public/` moet als
document root ingesteld worden.

## Lokaal opzetten (testen)

1. Zorg voor PHP 8.1+ met de mysqli-extensie, en een MySQL/MariaDB-server.
2. Maak een database aan en importeer `database/schema.sql`.
   Had je al een oudere versie van deze database draaien (vóór de
   blokkenbouwer)? Importeer dan in plaats daarvan
   `database/migrations/002_blocks_and_theme.sql` — lees de opmerking
   bovenaan dat bestand, want bestaande paginainhoud wordt daarbij geleegd.
3. `cp config/config.example.php config/config.php` en vul je lokale
   databasegegevens in.
4. Start de ingebouwde PHP-server vanaf de projectroot:
   ```
   php -S localhost:8000 -t public
   ```
5. Open `http://localhost:8000/admin/` — omdat er nog geen beheerder bestaat,
   kom je automatisch op de installatiepagina terecht om het eerste account
   aan te maken.

## Deployen op gedeelde hosting (cPanel-achtig)

1. Maak in je hostingpaneel een MySQL-database en -gebruiker aan, en
   importeer `database/schema.sql` via phpMyAdmin.
2. Upload de volledige projectmap naar je account, **buiten** `public_html`
   (bv. in een map `eigenwijzer-cms` naast `public_html`).
3. Verwijder de standaard `public_html`-inhoud en zet in plaats daarvan de
   inhoud van `public/` in `public_html` — of, als je host toelaat om het
   document root-pad aan te passen, wijs dat pad rechtstreeks naar de
   `public/`-map van de upload. Zo blijven `config/`, `database/` en
   `includes/` buiten bereik van de browser.
4. Maak `config/config.php` aan op basis van `config/config.example.php` met
   de echte databasegegevens van je hosting.
5. Bezoek `jouwdomein.be/admin/` om het eerste beheerdersaccount aan te
   maken via de installatiepagina.
6. Zorg dat de website via HTTPS draait (meestal gratis Let's Encrypt via
   het hostingpaneel) — logingegevens mogen nooit over onversleuteld http.

## Functionaliteit (MVP)

- Login voor beheerders (wachtwoorden gehasht met `password_hash`,
  sessie-gebaseerd, met een eenvoudige brute-force-vertraging na 5 mislukte
  pogingen).
- Pagina's aanmaken/bewerken/verwijderen, publiceren/concept, een
  instelbare homepagina en een handmatige menuvolgorde. Automatische,
  unieke URL-slugs afgeleid van de titel (aanpasbaar).
- **Blokkenbouwer**: elke pagina bestaat uit een lijst eenvoudige blokken
  die je toevoegt, herschikt (↑/↓) en verwijdert in het beheerpaneel —
  geen vrije HTML-editor meer, dus geen manier om per ongeluk kapotte
  opmaak of scripts in te voegen:
  - **Tekst** — optionele titel + platte tekst (alinea's gescheiden door
    een lege regel).
  - **Foto** — afbeeldings-URL, alt-tekst (verplicht, toegankelijkheid) en
    optioneel bijschrift.
  - **Quote** — citaat + optionele bron.
  - **Lijst** — titel, stijl (opsomming/vinkjes) en items (één per regel).
  - **Knoppen** — tot 3 knoppen, één per regel als `Tekst | link`.
  - Links/afbeeldings-URL's worden serverside gevalideerd (enkel `/...`,
    `http(s)://`, `mailto:` of `tel:` — geen `javascript:`-injectie
    mogelijk).
- **4 frontend-varianten**, per pagina instelbaar (dropdown in de
  pagina-editor): A "Helder & rustig", B "Warm & zacht", C "Natuurlijk &
  aards", D "Strak & minimalistisch". Alle vier delen dezelfde rustige,
  ruime opbouw — enkel kleuren, typografie en afronding wisselen; de
  contactpagina volgt automatisch de variant van de homepagina voor een
  consistente uitstraling.
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

## Beveiliging — belangrijk voor je gaat live

- **Nooit** `config/config.php` in git committen (staat al in `.gitignore`).
- Gebruik een sterk, uniek wachtwoord voor het beheerdersaccount.
- Zet HTTPS aan zodra de site live staat.
- `public/admin/install.php` sluit zichzelf automatisch af zodra er één
  account bestaat — dat is de enige manier waarop nieuwe accounts kunnen
  ontstaan; er is bewust geen registratiepagina.

## Mogelijke volgende stappen (niet in deze MVP)

- Media/afbeeldingen-uploadbeheer (map `public/uploads/` staat al klaar).
- E-mailnotificatie bij een nieuw contactformulier (bv. via PHP `mail()` of
  een transactionele e-maildienst).
- Meerdere beheerders met rollen, wachtwoord-reset via e-mail.
- Paginering als het aantal pagina's/berichten groot wordt.
