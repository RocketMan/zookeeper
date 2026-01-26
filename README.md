## Zookeeper Online
[![last commit](https://badgen.net/github/last-commit/RocketMan/zookeeper)](https://github.com/RocketMan/zookeeper/commits/master)
[![Build Status](https://github.com/RocketMan/zookeeper/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/RocketMan/zookeeper/actions/workflows/main.yml)

[![license](https://img.shields.io/github/license/RocketMan/zookeeper)](https://github.com/RocketMan/zookeeper/blob/master/LICENSE)
[![latest version](https://badgen.net/github/release/RocketMan/zookeeper?label=latest)](https://github.com/RocketMan/zookeeper/releases)

Zookeeper Online is a music database and charting application for
student and independent radio.

A snapshot of the master branch is maintained at
https://zookeeper.ibinx.com/master/


### Requirements 

* PHP 8.2 or later with MySQL PDO driver
* MySQL/MariaDB

If you have a requirment to run on PHP 7.4, you can use builds from
the release-3-0 branch (the v3.0.x release series).  Releases
beginning with v3.1.0 require PHP 8.2 or later.  Older versions of
PHP have reached EOL and are no longer supported.


### Getting Started

1. Clone the repository;
2. Create a database and populate it using the scripts in the 'db'
directory;
3. Copy config/config.example.php to config/config.php, and edit it
to point to your newly created database.

See [Zookeeper Online Installation](INSTALLATION.md) for detailed instructions.


### Contributing

Your contributions are welcome.  Please see [CONTRIBUTING](CONTRIBUTING.md)
for more information.


### License

**Zookeeper Online** is released under the
[**GNU GENERAL PUBLIC LICENSE Version 3 (GPL)**](http://www.gnu.org/licenses/gpl-3.0.html).

Copyright &copy; 1997-2026 Jim Mason.
