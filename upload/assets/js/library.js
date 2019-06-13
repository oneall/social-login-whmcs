function OneAll (subdomain, providers, homepage, custom_css) {
    this.subdomain = subdomain;
    this.providers = providers;
    this.homepage = homepage;
    
    this.callback_uri = ((this.homepage + (this.homepage.split('?')[1] ? '&amp;': '?')) + "return_url=" + encodeURIComponent(window.location.href));

    // index page fix : ie index.php?rp=/knowledgebase will crash with return_url param. We keep the page uri and add return url
    this.callback_uri = this.homepage.split('?rp=/')[1] ? this.homepage.split('?rp=/')[0] + '?return_url='+encodeURIComponent(window.location.href) : this.callback_uri ;


    this.custom_css = custom_css;
    this.library_added = false;
}

// Add clickable link after script
OneAll.prototype.display_link = function (script, caption) {
    var element, container;
    
    // Prevents displaying the icons twice
    if (script.getAttribute('data-processed') != 1) {

        // Library is required
        this.add_library();
        
        // Outer Container
        container = document.createElement('span');
        container.className = 'oneall_social_login_popup';
        script.parentElement.insertBefore(container, script.nextSibling);
        script.setAttribute('data-processed', 1);
        
        // Outer Container
        element = document.createElement('a');
        element.className = 'oneall_social_login_link';
        element.id = 'oneall_social_login_link_' + Math.random().toString(36).substr(2, 12);
        element.appendChild(document.createTextNode (caption));
        container.appendChild(element);
        
        // Providers
        window._oneall = (window._oneall || []);
        window._oneall.push(['social_login', 'set_providers', this.providers]);
        window._oneall.push(['social_login', 'set_callback_uri', this.callback_uri]);
        
        if (typeof this.custom_css === 'string' && this.custom_css.length > 0) {
            window._oneall.push(['social_login', 'set_custom_css_uri', this.custom_css]);
         }
        
        window._oneall.push(['social_login', 'attach_onclick_popup_ui', element.id])        
    }
}

// Embed providers after script
OneAll.prototype.display_embedded = function (script, caption) {
    var element, container;
    
    // Prevents displaying the icons twice
    if (script.getAttribute('data-processed') != 1) {
        
        // Library is required
        this.add_library(); 
        
        // Outer Container
        container = document.createElement('div');
        container.className = 'oneall_social_login_embedded';
        script.parentElement.insertBefore(container, script.nextSibling);
        script.setAttribute('data-processed', 1);
        
        // Caption
        if (typeof caption === 'string' && caption.length > 0) {
            element = document.createElement('h4');
            element.className = 'oneall_social_login_caption';
            element.appendChild(document.createTextNode(caption));
            container.appendChild(element);
        }    
        
        // Providers Container
        element = document.createElement('div');
        element.className = 'oneall_social_login_providers';
        element.id = 'oneall_social_login_providers_' + Math.random().toString(36).substr(2, 12);
        container.appendChild(element);
        
        // Providers
        window._oneall = (window._oneall || []);
        window._oneall.push(['social_login', 'set_providers', this.providers]);
        window._oneall.push(['social_login', 'set_callback_uri', this.callback_uri]);
        
        if (typeof this.custom_css === 'string' && this.custom_css.length > 0) {
           window._oneall.push(['social_login', 'set_custom_css_uri', this.custom_css]);
        }
        
        window._oneall.push(['social_login', 'do_render_ui', element.id])
    }
}

// Add the OneAll Library
OneAll.prototype.add_library = function(){
    var script, container;
    
    if ( ! this.library_added) {
        script = document.createElement('script'); 
        script.type = 'text/javascript'; script.async = true; 
        script.src = '//' + this.subdomain + '.api.oneall.com/socialize/library.js';
    
        container = document.getElementsByTagName('script')[0]; 
        container.parentNode.insertBefore(script, container);
        
        this.library_added = true;
    }    
}