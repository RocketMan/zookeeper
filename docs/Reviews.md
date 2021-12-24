## Zookeeper JSON:API :: Review

This is specific API information for the 'review' type.  For generic API
information, see the [JSON:API main page](./API.md).

### Fields

* airname
* date
* review

### Relations

* album (to-one)

### Filters

Review search by DJ airname is supported through the album type by using
the filter `reviews.airname.id`.
