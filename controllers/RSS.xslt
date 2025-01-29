<?xml version="1.0" encoding="UTF-8"?>
<!-- Zookeeper Online (C) 1997-2025 Jim Mason <jmason@ibinx.com> | @source: https://zookeeper.ibinx.com/ | @license: magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3.0 -->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:zk="http://zookeeper.ibinx.com/zkns"
    xmlns="http://www.w3.org/1999/xhtml" version="1.0">
<xsl:output method="xml"/>
<xsl:template match="/">
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title><xsl:value-of select="rss/channel/title"/></title>
<link rel="stylesheet" href="{rss/@zk:stylesheet}"/>
<link rel="icon" href="{rss/@zk:favicon}"/>
<style type="text/css">
<xsl:text><![CDATA[
h2 {
  margin-top: revert;
}
.rss-channel > .rss-item {
  padding: 2px 0px;
}
.rss-item:has(.album-thumb) {
  min-height: 180px;
}
.rss-item .album-thumb img {
  border-radius: 5px;
  opacity: 0;
  transition: all 400ms ease 0s;
}
]]>
</xsl:text>
</style>
<script type="text/javascript">
<!--
  /* RSS feeds contain HTML markup which the browser will escape;
   * that is, it will display HTML tags as literal text.  The
   * disable-output-escaping attribute is not supported on all
   * browsers (FX, for example).  We work around these limitations
   * by storing HTML content in a data attribute and then setting
   * the content into the document via innerHTML.
   */
-->
<xsl:text><![CDATA[
function fixup() {
   var nodes = document.getElementsByClassName("description");
   Array.prototype.slice.call(nodes).forEach(function(node) {
      node.innerHTML = node.dataset.description;
   });
   document.querySelectorAll('img[data-lazysrc]').forEach(function(img) {
      img.addEventListener('load', function() {
          img.style.opacity = 1;
      });
      img.src = img.getAttribute('data-lazysrc');
   })
}
]]>
</xsl:text>
</script>
</head>
<body onload="fixup()">
  <div class="box">
    <div class="user-tip" style="display: block">
      <p>This is a Really Simple Syndication (RSS) feed.  RSS is a family of
      web feed formats used to publish frequently updated content.</p>
      <p>To subscribe to this feed, drag or copy the address into your
      RSS feed reader.  New to RSS?
      <a href="https://www.google.com/search?q=how+to+get+started+with+rss+feeds"
      title="Getting started with RSS" target="_blank">Learn more</a>.</p>
    </div>
    <xsl:apply-templates select="rss/channel"/>
  </div>
</body>
</html>
</xsl:template>
<xsl:template match="channel">
  <h2><xsl:value-of select="title"/></h2>
  <div class="rss-channel">
    <xsl:apply-templates select="item"/>
  </div>
</xsl:template>
<xsl:template match="item">
  <div class="rss-item">
    <div>
    <xsl:apply-templates select="zk:albumart"/>
    <h3>
      <a class="nav" href="{link}">
        <xsl:value-of select="title"/>
      </a><br/>
      <span class="sub">
        <xsl:value-of select="zk:subtitle"/>
      </span>
    </h3>
    </div>
    <p class="description" data-description="{description}"/>
  </div>
</xsl:template>
<xsl:template match="zk:albumart">
  <div class='album-thumb pull-right'>
    <img data-lazysrc="{.}"/>
  </div>
</xsl:template>
</xsl:stylesheet>
