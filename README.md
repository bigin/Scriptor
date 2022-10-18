![Scriptor Header](https://scriptor-cms.info/site/themes/info/images/scriptor-header.png)

# Scriptor   
A lightweight, versatile flat-file CMS for creating microsites.   

Demo: https://demos.scriptor-cms.info    

#### Get started quickly:
Intuitive, user-friendly control panel helps you get up and running easily – you will have it installed in the blink of an eye.

#### Extensible:
You have a variety of options and a fun API for custom module development. The front-end and admin panel are simply designed and consist only of modules and templates.

#### Theming:
Use the default theme or create your own, as simple as you like. Write custom modules or hook into the admin methods and change their logic to your needs.


### Install Requirements
- A Unix or Windows-based web server running Apache.   
- Minimum PHP version of 8.1.   
- Write permission has to be granted into the complete `data/` directory except `data/config` folder.   
- Apache must have mod_rewrite enabled.   
- Apache must support .htaccess file.   

### Via Composer Create-Project
Scriptor is available from Packagist and can also be installed by entering the composer command:
```
composer create-project bigins/scriptor your-scriptor-project
```

### Via Composer Require
If you prefer, you can add Scriptor to an existing project inside the `vendor/` directory:
```
composer require bigins/scriptor
```

### Git Clone
```
git clone git@github.com:bigin/Scriptor.git
```

### Use Scriptor as a library
To use Scriptor library in your own project, just include the `boot.php` file:

```php
require './your-scriptor-project/boot.php'; 
```

or use composer autoload:
```php
require '../vendor/autoload.php'; 
```

Now you can just use Scriptor library in your own projects:
```php
<?php // /public/index.php

use Imanager\Util;
use Scriptor\Scriptor;

require dirname(__DIR__) . '/vendor/autoload.php'; 

$site = Scriptor::getSite();
$page = $site->getPage('slug=scriptors-demo-page');

Util::preformat($page->name);
```
    
### Installing from zip
1. Click `Clone or download`
2. Unpack the archive and rename the folder as you like.
3. (Optional) Rename the file `/data/settings/_custom.scriptor-config.php` to `custom.scriptor-config.php` (without `_` prefix/underscore). Add there your individual configuration parameters as they are in the file scriptor-config.php. 
4. Upload the contents of Scriptor folder to root on the server, or upload it in the folder if you want to run the CMS in a subfolder.
   
> (!) You might have to adjust the .htaccess file, comment out `RewriteBase /` etc.    

### Admin
To access the admin panel, go to the home page of your website and simply add the text `editor/` to the URL in your browser: 
```
https://your-website.com/editor/
```

If you are using Scriptor in a subdirectory: 
```
https://your-website.com/subdirectory/editor/
```

### Admin initial login  
`(!) Change password/username at first login`  
> User: `admin`   
> Password: `gT5nLazzyBob`

### Links   
- Official website: https://scriptor-cms.info   
- Documentation: https://scriptor-cms.info/documentation/    
- Module extensions: https://scriptor-cms.info/extensions/extensions-modules/    
- Demo: https://demos.scriptor-cms.info      
- Showcase: https://github.com/bigin/Scriptor/discussions/15      

### Header image by
[Freepik](https://www.freepik.com/free-vector/flat-cms-content-landing-page-style_11817459.htm#query=website%20cms%20content&position=3&from_view=search&track=sph#position=3&query=website%20cms%20content)    

### License
The [MIT License (MIT)](https://github.com/bigin/Scriptor/blob/master/LICENSE)
