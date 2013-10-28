#CSV to GeoJSON

##Usage

To generate GeoJSON from a CSV file:

	php geojson.php filename.csv

Note that this will just write to `stdout` — which can be redirected to a file:

	php geojson.php filename.csv > filename.geojson


###Options

By default the script will look for fields labelled "lat" or "latitide" and "lng", "long" or "longitude". If the fields in a particular CSV are called something else, these can be specified as options:

	php geojson.php filename.csv field_x="x coord" field_y="y coord"

If both of these fields can't be found then the script will look for combined coordinates (in "lat,lng" format) in a single field. By default, the script will search for a field named "location" but this can be overridden using another option:

	php geojson.php filename.csv field="location map"

WGS84 latitudes and longitudes are assumed. To use an alternative coordinate reference system:

	php geojson.php filename.csv crs="osgrid"

Valid options are currently "wgs84" and "osgrid".


##Licence
