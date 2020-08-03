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

use ZK\UI\UICommon as UI;

class KzsuContacts extends MenuItem {
    private static $actions = [
        [ "contact", "emitContacts" ],
        [ "contactGuidelines", "emitGuidelines" ],
    ];

    public function processLocal($action, $subaction) {
        return $this->dispatchAction($action, self::$actions);
    }

    public function emitContacts() {
    ?>
    <P>The KZSU Music Department is constantly on the lookout for new
    independent kickass releases of any genre.  If you put out CDs or
    vinyl from 7&quot;s to 12&quot;s, all it takes to be considered for
    airplay is to send us a copy.</P>
    
    <P><A HREF="?action=contactGuidelines"><B>PLEASE READ THESE GUIDELINES BEFORE SUBMITTING MUSIC</B></A></P>
    
    <TABLE BORDER=0 CELLSPACING=0 WIDTH="100%">
      <TR><TH ALIGN=LEFT CLASS="subhead">Music Mailing Addresses</TH></TR>
    </TABLE><BR>
    <TABLE BORDER=0 WIDTH="75%">
      <TR>
        <TH ALIGN=left VALIGN=top>
          <SPAN CLASS="sub"><I>Via Post:</I></SPAN><BR>
          KZSU Music<BR>
          PO Box 20510<BR>
          Stanford, CA  94309
        </TH>
        <TH ALIGN=left VALIGN=top>
          <SPAN CLASS="sub"><I>Via Courier (e.g., UPS, FedEx):</I></SPAN><BR>
          KZSU Music<BR>
          Memorial Hall Basement<BR>
          Stanford, CA 94305
        </TH>
    </TABLE><BR>
    
    <TABLE BORDER=0 CELLSPACING=0 WIDTH="100%">
      <TR><TH ALIGN=LEFT CLASS="subhead">Music Directors</TH></TR>
    </TABLE><BR>
    <TABLE BORDER=0 WIDTH="100%">
      <TR>
        <TD ALIGN="left" VALIGN="top">
          <B>Music Directors:</B><BR> <A HREF="mailto:music@kzsu.stanford.edu">Madeline Casas / Bill Cuevas /<BR>Maryam Khademi / Juan Luna-Avin</A><BR><BR><!--BR-->
          <B>Hip-hop Directors:</B><BR> <A HREF="mailto:hiphop@kzsu.stanford.edu">Mike McDowell, Johnathan Martin</A><BR>
          <I>Office Hours:</I> Thursday 6-9pm<BR><BR>
          <B>Jazz Director:</B> <A HREF="mailto:jazz@kzsu.stanford.edu">Tom McCarter</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
          <B>Country/Bluegrass Director:</B> <A HREF="mailto:country@kzsu.stanford.edu">Joseph Hnilo</A><BR>
          <I>Office Hours:</I> Sunday noon-4<BR><BR>
          <B>RPM/Electronica Director:</B> <A HREF="mailto:rpm@kzsu.stanford.edu">Johnathan Martin</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
          <B>Reggae Director:</B> <A HREF="mailto:reggae@kzsu.stanford.edu">Margy Kahn</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
        </TD>
        <TD>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</TD>
        <TD ALIGN="left" VALIGN="bottom">
          <B>Classical Director:</B> <A HREF="mailto:classical@kzsu.stanford.edu">Larry Koran</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
          <B>World Directors:</B> <A HREF="mailto:world@kzsu.stanford.edu">Margy Kahn</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
          <B>Blues Director:</B> <A HREF="mailto:blues@kzsu.stanford.edu">Byrd Hale</A><BR>
          <I>Office Hours:</I> Wednesday 6-9pm, Saturday 9-noon<BR><BR>
          <B>Vinyl/7&quot; Director:</B> <A HREF="mailto:seven@kzsu.stanford.edu">Juan Luna-Avin</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
          <B>Metal Director:</B> <A HREF="mailto:metal@kzsu.stanford.edu">Bill Cuevas</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
          <B>Noise/Experimental Directors:</B><BR> <A HREF="mailto:experimental@kzsu.stanford.edu">Abra Jeffers</A><BR>
          <I>Office Hours:</I> tba<BR><BR>
        </TD>
      </TR>
    </TABLE>
    
    <P CLASS="sub"><B>A FEW OF THE GENRES THAT WE PLAY:</B><BR>
    country, grind, punk, industrial, blues, world, techno, rockabilly,
    jazz, no wave, zydeco, goth, ska, noisecore, house, classical, garage,
    hiphop, spoken word, death metal, folk, skronk, crust, trance, pop,
    drum 'n' bass, bluegrass, grunge, surf, hardcore, reggae, gabber,
    experimental, emo, rock, queercore, klezmer, sea chanties, black
    metal, dub, psych, jungle</P><BR>
    
    <TABLE BORDER=0 CELLSPACING=0 WIDTH="100%">
      <TR><TH ALIGN=LEFT CLASS="subhead">Contact KZSU Radio</TH></TR>
    </TABLE>
    <P><A CLASS="nav" HREF="http://kzsu.stanford.edu/contact/"><B>Additional contact information</B></A> for KZSU radio is available here.</P>
    <?php 
        UI::setFocus();
    }
    
