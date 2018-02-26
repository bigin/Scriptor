<?php return array (
  1 => 
  Imanager\Item::__set_state(array(
     'categoryid' => 1,
     'id' => 1,
     'name' => 'Seite Nummer 1',
     'label' => NULL,
     'position' => 1,
     'active' => true,
     'created' => 1519052101,
     'updated' => 1519655895,
     'content' => 'An API overview of IManager, how to use it and some basic examples.

Use IManager\'s API in any other PHP scripts it\'s easy! The first thing you should do is just including IManager\'s ./imanager.php file from any other PHP script. IManager comes with an index.php file in the root directory by default. This file is not needed to run IManager and you can safely delete it, but look at this file anyway, it represent an example of how to include IManager in your script.

```php
include(\'/your-imanager-location/imanager.php\');
```
Once you have included IManager like in the example above, the API is now available to you in the $imanager global variable, or via the imanager() function. For instance, here\'s how you would access the systemDateFormat config variable:

```php
echo $imanager->config->systemDateFormat;
```
or
```php
echo imanager(\'config\')->systemDateFormat;
```',
     'pagetype' => 1,
     'slug' => 'seite-nummer-1',
     'parent' => 0,
  )),
  2 => 
  Imanager\Item::__set_state(array(
     'categoryid' => 1,
     'id' => 2,
     'name' => 'Seite Nummer 5',
     'label' => NULL,
     'position' => 5,
     'active' => true,
     'created' => 1519218138,
     'updated' => 1519656256,
     'content' => 'Der Inhalt der Seite Nummer 2',
     'parent' => 5,
     'pagetype' => 1,
     'slug' => 'seite-nummer-5',
  )),
  3 => 
  Imanager\Item::__set_state(array(
     'categoryid' => 1,
     'id' => 3,
     'name' => 'Seite Nummer 3',
     'label' => NULL,
     'position' => 3,
     'active' => true,
     'created' => 1519218219,
     'updated' => 1519655895,
     'content' => 'Und das ist der Inhalt',
     'parent' => 1,
     'pagetype' => 1,
     'slug' => 'seite-nummer-3',
  )),
  4 => 
  Imanager\Item::__set_state(array(
     'categoryid' => 1,
     'id' => 4,
     'name' => 'Seite Nummer 2',
     'label' => NULL,
     'position' => 2,
     'active' => true,
     'created' => 1519378052,
     'updated' => 1519655895,
     'content' => 'Blabal',
     'parent' => 1,
     'pagetype' => 1,
     'slug' => 'seite-nummer-2',
  )),
  5 => 
  Imanager\Item::__set_state(array(
     'categoryid' => 1,
     'id' => 5,
     'name' => 'Seite Nummer 4',
     'label' => NULL,
     'position' => 4,
     'active' => true,
     'created' => 1519508638,
     'updated' => 1519656182,
     'content' => 'Testinhalt',
     'parent' => 0,
     'pagetype' => 1,
     'slug' => 'seite-nummer-4',
  )),
); ?>