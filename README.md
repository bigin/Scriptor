![Scriptor Banner](https://scriptor-cms.info/data/uploads/scriptor-banner-21.png?v=1.03)

# Scriptor

_Scriptor is a simple flat-file CMS_   
A lightweight flat-file CMS that allows you to get started with minimal effort and time investment.

## Features   
#### Get started quickly: 
Intuitive, user-friendly control panel helps you get up and running easily – you will have it installed in the blink of an eye.   

#### A flexible and extensible architecture:
You have a variety of options and a powerful [ItemManager](https://github.com/bigin/ItemManager-3) API for module development.

#### You have total freedom:
Themes and modules can contain plain HTML/PHP code - Scriptor does not restrict the user's development approach with a mandatory template engine.


### Install Requirements
- A Unix or Windows-based web server running Apache.   
- PHP 7.4 or newer (8.0 preferable).   
- Write permission has to be granted into the complete `data/` directory except `data/config` folder.   
- Apache must have mod_rewrite enabled.   
- Apache must support .htaccess file.   
    
### Installing from zip
1. Click `Clone or download`
2. Unpack the archive and rename the `Scriptor-master` folder as you like.
3. (Optional) Rename the file `/data/settings/_custom.scriptor-config.php` to `custom.scriptor-config.php` (without `_` prefix/underscore).
4. Upload the contents of the folder to your server, or upload the folder if you want to run the application in a subfolder.
   
> (!) You might have to adjust the .htaccess file, comment out `RewriteBase /` etc.    

### Admin
Once installed, to access the administrator area of your Scriptor site go to your websites homepage, then simply add the text `editor/` to the URL in your browsers, for example: 
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
- Demo: https://demos.ehret-studio.com/scriptor/      
- Showcase: https://github.com/bigin/Scriptor/discussions/15      
- Module extensions: https://scriptor-cms.info/extensions/extensions-modules/     

### License
The [MIT License (MIT)](https://github.com/bigin/Scriptor/blob/master/LICENSE)

  
