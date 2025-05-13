## Zookeeper Online JSON:API :: Album

This is specific API information for the 'album' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/album` (filter/pagination) or
`api/v1/album/:id`, where :id is the tag of a specific album.  See
below for a list of possible filter options.

An [example album document](Samples.md#album) is available here.

### Fields

* artist
* album
* category
* medium
* size
* created (read-only)
* updated (read-only)
* location
* bin
* coll
* albumart (requires X-APIKEY authentication; see below)
* tracks -- array of zero or more:
  * seq
  * track
  * artist (for compilations)
  * duration (hh:mm:ss or null)
  * url

### Relations

* label (to-one)
* reviews (to-many)

### Filters

You may specify at most one filter as a query string parameter of the
form `filter[_field_]=_value_`.  Possible fields are listed below.

* Offset Profile
  * artist
  * album
  * track
  * location (requires X-APIKEY authentication; see below)
  * label.id
  * reviews.airname.id
  * match(artist)
  * match(artist,album)
  * match(artist,track)
  * match(track)
* Cursor Profile
  * artist
  * id

Note for the track filter, page[size] refers to the number of tracks
returned, not the number of albums.

Fields match exactly, unless '*' is appended, in which case a stemming
search is done.  The 'match' keyword indicates a full-text search against
the indicated columns.

X-APIKEY authentication is required to access the `albumart` property.
`albumart` is the absolute URI path to the cached resource, null if
artwork is not cached for the album, or empty string if artwork is
disabled for the album.  When setting `albumart` in a POST or PATCH
request, supply the full URL to the image you want to cache for the
album, null to remove album art, or empty string to disable album art.
Alternatively, you may specify a [data URL](https://developer.mozilla.org/en-US/docs/Web/URI/Reference/Schemes/data) with the image data.

X-APIKEY authentication is required for use of the location filter.
Possible filter values are (case-insensitive): A-File, Deaccessioned,
Library, Missing, Needs Repair, Out for Review, Pending Appr,
Received, Review Shelf, Storage.  With Storage, you may supply an
additional, optional filter `bin` to specify the storage location.

### Sorting

Specify sorting via the 'sort' query string parameter.  Values are listed
below.  Suffix with a '-' to reverse the sense of the sort.

* Offset Profile
  * artist
  * album
  * label
  * track

**TIP:** It is often useful to access the name of the music label
associated with an album.  Zookeeper Online denormalizes the label name and
makes it availble as metadata in the album's label relationship.  In
this way, it is not necessary to request separately the label
information just to obtain the name.
````
     "relationships": {
       "label": {
         "links": {
           "related": "/api/v1/album/825555/label",
           "self": "/api/v1/album/825555/relationships/label"
         },
         "meta": {
           "name": "Quango Music Group"
         },
         "data": {
           "type": "label",
           "id": "7211"
         }
       }
     }
````

### Insert

To insert a new album, issue a POST to `api/v1/album`.  Album details
are in the request body in the same format returned by GET.  X-APIKEY
authentication required; you must belong to the 'm' group.

There are three possibilities for specifying the music label:
1. By ID.  Simply specify the ID of the desired label in the album's
   `relationships.label.data.id`:
````
     "relationships": {
       "label": {
         "data": { "type": "label", "id": _id_ }
       }
     }
````
2. By name.  If you do not know the label's ID, then provide the name in
   an `included` object of type label.  Assign a locally generated ID
   for the included object, and specify this value for the album's
   `relationships.label.data.id`.  Zookeeper Online will lookup and assign the
   album by name.  (The locally generated ID will be discarded.)
````
   {"data":[{
      "type":"album",
          ...
      "relationships": {
        "label": {
          "data": { "type": "label", "id": "local-id-1234" }
        }
      }
    }],
    "included": [{
      "type": "label",
      "id": "local-id-1234",
      "attributes": {
        "name": __the name__
      }
    }]}
````
3. New label.  This is a variant of the second case above, but where name
   does not already exist.  In this case, Zookeeper Online will create a new label.
   Specify any other desired label attributes in the `included` object's
   attributes.

See the [JSON:API specification](https://jsonapi.org/format/) for more
information about `relationships` and `included`.

### Update

Update album with tag :id by issuing a PATCH request to
`api/v1/album/:id`.  Album details are in the request body in same
format returned by GET.  Attributes not specified in the PATCH request
remain unchanged.  X-APIKEY authentication required; you must belong to
the 'm' group.

Update the album's linked label by issuing a PATCH request to
`api/v1/album/:id/relationships/label`, where :id is the album tag.
The request body should contain a JSON document of the form:

    { "data": { "type": "label", "id": _lid_ } }

Where \_lid_ is the ID of the desired label.

### Delete

Delete the album with tag :id by sending a DELETE request to
`api/v1/album/:id`.  Delete will fail if the album has reviews, or if
it has ever been in the a-file or has charted.  X-APIKEY
authentication required; you must belong to the 'm' group.

## Print Queue

The album printer queue can be accessed and managed via the 'printq'
pseudo-relation.  Requests are as follows:

* `GET api/v1/album/printq` - retrieves collection of albums in the user's print queue
* `POST api/v1/album/:id/printq` - adds album with tag :id to the print queue
* `DELETE api/v1/album/:id/printq` - removes album with tag :id from the print queue

If you wish, you may also use the endpoint
`api/v1/album/:id/relationships/printq` for the POST and DELETE
methods.  The request and response semantics, as well as the server
action are the same.

All requests require X-APIKEY authentication, and you must belong to
the 'm' group.

Upon success, POST and DELETE return an HTTP `204 No Content` response.

The POST request will fail if the album is already in the user's
queue.
