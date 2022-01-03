## Zookeeper JSON:API :: Playlist

This is specific API information for the 'playlist' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/playlist` (filter/pagination) or
`api/v1/playlist/_id_`, where \_id_ is the id of a specific playlist.  See
below for a list of possible filter options.

An [example playlist document](Samples.md#playlist) is available here.

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
  * tag
  * comment
  * event
  * code

### Relations

There are no relations.

### Filters

You may specify at most one filter as a query string parameter of the
form `filter[_field_]=_value_`.  Possible fields are listed below.

  * date
  * id
  * match(event)

Fields match exactly, unless '*' is appended, in which case a stemming
search is done.  The 'match' keyword indicates a full-text search against
the indicated column.

Pagination is supported only for match.  Sorting is not supported.

### Insert

To insert a new playlist, issue a POST to `api/v1/playlist`.  Playlist
details are in the request body in the same format returned by GET.
X-APIKEY authentication required and you must own the playlist.

If you belong to 'v' group, you may insert playlists on behalf of
other users: You will own the list in these cases (i.e., can update or
delete them), but they will display publicly under the other user's
airname.

### Update

Playlist update is not supported.  Use Delete and Insert anew as
needed.

### Delete

Delete playlist with \_id_ by issuing a DELETE request to
`api/v1/playlist/_id_`.  X-APIKEY authentication required.
Delete will fail if you do not own the playlist.