    public function emitGuidelines() {
    ?>
    <P CLASS="subhead">Submission Guidelines</P>
    
    <P>Thanks for submitting your music to KZSU for airplay consideration.
    Before you do, please read the following for general guidelines,
    information.</P>
    
    <P CLASS="subhead2">What Do We Play?</P>
    <P>All submissions will be listened to and assessed for their
    appropriateness on KZSU.  If you are unfamiliar with what we play
    please consult our <A HREF="?action=viewChart">weekly charts</A> posted online or
    check our <A HREF="<?php echo Engine::param('urls')['home'];?>schedule/">air schedule</A> and
    the show descriptions of our current DJs.  KZSU has very limited
    library space and it is important that we only keep what we feel will
    be of most interest to our DJs and listeners.  If what comprises our
    weekly charts makes little sense to you, please reconsider.</P>
    
    <P CLASS="subhead2">Formats we accept:</P>
    <UL>
    <LI>CD
    <LI>Vinyl (7&quot;, 10&quot;, 12&quot;)
    <LI>Cassette
    <LI>CD-R versions of vinyl releases including  records
    <LI>CD-R versions of cassette releases
    <LI>CD-R versions of digital-only releases
    </UL>
    
    <P CLASS="subhead2">We do not accept:</P>
    <UL>
    <LI>CD singles/promos
    <LI>Label samplers (with exception of the unique and obscure)
    <LI>CD EPs with tracks from upcoming  (please wait and send us the full length)
    </UL>
    
    <P CLASS="subhead2">Regarding Digital Submissions:</P>
    <P>At this time we are not equipped to air digital releases directly.
    Digital submissions must be downloaded, ripped, and treated like any
    other CD-R.  Our preferred media for the moment is still CD.  Certain
    exceptions are granted (e.g. Hip Hop digital singles).  Otherwise,
    please do your best to send us a CD-R version.</P>
    
    <P CLASS="subhead2">Genres We Accept</P>
    <P>All. Everything.  Anything except blatantly commercial releases. If
    you feel that your music falls under the following specific subgenres
    please address it to: Jazz, Blues, Hip Hop, Classical, Experimental,
    Metal, World, Reggae, Country/Bluegrass, Dance/RPM.  Anything else
    goes to General where it will be well received.  If you can't figure
    out how to classify your music then it sounds exactly like something
    we will love.  Just send it.</P>
    
    <P>Please allow 4-6 weeks for processing of submissions.  If you are
    wondering whether your music has been accepted into our rotation and
    library, please consult our <A HREF="?">Zookeeper database</A> after that time.  All music that
    is accepted is entered into this easily searchable database.</P>
    
    <P CLASS="subhead2">Tracking</P>
    <P>With few exceptions, we do not do tracking.  With the volume of
    submissions and the fact that our staff is all-volunteer, busy with
    classes, day jobs, families, we prefer to let the publicly searchable
    <A HREF="?">Zookeeper</A> database, our CD
    reviews, our A-file and charts be a measure of how much we love your
    music.  Please consult Zookeeper in lieu of tracking calls,
    emails.</P>
    
    <P>CDs that are not accepted cannot be returned unless a SASE is
    enclosed.  We assure you that we do our best to recycle, reuse them.
    Again: PLEASE consult our charts to see whether your music is
    appropriate for KZSU BEFORE you mail it to us.  We strive to minimize
    waste of natural resources, energy.</P>
    
    <P CLASS="subhead2">Weekly Adds and Charting</P>
    <P>All music that is accepted will be reviewed by staff and put into
    A-File rotation on a weekly basis at which point it has the potential
    to chart.  Please allow up to several weeks for CDs to be reviewed and
    added.  CDs spend 9 weeks on this charting rotation after which they
    retire to our main library where they are available for airplay to all
    DJs in perpetuity.  Again, the Zookeeper database will allow you to
    track the number of plays a CD gets, when, and by which DJ.</P>
    
    <P CLASS="subhead2">A Note of Appreciation</P>
    <P>Our staff is composed of true music lovers and many of us are
    former/present musicians.  We know too well the hard work, passion,
    heart, soul and love that goes into every CD that is submitted.
    Please do not feel slighted if we do not accept your CD.  Please
    accept our gratitude for the honor of listening to it, screening it.
    KZSU strives for a certain sound and if we do not accept your CD it
    merely means that it is likely more appropriate for another radio
    station.</P>
     
    <P>Thanks in advance for your submissions.<BR>
    <A HREF="?action=contact"><I>The Music
    Department Staff of KZSU Stanford</I></A></P>
    <?php 
        UI::setFocus();
    }
}
