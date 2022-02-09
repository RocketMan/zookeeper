## Zookeeper JSON:API :: Review

This is specific API information for the 'review' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Retrieval

Retrieval is via GET request to `api/v1/review/:id`, where :id is
the id of a specific review.

Filtering is not possible at the api/v1/review endpoint; however,
pagination of all reviews by a DJ is possible at the api/v1/album
endpoint, via the reviews.airname.id filter.

An [example review document](Samples.md#review) is available here.

### Fields

* airname
* published
* date
* review

### Relations

* album (to-one)

### Filters

* match(review)

`match(review)` does a full-text search against the review body.

Review search by DJ airname is supported through the api/v1/album
endpoint by using the filter `reviews.airname.id`.

**TIP:** It is often useful to access the name and artist of the album
associated with a review.  Zookeeper denormalizes this information and
makes it availble as metadata in the review's album relationship.  In
this way, it is not necessary to request separately the album just to
obtain the artist and album name.
````
     "relationships": {
       "album": {
         "links": {
           "related": "/api/v1/review/19184/album",
           "self": "/api/v1/review/19184/relationships/album"
         },
         "meta": {
           "album": "Rave Tapes",
           "artist": "Mogwai"
         },
         "data": {
           "type": "album",
           "id": "1049082"
         }
       }
     }
````

### Insert

To insert a new music review, issue a POST to `api/v1/review`.  Review
details are in the request body in the same format returned by GET.
X-APIKEY authentication required.

### Update

Update of music reviews is not supported.  Use Delete and Insert anew
as needed.

### Delete

Delete the review with :id by sending a DELETE request to
`api/v1/review/:id`.  X-APIKEY authentication required; delete
will fail if you do not own the review.
