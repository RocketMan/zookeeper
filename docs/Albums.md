## Zookeeper JSON:API :: Album

This is specific API information for the 'album' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Fields

* artist
* album
* category
* medium
* size
* created
* updated
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

#### Offset Profile
  * artist
  * album
  * track
  * label.id
  * reviews.airname.id

#### Cursor Profile
  * artist
  * id

### Sorting

#### Offset Profile
  * artist
  * album
  * label
  * track
