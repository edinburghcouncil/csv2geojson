<?php

/**
 * CSV to GeoJSON conversion
 *
 * @author Nev Stokes <neville.stokes@edinburgh.gov.uk>
 * @license MIT
 */

namespace EdinburghCouncil;

use NevStokes\Coords\LatLng;
use NevStokes\Coords\OSRef;

/**
 * Class to convert CSV files into GeoJSON format
 *
 * Look for column headings named lat, latitude, lng, long or longitude falling
 * back to a location field in "latitude,longitude" form.
 */
class CSV2GeoJSON
{
	/**
	 * [$_points description]
	 * @var [type]
	 */
	protected $_points;

	/**
	 * [$_options description]
	 * @var [type]
	 */
	protected $_options;

	/**
	 * [__construct description]
	 * @param [type] $filename [description]
	 * @param array  $options  [description]
	 * @todo  Refactor!
	 */
	public function __construct($filename, array $options = array())
	{
		// Check we can actually read the supplied file
		if (!file_exists($filename) || !is_readable($filename)) {
			throw new \RuntimeException('Can\'t read ' . $filename);
		}

		// Merge given options with defaults
		$this->_options = array_map('strtolower', $options + array(
			'crs'   => 'wgs84',
			'field' => 'location',
		));

		// Make sure we dealing with a known coordinate system
		if (!in_array($this->_options['crs'], array(
			'osgrid',
			'wgs84',
		))) {
			throw new \RuntimeException('Unknow coordinate system');
		}

		switch ($this->_options['crs']) {
			case 'osgrid':
				$pointCreator = array($this, 'pointFromOSGrid');
				break;

			case 'wgs84':
			default:
				$pointCreator = array($this, 'pointFromWGS84');
				break;
		}

		$this->_points = array();
		$lines = 0;

		if (false !== (($handle = fopen($filename, 'r')))) {
			while (false !== (($data = fgetcsv($handle)))) {
				// Process header row
				if (1 == ++$lines) {
					$headers = array_map('trim', $data);
					$headers = array_map('strtolower', $headers);

					if (($keys = preg_grep(
						'/^
							(lat(:?itude)? |
							lng |
							long(:?itude)?)
						$/x',
						$headers
					))) {
						$latKeys = preg_grep('/^lat(:?itude)?$/i', $keys);
						$lngKeys = preg_grep('/^lng|long(:?itude)?$/i', $keys);

						if (is_array($latKeys) && is_array($lngKeys)) {
							$latKey = key($latKeys);
							$lngKey = key($lngKeys);

							$callback = function($data) use (
								$latKey,
								$lngKey,
								$pointCreator
							) {
								$point = call_user_func($pointCreator, $data[$latKey], $data[$lngKey]);

								// remove the coordinates from the data
								unset($data[$latKey]);
								unset($data[$lngKey]);

								return $point;
							};
						} else {
							echo 'Latitude or longitude missing', PHP_EOL;
							fclose($handle);

							exit;
						}
					} elseif (isset($this->_options['field_x'])
						&& isset($this->_options['field_y'])
					) {
						$xKey = array_search($this->_options['field_x'], $headers);
						$yKey = array_search($this->_options['field_y'], $headers);

						if ((false === $xKey) || (false === $yKey)) {
							echo 'Coordinate field missing', PHP_EOL;
							fclose($handle);

							exit;
						}

						$callback = function($data) use (
							$xKey,
							$yKey,
							$pointCreator
						) {
							$point = call_user_func($pointCreator, $data[$xKey], $data[$yKey]);

							// remove the coordinates from the data
							unset($data[$xKey]);
							unset($data[$yKey]);

							return $point;
						};

					} elseif (false !== (($key = array_search(
						$this->_options['field'],
						$headers
					)))) {
						$callback = function($data) use ($key, $pointCreator) {
							$latLng = explode(',', $data[$key]);
							list($lat, $lng) = array_map('trim', $latLng);
							$point = call_user_func($pointCreator, $lat, $lng);

							// remove the coordinates from the data
							unset($data[$key]);

							return $point;
						};
					} else {
						echo 'Coords not found', PHP_EOL;
						fclose($handle);

						exit;
					}
				} else {
					$latLng = call_user_func($callback, &$data);

					$properties = array();
					foreach ($data as $index => $value) {
						$key = $headers[$index];
						$properties[$key] = $value;
					}

					$this->_points[] = array(
						'point'      => $latLng,
						'properties' => array_filter($properties),
					);
				}
			}

			fclose($handle);
		}
	}

	/**
	 * [pointFromOSGrid description]
	 * @param  [type] $easting  [description]
	 * @param  [type] $northing [description]
	 * @return  LatLng [description]
	 */
	public function pointFromOSGrid($easting, $northing)
	{
		$point = OSRef::create($easting, $northing);
		$point = $point->toLatLng();
		$point->OSGB36ToWGS84();

		return $point;
	}

	/**
	 * [pointFromWGS84 description]
	 * @param  [type] $lat [description]
	 * @param  [type] $lng [description]
	 * @return  LatLng [description]
	 */
	public function pointFromWGS84($lat, $lng)
	{
		return new LatLng($lat, $lng);
	}

	/**
	 * [_addPoints description]
	 * @return  array [description]
	 */
	protected function _addPoints()
	{
		$features = array();

		// add each point from the CSV as a feature
		foreach ($this->_points as $point) {
			$features[] = array(
				'type'     => 'Feature',
				'geometry' => array(
					'type'        => 'Point',
					'coordinates' => array(
						floatval($point['point']->lng),
						floatval($point['point']->lat),
					),
				),
				'properties'  => $point['properties'],
			);
		}

		return $features;
	}

	/**
	 * [write description]
	 * @return string geojson formatted JSON object
	 */
	public function write()
	{
		// geojson skeleton
		$collection = array(
			'type'     => 'FeatureCollection',
			'features' => $this->_addPoints(),
		);

		return json_encode($collection);
	}
}
