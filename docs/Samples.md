## Example Zookeeper JSON:API documents

The following are sample documents for each of the data types.

---
### <a id="album"></a> sample album document:

````
{
  "data": {
    "type": "album",
    "id": "922610",
    "attributes": {
      "artist": "[coll]: Jazz Around The World",
      "album": "Jazz Around The World",
      "category": "World",
      "medium": "CD",
      "size": "Full",
      "created": "2010-01-15",
      "updated": "2010-01-15",
      "location": "Library",
      "bin": "",
      "coll": true,
      "tracks": [{
        "seq": "1",
        "artist": "Chamberland, Chantal",
        "track": "La Mer",
        "url": ""
      }, {
        "seq": "2",
        "artist": "Niuver",
        "track": "Quiereme Mucho",
        "url": ""
      }, {
        "seq": "3",
        "artist": "Bassey, Blick",
        "track": "Donalina",
        "url": ""
      }, {
        "seq": "4",
        "artist": "Kora Jazz Trio",
        "track": "Chan Chan",
        "url": ""
      }, {
        "seq": "5",
        "artist": "Rigdon, Heather",
        "track": "Young And Naive",
        "url": ""
      }, {
        "seq": "6",
        "artist": "Kad",
        "track": "J'aime Mon Lit",
        "url": ""
      }, {
        "seq": "7",
        "artist": "Sherele",
        "track": "Polka Dot Blues",
        "url": ""
      }, {
        "seq": "8",
        "artist": "Pipi, Kataraina",
        "track": "Te Reo A Papatuanuku",
        "url": ""
      }, {
        "seq": "9",
        "artist": "Diabate, Keletigui W/Habib Koite & Bamada",
        "track": "Summertime At Bamako",
        "url": ""
      }, {
        "seq": "10",
        "artist": "Cobham, Billy And Asere",
        "track": "Destinos",
        "url": ""
      }, {
        "seq": "11",
        "artist": "Masekela, Hugh W/Malaika",
        "track": "Open The Door",
        "url": ""
      }]
    },
    "relationships": {
      "reviews": {
        "links": {
          "related": "/api/v1/album/922610/reviews"
        },
        "data": [{
          "type": "review",
          "id": "11653"
        }]
      },
      "label": {
        "links": {
          "related": "/api/v1/album/922610/label",
          "self": "/api/v1/album/922610/relationships/label"
        },
        "meta": {
          "name": "Putumayo World Music"
        },
        "data": {
          "type": "label",
          "id": "6976"
        }
      }
    },
    "links": {
      "self": "/api/v1/album/922610"
    }
  },
  "jsonapi": {
    "version": "1.0"
  }
}

````
---
### <a id="label"></a> sample label document:

````
{
  "data": {
    "type": "label",
    "id": "4008",
    "attributes": {
      "name": "Thicker Records",
      "attention": "",
      "address": "Po Box 881983",
      "city": "San Francisco",
      "state": "CA",
      "zip": "94188-1983",
      "phone": "415-641-8648",
      "fax": "",
      "mailcount": "0",
      "maillist": "G",
      "international": false,
      "pcreated": "1994-01-05",
      "modified": "1996-07-14",
      "url": null,
      "email": null
    },
    "links": {
      "self": "/api/v1/label/4008"
    }
  },
  "jsonapi": {
    "version": "1.0"
  }
}
````
---
### <a id="playlist"></a> sample playlist document:

