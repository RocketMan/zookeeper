// Jim Mason <jmason@ibinx.com>
// Copyright (C) 1997-2018 Jim Mason.  All Rights Reserved.

function loadXMLDoc(url,selected){var req=false;if(window.XMLHttpRequest){try{req=new XMLHttpRequest();}catch(e){req=false;}}else if(window.ActiveXObject){try{req=new ActiveXObject("Msxml2.XMLHTTP");}catch(e){try{req=new ActiveXObject("Microsoft.XMLHTTP");}catch(e){req=false;}}}
if(req&&typeof(req.readyState)!="undefined"){req.open("GET",url,true);req.onreadystatechange=function(){processReqChange(req,selected);}
if(typeof(req.setRequestHeader)=="function")
req.setRequestHeader('If-Modified-Since','Sat, 1 Jan 2000 00:00:00 GMT');req.send(null);return false;}
return true;}
function getNodeValue(node){return(node&&node[0]&&node[0].firstChild)?node[0].firstChild.nodeValue:'';}
function urlEncode(url){return escape(url).replace(/\+/g,'%2B');}
function createNamedElement(tag,name){var element=null;try{element=document.createElement('<'+tag+' name="'+name+'">');}catch(e){}
if(!element||element.tagName!=tag){element=document.createElement(tag);element.name=name;}
return element;}
