## Zookeeper JSON:API :: Label

This is specific API information for the 'label' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/label` (filter/pagination) or
`api/v1/label/_id_`, where \_id_ is the id of a specific label.  See
below for a list of possible filter options.

An [example label document](Samples.md#label) is available here.

### Fields

* name
* attention
* address
* city
* state
* zip
* phone
* fax
* mailcount
* maillist
* international
* pcreated (read-only)
* modified (read-only)
* url
* email

### Relations

There are no relations.

### Filters

You may specify at most one filter as a query string parameter of the
form `filter[_field_]=_value_`.  Possible fields are listed below.

* Offset Profile
  * name
  * match(name)
* Cursor Profile
  * name
  * album.id

Fields match exactly, unless '*' is appended, in which case a stemming
search is done.  The 'match' keyword indicates a full-text search against
the indicated column.

### Sorting

Specify sorting via the 'sort' query string parameter.  Values are listed
below.  Suffix with a '-' to reverse the sense of the sort.

* Offset Profile
  * artist
  * album
  * label

### Insert

To insert a new label, issue a POST to `api/v1/label`.  Label details
are in the request body in the same format returned by GET.  X-APIKEY
authentication required; you must belong to the 'm' group.

### Update

Update label with \_id_ by issuing a PATCH request to
`api/v1/label/_id_`.  Label details are in the request body in same
format returned by GET.  Attributes not specified in the PATCH request
remain unchanged.  X-APIKEY authentication required; you must belong to
the 'm' group.

### Delete

Label deletion is not supported.
