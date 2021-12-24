## Zookeeper JSON:API :: Album

This is specific API information for the 'album' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/album` (filter/pagination) or
`api/v1/album/_id_`, where \_id_ is the tag of a specific album.  See
below for a list of possible filter options.

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
* tracks -- array of zero or more:
  * seq
  * track
  * artist (for compilations)
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
  * label.id
  * reviews.airname.id
* Cursor Profile
  * artist
  * id

### Sorting

Specify sorting via the 'sort' query string parameter.  Values are listed
below.  Suffix with a '-' to reverse the sense of the sort.

* Offset Profile
  * artist
  * album
  * label
  * track

### Insert

To insert a new album, issue a POST to `api/v1/album`.  Album details
are in the request body in the same format returned by GET.  X-APIKEY
authentication required, must belong to 'm' group for adding an
album.

### Update

Update album with tag \_id_ by issuing a PATCH request to
`api/v1/album/_id_`.  Album details are in the request body in same
format returned by GET.  Attributes not specified in the PATCH request
remain unchanged.  X-APIKEY authentication required, must belong to
'm' group.

Update the album's linked label by issuing a PATCH request to
`api/v1/album/_id_/relationships/label`, where \_id_ is the album tag.
The request body should contain a JSON document of the form:

    { "data": { "type": "label", "id": _lid_ } }

Where \_lid_ is the ID of the desired label.

### Delete

Delete the album with tag \_id_ by sending a DELETE request to
`api/v1/album/_id_`.  Delete will fail if the album has reviews, or if
it has ever been in the a-file or has charted.  DELETE is supported
for all types except label.  X-APIKEY authentication required; you
must belong to the 'm' group for deleting an album.
