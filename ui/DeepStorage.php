<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2025 Jim Mason <jmason@ibinx.com>
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

namespace ZK\UI;

use ZK\Engine\Engine;
use ZK\Engine\IEditor;
use ZK\Engine\ILibrary;
use ZK\Engine\Request;
use ZK\Engine\Session;

use ZK\UI\UICommon as UI;

class DeepStorage extends MenuItem {
    public function __construct(
        protected Request $request,
        protected Session $session,
        protected TemplateFactoryUI $templateFactory,
        protected IEditor $editorDBO,
        protected ILibrary $libraryDBO,
    ) {
        parent::__construct($session, $templateFactory);
    }

    public function processLocal($action, $subaction) {
        $count = 0;
        $albums = [];

        $userfile = $this->request->getUploadedFile('dsfile');
        $upload = $userfile && $this->request->getMethod() === 'POST';
        $test = $this->request->getRequestParam('test');

        if ($upload) {
            $bin = $this->request->getRequestParam('bin');
            $column = $this->request->getRequestParam('column') - 1;
            $deacc = $this->request->getRequestParam('deacc');

            $tempPath = tempnam(sys_get_temp_dir(), 'ds-');

            try {
                $userfile->moveTo($tempPath);
                $fd = fopen($tempPath, "r");
                while (!feof($fd)) {
                    $line = explode("\t", fgets($fd, 1024));
                    if (count($line) >= $column) {
                        $tag = $line[$column];
                        $album = $this->libraryDBO->search(ILibrary::ALBUM_KEY, 0, 1, $tag);
                        if (sizeof($album)) {
                            if ($test) {
                                $albums[] = $album[0];
                            } else if($deacc) {
                               // Change status to "Deaccessioned" (U) or "Missing" (M)
                               $this->editorDBO->setLocation($tag, $deacc);
                            } else {
                               // Change status to "Deep Storage" (G) and set box number
                               $this->editorDBO->setLocation(
                                   $tag,
                                   ILibrary::LOCATION_STORAGE,
                                   $bin
                               );
                            }

                            $count++;
                        }
                    }
                }

                fclose($fd);

            } finally {
                @unlink($tempPath);
            }
        }

        $this->addVar('albums', $albums);
        $this->addVar('count', $count);
        $this->addVar('test', $test);
        $this->addVar('upload', $upload);
        $this->setTemplate('deepstorage.html');
    }
}
