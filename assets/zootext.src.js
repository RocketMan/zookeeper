//
// Zookeeper Online
//
// @author Jim Mason <jmason@ibinx.com>
// @copyright Copyright (C) 1997-2018 Jim Mason <jmason@ibinx.com>
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

function emitMore(table, data, type) {
  var more = data.getAttribute("more");
  if(more > 0) {
    var offset = data.getAttribute("offset");
    var tr = document.createElement("TR");
    table.appendChild(tr);
    var td = document.createElement("TD");
    td.className = "ind";
    tr.appendChild(td);
    td = document.createElement("TD");
    td.colSpan = 3;
    if(offset != '') {
      var nav = '&nbsp;&nbsp;';
      var chunksize=15, numchunks = 10;
      var page = (offset / chunksize) | 0;
      var start = ((page / numchunks) | 0) * numchunks;
      if(start == 0) start = 1;
      for(var i=0; i<=numchunks+1; i++) {
        var cur = (start + i);
        var low = (cur - 1) * chunksize;
        var hi = cur * chunksize;
        if(low >= more)
          break;
        if(offset >= low && offset < hi)
          nav += '<B>' + cur + '</B>&nbsp;&nbsp;';
        else {
          loader = "return loadXMLDoc('zkapi.php?method=searchRq&type=" + type + "&size=15&offset=" + low + "&key=" + urlEncode(document.forms[0].search.value) + "')";
          nav += '<A HREF="#" CLASS="nav" onClick="' + loader + '"><B>' + cur + '</B></A>&nbsp;&nbsp;';
        }
      }
      if((start + i - 1) * chunksize < more)
         nav += "<B>...</B>";
      td.innerHTML = nav;
    } else {
      var next, offset = '';
      if(more > 25)
        offset = "&size=15&offset=0";
      next = "return loadXMLDoc('zkapi.php?method=searchRq&type=" + type + offset + "&key=" + urlEncode(document.forms[0].search.value) + "')";
      td.innerHTML = '<A HREF="#" CLASS="nav" onClick="' + next + '"><B>' + more + ' more...</B></A>';
    }
    tr.appendChild(td);
  }
}

function emitArtist(nameNode) {
  var name = getNodeValue(nameNode);
  if(name.substr(0, 8) == '[coll]: ')
    name = 'Various Artists';
  return name;
}

function emitTags(table, data) {
  emitAlbumsEx(table, data, "Album Tags:", 1);
}

function emitAlbums(table, data) {
  emitAlbumsEx(table, data, "Artists and Albums:", 0);
}

function emitAlbumsEx(table, data, header, tag) {
  var session = document.forms[0].session.value;
  var tr = document.createElement("TR");
  table.appendChild(tr);
  var th = document.createElement("TH");
  th.className = "sec";
  th.colSpan = 2;
  tr.appendChild(th);
  th.innerHTML = header;
  items = data.getElementsByTagName("albumrec");
  for(var i=0; i<items.length; i++) {
    tr = document.createElement("TR");
    table.appendChild(tr);
    var td = document.createElement("TD");
    td.className = "ind";
    tr.appendChild(td);
    var tagId = "";
    if(tag)
        tagId = "Tag #" + getNodeValue(items[i].getElementsByTagName("tag")) + "&nbsp;&#8226;&nbsp;";
    td = document.createElement("TD");
    td.innerHTML = tagId + '<A HREF="?s=byArtist&n=' +
      urlEncode(getNodeValue(items[i].getElementsByTagName("artist"))) + '&q=10&action=search&session=' +
      session + '" CLASS="nav">' +
      emitArtist(items[i].getElementsByTagName("artist")) + '</A>';
    td.innerHTML += "&nbsp;&#8226;&nbsp;";
    var album = document.createElement("I");
    album.innerHTML='<A HREF="?session=' + session + '&action=findAlbum&n=' +
      getNodeValue(items[i].getElementsByTagName("tag")) + '" CLASS="nav">' +
      getNodeValue(items[i].getElementsByTagName("album")) + '</A>';
    td.appendChild(album);
    td.innerHTML += '&nbsp; (' + getNodeValue(items[i].getElementsByTagName("name")) + ')';
    tr.appendChild(td);
  }
  emitMore(table, data, "albums");
}

