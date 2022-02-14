![Scriptor Banner](https://scriptor-cms.info/data/uploads/scriptor-banner-21.png)

# Scriptor   
A lightweight flat-file CMS that allows you to get started with minimal effort and time investment.

## Features   
#### Get started quickly: 
Intuitive, user-friendly control panel helps you get up and running easily – you will have it installed in the blink of an eye.   

#### Flexible and extensible:
You have a variety of options and a fun API for custom module development. The front-end and admin panel are simply designed and consist only of modules and templates.

#### You have total freedom:
Use the default theme or create your own, as simple as you like. Or hook into the admin methods and change their logic to your needs.


### Install Requirements
- A Unix or Windows-based web server running Apache.   
- PHP 8.0 or newer preferable.   
- Write permission has to be granted into the complete `data/` directory except `data/config` folder.   
- Apache must have mod_rewrite enabled.   
- Apache must support .htaccess file.   
    
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
- Demo: https://scriptor-demo.000webhostapp.com/      
- Showcase: https://github.com/bigin/Scriptor/discussions/15      
- Module extensions: https://scriptor-cms.info/extensions/extensions-modules/     

### License
The [MIT License (MIT)](https://github.com/bigin/Scriptor/blob/master/LICENSE)

### Last changes
`ENH`: `Minor styling updates` | `FIX`: `Return value of the set() methods in Category, Field and Item objects`
