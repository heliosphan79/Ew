# Thema's

Elk thema is een map onder `themes/<slug>/` met drie bestanden:

```
themes/<slug>/
    theme.json   verplicht
    style.css    verplicht
    logo.svg     optioneel (of logo.png/.jpg/.webp)
```

Beheerd via "Thema's" in het beheerpaneel — ge-upload als een `.zip` met
exact deze bestanden op het hoogste niveau (geen submappen, op het
toegestane `logo.*` na). Alles daarbuiten wordt geweigerd bij het
uploaden (zie `admin/theme-upload.php`).

## theme.json

```json
{
    "name": "Naam van het thema",
    "description": "Korte omschrijving (optioneel, max. 300 tekens).",
    "schemes": [
        {"key": "a", "label": "A — Omschrijving van kleurschema A"},
        {"key": "b", "label": "B — Omschrijving van kleurschema B"},
        {"key": "c", "label": "C — Omschrijving van kleurschema C"}
    ]
}
```

`schemes` moet altijd **exact 3** items bevatten, met sleutels `a`, `b`
en `c` (elk precies één keer) — dat zijn dezelfde sleutels als het
bestaande "Frontend-variant"-veld per pagina, dus bij het wisselen van
thema verandert enkel wat A/B/C betekenen, niet welke pagina's welke
variant gekozen hebben.

## style.css

Een volledig, zelfstandig stylesheet — geen uitbreiding op een ander
bestand. **De eenvoudigste en betrouwbaarste manier om een nieuw thema te
bouwen is `themes/eigen-wijzer-klassiek/style.css` te kopiëren als
startpunt**: alle class-namen en de pagina-structuur (header/nav, blok-
types, formulieren, footer, ...) blijven zo gegarandeerd behouden, en je
herschrijft enkel de ontwerptokens en de visuele uitwerking erachter.

Twee verplichte onderdelen van het contract:

1. **CSS-variabelen op `:root`** — dit zijn de tokens die de rest van het
   stylesheet (en een aantal inline e-mailsjablonen) via `var(--naam)`
   aanspreekt:

   | Variabele | Gebruik |
   |---|---|
   | `--text`, `--text-muted`, `--text-subtle` | Tekstkleuren, aflopend in nadruk |
   | `--bg`, `--surface` | Paginakleur vs. "kaart"-achtergrond (blokken, formulieren) |
   | `--accent`, `--accent-hover`, `--accent-contrast` | Merkkleur, hover-variant, en de tekstkleur erop (bv. knoptekst) |
   | `--btn-primary-bg`, `--btn-primary-hover` | Achtergrond van de primaire knop |
   | `--border` | Standaard randkleur |
   | `--eyebrow-color` | Kleur van het kleine "eyebrow"-label boven een titel |
   | `--quote-bg`, `--quote-text` | Achtergrond/tekst van het quote-blok |
   | `--color-error`, `--color-success` | Formulierfeedback |
   | `--radius` | Standaard afrondingsradius |
   | `--shadow` | Standaard schaduw (kaarten, hover-states) |
   | `--content-width` | Maximale breedte van de inhoudskolom |
   | `--font-heading`, `--font-body` | Lettertype-stacks (zie hieronder) |
   | `--dur`, `--ease` | Duur/easing van de (subtiele) overgangen |

2. **Drie kleurschema's** via `body[data-theme="b"]`/`body[data-theme="c"]`
   die dezelfde variabelen herdefiniëren (schema A is gewoon de waarden
   op `:root`, zonder attribuut) — exact het patroon dat
   `themes/eigen-wijzer-klassiek/style.css` nu al gebruikt.

Verder gebruikt elke paginasjabloon (header, footer, elk bloktype uit de
blokkenbouwer, elk formulier) een vaste set class-namen — die namen
*moeten* overeind blijven (de HTML zelf wijzigt niet per thema, enkel de
CSS erachter), maar hoe je ze stijlt is volledig vrij: andere
lettertypes, andere afstand/ritme, andere knopvormen, andere
kaartschaduwen, zelfs een heel andere lay-out via CSS Grid/Flexbox
(volgorde, uitlijning) binnen de bestaande HTML-structuur.

**Geen eigen lettertypebestanden.** Om dezelfde privacy-afweging aan te
houden als het bestaande ontwerp (geen Google Fonts-CDN, geen
derde-partij-lettertypeserver), gebruikt `--font-heading`/`--font-body`
een systeem-fallbackstack (bv. `Georgia, 'Times New Roman', serif` of
`-apple-system, 'Segoe UI', Roboto, sans-serif`) — geen `@font-face` naar
een extern bestand. Dat levert nog altijd duidelijk verschillende
typografische karakters op (klassiek serif vs. strak geometrisch vs.
rond-vriendelijk, ...), enkel zonder maatwerk-lettertypebestanden.

## logo.svg / .png / .jpg / .webp (optioneel)

Vervangt het ingebouwde, geanimeerde kompasnaald-logo in de header. Wordt
altijd als een gewone `<img>` getoond (nooit inline in de pagina
geplakt) — dus geen dynamische herkleuring per kleurschema via
CSS-variabelen, en geen scroll-/hover-animatie zoals het ingebouwde
logo. Ontbreekt dit bestand, dan blijft het ingebouwde logo zichtbaar.

Aanbevolen formaat: vierkant of licht breder dan hoog, representatief
rond 36×36px (wordt zo getoond in de header).
