<?php

namespace Drupal\Tests\weather\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests functions of weather.module.
 *
 * @group Weather
 */
class FunctionsTest extends BrowserTestBase {

  use WeatherCommonTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['weather'];

  /**
   * The tests don't need markup, so use 'stark' as theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Test _weather_get_link_for_geoid().
   *
   * @throws \ReflectionException
   */
  public function testFunctionWeatherGetLinkForGeoId() {
    // Fill database tables with test data.
    $this->weatherFillWeatherSchema();
    // Test different numbers for system-wide displays.
    $link = $this->weatherGetInformationAboutGeoid('geonames_2911298')['link'];
    $this->assertEquals('Hamburg/Hamburg', $link);
    // Test different numbers for yr.no links.
    $link = $this->weatherGetLinkForGeoId('geonames_2911298', 'yr.no');
    $this->assertEquals('https://www.yr.no/place/Germany/Hamburg/Hamburg/', $link);
    $link = $this->weatherGetLinkForGeoId('geonames_2911298', 'system-wide');
    $this->assertEquals('weather/Germany/Hamburg/Hamburg/1', $link);
    $link = $this->weatherGetLinkForGeoId('geonames_2911298', 'default');
    $this->assertEquals('weather/Germany/Hamburg/Hamburg', $link);
    $link = $this->weatherGetLinkForGeoId('geonames_2911298', 'user');
    $this->assertEquals('weather/Germany/Hamburg/Hamburg/u', $link);
    $link = $this->weatherGetLinkForGeoId('geonames_2911298', 'yr');
    $this->assertEquals('https://www.yr.no/place/Germany/Hamburg/Hamburg/forecast.xml', $link);
  }

}
