## Zookeeper Online

Zookeeper Online is a music database and charting application for
college and independent radio.

A snapshot of the master branch is maintained at
https://zookeeper.ibinx.com/master/


### Requirements

* PHP 5.6 or later with MySQL PDO driver
* MySQL/MariaDB


### Getting Started

1. Clone the repository;
2. Create a database and populate it using the scripts in the 'db'
directory (see [Setting up the
Database](#user-content-setting-up-the-database), below);
3. Copy config/config.example.php to config/config.php, and edit its
db stanza to point to your newly created database (see
[Configuration](#user-content-configuration), below).


### Setting up the Database

1. Launch the mysql client and login using the root password you setup when
you installed MySQL/MariaDB:

    `mysql -u root -p`

    You will be prompted for the password you configured when you setup MySQL.

2. Create a database for zookeeper:

    mysql> `CREATE DATABASE zkdb;`

    where *zkdb* can be any name you choose for the database.

3. Setup credentials for the database you created above:

    mysql> `GRANT USAGE ON *.* TO 'zkuser'@'localhost' IDENTIFIED BY 'zkpass';`
    
    mysql> `GRANT ALL PRIVILEGES ON zkdb.* TO 'zkuser'@'localhost';`

    where *zkdb* is your database name.  *zkuser* and *zkpass* are your
    choosen username and password, respectively. (*zkpass* is just an
    example; please use a strong password.)

    Pay close attention to the single tick marks ('); they are required.

4. Populate the new database.  You may populate the database from a backup,
or install a clean database instance.

    a. Populate the database from a backup:

        mysql -u zkuser -p zkdb < backup.sql

    where *zkuser* and *zkdb* are the user and database you setup
    above, and *backup.sql* is your existing backup.  You will be
    promtped for the password you configured above.

    If you restore from a backup, skip step (b) below.
        
    b. Create a clean zookeeper database instance:

        mysql -u zkuser -p zkdb < db/zkdbSchema.sql
        mysql -u zkuser -p zkdb < db/categories.sql
        mysql -u zkuser -p zkdb < db/chartemail.sql
        mysql -u zkuser -p zkdb < db/bootstrapUser.sql

    MySQL will prompt you for the password you setup above.

    The zkdbSchema.sql script sets up the schema, while the other
    scripts bootstrap selected tables.  The bootstrapUser.sql script
    configures one administrative user, 'root', with initial password
    'password'.  You may use this login to add other users or
    administer your instance.  (Please change its password when you
    login.)


### Configuration

The file config/config.php contains site-specific configuration data,
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
        'database' => 'zkdb',
        'user' => 'zkuser',
        'pass' => 'zkpass',
    ],
    ...
    

where *zkdb*, *zkuser*, and *zkpass* are the values from [Setting up
the Database](#user-content-setting-up-the-database), above.

The file config/config.php also contains other parameters that you may
wish to adjust for your zookeeper installation.  These include
branding (station name, style sheets and logo), contact information,
optional SSO login setup, and charting configuration.  Please see the
config.php file for more information.


### Contributing

Your contributions are welcome.  Please see [CONTRIBUTING](CONTRIBUTING.md)
for more information.


### License

**Zookeeper Online** is released under the
[**GNU GENERAL PUBLIC LICENSE Version 3 (GPL)**](http://www.gnu.org/licenses/gpl-3.0.html).

Copyright &copy; 1997-2018 Jim Mason.
