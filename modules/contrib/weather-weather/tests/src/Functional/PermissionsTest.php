<?php

namespace Drupal\Tests\weather\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests permissions and access settings for different users.
 *
 * @group Weather
 *
 * @requires module weather
 * @requires module block
 */
class PermissionsTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['weather', 'block'];

  use WeatherCommonTestTrait;

  /**
   * The tests don't need markup, so use 'stark' as theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Permissions of weather block.
   *
   * This test requires that at least one system wide block is enabled.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \ReflectionException
   */
  public function testPermissions() {

    // Set a fixed time for testing to 2013-10-07 20:00:00 UTC.
    $config = \Drupal::configFactory()->getEditable('weather.settings');
    $config->set('weather_time_for_testing', 1381176000)->save();
    // Fill database with a test data.
    $this->weatherFillWeatherSchema('geonames_703448.xml');

    // This user is allowed to view the system block.
    $normal_user = $this->drupalCreateUser([
      'access content',
    ]);
    // This user is allowed to administer a custom weather block.
    $weather_user_1 = $this->drupalCreateUser([
      'access content',
      'administer blocks',
    ]);
    // This user is also allowed to administer a custom weather block,
    // like weather_user_1. However, they are not allowed to edit the
    // custom block of weather_user_1.
    $weather_user_2 = $this->drupalCreateUser([
      'access content',
      'administer blocks',
    ]);
    // This user may setup a system-wide weather block.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer system-wide weather',
      'administer blocks',
    ]);

    // Test with admin user.
    $this->drupalLogin($admin_user);
    // Get different pages.
    $this->drupalGet('node');
    $this->drupalGet('admin/config/user-interface/weather');
    $this->assertSession()->pageTextContains('Directory for custom images');

    // Enable a system-wide weather block.
    $this->drupalGet('admin/config/user-interface/weather/system-wide/add');
    $this->submitForm([], 'Save');

    // Make sure that the weather block is not
    // displayed without a configured place.
    $this->drupalGet('node');
    $this->assertSession()->responseNotContains('<div class="weather">');
    $this->assertSession()->linkNotExists('Kyiv');
    $this->assertSession()->linkByHrefNotExists('weather/Ukraine/Kiev/Kyiv/1');
    // Configure the default place.
    $this->drupalGet('admin/config/user-interface/weather/system-wide/1/add');
    $this->submitForm([], 'Save');
    // Enable & place block.
    $this->drupalGet('admin/structure/block/add/weather_system_display_block:1/stark');
    $this->submitForm(['region' => 'sidebar_first'], 'Save block');

    $this->drupalGet('admin/config/user-interface/weather');
    $this->assertSession()->pageTextContains('Directory for custom images');
    $this->assertSession()->pageTextContains('Kyiv');
    $this->assertSession()->pageTextContains('Add location to this display');
    // Make sure that the weather block is displayed now.
    $this->drupalGet('node');
    $this->assertSession()->responseContains('<div class="weather">');
    $this->assertSession()->linkExists('Kyiv');
    $this->assertSession()->linkByHrefExists('weather/Ukraine/Kiev/Kyiv/1');
    // Logout current user.
    $this->drupalLogout();

    // Test with normal user.
    $this->drupalLogin($normal_user);
    // Get front page.
    $this->drupalGet('node');
    $this->assertSession()->pageTextContains('Weather');
    $this->assertSession()->responseContains('<div class="weather">');
    $this->assertSession()->linkExists('Kyiv');
    $this->assertSession()->linkByHrefExists('weather/Ukraine/Kiev/Kyiv/1');

    // Administration of weather module should be forbidden.
    $this->drupalGet('admin/config/user-interface/weather');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->pageTextContains('You are not authorized to access this page');
    // Search page should be forbidden.
    $this->drupalGet('weather');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('The requested page could not be found');
    // The user may view the page with the detailed forecast of the
    // system-wide display.
    $this->drupalGet('weather/Ukraine/Kiev/Kyiv/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Weather forecast');
    $this->assertSession()->pageTextContains('Kyiv');
    // Logout current user.
    $this->drupalLogout();

    // Test with weather user 1.
    $this->drupalLogin($weather_user_1);
    // Get front page.
    $this->drupalGet('node');
    $this->assertSession()->pageTextContains('Weather');
    $this->assertSession()->responseContains('<div class="weather">');
    $this->assertSession()->linkExists('Kyiv');
    $this->assertSession()->linkByHrefExists('weather/Ukraine/Kiev/Kyiv/1');

    // Administration of weather module should be forbidden.
    $this->drupalGet('admin/config/user-interface/weather');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->pageTextContains('You are not authorized to access this page');
    // Search page should be forbidden.
    $this->drupalGet('weather');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('The requested page could not be found');
    // Using the direct search URL should be forbidden.
    $this->drupalGet('weather/zollenspieker');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('The requested page could not be found');
    // The user may view the page with the detailed forecast of the
    // system-wide display.
    $this->drupalGet('weather/Ukraine/Kiev/Kyiv/1');
    $this->assertSession()->statusCodeEquals(200);
    // But the user may not view any other detailed forecasts.
    // This needs the permission to access the search page.
    $this->drupalGet('weather/Germany/Hamburg/Zollenspieker');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextNotContains('Zollenspieker');
    // Logout current user.
    $this->drupalLogout();

    // Test with weather user 2.
    $this->drupalLogin($weather_user_2);
    // Get front page.
    $this->drupalGet('node');
    $this->assertSession()->pageTextContains('Weather');
    $this->assertSession()->responseContains('<div class="weather">');
    $this->assertSession()->linkExists('Kyiv');
    $this->assertSession()->linkByHrefExists('weather/Ukraine/Kiev/Kyiv/1');

    // Administration of weather module should be forbidden.
    $this->drupalGet('admin/config/user-interface/weather');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->pageTextContains('You are not authorized to access this page');
    // Search page should be forbidden.
    $this->drupalGet('weather');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('The requested page could not be found');
    // Using the direct search URL should be forbidden.
    $this->drupalGet('weather/zollenspieker');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('The requested page could not be found');
    // The user may view the page with the detailed forecast of the
    // system-wide display.
    $this->drupalGet('weather/Ukraine/Kiev/Kyiv/1');
    $this->assertSession()->statusCodeEquals(200);
    // But the user may not view any other detailed forecasts.
    $this->drupalGet('weather/Germany/Hamburg/Zollenspieker');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextNotContains('Zollenspieker');

    // Logout current user.
    $this->drupalLogout();
  }

}