function emitCompilations(table, data) {
  var session = document.forms[0].session.value;
  var tr = document.createElement("TR");
  table.appendChild(tr);
  var th = document.createElement("TH");
  th.className = "sec";
  th.colSpan = 2;
  tr.appendChild(th);
  th.innerHTML = "Compilations:";
  items = data.getElementsByTagName("albumrec");
  for(var i=0; i<items.length; i++) {
    tr = document.createElement("TR");
    table.appendChild(tr);
    var td = document.createElement("TD");
    td.className = "ind";
    tr.appendChild(td);
    td = document.createElement("TD");
    td.innerHTML = '<A HREF="?s=byArtist&n=' +
      urlEncode(getNodeValue(items[i].getElementsByTagName("artist"))) + '&q=10&action=search&session=' +
      session + '" CLASS="nav">' +
      emitArtist(items[i].getElementsByTagName("artist")) + '</A>';
    td.innerHTML += "&nbsp;&#8226;&nbsp;";
    var album = document.createElement("I");
    album.innerHTML='<A HREF="?session=' + session + '&action=findAlbum&n=' +
      getNodeValue(items[i].getElementsByTagName("tag")) + '" CLASS="nav">' +
      getNodeValue(items[i].getElementsByTagName("album")) + '</A>';
    td.appendChild(album);
    td.innerHTML += '&nbsp;&#8226;&nbsp;"' + getNodeValue(items[i].getElementsByTagName("track")) + '"';
    tr.appendChild(td);
  }
  emitMore(table, data, "compilations");
}

function emitLabels(table, data) {
  var session = document.forms[0].session.value;
  var tr = document.createElement("TR");
  table.appendChild(tr);
  var th = document.createElement("TH");
  th.className = "sec";
  th.colSpan = 2;
  tr.appendChild(th);
  th.innerHTML = "Labels:";
  var items = data.getElementsByTagName("labelrec");
  for(var i=0; i<items.length; i++) {
    tr = document.createElement("TR");
    table.appendChild(tr);
    var td = document.createElement("TD");
    td.className = "ind";
    tr.appendChild(td);
    td = document.createElement("TD");
    tr.appendChild(td);
    td.innerHTML = '<A HREF="?s=byLabelKey&n=' +
      getNodeValue(items[i].getElementsByTagName("pubkey")) + '&q=10&action=search&session=' +
      session + '" CLASS="nav">' +
      getNodeValue(items[i].getElementsByTagName("name")) + '</A>';
    var city = getNodeValue(items[i].getElementsByTagName("city"));
    if(city.length > 0)
      td.innerHTML += "&nbsp;&#8226; " + city + "&nbsp;" + getNodeValue(items[i].getElementsByTagName("state"));
  }
  emitMore(table, data, "labels");
}

months = [ "Jan", "Feb", "Mar", "Apr", "May", "Jun",
           "Jul", "Aug", "Sep", "Oct", "Nov", "Dec" ];

function emitPlaylists(table, data) {
  var session = document.forms[0].session.value;
  var tr = document.createElement("TR");
  table.appendChild(tr);
  var th = document.createElement("TH");
  th.className = "sec";
  th.colSpan = 2;
  th.innerHTML = "Playlists:";
  tr.appendChild(th);
  //table = emitTable(table.parentNode.parentNode);
  //table.parentNode.width="";
  var last;
  var items = data.getElementsByTagName("playlistrec");
  for(var i=0; i<items.length; i++) {
    var list = getNodeValue(items[i].getElementsByTagName("list"));
    if(list != last) {
      last = list;
      tr = document.createElement("TR");
      table.appendChild(tr);
      var td = document.createElement("TD");
      td.className = "ind";
      tr.appendChild(td);
      var now = new Date();
      var sd = getNodeValue(items[i].getElementsByTagName("showdate"));
      var day = sd.substr(8, 2) * 1;
      var month = sd.substr(5, 2) * 1;
      var year = " " + sd.substr(0, 4);
      //if(now.getFullYear() == year) year = "";
      td = document.createElement("TD");
      td.align = "left";
      td.innerHTML = '<A HREF="?action=viewDJ&seq=selList&playlist=' +
        list + '&session=' +
        session + '" CLASS="nav">' +
        getNodeValue(items[i].getElementsByTagName("description")) + '</A>' +
        '&nbsp;&#8226;&nbsp;' + day + " " + months[month-1] + year +
        '&nbsp;&nbsp;(' +
        getNodeValue(items[i].getElementsByTagName("airname")) + ')';
      tr.appendChild(td);
    }
    if(getNodeValue(items[i].getElementsByTagName("artist"))) {
      td.innerHTML += '<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub">' +
        emitArtist(items[i].getElementsByTagName("artist")) +
        '&nbsp;&#8226;&nbsp;<I>' +
        getNodeValue(items[i].getElementsByTagName("album")) + '</I>' +
        '&nbsp;&#8226;&nbsp;"' +
        getNodeValue(items[i].getElementsByTagName("track")) + '"</SPAN>';
    } else if (comment = getNodeValue(items[i].getElementsByTagName("comment"))) {
      td.innerHTML += '<BR>&nbsp;&nbsp;&nbsp;&nbsp;<SPAN CLASS="sub"><I>' +
        comment + '...</I></SPAN>';
    }
  }
  emitMore(table, data, "playlists");
}

