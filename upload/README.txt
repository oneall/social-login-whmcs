1. Upload the folder oneall_social_login to /modules/addons/
2. Login to your WHMCS admin area and go to Setup \ Addon Module
2. Active the addon OneAll Social Login
3. Sign up for an OneAll account at https://app.oneall.com/
4. Create a new OneAll site.
5. Add your WHMCS domain name in the Settings \ Allowed Domains of your new site.
6. Add the application subdomain, public key, and private key provided by OneAll to the addon settings under WHMCS Admin->Setup->Addon Modules.
7. Update your template file login.tpl to add the following variable where you wish the options to appear: {$oneall_social_login_embedded}
8. If you wish the log in options to appear on the order process you need to edit the viewcart.tpl file.  We suggest adding:

{if !$loggedin}
    {$oneall_social_login_embedded}<br />
{/if}

After:
<h2>{$LANG.yourdetails}</h2>