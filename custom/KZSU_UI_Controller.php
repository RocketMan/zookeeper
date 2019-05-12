<?php
/**
 * Zookeeper Online
 *
 * @author Jim Mason <jmason@ibinx.com>
 * @copyright Copyright (C) 1997-2019 Jim Mason <jmason@ibinx.com>
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

/**
 * KZSU_UI_Controller is the UI controller for KZSU Radio.
 *
 * It inherits all of the functionality of the default ZK\UI\Main
 * controller, with the exception of specific methods which are
 * overridden and customized for KZSU.
 *
 * The file is provided as an example of a custom UI controller;
 * it is not part of the basic functionality of Zookeeper Online.
 */
class KZSU_UI_Controller extends Main {
    protected function emitBodyHeader($dispatcher) {
        $urls = Engine::param('urls');
        $station_full = Engine::param('station_full');
?>
    <DIV CLASS="headerLogo">
      <A HREF="<?php echo $urls['home']; ?>">
        <IMG SRC="<?php echo Engine::param('logo'); ?>" ALT="<?php echo $station_full; ?>" TITLE="<?php echo $station_full; ?>">
      </A>
    </DIV>
    <DIV CLASS="headerListen">
      <A HREF="<?php echo $urls['listen'];?>"><SPAN CLASS="clickTo">click to</SPAN><BR>listen<BR><SPAN CLASS="listenLive">LIVE</SPAN></A>
    </DIV>
    <DIV CLASS="headerNavbar">
      <A HREF="<?php echo $urls['home'];?>schedule/">schedule</A> +
      <A HREF="<?php echo "?session=".$this->session->getSessionID();?>">music</A> +
      <A HREF="<?php echo $urls['home'];?>sports/">sports</a> +
      <A HREF="<?php echo $urls['home'];?>concerts/">concerts</a> +
      <!--A HREF="<?php echo $urls['home'];?>news/" target="_blank">news</A> + -->
      <A HREF="<?php echo $urls['home'];?>join/">join</A>
      <BR>
      <A HREF="<?php echo $urls['home'];?>contact/">contact</A> +
      <A HREF="<?php echo $urls['home'];?>about/">about</A> +
      <A HREF="<?php echo $urls['home'];?>zine/">zine</A> +
      <A HREF="<?php echo $urls['home'];?>donate/">donate</A> +
      <A HREF="<?php echo $urls['home'];?>merch/">merch</A>
    </DIV>
<?php
    }
}
