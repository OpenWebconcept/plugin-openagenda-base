# Evenementenfeed (RSS)

De OpenAgenda Base plugin levert een RSS 2.0-feed met **aankomende evenementen**. De feed gebruikt alleen standaard RSS-elementen en gangbare uitbreidingen,
zodat je hem kunt gebruiken in nieuwsbrieftools (zoals LaPosta of Mailchimp), feedreaders, andere websites of een eigen koppeling.

In de voorbeelden hieronder staat `https://agenda.example.nl` voor het adres van de OpenAgenda-website. Vervang dit door het echte adres.

## Het feedadres

```
https://agenda.example.nl/feed/openagenda/
```

Gebruikt de website geen "mooie" permalinks, gebruik dan:

```
https://agenda.example.nl/?feed=openagenda
```

Het adres van de feed staat ook op de instellingenpagina (zie hieronder).

### Waar wordt de feed aangekondigd?

Feedreaders, browsers en koppelingen kunnen de feed zelf vinden:

- **In de HTML** (websites met een eigen thema): op elke pagina staat in de `<head>` een
  `<link rel="alternate" type="application/rss+xml">` naar de feed. Op een (gefilterd) evenementenoverzicht of een categoriepagina wijst die naar de feed met
  hetzelfde filter, bijvoorbeeld `/feed/openagenda/?thema=muziek`.
- **In de REST API** (headless websites): elke `GET`-aanvraag op `/wp-json/owc/openagenda/v1/…` geeft de header
  `Link: <https://agenda.example.nl/feed/openagenda/>; rel="alternate"; type=application/rss+xml`. De index van de API
  (`/wp-json/owc/openagenda/v1`) noemt de feed onder `_links.alternate`.

Gebruik je OpenAgenda als backend voor een andere website, zet dan op die website zelf een `<link rel="alternate">` naar de feed in de `<head>`.

## Wat staat er in de feed?

- Alleen **aankomende** evenementen: evenementen waarvan de eerstvolgende datum vandaag of later is. Verlopen evenementen verdwijnen vanzelf uit de feed.
- Bij terugkerende evenementen telt de **eerstvolgende** datum.
- Alleen gepubliceerde evenementen; concepten, privé-evenementen en evenementen met een wachtwoord komen er niet in. Van een gekoppelde locatie die
  (nog) niet gepubliceerd is, komen naam en adres niet in de feed.

## Standaardinstellingen

Ga in WordPress naar **Instellingen › OpenAgenda Settings**, onderdeel **Evenementenfeed**.

