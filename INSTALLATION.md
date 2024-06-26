## Zookeeper Online Installation

### Prerequisites

Zookeeper Online can be installed on any platform which support a
webserver, MySQL or MariaDB, and PHP.  The instructions in this
section assume you are self-hosting on Debian Linux.  Of course, you
may use another distro or OS; in this case, the exact steps may vary.

If you are using shared hosting, follow the instructions of your
hosting provider to activate and configure Apache 2.4, MySQL 5.6, and
PHP 7.4.

The remaining instructions in this section assume you are
administering your own server instance.  If you are using shared
hosting, skip to [Planning the Installation](#user-content-planning-the-installation), below.

For a self-administed server, you will need the following:

* A sudo user on your server: You can create a user with sudo
  privileges by following [these instructions](https://linuxize.com/post/how-to-create-a-sudo-user-on-debian/).
    
* An *AMP stack: Zookeeper Online requires a web server, a database,
  and PHP to function properly.  A LAMP stack (Linux, Apache, MySQL,
  and PHP) server fulfills all of these requirements.  [Follow this
  guide](https://www.digitalocean.com/community/tutorials/how-to-install-linux-apache-mysql-php-lamp-stack-on-debian)
  to install and configure this software.

* A signed SSL certificate: If you have a fully qualified domain name
  that resolves to your server, and you do not already have an SSL
  certificate, the easiest way to secure your site is with Let's
  Encrypt, which provides gratis, trusted SSL certificates.  Follow
  the [Let's Encrypt guide for Apache](https://www.digitalocean.com/community/tutorials/how-to-secure-apache-with-let-s-encrypt-on-debian-9)
  instructions to set this up.

* A self-signed SSL certificate: If are using Zookeeper Online for
  development or internal use, you can use a self-signed certificate.
  A self-signed certificate secures the communication channel, but
  does not prevent man-in-the-middle attacks nor provide domain name
  validation, which are essential security considerations in a
  production environment.  Follow the [self-signed SSL guide for
  Apache](https://www.digitalocean.com/community/tutorials/how-to-create-a-self-signed-ssl-certificate-for-apache-in-debian-9)
  to set this up.


### Planning the installation

1. Choose a directory to host the Zookeeper Online installation.  This
   directory needs to be under the DocumentRoot of your Apache
   installation, or otherwise configured to be served by Apache.  In
   Debian, you will want to setup a VirtualHost to point to your
   installation.  This is done by creating a [configuration file like
   this](#user-content-example-apache-configuration-file-for-debian)
   in the `/etc/apache/sites-available` directory and then
   creating a symbolic link to it from `/etc/apache/sites-enabled`.
   Note the `AllowOverride All` setting, which must be included.
   In addition, you will need to ensure the Apache 'rewrite' module
   is enabled.  If necessary, run the command `sudo a2enmod rewrite`
   and restart Apache after your configuration file is in place.
   (For shared hosting, follow the instructions of your hosting
   provider.)

2. Install the Zookeeper Online source from the repository:

    `git clone https://github.com/RocketMan/zookeeper /example/path/to/zookeeper`

    where `/example/path/to/zookeeper` is your desired installation
    directory for Zookeeper Online.  If the installation directory
    already exists, it must be empty.


### Setting up the Database

If you are already familiar with MySQL, or you are using shared
hosting and setup MySQL via phpMyAdmin, or some other hosting provider
tool, then you will need to create an empty database, as well as a
username and password with full access rights to that database.

If you are hosting on your own server and are unfamiliar with MySQL
particulars, then read on.

1. Optimise the database for full-text searching (optional)

   **IMPORTANT:** If you omit this step, some Zookeeper Online functions,
   such as artist autocomplete in the playlist editor, may not function
   as expected.

   Zookeeper Online uses several full-text indices.  By default,
   MySQL only indexes words which are 4 characters or longer, and
   in addition, excludes a number of useful 'stop' words.

   To optimise full-text searching for Zookeeper Online,
   edit the MySQL configuration file, which can be found at
   `/etc/mysql/mysql.conf.d/mysqld.cnf` (Debian).  Other distributions
   may locate and/or name the file differently.  In the `[mysqld]`
   section, add the lines:

        ft_min_word_len = 1
        ft_stopword_file = ''

   If `ft_min_word_len` or `ft_stopword_file` is already in the file,
   then change its value per the above.

   After you have made the above changes, restart the MySQL server.

2. From a shell, launch the mysql client and login using the root
   password you setup when you installed MySQL/MariaDB:

    `mysql -u root -p`

    You will be prompted for the password. Once logged in execute the
    following commands.

3. Create a mysql user and database for zookeeper:

    `CREATE DATABASE example_db;`
    `CREATE USER example_user;`

    where *example_db* and *example_user* can be any names you choose
    for the database.

4. Setup credentials for the database you created above:

    `GRANT USAGE ON *.* TO 'example_user'@'localhost' IDENTIFIED BY 'example_pass';`
    
    `GRANT ALL PRIVILEGES ON example_db.* TO 'example_user'@'localhost';`

    where *example_db* is your database name.  *example_user*
    and *example_pass* are your choosen username and password,
    respectively. (*example_pass* is just an example; please use a
    strong password.)

    Pay close attention to the single tick marks ('); they are required.

5. Populate the new database.  You may populate the database from a backup,
   or install a clean database instance.

    a. Populate the database from a backup:

        mysql -u example_user -p example_db < example_backup.sql

    where *example_user* and *example_db* are the user and database
    you setup above, and *example_backup.sql* is your existing backup.
    You will be prompted for the password you configured above.

    If you restore from a backup, skip step (b) below.
        
    b. Create a clean zookeeper database instance:

        mysql -u example_user -p example_db < db/zkdbSchema.sql
        mysql -u example_user -p example_db < db/categories.sql
        mysql -u example_user -p example_db < db/chartemail.sql
        mysql -u example_user -p example_db < db/bootstrapUser.sql

    MySQL will prompt you for the password you setup above.

    The zkdbSchema.sql script sets up the schema, while the other
    scripts bootstrap selected tables.  The bootstrapUser.sql script
    configures one administrative user, 'root', with initial password
    'password'.  You may use this login to add other Zookeeper Online
    users.  (Please change this password when you login.)


### Setting up PHP

Zookeeper Online requires that your local timezone be configured in
PHP.  Check your global php.ini file for the `date.timezone` value:

a. If php.ini does not include date.timezone and you want to set it on
   a global basis, edit php.ini and add the following setting.  For
   example, if your timezone is Indian Standard Time (IST), you would
   set:

        date.timezone="Asia/Kolkata"

   IMPORTANT: Some operating systems such as Debian have **two**
   global php.ini files, one for Apache and the other for command
   line.  If you are running Push Notification (below), you will need
   to set it for both.  On Debian, the locations of the files are:

        /etc/php/<version>/apache2/php.ini
        /etc/php/<version>/cli/php.ini

   where &lt;version> is the PHP version number (e.g., 8.2).

b. If you are using shared hosting and do not have permission to edit
   php.ini, or you want to set a different timezone value for
   Zookeeper Online, edit the `.htaccess` file in the `zookeeper`
   directory and add this setting:

        php_value date.timezone "Asia/Kolkata"

   within the &lt;IfModule mod_php7.c> stanza if you are using PHP
   7.4, or within &lt;IfModule mod_php.c> if you are using PHP 8.0.

   Alternatively, if you are using fastCGI, edit the `.user.ini`
   file in the `zookeeper` directory and add this setting:

       date.timezone="Asia/Kolkata"


### Configuration

The file `config/config.php` contains site-specific configuration data,
such as the database name and credentials.  Generally, it is the only
file which must be changed as part of the deployment process.

By default config/config.php does not exist; you must create it.  An
example file config/config.example.php is provided which you can use
as a template.  Simply copy it to config/config.php and change the
settings as desired.

At minimum, you will update the db stanza of config/config.php as follows:

    ...
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'example_db',
        'user' => 'example_user',
        'pass' => 'example_pass',
    ],
    ...
    

where *example_db*, *example_user*, and *example_pass* are the values
from [Setting up the Database](#user-content-setting-up-the-database),
above.

The file `config/config.php` also contains other parameters that you may
wish to adjust for your Zookeeper Online installation.  These include
branding (station name, style sheets and logo), contact information,
optional SSO login setup, and charting configuration.  Please see the
config.php file for more information.

### Install Composer dependencies [initial installation AND every release]

PHP Composer is a dependency management tool which Zookeeper uses to manage
third-party dependencies.

If you don't have PHP Composer installed somewhere on your system, you
will need it for the following steps.  Install it as follows:

        cd <directory where you want to install Composer>
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
        php composer-setup.php
        php -r "unlink('composer-setup.php');"

Install the Composer dependencies as follows:

        cd /example/path/to/zookeeper
        php <Composer directory>/composer.phar install

**Note:** In addition to the initial installation, run the
instructions above for each new Zookeeper Online release to ensure the
dependencies are kept up-to-date.

Once you have installed the Composer dependencies, it's fine to delete
the composer.phar that you downloaded, though you may want to keep it
around, as you will need it for each new release.


### Clear the Template Cache [every release]

Upon deployment of each new Zookeeper Online release, run the
following command to clear the Twig template cache:

       sudo -u www-data /example/path/to/zookeeper/zk cache:clear

where *www-data* is your Apache user and */example/path...* is the
path to your zookeeper installation.  If successful, the above
command will display a summary of the number of cache files deleted.


### Discogs Integration (optional)

Zookeeper can integrate with [Discogs](https://www.discogs.com/) to
obtain artwork and links to album and artist information.  It uses
this data to populate a 'Recently Played' song playlist on the home
page.

To enable Discogs integration, set either the `apikey` parameter, or
both the `client_id` and `client_secret` parameters in the `discogs`
stanza of config.php.  Obtain a Discogs API key or Consumer Key/Secret
through your Discogs developer account.

The `img` subdirectory must be writable by your webserver user, or at
minimum, create a new subdirectory `img/.cache` and make that writable
by the webserver.  `img/.cache` is where Zookeeper caches Discogs artwork.

**Note:** You must enable **and** run push notification (see below)
for Discogs integration to work.  If you do not enable push
notification, setting the parameters above will have no effect.  If
you enable push notification but do not run the push notification
server, the 'Recently Played' playlist will display but will include
no artwork.


### Push Notification (optional)

Zookeeper can send push notifications via websockets.  If you
want to support the optional push notification service, you will need to:

1. Update the webserver's configuration

   In your Apache configuration, enable the 'proxy' and 'proxy_wstunnel'
   modules.  With Apache on Debian, run:

        sudo a2enmod proxy_wstunnel

   The above will enable proxy_wstunnel and also module proxy (if it
   is not already enabled), then automatically restart Apache.  If you
   are on another OS, update your configuration to enable both modules,
   then restart Apache to pick up the change.

2. Update your system to run the push notification server

   As a first step, ensure you can run the notification server manually
   from a shell:

       sudo -u www-data /example/path/to/zookeeper/zk push

   where *www-data* is your Apache user and */example/path...* is the
   path to your zookeeper installation.  If successful, the above
   command will display no messages.  You can cancel it with Ctrl-C.

   Once you have confirmed that the notification server starts without
   issue, you can configure your system to run it automatically.  On
   most Linux distributions, you can use systemd(1) to start the push
   notification server and to ensure that it is running.  To do this,
   create a file `/etc/systemd/system/zkpush.service` using the
   example file below.

   Finally, run the command `sudo systemctl enable zkpush` to start
   the notification server automatically at boot time.

3. (Optional) Configure the HTTP push notification proxy

   If you want to send push notifications via HTTP, add the following
   stanza to the `config/config.php` file:

       'push_proxy' => [
           [
               'proxy' => ZK\PushNotification\PushHttpProxy::class,
               'ws_endpoint' => 'ws://127.0.0.1:32080/push/onair',
               'http_endpoints' => [ 'https://example/target/endpoint' ]
           ],
           ...repeat for additional proxies...
       ],

   where:

   * 'proxy' specifies the proxy implementation.  To send raw json
     data, use `ZK\PushNotification\PushHttpProxy::class`.  To send
     a FORM POST, use `ZK\PushNotification\PushFormPostProxy::class`.
   * 'ws_endpoint' is the ws push event stream to subscribe to.
     Set this value to 'ws://127.0.0.1:32080/push/onair' to subscribe
     to the default internal Zookeeper Online ws endpoint.
   * 'http_endpoints' is an array of targets to receive the HTTP requests

#### Example systemd file for push notification service

This file goes into the /etc/systemd/system directory of Debian:

    [Unit]
    Description=Zookeeper Push Notification
    Requires=mysql.service
    After=mysql.service

    [Service]
    User=www-data
    Type=simple
    TimeoutSec=0
    PIDFile=/var/run/zkpush.pid
    ExecStart=/example/path/to/zookeeper/zk push
    KillMode=process

    Restart=on-failure
    RestartSec=42s

    [Install]
    WantedBy=default.target

See systemctl(1) for information on how to control the service.

___


### Example Apache configuration file for Debian

This file goes into the /etc/apache/sites-available directory of
your Debian Apache 2.4 installation:
    
    <IfModule mod_ssl.c>
      <VirtualHost _default_:443>
        ServerAdmin webmaster@example.org
    
        DocumentRoot /example/path/to/zookeeper
    
        <Directory />
          Options FollowSymLinks
          AllowOverride None
        </Directory>
        <Directory /example/path/to/zookeeper/>
          Options Indexes FollowSymLinks MultiViews
          AllowOverride All
          Require all granted
        </Directory>
      
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
    
        SSLEngine on
    
        SSLCertificateFile /example/path/to/your/certificate/chain.crt
        SSLCertificateKeyFile /example/path/to/your/certificate/private.key
    
        <FilesMatch "\.(cgi|shtml|phtml|php)$">
          SSLOptions +StdEnvVars
        </FilesMatch>
      </VirtualHost>
    </IfModule>
