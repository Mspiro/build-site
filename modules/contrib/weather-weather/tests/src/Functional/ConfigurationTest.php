<?php

namespace Drupal\Tests\weather\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests configuration of weather displays.
 *
 * @group Weather
 */
class ConfigurationTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['weather', 'block'];

  /**
   * The tests don't need markup, so use 'stark' as theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  use WeatherCommonTestTrait;

  /**
   * Tests configuration of weather block.
   *
   * @throws \ReflectionException
   */
  public function testConfiguration() {
    // This user may setup a system-wide weather block.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer system-wide weather',
      'administer blocks',
    ]);
    // Test with admin user.
    $this->drupalLogin($admin_user);

    // First case.
    // Set a fixed time for testing to 2013-10-07 20:00:00 UTC.
    $config = \Drupal::configFactory()->getEditable('weather.settings');
    $config->set('weather_time_for_testing', 1381176000)->save();

    // Second case.
    // Enable a system-wide weather block.
    $this->drupalGet('admin/config/user-interface/weather/system-wide/add');
    $this->submitForm([], 'Save');
    $this->drupalGet('admin/config/user-interface/weather/system-wide/1/add');
    $this->assertSession()->pageTextContains('You do not have any weather places in system.');

    // Third case.
    // Configure the default place.
    $this->weatherFillWeatherSchema('geonames_703448.xml');
    $this->drupalGet('admin/config/user-interface/weather/system-wide/1/add');
    $this->submitForm([], 'Save');

    // Clear site cache to add block.
    \Drupal::cache()->invalidateAll();

    // Fourth case - enable & place block.
    $this->drupalGet('admin/structure/block/add/weather_system_display_block:1/stark');
    $this->submitForm(['region' => 'sidebar_first'], 'Save block');

    // Check block existing in blocks list.
    $this->drupalGet('admin/structure/block/list/stark');
    $this->assertSession()->pageTextContains('Weather: system-wide display (#1)');

    // Make sure that the weather block is displayed
    // with correct forecast data.
    $this->drupalGet('weather/Ukraine/Kiev/Kyiv/1');
    $this->assertSession()->responseContains('<div class="weather">');
    $this->assertSession()->pageTextContains('00:00-06:00');
    $this->assertSession()->responseContains('&thinsp;°C');
    $this->assertSession()->pageTextContains('18:00-00:00');
    $this->assertSession()->responseContains('&thinsp;°C');

    // Change temperature units to Fahrenheit.
    $edit = ['config[temperature]' => 'fahrenheit'];
    $this->drupalGet('admin/config/user-interface/weather/system-wide/1/edit');
    $this->submitForm($edit, 'Save');

    // Clear site cache to add block.
    \Drupal::cache()->invalidateAll();
    // Make sure that the weather block now shows different temperatures.
    $this->drupalGet('weather/Ukraine/Kiev/Kyiv/1');
    $this->assertSession()->responseContains('&thinsp;°F');
    $this->assertSession()->responseContains('&thinsp;°F');
    // Logout current user.
    $this->drupalLogout();
  }

}