function emitReviews(table, data) {
  var session = document.forms[0].session.value;
  var tr = document.createElement("TR");
  table.appendChild(tr);
  var th = document.createElement("TH");
  th.className = "sec";
  th.innerHTML = "Reviews:";
  th.colSpan = 2;
  tr.appendChild(th);
  var items = data.getElementsByTagName("reviewrec");
  for(var i=0; i<items.length; i++) {
    tr = document.createElement("TR");
    table.appendChild(tr);
    var td = document.createElement("TD");
    td.className = "ind";
    tr.appendChild(td);
    td = document.createElement("TD");
    td.innerHTML = '<A HREF="?s=byArtist&n=' +
      urlEncode(getNodeValue(items[i].getElementsByTagName("artist"))) + '&q=10&action=search&session=' +
      session + '" CLASS="nav">' +
      emitArtist(items[i].getElementsByTagName("artist")) + '</A>' +
      '&nbsp;&#8226;&nbsp;' +
      '<I><A HREF="?session=' + session + '&action=findAlbum&n=' +
      getNodeValue(items[i].getElementsByTagName("tag")) + '" CLASS="nav">' +
      getNodeValue(items[i].getElementsByTagName("album")) + '</A></I>' +
      '&nbsp;&nbsp;(' +
      getNodeValue(items[i].getElementsByTagName("airname")) + ')';
    tr.appendChild(td);
  }
  emitMore(table, data, "reviews");
}

function emitTracks(table, data) {
  var session = document.forms[0].session.value;
  var tr = document.createElement("TR");
  table.appendChild(tr);
  var th = document.createElement("TH");
  th.className = "sec";
  th.colSpan = 2;
  tr.appendChild(th);
  th.innerHTML = "Tracks:";
  items = data.getElementsByTagName("albumrec");
  for(var i=0; i<items.length; i++) {
    tr = document.createElement("TR");
    table.appendChild(tr);
    var td = document.createElement("TD");
    td.className = "ind";
    tr.appendChild(td);
    td = document.createElement("TD");
    td.innerHTML = '<A HREF="?s=byArtist&n=' +
      urlEncode(getNodeValue(items[i].getElementsByTagName("artist"))) + '&q=10&action=search&session=' +
      session + '" CLASS="nav">' +
      emitArtist(items[i].getElementsByTagName("artist")) + '</A>';
    td.innerHTML += "&nbsp;&#8226;&nbsp;";
    var album = document.createElement("I");
    album.innerHTML='<A HREF="?session=' + session + '&action=findAlbum&n=' +
      getNodeValue(items[i].getElementsByTagName("tag")) + '" CLASS="nav">' +
      getNodeValue(items[i].getElementsByTagName("album")) + '</A>';
    td.appendChild(album);
    td.innerHTML += '&nbsp;&#8226;&nbsp;"' + getNodeValue(items[i].getElementsByTagName("track")) + '"';
    tr.appendChild(td);
  }
  emitMore(table, data, "tracks");
}

function emitTable(parent, id) {
  var table = document.createElement("TABLE");
  table.cellPadding = 2;
  table.cellSpacing = 2;
  table.border = 0;
  table.width = "100%";
  parent.appendChild(table);
  var body = document.createElement("TBODY");
  table.appendChild(body);
  body.id = id.toLowerCase();
  return body;
}

var savedTable = null;
var savedTableID = null;

function getTable(id) {
  var table = document.getElementById(id);
  if(savedTableID != id) {
    if(savedTableID) {
      var oldTable = document.getElementById(savedTableID);
      oldTable.parentNode.replaceChild(savedTable, oldTable);
    }
    savedTable = table.cloneNode(true);
    savedTableID = id;
  }
  while(table.firstChild)
    table.removeChild(table.firstChild);
  return table;
}

function clearSavedTable() {
  savedTableID = null;
}
