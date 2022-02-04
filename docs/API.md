## Zookeeper JSON:API

Zookeeper exposes a JSON:API v1.0 REST API to facilitate programmatic
integration with external applications.

The endpoints exposed by the API are:

* [api/v1/album](Albums.md)
* [api/v1/label](Labels.md)
* [api/v1/playlist](Playlists.md)
* [api/v1/review](Reviews.md)

The endpoints are relative to the installation directory of zookeeper.

You will find detailed information about each endpoint through the
links above.  The following is a discussion of the general conventions
of the API.

See the [JSON:API specification](https://jsonapi.org/format/) for
information on the request format and document structure.

### Retrieval

* Retreive an album (label, playlist, review) by doing a GET request
  to the endpoint (e.g, `api/v1/album/1001` retrieves album with tag
  1001).  In place of album, substitute label, playlist, or review to
  retrieve the other data types.

* You can request related information be included in the response by
  adding an 'include' query string parameter with comma separated
  values (e.g., `api/v1/album/1001?include=label,reviews`)

* You can also request specific related information by suffixing the
  path info of the resource (e.g., `api/v1/album/1001/reviews` returns
  all reviews associated with album tag 1001)

* You can search albums, labels, and playlists.  For example,
  `api/v1/playlist?filter[date]=2021-12-16` will give you all
  playlists on 2021-12-16.  See the links above for details of the
  filter options that are available for each type.

* You can request specific attributes (e.g.,
  `api/v1/playlist/1?fields[show]=name,airname,date`) This will
  exclude all attributes not named.  You may also use negative
  attributes to exclude specific fields (e.g.,
  `api/v1/album/1001?fields[album]=-tracks` includes all fields except
  for 'tracks')  See the links at the top of the page for available fields
  for each type.

#### Pagination

Pagination is supported via both the JSON:API cursor profile as well
as the offset profile.  Cursor pagination is more limited; it is
useful for scrolling through the music library or labels.  For
example, `api/v1/album?filter[artist]=_value_&page[profile]=cursor`
will zoom to the artist named \_value_.

The offset pagination profile is the default.  Request the cursor
profile with query string parameter `page[profile]=cursor`.

Other query string parameters are:
  * **filter[_name_]=_key_** (required; e.g., `filter[artist]=example`)
  * **page[size]=_count_** (default 50)
  * **page[offset]=_offset_** (for the offset profile, default is 0)

For the **offset profile**, the response contains pagination information
in the metadata of the `links/first` element:
  * `links/first/meta/total` = total number of matches
  * `links/first/meta/more` = number of matches remaining after the current page
  * `links/first/meta/offset` = offset of the current page

As well, the `links/next` element contains a link to retrieve the next
page.  It is omitted if there are no more pages.

For the **cursor pagination profile**, the response contains elements
`links/prev`, `links/prevLine`, `links/next`, and `links/nextLine`, which
are links to the corresponding page.

See the hyperlinks at the top of the page for specific filters that are
supported by each type.

### Creation and update

* **Create** an object by sending a POST request.  For example, to
    insert a new album, issue a POST to `api/v1/album`.  Album details
    are in request body in the same format returned by GET.  X-APIKEY
    authentication required, must belong to 'm' group for adding an
    album.  POST is supported for all types (album, label, playlist,
    review).

* **Updates** are performed by sending a PATCH request.  For example,
    update album :id with PATCH to `api/v1/album/:id`.  Album details
    are in request body in same format returned by GET.  X-APIKEY
    authentication required, must belong to 'm' group.  PATCH is
    supported for album and label only.

* Attributes not specified in the PATCH request remain unchanged.

* **Delete** an object with a DELETE request.  For example, delete the
    album with tag :id by sending a DELETE request to
    `api/v1/album/:id`.  Delete will fail if the album has reviews,
    or if it has ever been in the a-file or has charted.  DELETE is
    supported for all types except label.  X-APIKEY authentication
    required; you must belong to the 'm' group for deleting an album.

* For playlist insert/delete, X-APIKEY authentication required and you
  must own the playlist.  If you belong to 'v' group, you may insert
  playlists on behalf of other users: You will own the list in these
  cases (i.e., can edit or delete them), but they will display
  publicly under the other user's airname.

