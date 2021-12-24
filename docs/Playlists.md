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

Pagination is not supported.
