# ZIP coordinates for staff invitation filtering

`us-zcta.csv` contains the `GEOID`, `INTPTLAT`, and `INTPTLONG` columns
from the U.S. Census Bureau's 2026 national ZIP Code Tabulation Area Gazetteer,
renamed `zip`, `latitude`, and `longitude`. ZIPs remain five-character strings.

- Source: https://www.census.gov/geographies/reference-files/time-series/geo/gazetteer-files.html
- Download: https://www2.census.gov/geo/docs/maps-data/data/gazetteer/2026_Gazetteer/2026_Gaz_zcta_national.zip
- Retrieved: 2026-11-25
- Original ZIP SHA-256: `f1e9046b91f6e60686a99343cb2752834c02b0764939403b331c78c017e2c54c`

To refresh, download the national ZCTA archive, read the pipe-delimited TXT,
and export these three columns as UTF-8 CSV in source order. Validate unique
five-digit ZIPs and coordinate ranges before replacing the bundled file.

These are representative points for statistical ZIP areas, not street-address
coordinates or driving distances. Some USPS ZIPs have no matching ZCTA. Missing
or malformed locations are excluded only while a distance filter is active;
Any distance retains the existing discovery behavior. ZIP+4 inputs use the
first five digits. Same-ZIP estimates can be zero despite different addresses.

The file ships with the application. No API key, database migration, external
geocoding request, or transmission of customer addresses is needed at runtime.
