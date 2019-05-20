/*
 * Zookeeper Online
 *
 * @author Eric Gilbertson <ericg@kzsu.stanford.edu>
 * @copyright Copyright (C) 1997-2018 Jim Mason <jmason@ibinx.com>
 * @link https://zookeeper.ibinx.com/
 * @license GPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License,
 * version 3, along with this program.  If not, see
 * http://www.gnu.org/licenses/
 */

/**
 * IMPORTANT NOTE:
 *
 * Run this script ONLY if you are converting an existing Zookeeper Online v2
 * database for use with the current codebase.
 *
 * If you are creating a new database, run zkdbSchema.sql and then populate
 * the resulting db using the various bootstrap scripts as appropriate.
 */

--
-- Database: `zkdb`
--

alter table tracks add created timestamp null default NULL;
