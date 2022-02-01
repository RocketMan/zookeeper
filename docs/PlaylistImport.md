## Example Zookeeper JSON:API playlist import

The following example imports a show and its events in a single request.

If you want to create a show and then dynamically add events to it,
see the [playlist creation](PlaylistEvents.md) example.

Playlist creation requires a valid API Key, which you can manage from
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
POST /api/v1/playlist HTTP/1.1
X-APIKEY: eb5e0e0b42a84531af5f257ed61505050494788d
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "show",
    "attributes": {
      "name": "example show",
      "date": "2022-01-31",
      "time": "1700-1900",
      "airname": "Jim",
      "events": [{
        "type": "comment",
        "comment": "welcome to the show"
        "created": "17:00:00",
      }, {
        "type": "spin",
        "artist": "Calla",
        "track": "Elsewhere",
        "album": "Calla",
        "label": "Arena Rock Recording Co."
        "created": "17:01:00",
        "xa:relationships": {
          "album": {
            "data": {
              "type": "album",
              "id": "1060007"
            }
          }
        }
      }]
    }
  }
}
````
---

The zookeeper album tag, if any, is specified in the xa:relationships
element.  For more information, see the [Complex Attribute
Extension](xa.md).

### The server responds:
---
````
HTTP/1.1 201 Created
Location: /api/v1/playlist/921
Content-Length: 0
````
---

Upon successful playlist creation, the server returns HTTP code `201
Created`, together with a response header `Location` that identifies
the newly created playlist resource.  This is the expected response,
per [section 7.1.2.1](https://jsonapi.org/format/#crud-creating-responses)
of the JSON:API specification.
