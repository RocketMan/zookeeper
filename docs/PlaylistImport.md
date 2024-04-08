## Example Zookeeper Online JSON:API playlist import

In the following example, we import a playlist, inclusive of all its
events, in a single request.

If you want to create a show and then dynamically add events to it,
see the [playlist creation](PlaylistEvents.md) example.

Playlist import requires a valid API Key, which you can manage from
within the application (Edit Profile > Manage API Keys).

### Request:

The playlist details are included in the request body in the same
format returned by GET.

If you belong to 'v' group, you may insert playlists on behalf of
other users: You will own the list in these cases (i.e., can update or
delete them), but they will display publicly under the other user's
airname.

---
````
POST /api/v2/playlist HTTP/1.1
X-APIKEY: eb5e0e0b42a84531af5f257ed61505050494788d
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "show",
    "attributes": {
      "name": "Example Playlist",
      "date": "2022-01-01",
      "time": "1800-2000",
      "airname": "Sample DJ"
    },
    "relationships": {
      "events": {
        "data": [{
          "type": "event",
          "id": "1"
	}, {
          "type": "event",
          "id": "2"
        }]
      }
    }
  },
  "included": [{
      "type": "event",
      "id": "1",
      "attributes": {
          "type": "spin",
	  "artist": "example artist",
	  "track": "example track",
	  "album": "example album",
	  "label": "example label",
	  "created": "2022-01-01 18:00:00"
      }
  }, {
      "type": "event",
      "id": "2",
      "attributes": {
          "type": "spin",
	  "track": "another track",
	  "created": "2022-01-01 18:02:30"
      },
      "relationships": {
          "album": {
	      "data": {
	          "type": "album",
		  "id": "1060007"
	      }
	  }
      }
  }]
}
````
---

The album tag, if any, is specified in the relationships stanza of the
included event.  If an album tag is supplied, it is unnecessary to
specify the `album` or `label` attributes, or the `artist` attribute
for non-compilations, as these will be populated automatically from
the album record.

### The server responds:
---
````
HTTP/1.1 201 Created
Location: /api/v2/playlist/921
Content-Length: 0
````
---

Upon successful playlist creation, the server returns HTTP code `201
Created`, together with a response header `Location` that identifies
the newly created playlist resource.  This is the expected response,
per [section 7.1.2.1](https://jsonapi.org/format/#crud-creating-responses)
of the JSON:API specification.
