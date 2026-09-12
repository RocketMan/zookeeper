<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2026 Jim Mason <jmason@ibinx.com>
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
 *
 */

namespace ZK\Service;

use Monolog\LogRecord;
use Monolog\Processor\IntrospectionProcessor;

class CallerProcessor extends IntrospectionProcessor {
    public function __invoke(LogRecord $record): LogRecord {
        $record = parent::__invoke($record);

        $shortClass = $record->extra['class'] ?? null;
        $method = $record->extra['function'] ?? null;

        if (is_string($shortClass)
                && ($r = strrpos($shortClass, '\\')) !== false)
            $shortClass = substr($shortClass, $r + 1);

        if (is_string($method)
                && str_starts_with($method, '{closure:')
                && preg_match('/::(\w+)\(\)/', $method, $m))
            $method = $m[1];

        return $record->with(
            extra: [
                ...$record->extra,
                'shortClass' => $shortClass,
                'method' => $method,
            ]
        );
    }
}
