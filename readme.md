# OpenAgenda Plugin

This plugin adds Events and Locations to WordPress which can be retrieved via the OpenAgenda REST API.

## Requirements

### OpenAgenda

In order to make the OpenAgenda Plugin work, you will need to have a WordPress installation with at least the following installed (and activated):

* [WordPress](https://wordpress.org/)
* [CMB2](https://wordpress.org/plugins/cmb2/)

On this WordPress installation you will have to enable pretty permalinks (Settings > Permalinks > Select any of the options that is not plain).

There are two possible setups for the OpenAgenda, this can be:

1. On the WordPress installation of an existing website.
2. On a completely new WordPress installation.

In all scenarios the OpenAgenda needs to have the following installed (and activated):

* [WordPress](https://wordpress.org/)
* [CMB2](https://wordpress.org/plugins/cmb2/)
* [OpenAgenda Base](https://github.com/OpenWebconcept/plugin-openagenda-base)

With this installed you can use the OpenAgenda Base plugin in your WordPress website.

If you chose for option 2 (new WordPress installation), you will probably need to install a WordPress theme. Since the OpenAgenda plugin is a REST API, it can be used in any WordPress theme.

## Installation

### Manual installation

1. Upload the `openagenda-base` folder to the `/wp-content/plugins/` directory.
2. Activate the OpenAgenda Base Plugin through the 'Plugins' menu in WordPress.

### Composer installation

1. `composer source git@github.com:OpenWebconcept/plugin-openagenda-base.git`
2. `composer require acato/openagenda-base`
3. `cd /wp-content/plugins/openagenda-base`
4. `composer install`
5. Activate the OpenAgenda Base Plugin through the 'Plugins' menu in WordPress.

## Development

### Coding Standards

Please remember, we use the WordPress PHP Coding Standards for this plugin! (https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/) To check if your changes are compatible with these standards:

*  `cd /wp-content/plugins/openagenda-base`
*  `composer install` (this step is only needed once after installing the plugin)
*  `./vendor/bin/phpcs --standard=phpcs.xml.dist .`
*  See the output if you have made any errors.
    *  Errors marked with `[x]` can be fixed automatically by phpcbf, to do so run: `./vendor/bin/phpcbf --standard=phpcs.xml.dist .`

N.B. the `composer install` command will also install a git hook, preventing you from committing code that isn't compatible with the coding standards.

### Translations

Update the POT file after adding/changing source texts.

```
wp i18n make-pot . languages/openagenda-base.pot --exclude="node_modules/,vendor/" --domain="openagenda-base"
```

Update the PO file(s) after adding/changing source texts.

```
wp i18n update-po languages/openagenda-base-nl_NL.po languages/
```

Compile the PO file(s) to MO, PHP and JSON after adding/changing source texts.

```
wp i18n make-php languages/
wp i18n make-mo languages/
wp i18n make-json languages/ --no-purge
```

(Currently, only PHP and MO files are in use.)

### Event and Location Custom Post Types
This plugin adds two custom post types to WordPress:
- Event
- Location

### REST API Endpoints
This plugin adds the following REST API GET-endpoints:
- `/wp-json/owc/openagenda/v1`
- `/wp-json/owc/openagenda/v1/items`
- `/wp-json/owc/openagenda/v1/items/id/{id}`
- `/wp-json/owc/openagenda/v1/fields`
- `/wp-json/owc/openagenda/v1/locations`
- `/wp-json/owc/openagenda/v1/locations/id/{id}`

This plugin adds the following REST API POST-endpoint:
- `/wp-json/owc/openagenda/v1/items`

The POST-endpoint is used to create new items in the OpenAgenda. The endpoint is protected by Basic Authentication with Application Passwords. The user needs to have the capability `edit_posts` to be able to use this endpoint.

In the plugin settings located at `Settings > OpenAgenda Settings` you can set the user that is allocated to items created with this POST-endpoint.

Further documentation about using the REST API can be found in the [OpenAgenda API documentation](https://redocly.github.io/redoc/?url=https://raw.githubusercontent.com/OpenWebconcept/plugin-openagenda-base/main/openapi/openapi.yaml&nocors).

### Event dates
In the event post type you can set the date(s) for an event with the following date types:
- Specific date or date range
- Configurable recurring date pattern

In both cases after saving the event, the date(s) within the range of the pattern will be generated for the first year and saved as post meta for the event. All generated dates are included in the REST API response.

### Cron jobs
This plugin uses two cron jobs:
- `openagenda_cron_event_weekly`: a cron job to generate the recurring dates for the events. The cron job runs once a week on Sunday.
- `openagenda_add_cron_schedule`: a cron job to set the expiration date of an event, based on generated dates of the first cron job. On the expiration date the event will be set to draft. This cron job runs every 15 minutes.

Make sure that the WordPress cron is running on your server. If you have disabled the cron via `define('DISABLE_WP_CRON', true);` in the wp-config.php then make sure you trigger the cron jobs with a server cron job.
If you are not sure, you can use the plugin [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) to check if the cron jobs are running.

### Event feed (RSS)
This plugin adds an RSS 2.0 feed of upcoming events at `/feed/openagenda/` (or `?feed=openagenda` without pretty permalinks). The feed uses standard RSS 2.0 elements and established modules, so it can be used in newsletter tools (e.g. LaPosta, Mailchimp), feed readers and other websites.

Per item the feed contains:
- `title`, `link`, `guid`, `pubDate`, `description` (the teaser) and `content:encoded` (the full description)
- `category` for every term, with the taxonomy name as `domain`
- `enclosure` and `media:content` for the first event image (or the featured image)
- `ev:startdate`, `ev:enddate`, `ev:location` and `ev:organizer` ([RSS 1.0 event module](http://web.resource.org/rss/1.0/modules/event/)), the start being the next occurrence of the event
- `openagenda:date_text`, `openagenda:time_text`, `openagenda:price_text`, `openagenda:location_name` and `openagenda:address`: ready to display texts in the site language

Only upcoming events are included. The defaults are set at `Settings > OpenAgenda Settings`:
- Sort by: event date (next occurrence, default) or publication date
- Sort order: ascending (default) or descending
- Number of items: defaults to `Settings > Reading > Syndication feeds show the most recent`, maximum 100
- Item date (`pubDate`): event date (default) or publication date

These defaults can be overridden per feed url with query parameters:
- `orderby`: `event_date` or `publish_date`
- `order`: `asc` or `desc`
- `limit`: number of items per page
- `page`: the page, when there are more events than `limit`. The other pages are linked in the channel with `atom:link` `rel="first|previous|next|last"` ([RFC 5005](https://www.rfc-editor.org/rfc/rfc5005#section-3)), the total is in `opensearch:totalResults`
- `{taxonomy}`: comma separated term slugs, e.g. `?thema=muziek,gratis` (items in any of the terms)
- `tax_relation`: `AND` (default) or `OR`, how multiple taxonomies are combined

The feed is advertised with a `<link rel="alternate">` in the html head (filtered like the current event archive), and, for headless sites, with a `Link: <…>; rel="alternate"` header on every `GET` response of the OpenAgenda REST API and in the namespace index (`/wp-json/owc/openagenda/v1`).

A user guide in Dutch, with example urls and a LaPosta template, is available in [docs/evenementenfeed.md](docs/evenementenfeed.md).

### Event permalinks (headless)
When the events are shown on another website, set the url structure of the event pages on that website at `Settings > OpenAgenda Settings > Event permalinks`, e.g. `https://www.example.com/agenda/%postname%/`. All WordPress permalink tags are available (`%year%`, `%monthnum%`, `%day%`, `%hour%`, `%minute%`, `%second%`, `%post_id%`, `%postname%`, `%category%`, `%author%`), plus one per event taxonomy (e.g. `%thema%`); `%category%` is the first term of the first event taxonomy. The structure must contain `%postname%` or `%post_id%`.

Published events then get this url wherever WordPress builds their permalink: the feed, the `link` field in the REST API, the admin. Previews keep the WordPress url, the slug stays editable, and events are left out of the WordPress sitemap (a sitemap may only list urls of its own site). The feed's channel link becomes the part of the structure before the first tag (e.g. `https://www.example.com/agenda/`). Leave it empty to use the WordPress permalink. Filters: `openagenda_base_event_permalink` and `openagenda_base_event_overview_url`.

Developers can change the feed with the filters `openagenda_base_feed_args`, `openagenda_base_feed_query_args`, `openagenda_base_feed_item`, `openagenda_base_feed_title` and `openagenda_base_feed_image_size`.

### Event Taxonomies
For the event post type you can create your own taxonomies, located at `Events > Create Taxonomies`. These taxonomies are included in the REST API response.
When a taxonomy has terms attached to it, the taxonomy can't be deleted.

### Integration with plugins
This plugin is compatible with the following open source projects:
* [CMB2](https://wordpress.org/plugins/cmb2/)
* [Image Background Focus Position](https://www.wordpress-focalpoint.com/)



