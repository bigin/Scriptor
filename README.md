![](https://demos.ehret-studio.com/scriptor/data/uploads/scriptor-dashboard-github.png)

# Scriptor

_Scriptor is a simple flat-file CMS, based on [ItemManager 3](https://github.com/bigin/ItemManager-3) - 
decoupled and reusable PHP library for web applications._   

## Features   
Get started quickly and easily: Intuitive, user-friendly control panel helps you get up and running quickly. 
You will have it installed in 2 minutes - ready to use.   

Flexible features extensible architecture: You have a variety of options and a powerful [ItemManager](https://github.com/bigin/ItemManager-3) 
framework for module development.

You have total freedom: Themes and Modules can contain plain HTML/PHP source code. Scriptor does not restrict the user's development approach 
by a mandatory template engine.


### Install Requirements
- A Unix or Windows-based web server running Apache.   
- PHP 7 or newer (7.3+ preferable).   
- Write permission has to be granted into the complete `data/` directory except `data/config` folder.   
- Apache must have mod_rewrite enabled.   
- Apache must support .htaccess file.   
    
### Installing from zip
1. Click `Clone or download`
2. Unpack the archive and rename the `Scriptor-master` folder as you like.
3. Upload the contents of the folder to your server, or upload the folder if you want to run the application in a subfolder.
    
> NOTE: If you want to use Scriptor in a subdirectory, you might have to adjust the .htaccess file.
> If you rename the `editor` directory, you have to adjust the config.php as well.
    
### Admin
Once installed, to access the administrator area of your Scriptor site go to your websites homepage, then simply add the text `editor/` to the URL in your browsers, for example: 
```
https://yourwebsite.com/editor/
```

If you are using Scriptor in a subdirectory: 
```
https://yourwebsite.com/subdirectory/editor/
```

### Admin initial login  
> – Change password at first login!   
User: `admin`   
Password: `gT5nLazzyBob`


### More info
Official website: https://ehret-studio.com/lab/scriptor-a-simple-flat-file-cms/   
Demo (default template): https://demos.ehret-studio.com/scriptor/   
Demo ([UIkit 3](https://getuikit.com) template): https://im.ehret-studio.com/tuts/   

### License
The [MIT License (MIT)](https://github.com/bigin/Scriptor/blob/master/LICENSE)
