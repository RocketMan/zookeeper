## Zookeeper JSON:API :: Playlist

This is specific API information for the 'playlist' type.  For generic API
information, see the [JSON:API main page](./API.md).

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
  * comment
  * event
  * code

### Relations

There are no relations.

### Filters

  * date
  * id

Review search by airname is supported through the album type by using
the filter reviews.airname.id.

Pagination is not supported.
