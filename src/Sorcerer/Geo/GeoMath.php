<?php
/**
 * Geo-Math
 * 
 * Handles math calculations needed for Geography. 
 *  IE: Distance between two geocoded points
 *
 * @package     Sorcerer\Geo\GeoMath
 * @author      Eric Harris <ericktheredd5875@gmail.com>
 * @copyright   2024 - Eric Harris
 * @version     x.x.x - Comments or Notes
 * @deprecated  x.x.x - Comments or Notes
 */

/*----------  Class Namespacing  ----------*/
namespace Sorcerer\Geo;

/*==================================================
=            Classes Used by this Class            =
==================================================*/
use Sorcerer\Geo\GeoMathExcepton;
/*=====  End of Classes Used by this Class  ======*/

class GeoMath
{
    const EARTH_RADIUS_MILES = 3963;
    const EQUATOR_LAT_MILE   = 69.172;

    private $latitude      = 0.00;
    private $max_latitude  = 0.00;
    private $min_latitude  = 0.00;

    private $longitude     = 0.00;
    private $max_longitude = 0.00;
    private $min_longitude = 0.00;

    private $miles         = 0.00;
    private $distance      = 0.00;

    function __construct()
    {
        
        return $this;
    }

    /*** SET Methods ***/
    
    /**
     * Set the Latitude variable
     *
     * @param float $_lat
     * @return self
     */
    public function setLatitude(float $_lat): self
    {
        $this->latitude = $_lat;
        return $this;
    }

    public function setMaxLatitude(float $max_lat): self
    {
        $this->max_latitude = $max_lat;
        return $this;
    }

    public function setMinLatitude(float $min_lat): self
    {
        $this->min_latitude = $min_lat;
        return $this;
    }

    public function setLongitude(float $_lng): self
    {
        $this->longitude = $_lng;
        return $this;
    }

    public function setMaxLongitude(float $max_lng): self
    {
        $this->max_longitude = $max_lng;
        return $this;
    }

    public function setMinLongitude(float $min_lng): self
    {
        $this->min_longitude = $min_lng;
        return $this;
    }

    public function setMiles($_miles): self
    {
        $this->miles = $_miles;
        return $this;
    }

    public function setDistance(float $_distance): self
    {
        $this->distance = $_distance;
        return $this;
    }


    /*** GET Methods ***/
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getMaxLatitude(): float
    {
        return $this->max_latitude;
    }

    public function getMinLatitude(): float
    {
        return $this->min_latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getMaxLongitude(): float
    {
        return $this->max_longitude;
    }

    public function getMinLongitude(): float
    {
        return $this->min_longitude;
    }

    public function getMiles()
    {
        return $this->miles;
    }

    public function getDistance(): float
    {
        return $this->distance;
    }

    /** Methods **/
    public function calculateRadiusPoints(): self
    {
        $this->setMaxLatitude($this->latitude + $this->miles / SELF::EQUATOR_LAT_MILE);
        $this->setMinLatitude($this->latitude - ($this->max_latitude - $this->latitude));

        $this->setMaxLongitude($this->longitude + $this->miles 
                                / (cos($this->min_latitude * M_PI / 180) * SELF::EQUATOR_LAT_MILE));
        $this->setMinLongitude($this->longitude - ($this->max_longitude - $this->longitude));

        return $this;
    }

    function calculateDistance(): self
    {
        $dblLat1  = $this->max_latitude;
        $dblLong1 = $this->max_longitude;
        $dblLat2  = $this->min_latitude;
        $dblLong2 = $this->min_longitude;

        $dist = 0;

        $dist = sin($dblLat1 * M_PI/180)
                * sin($dblLat2 * M_PI/180 ) + cos($dblLat1 * M_PI/180)
                * cos($dblLat2 * M_PI/180)
                * cos(abs(($dblLong1 * M_PI/180) - ($dblLong2 * M_PI/180)));
        $dist = atan((sqrt(1 - pow($dist, 2)))/$dist);
        $dist = (1.852 * 60.0 * (($dist/M_PI) * 180) ) / 1.609344;

        $this->setDistance($dist);

        return $this;
    }

    function __destruct()
    {
        
    }
}
/* End of file GeoMath.php */
/* Location: /Sorcerer/Geo/GeoMath.php */