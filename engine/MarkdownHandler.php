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

namespace ZK\Engine;

use League\CommonMark\GithubFlavoredMarkdownConverter;

class MarkdownHandler extends ResourceHandler {
    const STYLESHEET = "css/zoostyle.css";

    public function __construct(
        private GithubFlavoredMarkdownConverter $markdownConverter,
        Request $request,
    ) {
        parent::__construct($request);
    }

    public function processRequest() {
        $target = $this->prepareRequest();
        if ($target === null)
            return;

        header("Content-Type: text/html; charset=UTF-8");

        // for HEAD requests, there is nothing more to do
        if ($this->request->getMethod() == "HEAD")
            return;

        $stylesheet = $this->request->getAppBasePath()
                        . Decorator::decorateAsset(self::STYLESHEET);

        ob_start("ob_gzhandler");

        echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        echo "<link rel=\"stylesheet\" href=\"$stylesheet\">\n";
        echo "<title>" . basename($this->request->getUri()) . "</title>\n";
        echo "</head>\n<body>\n<div class='box'>\n";
        echo $this->markdownConverter->convert(file_get_contents($target));
        echo "</div>\n</body>\n</html>\n";

        ob_end_flush(); // ob_gzhandler
    }
}
