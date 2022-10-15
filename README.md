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
