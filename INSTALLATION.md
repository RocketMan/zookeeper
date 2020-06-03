## Zookeeper Online Installation

### Prerequisites

Zookeeper Online can be installed on any platform which support a
webserver, MySQL or MariaDB, and PHP.  The instructions in this
section assume you are self-hosting on Debian Linux.  Of course, you
may use another distro or OS; in this case, the exact steps may vary.

If you are using shared hosting, follow the instructions of your
hosting provider to activate and configure Apache 2.4, MySQL 5.6, and
PHP 7.2.

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

1. From a shell, launch the mysql client and login using the root
   password you setup when you installed MySQL/MariaDB:

    `mysql -u root -p`

    You will be prompted for the password. Once logged in execute the
    following commands.

2. Create a mysql user and database for zookeeper:

    CREATE DATABASE example_db;
    CREATE USER example_user;

    where example_db and example_user can be any names you choose for the database.

3. Setup credentials for the database you created above:

    GRANT USAGE ON *.* TO 'example_user'@'localhost' IDENTIFIED BY 'example_pass';
    
    GRANT ALL PRIVILEGES ON example_db.* TO 'example_user'@'localhost';

    where *example_db* is your database name.  *example_user*
    and *example_pass* are your choosen username and password,
    respectively. (*example_pass* is just an example; please use a
    strong password.)

    Pay close attention to the single tick marks ('); they are required.

4. Populate the new database.  You may populate the database from a backup,
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

        mysql -u zookeeper -p zookeeper zookeeper < db/zkdbSchema.sql
        mysql -u zookeeper -p zookeeper zookeeper < db/categories.sql
        mysql -u zookeeper -p zookeeper zookeeper < db/chartemail.sql
        mysql -u zookeeper -p zookeeper zookeeper < db/bootstrapUser.sql

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

b. If you are using shared hosting and do not have permission to edit
   php.ini, or you want to set a different timezone value for
   Zookeeper Online, edit the `.htaccess` file in the `zookeeper`
   directory and add this setting:

        php_value date.timezone "Asia/Kolkata"

   within the &lt;IfModule mod_php7.c> stanza, or within &lt;IfModule mod_php5.c>
   if you are using PHP 5.6.

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

NOTES:
As of MySQL 5.7 the default validation rules were changed in order to be ANSI compliant.
If you get the following error you can work around it by disabling the ONLY_FULL_GROUP_BY
rule using the statement shown below.

Expression #1 of SELECT list is not in GROUP BY clause and contains nonaggregated column 'db.table.col' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by

SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));

Note that you must have admin rights in order to run this command.

For more info see:
https://tableplus.com/blog/2018/08/mysql-how-to-turn-off-only-full-group-by.html
