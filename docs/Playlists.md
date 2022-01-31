## Zookeeper JSON:API :: Playlist

This is specific API information for the 'playlist' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/playlist` (filter/pagination) or
`api/v1/playlist/:id`, where :id is the id of a specific playlist.  See
below for a list of possible filter options.

An [example playlist document](Samples.md#playlist) is available here.

An example of [playlist creation](PlaylistCreation.md) is here.

### Fields

* name
* date
* time
* airname
* events -- array of zero or more:
  * type (one of `break`, `comment`, `logEvent`, `spin`)
  * created
  * artist
  * album
  * track
  * xa:relationships**
  * comment
  * event
  * code

(** See https://github.com/RocketMan/zookeeper/pull/263 for a discussion
of the 'xa:relationships' attribute.)

### Relations

* albums (to-many)

### Filters

You may specify at most one filter as a query string parameter of the
form `filter[_field_]=_value_`.  Possible fields are listed below.

  * date
  * id
  * match(event)

`date` may have the value 'onNow', which returns the curently on-air
playlist, if any.  The 'match' keyword indicates a full-text search
against artist, album, or label of any spin.

Pagination is supported only for match.  Sorting is not supported.

### <a name="insert"></a>Insert

To insert a new playlist, issue a POST to `api/v1/playlist`.  Playlist
details are in the request body in the same format returned by GET.
X-APIKEY authentication required.

If you belong to 'v' group, you may insert playlists on behalf of
other users: You will own the list in these cases (i.e., can update or
delete them), but they will display publicly under the other user's
airname.

### Update

Update the playlist with id :id by issuing a PATCH request to
`api/v1/playlist/:id`.  Playlist details are in the request body in same
format returned by GET.  Attributes not specified in the PATCH request
remain unchanged.  X-APIKEY authentication required; update will fail
if you do not own the playlist.

**Note:** Events are not updated/updatable via this PATCH but instead
may be added, updated, or deleted via the [Events Relationship](#events) (see below).

### Delete

Delete playlist with :id by issuing a DELETE request to
`api/v1/playlist/:id`.  X-APIKEY authentication required.
Delete will fail if you do not own the playlist.

<a name="events"></a>
### Events Relationship

Events appear as attributes of a playlist.  In addition, they are
exposed via the "events" relationship, where they may be individually
added, updated, or deleted.

When events are accessed as a relationship, each one has a unique 'id'.
To get the list of events with id's, do a GET request to
`api/v1/playlist/:id/events`, where :id is the playlist id.

A [sample events document](Samples.md#events) is available here.

You may add new events, update existing events, or delete events via
the endpoint:

`api/v1/playlist/:id/relationships/events`

where :id is the playlist id.

* To add a new event, issue a POST to the endpoint above;
* To modify an event, issue a PATCH to the endpoint above;
* To delete an event, issue a DELETE to the above endpoint.

In all cases, the request body contains a single event in the format
returned by `api/v1/playlist/:id/events`.

X-APIKEY authentication is required; the operation will fail if you do
not own the playlist.

**Notes:**
* If you add an event to a live playlist (one that is on-air 'now')
and do not supply a `created` property, created will be set automatically;
* Added or modified events are automatically positioned to the correct
position in the playlist relative to their created time;
* Added events with no created time are placed at the end of the playlist;
* To modify an event, you must specify _all_ attributes.
