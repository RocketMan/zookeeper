## Zookeeper JSON:API :: Review

This is specific API information for the 'review' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/review/_id_`, where \_id_ is
the id of a specific review.

Filtering is not possible at the api/v1/review endpoint; however,
pagination of all reviews by a DJ is possible at the api/v1/album
endpoint, via the reviews.airname.id filter.

An [example review document](Samples.md#review) is available here.

### Fields

* airname
* date
* review

### Relations

* album (to-one)

### Filters

Review search by DJ airname is supported through the api/v1/album
endpoint by using the filter `reviews.airname.id`.

### Insert

To insert a new music review, issue a POST to `api/v1/review`.  Review
details are in the request body in the same format returned by GET.
X-APIKEY authentication required.

### Update

Update of music reviews is not supported.  Use Delete and Insert anew
as needed.

### Delete

Delete the review with \_id_ by sending a DELETE request to
`api/v1/review/_id_`.  X-APIKEY authentication required; delete
will fail if you do not own the review.
