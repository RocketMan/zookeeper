//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2019 Jim Mason <jmason@ibinx.com>
// @link https://zookeeper.ibinx.com/
// @license GPL-3.0
//
// This code is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License, version 3,
// as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License,
// version 3, along with this program.  If not, see
// http://www.gnu.org/licenses/
//

/*! Zookeeper Online (C) 1997-2019 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 */

function loadXMLDoc(url,selected) {
   var req=false;
   if(window.XMLHttpRequest) {
      // native XMLHttpRequest object
      try {
         req = new XMLHttpRequest();
      } catch(e) {
         req = false;
      }
   } else if(window.ActiveXObject) {
      // IE/Windows ActiveX version
      try {
         req = new ActiveXObject("Msxml2.XMLHTTP");
      } catch(e) {
         try {
            req = new ActiveXObject("Microsoft.XMLHTTP");
         } catch(e) {
            req = false;
         }
      }
   }

   if(req && typeof(req.readyState) != "undefined" ) {
      req.open("GET", url, true);
      req.onreadystatechange = function() { processReqChange(req,selected); }
      if(typeof(req.setRequestHeader) == "function")
         req.setRequestHeader('If-Modified-Since', 'Sat, 1 Jan 2000 00:00:00 GMT');
      req.send(null);
      return false;
   }

   return true;
}

function getNodeValue(node) {
   return (node&&node[0]&&node[0].firstChild)?node[0].firstChild.nodeValue:'';
}

function urlEncode(url) {
   return encodeURI(url).replace(/\+/g, '%2B');
}

function createNamedElement(tag, name) {
   var element = null;
   try {
      // IE requires named elements to be created thusly:
      element = document.createElement('<'+tag+' name="'+name+'">');
   } catch(e) {}

   if(!element || element.tagName != tag) {
      // Failed, so probably not IE; create it the standard way
      element = document.createElement(tag);
      element.name = name;
   }
   return element;
}
