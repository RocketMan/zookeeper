## Zookeeper Online

Zookeeper Online is a music database and charting application for
college and independent radio.

A snapshot of the master branch is maintained at
https://zookeeper.ibinx.com/master/


### Requirements

* Apache 2.4
* MySQL/MariaDB 5.6+
* PHP 7.2 & PHP 7.2-mysql (thread safe version required)

### Getting Started


The PHP setup can be installed either by manually installing its constituent
components or by installing an integrated XAMPP or LAMPP framework. The 
procedure is as follows:

  - cd <SRC_DIR>
  - git clone https://github.com/RocketMan/zookeeper.git
  - Install Apache, PHP and MySql
  - set port in httpd.conf to <SOME_PORT>
  - edit htdocs in httpd.conf to point to <SRC_DIR>
  - check that date.timezone in php.ini is correct
  - check that error_log in php.ini points to a writable file
  - start Apache & hit localhost:<SOME_PORT> - should get PHP error page

Note that there are two active branches for Zookeeper, eg master and 
kzsu. In order to minimize the differences between the two branches
two CSS helper classes are provided: genericVisible and kzsuVisible that
can be used to enable implementation specific markup. Also note that PHP's
default timezone must be configured in your php.ini since PHP no longer 
obtains it from the OS.

2. Create a database and populate it using the scripts in the 'db' directory to create a zkdb database;
  run 'mysql -u root' and enter the following:
      CREATE USER 'zookeeper'@'localhost' IDENTIFIED BY 'zookeeper';
      GRANT ALL PRIVILEGES ON zkdb.* TO 'zookeeper'@'localhost';
      CREATE DATABASE zkdb;

   Populate the DB by running the following: 
      mysql -u zookeeper -pzookeeper -Dzkdb < zkdbSchema.sql
      mysql -u zookeeper -pzookeeper -Dzkdb < categories.sql
      mysql -u zookeeper -pzookeeper -Dzkdb < chartemail.sql

   Note that the above procedure will result in a bare DB with a single
   'root' user. If a more realistic test DB is desired you can obtain
   a backup DB from an established instance and restore it with:

   mysql -u root <DB_NAME> < dbbackup.sql


3. Edit the config/config.php file and set datbase, user and pass fields
   to match the values used in step #2, eg zkdb, zookeeper, zookeeper.

4. Hit localhost:<SOME_PORT> - should get home page, eg 9MMM

### Debugging & Logging
Because there is no simple way to use an IDE with PHP, debugging is done
via outputting to html using print(), echo() or var_dump() or logging 
to PHP's error log using php_error(). The latter will write to the log file 
defined by the error_log entry in your php.ini configuration file.


### Contributing

Your contributions are welcome.  Please see [CONTRIBUTING](CONTRIBUTING.md)
for more information.


### License

**Zookeeper Online** is released under the
[**GNU GENERAL PUBLIC LICENSE Version 3 (GPL)**](http://www.gnu.org/licenses/gpl-3.0.html).

Copyright &copy; 1997-2018 Jim Mason.
