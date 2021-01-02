![Scriptor Banner](https://scriptor-cms.info/data/uploads/scriptor-banner.png)

# Scriptor

_Scriptor is a simple flat-file CMS_   
The perfect solution for building any kind of microsites.

## Features   
#### Get started quickly: 
Intuitive, user-friendly control panel helps you get up and running easily. 
You will have it installed in 2 minutes - ready to use.   

#### Flexible features extensible architecture:
You have a variety of options and a powerful [ItemManager](https://github.com/bigin/ItemManager-3) 
framework for module development.

#### You have total freedom:
Themes and Modules can contain plain HTML/PHP source code. Scriptor does not restrict the user's development approach 
by a mandatory template engine.


### Install Requirements
- A Unix or Windows-based web server running Apache.   
- PHP 7.4 or newer (8.0 preferable).   
- Write permission has to be granted into the complete `data/` directory except `data/config` folder.   
- Apache must have mod_rewrite enabled.   
- Apache must support .htaccess file.   
    
### Installing from zip
1. Click `Clone or download`
2. Unpack the archive and rename the `Scriptor-master` folder as you like.
3. Rename the file `/data/settings/_custom.scriptor-config.php` to `custom.scriptor-config.php` (without `_` prefix/underscore).
4. Upload the contents of the folder to your server, or upload the folder if you want to run the application in a subfolder.
   
> (!) You might have to adjust the .htaccess file, comment out `RewriteBase /` etc.    

### How to upgrade from Scriptor 1.4.4- to 1.4.5+:
> (!) Backup any files you replace or delete!

  – Replace: /editor/ with the new version   
  – Replace: /imanager/ with the new version   
  – Replace: /index.php with the new version   
  – Replace: /imanager.php with the new version   
  – Replace: /data/datasets/buffers/fields/1.fields.php with the new version   
  – Replace: /data/settings/scriptor-config.php with the new version   

  Your site is now upgraded, test that everything works.

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
Official website: https://scriptor-cms.info
Documentation: https://scriptor-cms.info/documentation/    
Demo: https://demos.ehret-studio.com/scriptor/        
Module extensions: https://scriptor-cms.info/extensions/extensions-modules/     

### License
The [MIT License (MIT)](https://github.com/bigin/Scriptor/blob/master/LICENSE)

### Changelog
- `1.4.11` `ENH`: `The handling for session cookie.` | `ENH`: `Exception Handling.` | `FIX`: `Security update for session check statement on file upload.`   
- `1.4.10` `ENH`: `Added a new hookable method editor::afterExecute()` | `Minor design updates.`    
- `1.4.9` `NEW`: `Several design adjustments.` | `ENH`: `Dropped Font Awesome support`    
- `1.4.8` `FIX`: `Scriptor\Profile properties.` | `NEW`: `Shared configuration parameters PHP and Javascript.` | `ENH`: `Styles adjustments.`    
- `1.4.7` `ENH`: `Code quality improvements.`   
- `1.4.6` `NEW`: `This core version adds the ability to hook into the Editor methods.`   
- `1.4.5` `NEW`: `File upload field as an integral part of the core application.`    
