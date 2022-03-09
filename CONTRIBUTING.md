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

Zookeeper follows the MVC (Model-View-Controller) pattern.  There are
clear architectural boundaries between the busienss logic / model
(in `engine`), the presentation, which is contained in `js` and `ui`,
and the controllers, which are in `controllers`.

Zookeeper APIs follow the JSON:API standard.  More information about
[Zookeeper JSON:API](docs/API.md) is available here.

The following is an overview of the source code directory structure:

    project-root/
        api/
            JSON:API implementation.

        build/
            files for continuous integration

        config/
            config.php
                 This is the main configuration file.  It includes
                 settings for the database, SSO setup (if any),
                 e-mail, hyperlinks, branding, etc.

            controller_config.php
                 Controller configuration.  Here we map request targets
                 onto controllers.

            engine_config.php
                 Model configuration.  This maps the model interfaces
                 onto concrete implementations.

            ui_config.php
                 User interface configuration.  This defines menu items,
                 access controls, and implementations.

        controllers/
            Controllers are responsible for processing requests
            that are received by the application.  Controllers are
            instantiated and invoked by the Dispatcher, whose
            operation is specified via metadata in
            config/controller_config.php.

        css/
            CSS assets.  These files are automatically whitespace
            compressed upon delivery.

        custom/
            Instance-specific customizations that you wish to keep
            separate from the standard installation.  For example,
            if you have custom controllers, you can put them here.

            TIP: If you add classes to this directory after installation,
            run `composer dump-autoload` to regenerate the classmap.
            See INSTALLATION.md for more information about Composer.

        engine/
            Business operations, configuration, and session
            management.

            Business operations are defined by interfaces.
            Each interface represents a logic grouping of
            operations.  The pattern is to call Engine::api
            for the interface you want to use.  Engine::api is
            a factory which instantiates a concrete
            implementation for a given interface.  You can
            then invoke the methods on the object instance returned
            by Engine::api.  The interface to concrete implementation
            bindings are metadata driven, via config/engine_config.php.

            The Engine::api interfaces are:
              • IArtwork - album and artist artwork
              • IChart - rotation and charting
              • IDJ - DJ airname management
              • IEditor - music library management
              • ILibrary - music library search
              • IPlaylist - DJ playlist operations
              • IReview - music review operations
              • IUser - user management

            Session state is application-managed; access the
            current session state through the singleton
            Engine::session.

            Configuration file data is accessible through
            various methods on the Engine class.

        engine/impl/
            Concrete implementations of the business operations.
            Classes in this directory should never be referenced
            nor accessed directly; all access should be mediated
            through the respective interfaces.  See 'engine',
            above, for a discussion of the Engine::api pattern.

        fonts/
            TrueType fonts for label printing

        img/
            image files

        js/
            JavaScript assets.  These are the client (browser) resident
            components of the UI.  The files are automatically minified
            and source maps generated upon delivery.

        ui/
            Server-generated UI.  Menu items are specified in metadata,
            via config/ui_config.php.

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

* Code in `engine` may never reference other parts of the application;
* All access to the engine is mediated via the Engine::api pattern (see
  above for a discussion);
* Code outside the engine must delegate all database access to the engine;
* User inputs (UI elements as well as imports) must be scrubbed for validity,
  which includes limiting the input to the size of the respective database
  columns.  Size consts should be declared in the engine interfaces.

Questions, comments, queries, or suggestions are welcome.

Thank you for contributing to Zookeeper Online!
