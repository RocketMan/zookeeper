## Zookeeper JSON:API :: Album

This is specific API information for the 'album' type.  For generic API
information, see the [JSON:API main page](./API.md).

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

* Offset Profile
  * artist
  * album
  * label
  * track

### Update

Update the album's label by issuing a PATCH request to
`api/v1/album/_id_/relationships/label`, where \_id_ is the album tag.
The request body should contain a JSON document of the form:

    { "data": { "type": "label", "id": _lid_ } }

Where \_lid_ is the ID of the desired label.
