### Contributing

1. Fork the repo and apply your changes in a feature branch.
2. Issue a pull request.


### Getting Started

1. Fork Zookeeper Online
2. Clone Zookeeper from your fork
3. Create and check out a new branch for your feature or enhancement
4. Copy config/config.example.php to config/config.php and edit as appropriate
5. Apply and test your changes.  Please keep the source code style
   as consistent as possible with the existing codebase.  Zookeeper
   Online uses the [PSR-2 coding style](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
   with a couple of exceptions:

   * Opening braces for classes go on the SAME line.
   * Opening braces for methods go on the SAME line.

6. Push your changes to your branch at github.
7. Create a pull request


### Tour

Zookeeper is organized into distinct components for request handling,
application/runtime infrastructure, database/domain objects, presentation,
and external services.

Zookeeper APIs follow the JSON:API standard.  More information about
[Zookeeper JSON:API](docs/API.md) is available here.

The following is an overview of the source code directory structure:

    project-root/
        api/
            JSON:API implementation.

        config/
            Application configuration data.

            config.php
                 This is the main configuration file.  It includes
                 settings for the database, SSO setup (if any),
                 e-mail, hyperlinks, branding, etc.

            controller_config.php
                 Controller configuration.  This maps request targets
                 onto controllers.

            engine_config.php
                 This maps the DBO model interfaces onto concrete
                 implementations.

            ui_config.php
                 User interface configuration.  This defines menu items,
                 access controls, and implementations.

        controllers/
            Controllers are responsible for processing requests
            that are received by the application.  Controllers are
            instantiated and invoked by the Dispatcher, whose
            operation is specified via metadata in
            config/controller_config.php.

        controllers/templates/default/
            Twig templates directory for non-UI controllers.  These
            can be replaced or extended via custom templates.

            The UI templates are NOT in this directory; they are
            in ui/templates/... (see below).

        controllers/templates/_custom dir_/
            Custom Twig templates.  _custom dir_ is specified in
            config/config.php; by default, 'custom' is used.

            To extend or reference a default template, you may use
            path expressions of the form 'default/_template_',
            where _template_ is the default template file name.

        css/
            CSS assets.  These files are automatically whitespace
            compressed upon delivery.

        db/
            Database schema and deployment SQL.  These files are used
            to create or update the database and are not application
            runtime code.

        engine/
            Application runtime infrastructure

            This includes configuration and request dispatch,
            database object interfaces, and access to application
            services.  Runtime components receive their dependencies
            through constructor dependency injection.

            The database object (DBO) interfaces are:
              • IArtwork - album and artist artwork
              • IChart - rotation and charting
              • IDJ - DJ airname management
              • IEditor - music library management
              • ILibrary - music library search
              • IPlaylist - DJ playlist operations
              • IReview - music review operations
              • IUser - user management

            Other noteworthy interfaces in engine:
              • IConfig - application configuration
              • Session - session state

            Application components receive the interfaces they
            need through constructor dependency injection.

        engine/impl/
            Concrete implementations of the engine interfaces.

            Classes in this directory should never be referenced
            nor accessed directly; all access should be mediated
            through dependency injection of the respective interfaces.

        fonts/
            TrueType fonts for label printing

        img/
            image files

        js/
            JavaScript assets.  These are the client (browser) resident
            components of the UI.  The files are automatically minified
            and source maps generated upon delivery.

        service/
            Daemon-style components that run independently of web
            requests, driven by a dedicated ReactPHP event loop.  Services
            include push notifications and Turnstile pre-clearance.

            All components in this directory are strictly non-blocking.

        ui/
            Server-generated UI.  Menu items are specified in metadata,
            via config/ui_config.php.

        ui/templates/default/
            Twig UI templates directory.  These can be replaced or
            extended via custom templates.

        ui/templates/_custom dir_/
            Custom Twig templates.  _custom dir_ is specified in
            config/config.php; by default, 'custom' is used.

            To extend or reference a default template, you may use
            path expressions of the form 'default/_template_',
            where _template_ is the default template file name.

        vendor/
            PHP Composer dependencies (not delivered from the repo)
            See INSTALLATION.md for more information.

        composer.json
            Composer manifest

        composer.lock
            Composer dependency version lock

        index.php
            main endpoint for the application

       .htaccess
            maps virtual endpoints onto index.php.  This file also
            contains PHP settings when run via a webserver module.

       .user.ini
            PHP settings when run via fastCGI


### Guidelines

As you contribute code, please observe the following guidelines:

* All access to the engine is mediated via constructor dependency injection
  (see above for a discussion);
* Code outside the engine must delegate all database access to the engine;
* Code within the 'service' subdirectory must be asynchronous/non-blocking;
* User inputs (UI elements as well as imports) must be scrubbed for validity,
  which includes limiting the input to the size of the respective database
  columns.  Size consts should be declared in the engine interfaces.

Questions, comments, queries, or suggestions are welcome.

Thank you for contributing to Zookeeper Online!