````
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
        "type": "comment",
        "comment": "Rebroadcast of an episode originally aired on May 20, 2021.",
        "created": null
      }, {
        "artist": "Marika Papagika",
        "track": "Smyrneiko Minore",
        "album": "I Believe I'll Go Back Home: 1906\u20131959",
        "label": "Mississippi",
        "created": "21:00:00",
        "type": "spin"
      }, {
        "artist": "His Name Is Alive",
        "track": "Liadin",
        "album": "Hope Is a Candle",
        "label": "Disciples",
        "created": "21:04:00",
        "type": "spin"
      }, {
        "artist": "The Durutti Column",
        "track": "Weakness and Fever (originally released as a 7\" single)",
        "album": "LC (Reissue)",
        "label": "Factory Benelux",
        "created": "21:07:00",
        "type": "spin"
      }, {
        "artist": "For Against",
        "track": "You Only Live Twice",
        "album": "Aperture",
        "label": "Independent Project",
        "created": "21:12:00",
        "type": "spin",
        "xa:relationships": {
          "album": {
            "data": {
              "type": "album",
              "id": "118820"
            }
          }
        }
      }, {
        "artist": "Andrew Weathers & Hayden Pedigo",
        "track": "Tomorrow Is the Song I Sing",
        "album": "Big Tex, Here We Come",
        "label": "Debacle",
        "created": "21:16:00",
        "type": "spin"
      }, {
        "artist": "Souled American",
        "track": "Dark as a Dungeon",
        "album": "Sonny",
        "label": "Rough Trade",
        "created": "21:21:00",
        "type": "spin"
      }, {
        "type": "break",
        "created": null
      }, {
        "artist": "Hey Exit",
        "track": "Last Harvest",
        "album": "Eulogy for Land",
        "label": "Full Spectrum",
        "created": "21:26:00",
        "type": "spin"
      }, {
        "artist": "Calla",
        "track": "Elsewhere",
        "album": "Calla",
        "label": "Arena Rock Recording Co.",
        "created": "21:34:00",
        "type": "spin",
        "xa:relationships": {
          "album": {
            "data": {
              "type": "album",
              "id": "1060007"
            }
          }
        }
      }, {
        "artist": "claire rousay",
        "track": "discrete (the market)",
        "album": "a softer focus",
        "label": "American Dreams",
        "created": "21:39:00",
        "type": "spin"
      }, {
        "artist": "Jusell, Prymek, Sage, Shiroishi",
        "track": "Flower Clock",
        "album": "Yamawarau (\u5c71\u7b11\u3046)",
        "label": "cachedmedia",
        "created": "21:45:00",
        "type": "spin"
      }, {
        "artist": "Rolf Lislevand",
        "track": "Santiago De Murcia: Folias Gallegas",
        "album": "Altre Follie, 1500-1750",
        "label": "AliaVox",
        "created": "21:49:00",
        "type": "spin"
      }, {
        "artist": "Loren Mazzacane Connors",
        "track": "Dance Acadia",
        "album": "Evangeline",
        "label": "Road Cone",
        "created": "21:52:00",
        "type": "spin",
        "xa:relationships": {
          "album": {
            "data": {
              "type": "album",
              "id": "463845"
            }
          }
        }
      }, {
        "artist": "Paul Galbraith",
        "track": "Sonata No. 3 BWV 1005 in D Major",
        "album": "The Sonatas & Partitas (arr. for 8-String Guitar)",
        "label": "Delos",
        "created": "21:53:00",
        "type": "spin"
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
          "id": "1060007"
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
### <a id="events"></a> sample playlist events document:

````
{
  "data": [{
    "type": "event",
    "id": "818834",
    "attributes": {
      "type": "comment",
      "comment": "Rebroadcast of an episode originally aired on May 20, 2021.",
      "created": null
    }
  }, {
    "type": "event",
    "id": "818835",
    "attributes": {
      "type": "spin",
      "artist": "Marika Papagika",
      "track": "Smyrneiko Minore",
      "album": "I Believe I'll Go Back Home: 1906\u20131959",
      "label": "Mississippi",
      "created": "21:00:00"
    }
  }, {
    "type": "event",
    "id": "818836",
    "attributes": {
      "type": "spin",
      "artist": "His Name Is Alive",
      "track": "Liadin",
      "album": "Hope Is a Candle",
      "label": "Disciples",
      "created": "21:04:00"
    }
  }, {
    "type": "event",
    "id": "818837",
    "attributes": {
      "type": "spin",
      "artist": "The Durutti Column",
      "track": "Weakness and Fever (originally released as a 7\" single)",
      "album": "LC (Reissue)",
      "label": "Factory Benelux",
      "created": "21:07:00"
    }
  }, {
    "type": "event",
    "id": "818838",
    "attributes": {
      "type": "spin",
      "artist": "For Against",
      "track": "You Only Live Twice",
      "album": "Aperture",
      "label": "Independent Project",
      "created": "21:12:00"
    },
    "relationships": {
      "album": {
        "data": {
          "type": "album",
          "id": "118820"
        }
      }
    }
  }, {
    "type": "event",
    "id": "818839",
    "attributes": {
      "type": "spin",
      "artist": "Andrew Weathers & Hayden Pedigo",
      "track": "Tomorrow Is the Song I Sing",
      "album": "Big Tex, Here We Come",
      "label": "Debacle",
      "created": "21:16:00"
    }
  }, {
    "type": "event",
    "id": "818840",
    "attributes": {
      "type": "spin",
      "artist": "Souled American",
      "track": "Dark as a Dungeon",
      "album": "Sonny",
      "label": "Rough Trade",
      "created": "21:21:00"
    }
  }, {
    "type": "event",
    "id": "818841",
    "attributes": {
      "type": "break",
      "created": null
    }
  }, {
    "type": "event",
    "id": "818842",
    "attributes": {
      "type": "spin",
      "artist": "Hey Exit",
      "track": "Last Harvest",
      "album": "Eulogy for Land",
      "label": "Full Spectrum",
      "created": "21:26:00"
    }
  }, {
    "type": "event",
    "id": "818843",
    "attributes": {
      "type": "spin",
      "artist": "Calla",
      "track": "Elsewhere",
      "album": "Calla",
      "label": "Arena Rock Recording Co.",
      "created": "21:34:00"
    },
    "relationships": {
      "album": {
        "data": {
          "type": "album",
          "id": "1060007"
        }
      }
    }
  }, {
    "type": "event",
    "id": "818844",
    "attributes": {
      "type": "spin",
      "artist": "claire rousay",
      "track": "discrete (the market)",
      "album": "a softer focus",
      "label": "American Dreams",
      "created": "21:39:00"
    }
  }, {
    "type": "event",
    "id": "818845",
    "attributes": {
      "type": "spin",
      "artist": "Jusell, Prymek, Sage, Shiroishi",
      "track": "Flower Clock",
      "album": "Yamawarau (\u5c71\u7b11\u3046)",
      "label": "cachedmedia",
      "created": "21:45:00"
    }
  }, {
    "type": "event",
    "id": "818846",
    "attributes": {
      "type": "spin",
      "artist": "Rolf Lislevand",
      "track": "Santiago De Murcia: Folias Gallegas",
      "album": "Altre Follie, 1500-1750",
      "label": "AliaVox",
      "created": "21:49:00"
    }
  }, {
    "type": "event",
    "id": "818847",
    "attributes": {
      "type": "spin",
      "artist": "Loren Mazzacane Connors",
      "track": "Dance Acadia",
      "album": "Evangeline",
      "label": "Road Cone",
      "created": "21:52:00"
    },
    "relationships": {
      "album": {
        "data": {
          "type": "album",
          "id": "463845"
        }
      }
    }
  }, {
    "type": "event",
    "id": "818848",
    "attributes": {
      "type": "spin",
      "artist": "Paul Galbraith",
      "track": "Sonata No. 3 BWV 1005 in D Major",
      "album": "The Sonatas & Partitas (arr. for 8-String Guitar)",
      "label": "Delos",
      "created": "21:53:00"
    }
  }],
  "links": {
    "self": "/api/v1/playlist/42667/events"
  },
  "jsonapi": {
    "version": "1.0"
  }
}
````
---
### <a id="review"></a> sample review document:
````
{
  "data": {
    "type": "review",
    "id": "11653",
    "attributes": {
      "airname": "Decca",
      "published": true,
      "date": "2010-01-15",
      "review": "Another \u201cevery track is good\u201d release from Putumayo. Featuring established stars such as Huge Masekela and newcomers.  Stylish, fresh, and all so damned good.\r\n\r\n1. Slow, sexy  French version of \u201cThe Sea\u201d.  Yum! \r\n2. Slow and romantic w/delicate female vocals & a gentle lyricism.\r\n3. Acoustic guitar, sweet Afro-jazz w/warm male vocals.\r\n4. Chan Chan for kora?!?!  Check out this Afro-Cuban blend.\r\n5. Piano, female vocals in a jazzy/bluesy blend in English.  Stylish.\r\n6. Muted trumpet & piano low key, laid back French jazz.  Tres hip.\r\n7. Jazz/Klezmer blend w/sweet clarinet & a relaxed vibe.\r\n8. Mixes retro Hawaiian w/laid back jazz.  Light & breezy.\r\n9. Gershwin goes African. Balafon/guitar mix on \u201cSummertime\u201d\r\n10. Sexy jazz cha-cha w/sparkling trumpet & hip perc.\r\n11. Mid/upbeat Afro-jazz-soul w/cool vocals.  So good.\r\n"
    },
    "relationships": {
      "album": {
        "links": {
          "related": "/api/v1/review/11653/album",
          "self": "/api/v1/review/11653/relationships/album"
        },
        "meta": {
          "album": "Jazz Around The World",
          "artist": "[coll]: Jazz Around The World"
        },
        "data": {
          "type": "album",
          "id": "922610"
        }
      }
    },
    "links": {
      "self": "/api/v1/review/11653"
    }
  },
  "jsonapi": {
    "version": "1.0"
  }
}
````
