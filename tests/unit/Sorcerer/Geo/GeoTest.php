<?php

use PHPUnit\Framework\TestCase;

use Sorcerer\Geo\GeoMath;

class GeoMathTest extends TestCase
{
    public function testLatitudeIsFloat()
    {
        $geo_math = new GeoMath();
        $geo_math->setLatitude(M_PI);
        
        $this->assertIsFloat($geo_math->getLatitude());
    }

    public function testLongitudeIsFloat()
    {
        $geo_math = new GeoMath();
        $geo_math->setLongitude(M_PI);

        $this->assertIsFloat($geo_math->getLongitude());
    }

    public function testMaxLatitudeIsFloat()
    {
        $geo_math = new GeoMath();
        $geo_math->setMaxLatitude(M_PI);

        $this->assertIsFloat($geo_math->getMaxLatitude());
    }

    public function testMinLatitudeIsFloat()
    {
        $geo_math = new GeoMath();
        $geo_math->setMinLatitude(M_PI);

        $this->assertIsFloat($geo_math->getMinLatitude());
    }

    public function testMaxLongitudeIsFloat()
    {
        $geo_math = new GeoMath();
        $geo_math->setMaxLongitude(M_PI);

        $this->assertIsFloat($geo_math->getMaxLongitude());
    }

    public function testMinLongitudeIsFloat()
    {
        $geo_math = new GeoMath();
        $geo_math->setMinLongitude(M_PI);

        $this->assertIsFloat($geo_math->getMinLongitude());
    }

    public function testCalculateRadiusPoints()
    {
        $geo_math = new GeoMath();
        $geo_math->setLatitude(47.6163254)
            ->setLongitude(-122.239754)
            ->setMiles(5.25)
            ->calculateRadiusPoints();

        $this->assertIsFloat($geo_math->getMaxLatitude());
        $this->assertIsFloat($geo_math->getMinLatitude());
        $this->assertIsFloat($geo_math->getMaxLongitude());
        $this->assertIsFloat($geo_math->getMinLongitude());
    }

    public function testDistanceIsFloat()
    {
        // Lat,Lng = 47.6017609,-122.213364
        // Lat,Lng = 47.6163254,-122.239754

        $geo_math = new GeoMath();
        $geo_math->setMaxLatitude(47.6017609)
                ->setMaxLongitude(-122.213364)
                ->setMinLatitude(47.6163254)
                ->setMinLongitude(-122.239754)
                ->calculateDistance();
        
        $this->assertIsFloat($geo_math->getDistance());
    }
}