| Instelling          | Standaard                                                                                     | Mogelijkheden                                                              |
|---------------------|-----------------------------------------------------------------------------------------------|----------------------------------------------------------------------------|
| Sorteren op         | Evenementdatum (eerstvolgende)                                                                | Evenementdatum of publicatiedatum                                          |
| Sorteervolgorde     | Oplopend                                                                                      | Oplopend (eerstvolgende/oudste eerst) of aflopend (laatste/nieuwste eerst) |
| Aantal items        | Gelijk aan **Instellingen › Lezen › "Syndicatiefeeds tonen de meest recente"** (standaard 10) | 1 tot en met 100                                                           |
| Itemdatum (pubDate) | Evenementdatum                                                                                | Evenementdatum of publicatiedatum                                          |
| URL-structuur       | Leeg (de WordPress-URL van het evenement)                                                     | Zie [Links naar een andere website](#links-naar-een-andere-website-headless) |

**Itemdatum (pubDate):** veel nieuwsbrieftools en feedreaders tonen bij elk item een datum, en sommige gebruiken die om te bepalen wat "nieuw" is. Staat deze op
*Evenementdatum*, dan is dat de begindatum en -tijd van het evenement. Staat deze op *Publicatiedatum*, dan is dat het moment waarop het evenement in OpenAgenda
is gepubliceerd.

## Instellingen per feedadres aanpassen

Alle standaardinstellingen kun je per feedadres overschrijven met parameters achter het adres. Zo kan één website verschillende feeds leveren, bijvoorbeeld een
aparte feed per nieuwsbrief.

| Parameter      | Waarden                                         | Betekenis                                                                          |
|----------------|-------------------------------------------------|------------------------------------------------------------------------------------|
| `limit`        | een getal, maximaal 100                         | Aantal evenementen (per pagina)                                                    |
| `page`         | een getal, standaard 1                          | De pagina, zie [Pagina's](#paginas)                                                |
| `orderby`      | `event_date` of `publish_date`                  | Sorteren op evenementdatum of publicatiedatum                                      |
| `order`        | `asc` of `desc`                                 | Oplopend of aflopend                                                               |
| `{taxonomie}`  | één of meer term-slugs, gescheiden door komma's | Alleen evenementen met (minstens één van) deze termen, bijvoorbeeld `thema=muziek` |
| `tax_relation` | `AND` (standaard) of `OR`                       | Hoe filters op verschillende taxonomieën worden gecombineerd                       |

De eerste parameter begint met `?`, elke volgende met `&`.

### Voorbeelden

De eerstvolgende 5 evenementen:

```
https://agenda.example.nl/feed/openagenda/?limit=5
```

Alle aankomende evenementen in het thema Muziek:

```
https://agenda.example.nl/feed/openagenda/?thema=muziek
```

Evenementen in het thema Muziek **of** Lezing, maximaal 20:

```
https://agenda.example.nl/feed/openagenda/?thema=muziek,lezing&limit=20
```

De 10 meest recent toegevoegde evenementen, nieuwste eerst:

```
https://agenda.example.nl/feed/openagenda/?orderby=publish_date&order=desc&limit=10
```

Gratis evenementen voor kinderen, de eerstvolgende 8:

```
https://agenda.example.nl/feed/openagenda/?thema=gratis&limit=8
```

> Let op: binnen één taxonomie betekent een komma "of". `?thema=gratis,voor-kids` geeft evenementen die gratis zijn **of** voor kinderen. Wil je evenementen die
> aan twee voorwaarden tegelijk voldoen, dan moeten die voorwaarden in verschillende taxonomieën staan, bijvoorbeeld:
>
> ```
> https://agenda.example.nl/feed/openagenda/?thema=voor-kids&wijk=centrum
> ```
>
> (Dit voorbeeld werkt alleen als er een taxonomie "Wijk" bestaat.) Met `&tax_relation=OR` krijg je evenementen die aan minstens één van beide voorwaarden
> voldoen.

### Taxonomie- en term-slugs opzoeken

- **Taxonomieën** maak je aan via **Evenementen › Create Taxonomies**. De naam in de feedparameter is de taxonomienaam in kleine letters, zonder spaties of
  leestekens. Zo wordt "Thema" `thema`.
- **Termen** (de categorieën zelf) staan onder **Evenementen › [naam van de taxonomie]**. Gebruik de waarde uit de kolom **Slug**, bijvoorbeeld `muziek`,
  `eten-drinken` of `voor-kids`.

Een onbekende taxonomie of term levert geen foutmelding op. Een onbekende taxonomie wordt genegeerd en een onbekende term geeft een lege feed. Controleer het
adres dus in de browser voordat je het in een nieuwsbrief gebruikt.

## Pagina's

Zijn er meer evenementen dan het aantal items, dan is de feed verdeeld over pagina's. Pagina 2 van een feed haal je op met `page=2`:

```
https://agenda.example.nl/feed/openagenda/?thema=muziek&limit=20&page=2
```

De feed vertelt zelf hoeveel er zijn en waar de andere pagina's staan (volgens [RFC 5005](https://www.rfc-editor.org/rfc/rfc5005#section-3) en
[OpenSearch](https://github.com/dewitt/opensearch/blob/master/opensearch-1-1-draft-6.md#opensearch-response-elements)):

```xml
<atom:link href="…/feed/openagenda/?thema=muziek&amp;limit=20&amp;page=2" rel="self" type="application/rss+xml" />
<atom:link href="…/feed/openagenda/?thema=muziek&amp;limit=20" rel="first" type="application/rss+xml" />
<atom:link href="…/feed/openagenda/?thema=muziek&amp;limit=20" rel="previous" type="application/rss+xml" />
<atom:link href="…/feed/openagenda/?thema=muziek&amp;limit=20&amp;page=3" rel="next" type="application/rss+xml" />
<atom:link href="…/feed/openagenda/?thema=muziek&amp;limit=20&amp;page=3" rel="last" type="application/rss+xml" />
<opensearch:totalResults>47</opensearch:totalResults>
<opensearch:startIndex>21</opensearch:startIndex>
<opensearch:itemsPerPage>20</opensearch:itemsPerPage>
```

Past alles op één pagina, dan ontbreken de links `first`, `previous`, `next` en `last`.

Let op:

- Nieuwsbrieftools en de meeste feedreaders lezen alleen de eerste pagina. Wil je meer evenementen in een nieuwsbrief, verhoog dan `limit`.
- De feed bevat alleen aankomende evenementen, dus de inhoud van de pagina's schuift elke dag op. Wie alle evenementen wil ophalen, doet dat het best in één
  keer, of via de REST API.

## Links naar een andere website (headless)

Standaard linkt elk evenement naar zijn pagina op de OpenAgenda-website zelf, bijvoorbeeld `https://agenda.example.nl/event/winterfestijn/`. Wordt OpenAgenda
als backend gebruikt en staan de evenementen op een andere website, dan bestaat die pagina niet (of niet voor bezoekers). Stel dan bij
**Instellingen › OpenAgenda Settings › Permalinks van evenementen** de **URL-structuur** in van de evenementpagina's op de andere website, bijvoorbeeld:

```
https://www.example.nl/agenda/%postname%/
```

Met de knoppen onder het veld voeg je tags toe, net als bij **Instellingen › Permalinks**:

| Tag            | Wordt                                                                                                            |
|----------------|------------------------------------------------------------------------------------------------------------------|
| `%year%`       | Jaar waarin het evenement is gepubliceerd, bijvoorbeeld `2026`                                                   |
| `%monthnum%`   | Maand van publicatie, bijvoorbeeld `05`                                                                          |
| `%day%`        | Dag van publicatie, bijvoorbeeld `28`                                                                            |
| `%hour%`       | Uur van publicatie, bijvoorbeeld `15`                                                                            |
| `%minute%`     | Minuut van publicatie, bijvoorbeeld `43`                                                                         |
| `%second%`     | Seconde van publicatie, bijvoorbeeld `33`                                                                        |
| `%post_id%`    | ID van het evenement, bijvoorbeeld `423`                                                                         |
| `%postname%`   | Slug van het evenement, bijvoorbeeld `winterfestijn`                                                             |
| `%category%`   | Eerste term van de eerste evenemententaxonomie, bijvoorbeeld `muziek`; onderliggende termen als `cultuur/muziek` |
| `%author%`     | Gebruikersnaam (nicename) van de auteur                                                                          |
| `%{taxonomie}%` | Eerste term van die taxonomie, bijvoorbeeld `%thema%`                                                           |

De datumtags gaan over de **publicatiedatum** van het evenement in WordPress, net als bij berichten, niet over de datum van het evenement.

De structuur moet een volledige URL zijn (beginnend met `https://`) en `%postname%` of `%post_id%` bevatten, zodat elk evenement een eigen URL krijgt.
Tags mogen alleen in het pad of de querystring staan, niet in de domeinnaam. Een onbekende tag wordt geweigerd.

Let op: `%author%` zet de gebruikersnaam (nicename) van de auteur in openbare URL's. Die is vaak afgeleid van de inlognaam.

Is de URL-structuur ingevuld, dan geldt die overal waar WordPress de URL van een gepubliceerd evenement maakt:

- `<link>` van elk item in de feed;
- `link` van elk evenement in de REST API;
- de links "Bekijken" in de beheeromgeving.

De `<link>` van de feed zelf wordt het deel van de structuur vóór de eerste tag, hier `https://www.example.nl/agenda/`.

Wat in WordPress blijft:

- **Voorbeeld bekijken** gebruikt de WordPress-URL; de andere website kan geen niet-opgeslagen wijzigingen tonen. Concepten en ingeplande evenementen
  houden ook hun WordPress-URL.
- De **slug** blijft te wijzigen in de editor; die toont daarbij de URL op de andere website.
- Korte links en oude links naar een evenement op de OpenAgenda-website (zoals `/?p=123`) sturen door naar de URL op de andere website.
- De **WordPress-sitemap** (`/wp-sitemap.xml`) bevat dan geen evenementen meer: een sitemap mag alleen URL's van de eigen website bevatten. Neem de
  evenementen op in de sitemap van de andere website.

Laat het veld leeg voor een gewone WordPress-website met een eigen thema.

## Velden per evenement

Elk evenement is een `<item>` in de feed met de volgende velden:

| Veld                          | Inhoud                                                                                         | Voorbeeld                                                 |
|-------------------------------|------------------------------------------------------------------------------------------------|-----------------------------------------------------------|
| `title`                       | Titel                                                                                          | Koffie met… Breathfulness                                 |
| `link`                        | Adres van de evenementpagina                                                                   | https://agenda.example.nl/event/koffie-met-breathfulness/ |
| `guid`                        | Vaste unieke code van het evenement, ook uniek over websites heen                              | https://agenda.example.nl/?post_type=event&p=48           |
| `pubDate`                     | Itemdatum (zie instellingen)                                                                   | Tue, 03 Nov 2026 10:30:00 +0100                           |
| `description`                 | Korte tekst (teaser), zonder opmaak                                                            | 'De bibliotheek van het lichaam' - …                      |
| `content:encoded`             | Volledige omschrijving, met opmaak (HTML)                                                      |                                                           |
| `category`                    | Eén per term; de taxonomie staat in het attribuut `domain`                                     | `<category domain="thema">Gratis</category>`              |
| `enclosure` / `media:content` | Afbeelding van het evenement (de eerste evenementafbeelding, anders de uitgelichte afbeelding) |                                                           |
| `ev:startdate`                | Begin van de eerstvolgende keer, machineleesbaar (ISO 8601)                                    | 2026-11-03T10:30:00+01:00                                 |
| `ev:enddate`                  | Einde van die keer (bij een meerdaags evenement de laatste dag), machineleesbaar (ISO 8601)    | 2026-11-03T11:30:00+01:00                                 |
| `ev:location`                 | Locatienaam en adres                                                                           | Bibliotheek Centrum, Koningsplein 1, 2981 EA Ridderkerk   |
| `ev:organizer`                | Organisator (als ingevuld)                                                                     |                                                           |
| `openagenda:date_text`        | Leesbare omschrijving van de datum of data                                                     | 3 november                                                |
| `openagenda:time_text`        | Leesbare tijd                                                                                  | 10:30 - 11:30                                             |
| `openagenda:price_text`       | Leesbare prijs                                                                                 | Gratis, € 15,00, Vanaf € 4,00, € 5,00 - € 15,00           |
| `openagenda:location_name`    | Locatienaam                                                                                    | Bibliotheek Centrum                                       |
| `openagenda:address`          | Adres                                                                                          | Koningsplein 1, 2981 EA Ridderkerk                        |

De `ev:`-velden volgen de standaard [RSS 1.0 event module](http://web.resource.org/rss/1.0/modules/event/) en zijn bedoeld voor koppelingen die zelf datums
verwerken. De `openagenda:`-velden zijn kant-en-klare teksten in de taal van de website, handig voor nieuwsbrieftools die datums en prijzen niet zelf kunnen
opmaken. Lege velden worden weggelaten.

## Gebruik in LaPosta

LaPosta kan een RSS-feed inlezen in een sjabloon op maat ("Joe" of een zelfgemaakt sjabloon, niet in de drag & drop-editor). Zie de
LaPosta-documentatie: [Tags om een RSS of Atom feed te importeren](https://docs.laposta.nl/article/531-tags-om-een-rss-of-atom-feed-te-importeren).

De `lp-rss`-tags hieronder zijn **LaPosta-sjabloontags**: je zet ze in de HTML van het sjabloon in LaPosta, niet in de feed. De feed zelf blijft gewone RSS (er is geen aparte LaPosta-variant of instelling nodig). LaPosta haalt de feed op via `lp-rss-url` en vult per evenement elke `<lp-rss-content:veldnaam>` met het gelijknamige veld uit de feed, bijvoorbeeld `<lp-rss-content:title>` met `<title>` en `<lp-rss-content:openagenda:date_text>` met `<openagenda:date_text>` (zie [Velden per evenement](#velden-per-evenement)).

Voorbeeld van een blok met de eerstvolgende 5 muziekevenementen:

```html

<lp-rss lp-rss-url="https://agenda.example.nl/feed/openagenda/?thema=muziek" lp-rss-type="rss" lp-rss-maxitems="5">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="left">
                <lp-rss-image width="560" lp-edit>
            </td>
        </tr>
        <tr>
            <td align="left">
                <a href="<lp-rss-content:link>">
                    <lp-rss-content:title lp-type="singleline">
                </a>
            </td>
        </tr>
        <tr>
            <td align="left">
                <lp-rss-content:openagenda:date_text> ·
                    <lp-rss-content:openagenda:time_text><br>
                        <lp-rss-content:openagenda:location_name> ·
                            <lp-rss-content:openagenda:price_text>
            </td>
        </tr>
        <tr>
            <td align="left">
                <lp-rss-content:description lp-type="multiline">
            </td>
        </tr>
    </table>
</lp-rss>
```

Tips:

- Bepaal **welke** evenementen in de nieuwsbrief komen met het feedadres (`thema`, `limit`). Met `lp-rss-maxitems` of `lp-rss-range` bepaal je hoeveel daarvan
  LaPosta toont.
- LaPosta filtert ook zelf met `lp-rss-filter`, bijvoorbeeld `lp-rss-filter="|category|Muziek,Lezing"`. Filteren via het feedadres is betrouwbaarder, omdat de
  website dan alleen de juiste evenementen levert.
- `<lp-rss-content:datenl>` toont de `pubDate` in Nederlandse opmaak. Zet de **Itemdatum** op *Evenementdatum* als je daarmee de datum van het evenement wilt
  tonen.
- Nog niet getest: of LaPosta velden met een voorvoegsel (zoals `openagenda:date_text` of `ev:startdate`) kan lezen. Werkt dat niet, gebruik dan `datenl` voor
  de datum en neem de overige informatie op in de teaser.
- Test een nieuw sjabloon altijd eerst met een testmailing.

## Gebruik in andere tools

- **Mailchimp (RSS-to-email)**, **Brevo** en vergelijkbare tools: gebruik het feedadres. Titel, link, omschrijving, afbeelding en `pubDate` worden overal
  ondersteund. Zet de **Itemdatum** op *Publicatiedatum* als de tool alleen "nieuwe" items mag versturen. Anders kan een evenement opnieuw als nieuw worden
  gezien wanneer de eerstvolgende datum verschuift.
- **Feedreaders**: werken zonder extra instellingen.
- **Eigen koppelingen**: gebruik `guid` als vaste sleutel, `ev:startdate`/`ev:enddate` voor datums en `category` (met `domain`) voor de indeling. Heb je meer
  gegevens nodig, gebruik dan de OpenAgenda REST API (zie de readme).

## Aandachtspunten

- De feed werkt alleen als **WP-cron** draait. Elk uur wordt de eerstvolgende datum van elk evenement bijgewerkt. Staat WP-cron uit (`DISABLE_WP_CRON`), dan
  moet een servercronjob `wp-cron.php` aanroepen.
- De feed stuurt een `ETag` mee die verandert zodra de inhoud verandert, ook als er alleen een evenement voorbij is of een instelling is gewijzigd.
  Tools die met `If-None-Match` vragen of er iets nieuws is, krijgen dan `304 Not Modified` of de nieuwe feed.
- Nieuwsbrieftools en feedreaders slaan feeds vaak een tijd op. Een wijziging in de agenda is daar dus niet altijd direct zichtbaar.
- Werkt het feedadres niet (pagina niet gevonden)? Sla dan **Instellingen › Permalinks** één keer opnieuw op.
- Evenementen op dezelfde dag staan op begintijd, ook over pagina's heen.

## Voor ontwikkelaars

De feed is aan te passen met de filters `openagenda_base_feed_args`, `openagenda_base_feed_query_args`, `openagenda_base_feed_item`,
`openagenda_base_feed_title` en `openagenda_base_feed_image_size`. Zie `src/frontend/class-feed.php`.

De URL's van evenementen zijn aan te passen met de filters `openagenda_base_event_permalink` (de URL van één evenement) en
`openagenda_base_event_overview_url` (de `<link>` van de feed). Zie `src/frontend/class-event-permalink.php`.
