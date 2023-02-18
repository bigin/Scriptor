
![Scriptor Header](https://scriptor-cms.info/site/themes/info/images/scriptor-header.png)

  

# Scriptor

Scriptor is a lightweight and versatile flat-file CMS for creating microsites, blogs or wikis.

  

Demo: https://demos.scriptor-cms.info

  

### Get started quickly:

The intuitive control panel helps you get up and running quickly - you'll have it installed in no time. A basic blog theme is already pre-installed, so you can get started right away. Use the default theme or create your own theme as simply as possible.
  
  


## Install

#### Install Requirements

- A Unix or Windows-based web server running Apache.
- Minimum PHP version of 8.1.
- ext-mbstring
- ext-gd
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

#### Installing from a Zip Archive

To install Scriptor from a zip archive, follow these steps:   
    
1. Click [Download](https://scriptor-cms.info) to download the archive.
2. Unpack the archive.
3. Upload the contents of the Scriptor folder to the root directory on the server. Alternatively, you can upload it to a folder if you want to run the CMS in a subfolder. If you only want to interact programmatically with Scriptor, place the library outside the root directory. For more information, see the section on "Using Scriptor as a library" below.  
   
   
## Use Scriptor as your website platform
In this case, Scriptor should be located in the root directory of your domain.

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

$page  =  Scriptor::getSite()->pages()->getPage('slug=scriptors-demo-page');
```
  

### Links

- Documentation: https://scriptor-cms.info/documentation/

- Module/Extensions: https://scriptor-cms.info/extensions/extensions-modules/

- A demo of the default blog theme: https://demos.scriptor-cms.info

  

### Header image by

[Freepik](https://www.freepik.com/free-vector/flat-cms-content-landing-page-style_11817459.htm#query=website%20cms%20content&position=3&from_view=search&track=sph#position=3&query=website%20cms%20content)

  

### License

The [MIT License (MIT)](https://github.com/bigin/Scriptor/blob/master/LICENSE)
