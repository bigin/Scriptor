
![Scriptor Header](https://scriptor-cms.info/site/themes/info/images/scriptor-header.png)

  

# Scriptor

A lightweight, versatile flat-file CMS for creating microsites, blogs or wikis.

  

Demo: https://demos.scriptor-cms.info

  

### Get started quickly:

The intuitive control panel helps you get up and running – you'll have it installed in no time. A basic blog theme is already pre-installed, so you can get started right away. Use the default theme or create your own as simple as you like. 
  
  
  


## Install

#### Install Requirements

- A Unix or Windows-based web server running Apache.
- Minimum PHP version of 8.1.
- ext-mbstring
- ext-gd
- ext-mbstring
- ext-dom
- ext-json
- Apache must support .htaccess file.


#### Via Composer Create-Project

Scriptor is available from Packagist and can also be installed by entering the composer command:

```
composer create-project bigins/scriptor your-scriptor-project
```

#### Via Composer Require

If you prefer, you can add Scriptor to an existing project inside the `vendor/` directory:

```
composer require bigins/scriptor
```

  

#### Git Clone

```
git clone git@github.com:bigin/Scriptor.git
```

#### Installing from zip

1. Click [download](https://scriptor-cms.info).
2. Unpack the archive and rename the folder as you like.
3. Upload the contents of Scriptor folder to root on the server, or upload it in the folder if you want to run the CMS in a subfolder.

   
   
## Use Scriptor as your website platform
In that case, Scriptor would have to be in the root of your domain.

### Admin panel
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


## Use Scriptor as a library

To make the Scriptor library available in your own project, simply include the `boot.php` file:  

```php
require  './your-scriptor-project/boot.php';
```


or use composer autoload:

```php
require  '../vendor/autoload.php';
```
 

Now you can just add Scriptor in your own code:

```php
<?php  // /public/index.php

use Scriptor\Core\Scriptor;

require  dirname(__DIR__) .  '/vendor/autoload.php';

$site  =  Scriptor::getSite();
$page  =  $site->getPage('slug=scriptors-demo-page');
```
  

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
