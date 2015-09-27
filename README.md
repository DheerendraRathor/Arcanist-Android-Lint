# Arcanist-Android-Lint
Android lint rule for phabricator arcanist. It includes findbugs, checkstyle and pmd configurations with gradle lint.

The library is based on https://github.com/imobile3/cassowary/blob/master/libcassowary/src/lint/linter/ArcanistAndroidLinter.php but configured to work with only gradle. 
Also the library is extended to include findbugs, checkstyle and pmd for better results. 

To work with library:
- Include `.arclib/` in phputil libraries in `.arcconfig` (See included `.arcconfig` for exmaple)
- Add `.arclint` to your project. Modify the same according to your requirements
- If you have more than one android modules or your default module is not `app`, you need to modify `.arclib/lint/ArcanistAndroidLinter.php` and add your modules there in `private $gradleModules` variable
- In the same php file you can also disable `findbugs, checkstyle and pmd`. By default they're enabled
- Include `app/config` and `app/gradle_scripts`  in your module root
- Modify `app/config/checkstyle/checkstyle.xml` according to your own taste. Same goes with every other configuration file listed in `app/config`
- Edit your `app/build.gradle` and include these lines as per your requirements
  - `apply from: 'gradle_scripts/checkstyle.gradle`
  - `apply from: 'gradle_scripts/checkstyle.gradle'`
  - `apply from: 'gradle_scripts/pmd.gradle'`
