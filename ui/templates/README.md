## User Interface templates

Twig user interface templates reside in the `default` subdirectory.
Make changes there if you are extending the upstream base functionality.

Custom templates reside in the __custom__ directory.  The directory name
is specified in config/config.php; by default, `custom` is used.

Custom templates may replace or override default templates.  To replace a
template, give the custom template the same filename as the default template.
To extend a default template, reference it as 'default/__template__', where
__template__ is the filename of the default template; e.g.,

    {% extends 'default/content.html' %}

### Filters

In addition to the usual Twig filters, the following
application-specific filters are also available for use:

#### decorate
The 'decorate' filter decorates an asset URI in such a way that it
will change when the referenced asset changes.  In this way, assets
such as .js and .css files are guaranteed to load anew in the client
browser whenever they change in the service.

Example:

    <link rel="stylesheet" type="text/css" href="{{ 'css/mystyle.css' | decorate }}" />

where `css/mystyle.css` is the path to a real asset, relative to the
project root.

#### markdown
The 'markdown' filter converts all markdown in the string to HTML.

#### smartURL
The 'smartURL' filter inserts HTML anchor tags for all URLs in the
string, thereby rendering the URLs clickable.


### Template environment

In addition to controller-specific variables, the following global
variables are available in the template environment:

#### Menu:
* app.menu - 1st level menu
* app.submenu - 2nd level menu
* app.tertiary - text for breadcrumb leaf
* app.extra - extra HTML for the menu area

#### Content:
* app.content.template - template name or null
* app.content.data - HTML content in lieu of a template
* app.content.title - title of current page or null

#### Request:
* app.request - request variables

#### Application:
* app.session - session.  See Engine::session
* app.sso - true if SSO is supported
* app.version - application version

#### Configuration:
The following values are available from `config/config.php`:
* app.copyright
* app.email.*
* app.favicon
* app.logo
* app.nme
* app.station
* app.station_full
* app.station_slogan
* app.station_title
* app.stylesheet
* app.urls.*
