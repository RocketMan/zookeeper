## Zookeeper JSON:API :: Playlist

This is specific API information for the 'playlist' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/playlist` (filter/pagination) or
`api/v1/playlist/:id`, where :id is the id of a specific playlist.  See
below for a list of possible filter options.  An [example playlist
document](Samples.md#playlist) is available here.

An example of [playlist creation](PlaylistEvents.md) via the API can
be found here.

### Fields

* name
* date
* time
* airname
* expires (expiration date, present only for deleted playlists)
* rebroadcast
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

* origin (to-one)
* albums (to-many)
* events (to-many)

Events are included as attributes rather than relations in the
playlist resource object.  To fetch events as relations, issue a GET
request to `api/v1/playlist/:id/events`.  See the
[Events Relationship](#events) section below for more information.

### Filters

In general, you may specify at most one filter as a query string
parameter of the form `filter[_field_]=_value_`.  Possible fields are
listed below.

  * date
  * id
  * user
  * match(event)

`date` may have the value 'onNow', which returns the curently on-air
playlist, if any.  `user` may have the value 'self' for the
currently authenticated user.  The 'match' keyword indicates a
full-text search against artist, album, or label of any spin.

In the case of `filter[user]`, you may specify in addition
`filter[deleted]=1` to return only deleted but not yet purged
playlists.  In this case, the `expires` property will be set for each
playlist in the response.

Pagination is supported only for match.  Sorting is not supported.

### <a name="insert"></a>Insert

To insert a new playlist, issue a POST to `api/v1/playlist`.  Playlist
details are in the request body in the same format returned by GET.
X-APIKEY authentication required.

If you belong to 'v' group, you may insert playlists on behalf of
other users: You will own the list in these cases (i.e., can update or
delete them), but they will display publicly under the other user's
airname.

### <a name="duplicate"></a>Duplicate

Duplicate is identical to Insert, except that in the request body,
you must also:
* include an attribute `rebroadcast` with value `true`; and
* include a relationship `origin` to specify the playlist you wish
to duplicate.

The date and time of rebroadcast must be specified in attributes `date`
and `time`, respectively.  All other attributes are optional.

The name of the duplicated playlist follows the same convention as
playlists duplicated in the user interface.  If desired, an alternate
name can be specified via the `name` attribute.

In addition, you may specify an optional meta attribute `fromtime` to
indicate the portion of the playlist you wish to duplicate.  The value
may be a range (hhmm-hhmm) or a time (hhmm).  If you specify a time,
the API copies from the specified time to the end of the playlist.
**Important:** `fromtime` specifies the time relative to the original
playlist that is being copied.

Example:

Playlist 12345 runs from 0000-0300.  To duplicate only one hour of
this playlist, from 0100-0200, for rebroadcast on 2022-01-01 from
1800-2000:

````
POST /api/v1/playlist HTTP/1.1
X-APIKEY: eb5e0e0b42a84531af5f257ed61505050494788d
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "show",
    "attributes": {
      "rebroadcast": true,
      "date": "2022-01-01",
      "time": "1800-2000"
    },
    "relationships": {
      "origin": {
        "data": {
          "type": "show",
          "id": "12345"
        }
      }
    }
  }
}
````

Example with range:

To duplicate one hour of playlist 12345 from 0100-0200 for rebroadcast
on 2022-01-01 at 1800-1900:

````
POST /api/v1/playlist HTTP/1.1
X-APIKEY: eb5e0e0b42a84531af5f257ed61505050494788d
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "show",
    "attributes": {
      "rebroadcast": true,
      "date": "2022-01-01",
      "time": "1800-1900"
    },
    "meta": {
      "fromtime": "0100-0200"
    },
    "relationships": {
      "origin": {
        "data": {
          "type": "show",
          "id": "12345"
        }
      }
    }
  }
}
````

In general, you may only duplicate your own playlists.  If you belong
to the 'v' group, you may also duplicate playlists of other users.
You will own the list in these cases (i.e., can update or delete
them), but they will display publicly under the other user's airname.

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

### Restore

DELETE soft-deletes a playlist for 30 days prior to its being deleted
permanently.  During this period, a deleted playlist with id :id can
be restored by sending a PATCH request to `api/v1/playlist/:id`.

The request body must include a single request object with at minimum
`type` and `id` members, per [section
9.2](https://jsonapi.org/format/#crud-updating) of the JSON:API
specification.

It is NOT necessary to specify attributes in the request body.
However, as with any PATCH request, you may provide one or more
attributes to modify the playlist at the same time as the restore.

X-APIKEY is authentication required.  Restore will fail if you do not
own the playlist.

<a name="events"></a>
## Events Relationship

Events appear as attributes of a playlist.  In addition, they are
exposed via the 'events' relationship, where they may be individually
added, updated, or deleted.

When events are accessed as a relationship, each one has a unique 'id'.
To get the list of events with id's, issue a GET request to the
endpoint:

        api/v1/playlist/:id/events

where :id is the playlist id.  A [sample events
document](Samples.md#events) returned by this endpoint is available
here.

You may add new events, update existing events, or delete events via
this same endpoint:

* To add a new event, issue a POST to the endpoint;
* To modify an event, issue a PATCH to the endpoint;
* To delete an event, issue a DELETE to the endpoint.

In all cases, the request body contains a single event in the format
returned by a GET request to the endpoint.

For POST, upon success, you will receive an HTTP `200 OK` response;
the response body will contain a resource object with the id of the
created event.  For PATCH and DELETE, upon success, you will receive
an HTTP `204 No Content` response.

If you wish, you may also use the endpoint
`api/v1/playlist/:id/relationships/events` for event addition,
modification, and deletion.  The request and response semantics, as
well as the server action are the same.

X-APIKEY authentication is required; the operation will fail if you do
not own the playlist.

### Automatic timestamping

Events added to, or updated in a 'live' (currently on-air) playlist,
can be automatically timestamped with the current time.

* POST (API version 1): If you omit, or supply an empty `created`
attribute, or a `created` attribute whose value is 'auto' in a POST
request to the `api/v1/playlist/:id/events` endpoint, zookeeper will
automatically apply a timestamp to the new event, if the playlist is
currently on-air;
* POST (API version 1.1 and later): If you supply a `created` attribute
with value 'auto' in a POST request to the `api/v1.1/playlist/:id/events`
endpoint, zookeeper will automatically apply a timestamp to the new event,
if the playlist is currently on-air.  Unlike API version 1, an empty
or absent `created` attribute will **not** timestamp the event;
* PATCH (all API versions): If you supply a `created` attribute with
value 'auto' in a PATCH request to the events endpoint, zookeeper will
timestamp the existing event, if the playlist is currently on-air.
An empty or absent `created` attribute will not timestamp the event.

### Event resequencing

To reorder an event within a playlist, issue a PATCH request with a
`moveTo` meta key, the value of which is the id of the event currently
in the target position.

It is NOT necessary to specify attributes in the request body.
However, as with any PATCH request, you may provide attributes to
modify the event at the same time as the resequencing.

Example:

To move event with ID 12345 to the position currently occupied by
the event with ID 67890 in playlist 98765:

````
PATCH /api/v1/playlist/98765/events HTTP/1.1
X-APIKEY: eb5e0e0b42a84531af5f257ed61505050494788d
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "event",
    "id": "12345",
    "meta": {
      "moveTo": "67890"
    }
  }
}
````

Upon success, the timestamp of the resequenced event is cleared.

### Notes
* Added or modified events are automatically positioned to the correct
position in the playlist, relative to their created time;
* Added events with no created time are placed at the end of the playlist;
* To modify an event, you must specify _all_ attributes.  Exception: You
may change an event's timestamp by supplying only the `created` attribute.
