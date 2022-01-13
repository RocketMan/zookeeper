## JSON:API 'XA' extension

This extension provides a means to link complex attributes to related
resources.

### URI

The extension has the URI `https://zookeeper.ibinx.com/ext/xa`.

### Namespace

This extension uses the namespace `xa`.

### Document Structure

A document that supports this extension **MAY** include either of the
following elements within the `attributes` value of a resource,
provided that the element **MUST NOT** be a top-level attribute.

* `xa:relationships` - a 'relationships object' as defined by [section
  5.2.4](https://jsonapi.org/format/#document-resource-object-relationships)
  of the JSON:API specification;
* `xa:links` - a 'links object' as defined by [section
  5.6](https://jsonapi.org/format/#document-links) of the JSON:API
  specification.

Relationships specified by `xa:relationships` **MAY** also be included
in the `relationships` object of the resource.

### Non-Normative Reference

For a non-normative discussion of the problem space, see [Zookeeper
PR #263](https://github.com/RocketMan/zookeeper/pull/263).

### Example

A server might respond to a resource fetch as follows:

---
````
HTTP/1.1 200 OK
Content-Type: application/vnd.api+json; ext="https://zookeeper.ibinx.com/ext/xa"

{
  "data": {
    "type": "show",
    "id": "42667",
    "attributes": {
      "name": "Stranded at Settembrini's (rebroadcast from May 20, 2021)",
      "date": "2021-12-23",
      "time": "2100-2200",
      "airname": "DJ Away",
      "events": [{
        "artist": "For Against",
        "track": "You Only Live Twice",
        "album": "Aperture",
        "label": "Independent Project",
        "created": "21:12:00",
        "type": "track",
        "xa:relationships": {
          "album": {
            "data": {
              "type": "album",
              "id": "118820"
            }
          }
        }
      }, {
        "artist": "Loren Mazzacane Connors",
        "track": "Dance Acadia",
        "album": "Evangeline",
        "label": "Road Cone",
        "created": "21:52:00",
        "type": "track",
        "xa:relationships": {
          "album": {
            "data": {
              "type": "album",
              "id": "463845"
            }
          }
        }
      }]
    },
    "relationships": {
      "albums": {
        "links": {
          "related": "/api/v1/playlist/42667/albums"
        },
        "data": [{
          "type": "album",
          "id": "118820"
        }, {
          "type": "album",
          "id": "463845"
        }]
      }
    },
    "links": {
      "self": "/api/v1/playlist/42667"
    }
  },
  "jsonapi": {
    "version": "1.0"
  }
}
````
---
