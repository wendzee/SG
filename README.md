# Trip Data to GeoJSON Converter

This PHP script processes GPS trip data from a CSV file and generates a **GeoJSON FeatureCollection**, where each trip is represented as a **LineString** with a unique color.  

---

## Features

- Reads trip data from a CSV file (fields: `device_id`, `lat`, `lon`, `timestamp`).
- Groups points by `device_id` to form separate trips.
- Outputs a valid **GeoJSON** file following the [GeoJSON specification](https://datatracker.ietf.org/doc/html/rfc7946).
- Assigns a different color to each trip for map visualization.


---

## Requirements

- **PHP** 7.4+ (or newer)
- A CSV file containing GPS data with the following structure:

```csv
device_id,lat,lon,timestamp
van007,14.670985,120.748402,2025-05-13T05:15:30
van007,15.872421,120.706506,2025-05-13T07:45:15
...
