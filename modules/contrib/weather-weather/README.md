Weather
=======

This module uses free weather data from https://www.yr.no to display
current weather conditions from anywhere in the world. Data for more
than 14.000 places worldwide is included for easy weather display.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/weather

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/weather


Requirements
------------

No special requirements.

Installation
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/docs/extending-drupal/installing-modules
   for further information.

 * Base setup:
    - Go to /admin/config/user-interface/weather
    - Import all weather places to the database (one-time action). This
      operation can take some time, depending on your system's performance,
      as more than 14.000 records must be inserted into the database.
      If you'll click this button again in the future, it will remove all
      places already imported and perform a new 'clean' import of all
      places.
    - (optionally) Configure default weather display settings, these
      settings will be inherited by all newly created displays
    - Add and configure your first system-wide display
    - Add new places to the display
    - Clear cache and you will be able to see the new block in the
      system, created for each weather display.

 * Add custom places:
    If you can't find the place you need in the initially imported list,
    go to https://www.yr.no and search for the place. Copy the link to
    the page, it should look similar to this:
    https://www.yr.no/place/Ukraine/Kiev/Kyiv/. Always use the link to
    the English variant of the page. Afterwards, go to the module's
    settings page, /admin/config/user-interface/weather/places and
    add this place with a form you see. Now you can use the place
    in any display.

Maintainers
-----------

Current maintainers:
 * Dr. Tobias Quathamer (toddy) - https://www.drupal.org/u/toddy
 * Yaroslav Samoilenko (ysamoylenko) - https://www.drupal.org/u/ysamoylenko
 * Taras Kyryliuk (tarasich) - https://www.drupal.org/u/tarasich
