<?php
if (!defined("WHMCS"))
{
    die("This file cannot be accessed directly");
}

// Database handler
use WHMCS\Database\Capsule;

// ///////////////////////////////////////////////////////////////////////////////////////////////////////////
// API COMMUNICATION
// ///////////////////////////////////////////////////////////////////////////////////////////////////////////

// Returns the user agent
function oneall_social_login_get_user_agent()
{
    global $CONFIG;

    // Compute versions
    $social_login_version = "1.4.0";
    $whmcs_version = $CONFIG['Version'];

    // Build Agent
    $user_agent = 'SocialLogin/' . $social_login_version . ' WHMCS/' . $whmcs_version . ' (+http://www.oneall.com/)';

    // Done

    return $user_agent;
}

// Sends an API request by using the given handler
function oneall_social_login_do_api_request($handler, $url, $opts = array(), $timeout = 25)
{
    // FSOCKOPEN
    if ($handler == 'fsockopen')
    {
        return oneall_social_login_fsockopen_request($url, $opts, $timeout);
    }
    // CURL
    else
    {
        return oneall_social_login_curl_request($url, $opts, $timeout);
    }
}

// Sends an API request using FSOCKOPEN
function oneall_social_login_fsockopen_request($url, $options = array(), $timeout = 15)
{
    global $CONFIG;

    // Store the result
    $result = new stdClass();

    // Make sure that this is a valid URL
    if (($uri = parse_url($url)) === false)
    {
        $result->http_error = 'invalid_uri';

        return $result;
    }

    // Check the scheme
    if ($uri['scheme'] == 'https')
    {
        $port = (isset($uri['port']) ? $uri['port'] : 443);
        $url = ($uri['host'] . ($port != 443 ? ':' . $port : ''));
        $url_protocol = 'https://';
        $url_prefix = 'ssl://';
    }
    else
    {
        $port = (isset($uri['port']) ? $uri['port'] : 80);
        $url = ($uri['host'] . ($port != 80 ? ':' . $port : ''));
        $url_protocol = 'http://';
        $url_prefix = '';
    }

    // Construct the path to act on
    $path = (isset($uri['path']) ? $uri['path'] : '/') . (!empty($uri['query']) ? ('?' . $uri['query']) : '');

    // HTTP Headers
    $headers = array();

    // We are using a proxy
    if (!empty($options['proxy_url']) && !empty($options['proxy_port']))
    {
        // Open Socket
        $fp = @fsockopen($options['proxy_url'], $options['proxy_port'], $errno, $errstr, $timeout);

        // Make sure that the socket has been opened properly
        if (!$fp)
        {
            $result->http_error = trim($errstr);

            return $result;
        }

        // HTTP Headers
        $headers[] = "GET " . $url_protocol . $url . $path . " HTTP/1.0";
        $headers[] = "Host: " . $url . ":" . $port;
        $headers[] = "User-Agent: " . oneall_social_login_get_user_agent();

        // Proxy Authentication
        if (!empty($options['proxy_username']) && !empty($options['proxy_password']))
        {
            $headers[] = 'Proxy-Authorization: Basic ' . base64_encode($options['proxy_username'] . ":" . $options['proxy_password']);
        }
    }
    // We are not using a proxy
    else
    {
        // Open Socket
        $fp = @fsockopen($url_prefix . $url, $port, $errno, $errstr, $timeout);

        // Make sure that the socket has been opened properly
        if (!$fp)
        {
            $result->http_error = trim($errstr);

            return $result;
        }

        // HTTP Headers
        $headers[] = "GET " . $path . " HTTP/1.0";
        $headers[] = "Host: " . $url;
    }

    // Enable basic authentication
    if (isset($options['api_key']) and isset($options['api_secret']))
    {
        $headers[] = 'Authorization: Basic ' . base64_encode($options['api_key'] . ":" . $options['api_secret']);
    }

    // Build and send request
    fwrite($fp, (implode("\r\n", $headers) . "\r\n\r\n"));

    // Fetch response
    $response = '';
    while (!feof($fp))
    {
        $response .= fread($fp, 1024);
    }

    // Close connection
    fclose($fp);

    // Parse response
    list($response_header, $response_body) = explode("\r\n\r\n", $response, 2);

    // Parse header
    $response_header = preg_split("/\r\n|\n|\r/", $response_header);
    list($header_protocol, $header_code, $header_status_message) = explode(' ', trim(array_shift($response_header)), 3);

    // Build result
    $result->http_code = $header_code;
    $result->http_data = $response_body;

    // Done

    return $result;
}

// Sends a CURL request.
function oneall_social_login_curl_request($url, $options = array(), $timeout = 15)
{
    global $CONFIG;

    // Store the result
    $result = new stdClass();

    // Send request
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_VERBOSE, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_USERAGENT, oneall_social_login_get_user_agent());

    // BASIC AUTH?
    if (isset($options['api_key']) and isset($options['api_secret']))
    {
        curl_setopt($curl, CURLOPT_USERPWD, $options['api_key'] . ":" . $options['api_secret']);
    }

    // Proxy Settings
    if (!empty($options['proxy_url']) && !empty($options['proxy_port']))
    {
        // Proxy Location
        curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        curl_setopt($curl, CURLOPT_PROXY, $options['proxy_url']);

        // Proxy Port
        curl_setopt($curl, CURLOPT_PROXYPORT, $options['proxy_port']);

        // Proxy Authentication
        if (!empty($options['proxy_username']) && !empty($options['proxy_password']))
        {
            curl_setopt($curl, CURLOPT_PROXYAUTH, CURLAUTH_ANY);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $options['proxy_username'] . ':' . $options['proxy_password']);
        }
    }

    // Make request
    if (($http_data = curl_exec($curl)) !== false)
    {
        $result->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result->http_data = $http_data;
        $result->http_error = null;
    }
    else
    {
        $result->http_code = -1;
        $result->http_data = null;
        $result->http_error = curl_error($curl);
    }

    // Done

    return $result;
}

// ///////////////////////////////////////////////////////////////////////////////////////////////////////////
// URL TOOLS
// ///////////////////////////////////////////////////////////////////////////////////////////////////////////

// Returns the current url
function oneall_social_login_get_current_url()
{
    // Extract parts
    $request_uri = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF']);
    $request_protocol = (oneall_social_login_is_https_on() ? 'https' : 'http');
    $request_host = (isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));

    // Port of this request
    $request_port = '';

    // We are using a proxy
    if (isset($_SERVER['HTTP_X_FORWARDED_PORT']))
    {
        // SERVER_PORT is usually wrong on proxies, don't use it!
        $request_port = intval($_SERVER['HTTP_X_FORWARDED_PORT']);
    }
    // Does not seem like a proxy
    elseif (isset($_SERVER['SERVER_PORT']))
    {
        $request_port = intval($_SERVER['SERVER_PORT']);
    }

    // Remove standard ports
    $request_port = (!in_array($request_port, array(80, 443)) ? $request_port : '');

    // Build url
    $current_url = $request_protocol . '://' . $request_host . (!empty($request_port) ? (':' . $request_port) : '') . $request_uri;

    // Done

    return $current_url;
}

// Check if the current connection is being made over https
function oneall_social_login_is_https_on()
{
    if (!empty($_SERVER['SERVER_PORT']))
    {
        if (trim($_SERVER['SERVER_PORT']) == '443')
        {
            return true;
        }
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
    {
        if (strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO'])) == 'https')
        {
            return true;
        }
    }

    if (!empty($_SERVER['HTTPS']))
    {
        if (strtolower(trim($_SERVER['HTTPS'])) == 'on' or trim($_SERVER['HTTPS']) == '1')
        {
            return true;
        }
    }

    return false;
}

// ///////////////////////////////////////////////////////////////////////////////////////////////////////////
// USER FUNCTIONS
// ///////////////////////////////////////////////////////////////////////////////////////////////////////////

// Login the user
function oneall_social_login_login_userid($userid, $ip_address)
{
    global $cc_encryption_hash;

    // Read user
    $entry = Capsule::table('tblclients')->select('id', 'password', 'email')->where('id', '=', $userid)->first();
    if (is_object($entry) && isset($entry->id))
    {
        // Start a session if none has been started yet
        if (!session_id())
        {
            session_start();
        }

        // Add in more recent versions of WHMCS.
        if (method_exists('WHMCS\Authentication\Client', 'generateClientLoginHash'))
        {
            $_SESSION['uid'] = $entry->id;
            $_SESSION['upw'] = WHMCS\Authentication\Client::generateClientLoginHash($entry->id, '', $entry->password, $entry->email);
        }
        else
        {
            $_SESSION['uid'] = $entry->id;
            $_SESSION['upw'] = sha1($entry->id . $entry->password . $ip_address . substr(sha1($cc_encryption_hash), 0, 20));
        }

        // Persist
        session_write_close();

        // Success

        return true;
    }

    // Error

    return false;
}

// Return the settings
function oneall_social_login_get_settings()
{
    // Result
    $settings = array(
        'enabled_providers' => array());

    // Read settings from database
    $entries = Capsule::table('tbladdonmodules')->select('setting', 'value')->where('module', '=', 'oneall_social_login')->get();
    foreach ($entries as $entry)
    {
        if (preg_match('/^provider_(.+)$/', $entry->setting, $matches))
        {
            if (!empty($entry->value))
            {
                $settings['enabled_providers'][] = $matches[1];
            }
        }
        else
        {
            // Check for full subdomain
            if ($entry->setting == 'subdomain')
            {
                // Full domain entered
                if (preg_match("/([a-z0-9\-]+)\.api\.oneall\.com/i", $entry->value, $matches))
                {
                    $entry->value = $matches[1];
                }
            }

            // Add Setting
            $settings[$entry->setting] = $entry->value;
        }
    }

    // Defaults
    $settings['handler'] = ((!empty($settings['handler']) && $settings['handler'] == 'fsockopen') ? 'fsockopen' : 'curl');
    $settings['port'] = ((!empty($settings['port']) && $settings['port'] == '80') ? '80' : '443');
    $settings['api_use_https'] = ($settings['port'] == 80 ? false : true);

    // Done

    return $settings;
}

// Return the list of available providers
function oneall_social_login_get_all_providers()
{
    return array(
        'amazon' => array(
            'name' => 'Amazon'),
        'apple' => array(
            'name' => 'Apple'),
        'battlenet' => array(
            'name' => 'Battle.net'),
        'blogger' => array(
            'name' => 'Blogger'),
        'discord' => array(
            'name' => 'Discord'),
        'disqus' => array(
            'name' => 'Disqus'),
        'draugiem' => array(
            'name' => 'Draugiem'),
        'dribbble' => array(
            'name' => 'Dribbble'),
        'facebook' => array(
            'name' => 'Facebook',
            'enabled_default' => 1),
        'foursquare' => array(
            'name' => 'Foursquare'),
        'github' => array(
            'name' => 'Github.com'),
        'google' => array(
            'name' => 'Google',
            'enabled_default' => 1),
        'instagram' => array(
            'name' => 'Instagram'),
        'line' => array(
            'name' => 'Line'),
        'linkedin' => array(
            'name' => 'LinkedIn',
            'enabled_default' => 1),
        'livejournal' => array(
            'name' => 'LiveJournal'),
        'mailru' => array(
            'name' => 'Mail.ru'),
        'meetup' => array(
            'name' => 'Meetup'),
        'mixer' => array(
            'name' => 'Mixer'),
        'odnoklassniki' => array(
            'name' => 'Odnoklassniki'),
        'openid' => array(
            'name' => 'OpenID'),
        'patreon' => array(
            'name' => 'Patreon'),
        'paypal' => array(
            'name' => 'PayPal'),
        'pinterest' => array(
            'name' => 'Pinterest'),
        'pixelpin' => array(
            'name' => 'PixelPin'),
        'reddit' => array(
            'name' => 'Reddit'),
        'skyrock' => array(
            'name' => 'Skyrock.com'),
        'stackexchange' => array(
            'name' => 'StackExchange'),
        'steam' => array(
            'name' => 'Steam'),
        'soundCloud' => array(
            'name' => 'SoundCloud'),
        'tumblr' => array(
            'name' => 'Tumblr'),
        'twitch' => array(
            'name' => 'Twitch.tv'),
        'twitter' => array(
            'name' => 'Twitter',
            'enabled_default' => 1),
        'vimeo' => array(
            'name' => 'Vimeo'),
        'vkontakte' => array(
            'name' => 'VKontakte'),
        'weibo' => array(
            'name' => 'Weibo'),
        'windowslive' => array(
            'name' => 'Windows Live'),
        'wordpress' => array(
            'name' => 'WordPress.com'),
        'xing' => array(
            'name' => 'Xing'),
        'yahoo' => array(
            'name' => 'Yahoo'),
        'youtube' => array(
            'name' => 'YouTube'));
}

// Get the userid for a given email
function oneall_social_login_get_userid_by_email($email)
{
    $userid = null;

    // Read user
    $entry = Capsule::table('tblclients')->select('id')->where('email', '=', trim(strval($email)))->first();
    if (is_object($entry) && isset($entry->id))
    {
        $userid = $entry->id;
    }

    // Done

    return $userid;
}

// Get the username of the admin
function oneall_social_login_get_admin_username()
{
    $username = null;

    // Read zser
    $entry = Capsule::table('tbladmins')->select('username')->where('roleid', '=', 1)->first();
    if (is_object($entry) && isset($entry->username))
    {
        $username = $entry->username;
    }

    // Done

    return $username;
}

// Get the userid for a given token
function oneall_social_login_get_userid_by_token($token)
{
    $userid = null;

    // Read userid for user_token
    $entry = Capsule::table('tbloneall_user_token')->select('id', 'userid')->where('user_token', '=', strval(trim($token)))->first();
    if (is_object($entry) && isset($entry->id))
    {
        // User Token
        $user_tokenid = $entry->id;

        // Make sure the user actually exists
        $entry = Capsule::table('tblclients')->select('id')->where('id', '=', $entry->userid)->first();
        if (is_object($entry) && isset($entry->id))
        {
            $userid = $entry->id;
        }
        else
        {
            // Delete the wrongly linked user_token.
            Capsule::table('tbloneall_user_token')->where('id', '=', $user_tokenid)->delete();

            // Delete the wrongly linked identity_token.
            Capsule::table('tbloneall_identity_token')->where('user_tokenid', '=', $user_tokenid)->delete();
        }
    }

    // Done

    return $userid;
}

// Links the user/identity tokens to a userid
function oneall_social_login_link_tokens_to_userid($userid, $user_token, $identity_token, $identity_provider)
{
    // Delete wrongly linked tokens
    $entries = Capsule::table('tbloneall_user_token')->select('id')->where('userid', '=', intval($userid))->where('user_token', '<>', $user_token)->get();
    foreach ($entries as $entry)
    {
        // Delete the wrongly linked user_token.
        Capsule::table('tbloneall_user_token')->where('id', '=', $entry->id)->delete();

        // Delete the wrongly linked identity_token.
        Capsule::table('tbloneall_identity_token')->where('user_tokenid', '=', $entry->id)->delete();
    }

    // Read the entry for the given user_token.
    $entry = Capsule::table('tbloneall_user_token')->select('id')->where('user_token', '=', $user_token)->first();
    if (is_object($entry) && isset($entry->id))
    {
        $user_tokenid = $entry->id;
    }
    else
    {
        $user_tokenid = Capsule::table('tbloneall_user_token')->insertGetId(['userid' => $userid, 'user_token' => $user_token]);
    }

    // Read the entry for the given identity_token.
    $identity_tokenid = null;
    $entry = Capsule::table('tbloneall_identity_token')->select('id', 'user_tokenid')->where('identity_token', '=', $identity_token)->first();
    if (is_object($entry) && isset($entry->id))
    {
        // Token matches
        if ($entry->user_tokenid == $user_tokenid)
        {
            $identity_tokenid = $entry->id;
        }
        // Wrongly linked
        else
        {
            Capsule::table('tbloneall_identity_token')->where('id', '=', $entry->id)->delete();
        }
    }

    // The identity_token does not exist yet.
    if (empty($identity_tokenid))
    {
        $identity_tokenid = Capsule::table('tbloneall_identity_token')->insertGetId(['user_tokenid' => $user_tokenid, 'identity_token' => $identity_token, 'identity_provider' => $identity_provider]);
    }

    // Done.

    return array(
        $user_tokenid,
        $identity_tokenid);
}

// Extracts the social network data from a result-set returned by the OneAll API.
function oneall_social_login_extract_social_network_profile($result)
{
    // Decode the social network profile Data.
    $social_data = @json_decode($result);

    // Make sure that the data has beeen decoded properly
    if (is_object($social_data))
    {
        // Provider may report an error inside message:
        if (!empty($social_data->response->result->status->flag) && $social_data->response->result->status->code >= 400)
        {
            return false;
        }

        // Container for user data
        $data = array();

        // Parse plugin data.
        if (isset($social_data->response->result->data->plugin))
        {
            // Plugin.
            $plugin = $social_data->response->result->data->plugin;

            // Add plugin data.
            $data['plugin_key'] = $plugin->key;
            $data['plugin_action'] = (isset($plugin->data->action) ? $plugin->data->action : null);
            $data['plugin_operation'] = (isset($plugin->data->operation) ? $plugin->data->operation : null);
            $data['plugin_reason'] = (isset($plugin->data->reason) ? $plugin->data->reason : null);
            $data['plugin_status'] = (isset($plugin->data->status) ? $plugin->data->status : null);
        }

        // Do we have a user?
        if (isset($social_data->response->result->data->user) && is_object($social_data->response->result->data->user))
        {
            // User.
            $oneall_user = $social_data->response->result->data->user;

            // Add user data.
            $data['user_token'] = $oneall_user->user_token;

            // Do we have an identity ?
            if (isset($oneall_user->identity) && is_object($oneall_user->identity))
            {
                // Identity.
                $identity = $oneall_user->identity;

                // Add identity data.
                $data['identity_token'] = $identity->identity_token;
                $data['identity_provider'] = !empty($identity->source->name) ? $identity->source->name : '';

                $data['user_first_name'] = !empty($identity->name->givenName) ? $identity->name->givenName : '';
                $data['user_last_name'] = !empty($identity->name->familyName) ? $identity->name->familyName : '';
                $data['user_formatted_name'] = !empty($identity->name->formatted) ? $identity->name->formatted : '';
                $data['user_location'] = !empty($identity->currentLocation) ? $identity->currentLocation : '';
                $data['user_constructed_name'] = trim($data['user_first_name'] . ' ' . $data['user_last_name']);
                $data['user_picture'] = !empty($identity->pictureUrl) ? $identity->pictureUrl : '';
                $data['user_thumbnail'] = !empty($identity->thumbnailUrl) ? $identity->thumbnailUrl : '';
                $data['user_current_location'] = !empty($identity->currentLocation) ? $identity->currentLocation : '';
                $data['user_about_me'] = !empty($identity->aboutMe) ? $identity->aboutMe : '';
                $data['user_note'] = !empty($identity->note) ? $identity->note : '';

                // Birthdate - MM/DD/YYYY
                if (!empty($identity->birthday) && preg_match('/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/', $identity->birthday, $matches))
                {
                    $data['user_birthdate'] = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $data['user_birthdate'] .= '/' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $data['user_birthdate'] .= '/' . str_pad($matches[3], 4, '0', STR_PAD_LEFT);
                }
                else
                {
                    $data['user_birthdate'] = '';
                }

                // Fullname.
                if (!empty($identity->name->formatted))
                {
                    $data['user_full_name'] = $identity->name->formatted;
                }
                elseif (!empty($identity->name->displayName))
                {
                    $data['user_full_name'] = $identity->name->displayName;
                }
                else
                {
                    $data['user_full_name'] = $data['user_constructed_name'];
                }

                // Preferred Username.
                if (!empty($identity->preferredUsername))
                {
                    $data['user_login'] = $identity->preferredUsername;
                }
                elseif (!empty($identity->displayName))
                {
                    $data['user_login'] = $identity->displayName;
                }
                else
                {
                    $data['user_login'] = $data['user_full_name'];
                }

                // phpBB does not like spaces here
                $data['user_login'] = str_replace(' ', '', trim($data['user_login']));

                // Website/Homepage.
                $data['user_website'] = '';
                if (!empty($identity->profileUrl))
                {
                    $data['user_website'] = $identity->profileUrl;
                }
                elseif (!empty($identity->urls[0]->value))
                {
                    $data['user_website'] = $identity->urls[0]->value;
                }

                // Gender.
                $data['user_gender'] = '';
                if (!empty($identity->gender))
                {
                    switch ($identity->gender)
                    {
                        case 'male':
                            $data['user_gender'] = 'm';
                            break;

                        case 'female':
                            $data['user_gender'] = 'f';
                            break;
                    }
                }

                // Email Addresses.
                $data['user_emails'] = array();
                $data['user_emails_simple'] = array();

                // Email Address.
                $data['user_email'] = '';
                $data['user_email_is_verified'] = false;

                // Extract emails.
                if (property_exists($identity, 'emails') && is_array($identity->emails))
                {
                    // Loop through emails.
                    foreach ($identity->emails as $email)
                    {
                        // Add to simple list.
                        $data['user_emails_simple'][] = $email->value;

                        // Add to list.
                        $data['user_emails'][] = array(
                            'user_email' => $email->value,
                            'user_email_is_verified' => $email->is_verified);

                        // Keep one, if possible a verified one.
                        if (empty($data['user_email']) || $email->is_verified)
                        {
                            $data['user_email'] = $email->value;
                            $data['user_email_is_verified'] = $email->is_verified;
                        }
                    }
                }

                // Addresses.
                $data['user_addresses'] = array();
                $data['user_addresses_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'addresses') && is_array($identity->addresses))
                {
                    // Loop through entries.
                    foreach ($identity->addresses as $address)
                    {
                        // Add to simple list.
                        $data['user_addresses_simple'][] = $address->formatted;

                        // Add to list.
                        $data['user_addresses'][] = array(
                            'formatted' => $address->formatted);
                    }
                }

                // Phone Number.
                $data['user_phone_numbers'] = array();
                $data['user_phone_numbers_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'phoneNumbers') && is_array($identity->phoneNumbers))
                {
                    // Loop through entries.
                    foreach ($identity->phoneNumbers as $phone_number)
                    {
                        // Add to simple list.
                        $data['user_phone_numbers_simple'][] = $phone_number->value;

                        // Add to list.
                        $data['user_phone_numbers'][] = array(
                            'value' => $phone_number->value,
                            'type' => (isset($phone_number->type) ? $phone_number->type : null));
                    }
                }

                // URLs.
                $data['user_interests'] = array();
                $data['user_interests_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'interests') && is_array($identity->interests))
                {
                    // Loop through entries.
                    foreach ($identity->interests as $interest)
                    {
                        // Add to simple list.
                        $data['user_interests_simple'][] = $interest->value;

                        // Add to list.
                        $data['users_interests'][] = array(
                            'value' => $interest->value,
                            'category' => (isset($interest->category) ? $interest->category : null));
                    }
                }

                // URLs.
                $data['user_urls'] = array();
                $data['user_urls_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'urls') && is_array($identity->urls))
                {
                    // Loop through entries.
                    foreach ($identity->urls as $url)
                    {
                        // Add to simple list.
                        $data['user_urls_simple'][] = $url->value;

                        // Add to list.
                        $data['user_urls'][] = array(
                            'value' => $url->value,
                            'type' => (isset($url->type) ? $url->type : null));
                    }
                }

                // Certifications.
                $data['user_certifications'] = array();
                $data['user_certifications_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'certifications') && is_array($identity->certifications))
                {
                    // Loop through entries.
                    foreach ($identity->certifications as $certification)
                    {
                        // Add to simple list.
                        $data['user_certifications_simple'][] = $certification->name;

                        // Add to list.
                        $data['user_certifications'][] = array(
                            'name' => $certification->name,
                            'number' => (isset($certification->number) ? $certification->number : null),
                            'authority' => (isset($certification->authority) ? $certification->authority : null),
                            'start_date' => (isset($certification->startDate) ? $certification->startDate : null));
                    }
                }

                // Recommendations.
                $data['user_recommendations'] = array();
                $data['user_recommendations_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'recommendations') && is_array($identity->recommendations))
                {
                    // Loop through entries.
                    foreach ($identity->recommendations as $recommendation)
                    {
                        // Add to simple list.
                        $data['user_recommendations_simple'][] = $recommendation->value;

                        // Build data.
                        $data_entry = array(
                            'value' => $recommendation->value);

                        // Add recommender
                        if (property_exists($recommendation, 'recommender') && is_object($recommendation->recommender))
                        {
                            $data_entry['recommender'] = array();

                            // Add recommender details
                            foreach (get_object_vars($recommendation->recommender) as $field => $value)
                            {
                                $data_entry['recommender'][oneall_social_login_undo_camel_case($field)] = $value;
                            }
                        }

                        // Add to list.
                        $data['user_recommendations'][] = $data_entry;
                    }
                }

                // Accounts.
                $data['user_accounts'] = array();

                // Extract entries.
                if (property_exists($identity, 'accounts') && is_array($identity->accounts))
                {
                    // Loop through entries.
                    foreach ($identity->accounts as $account)
                    {
                        // Add to list.
                        $data['user_accounts'][] = array(
                            'domain' => (isset($account->domain) ? $account->domain : null),
                            'userid' => (isset($account->userid) ? $account->userid : null),
                            'username' => (isset($account->username) ? $account->username : null));
                    }
                }

                // Photos.
                $data['user_photos'] = array();
                $data['user_photos_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'photos') && is_array($identity->photos))
                {
                    // Loop through entries.
                    foreach ($identity->photos as $photo)
                    {
                        // Add to simple list.
                        $data['user_photos_simple'][] = $photo->value;

                        // Add to list.
                        $data['user_photos'][] = array(
                            'value' => $photo->value,
                            'size' => $photo->size);
                    }
                }

                // Languages.
                $data['user_languages'] = array();
                $data['user_languages_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'languages') && is_array($identity->languages))
                {
                    // Loop through entries.
                    foreach ($identity->languages as $language)
                    {
                        // Add to simple list
                        $data['user_languages_simple'][] = $language->value;

                        // Add to list.
                        $data['user_languages'][] = array(
                            'value' => $language->value,
                            'type' => $language->type);
                    }
                }

                // Educations.
                $data['user_educations'] = array();
                $data['user_educations_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'educations') && is_array($identity->educations))
                {
                    // Loop through entries.
                    foreach ($identity->educations as $education)
                    {
                        // Add to simple list.
                        $data['user_educations_simple'][] = $education->value;

                        // Add to list.
                        $data['user_educations'][] = array(
                            'value' => $education->value,
                            'type' => $education->type);
                    }
                }

                // Organizations.
                $data['user_organizations'] = array();
                $data['user_organizations_simple'] = array();

                // Extract entries.
                if (property_exists($identity, 'organizations') && is_array($identity->organizations))
                {
                    // Loop through entries.
                    foreach ($identity->organizations as $organization)
                    {
                        // At least the name is required.
                        if (!empty($organization->name))
                        {
                            // Add to simple list.
                            $data['user_organizations_simple'][] = $organization->name;

                            // Build entry.
                            $data_entry = array();

                            // Add all fields.
                            foreach (get_object_vars($organization) as $field => $value)
                            {
                                $data_entry[oneall_social_login_undo_camel_case($field)] = $value;
                            }

                            // Add to list.
                            $data['user_organizations'][] = $data_entry;
                        }
                    }
                }
            }
        }

        return $data;
    }

    return false;
}

// Inverts CamelCase -> camel_case.
function oneall_social_login_undo_camel_case($input)
{
    $result = $input;

    if (preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches))
    {
        $ret = $matches[0];

        foreach ($ret as &$match)
        {
            $match = ($match == strtoupper($match) ? strtolower($match) : lcfirst($match));
        }

        $result = implode('_', $ret);
    }

    return $result;
}

// Generate a random character
function oneall_social_login_generate_hash_char($case_sensitive = false)
{
    // Regular expression
    $regexp = ($case_sensitive ? 'a-zA-Z0-9' : 'a-z0-9');

    do
    {
        $char = chr(mt_rand(48, 122));
    } while (!preg_match('/[' . $regexp . ']/', $char));

    return $char;
}

// Generate a hash
function oneall_social_login_generate_hash($length = 5, $case_sensitive = false)
{
    $hash = '';

    for ($i = 0; $i < $length; $i++)
    {
        $hash .= oneall_social_login_generate_hash_char($case_sensitive);
    }

    return $hash;
}

// Return the client IP used in $_SERVER
function oneall_social_login_get_client_ip()
{
    if (isset($_SERVER) && is_array($_SERVER))
    {
        $keys = array();
        $keys[] = 'HTTP_X_REAL_IP';
        $keys[] = 'HTTP_X_FORWARDED_FOR';
        $keys[] = 'HTTP_CLIENT_IP';
        $keys[] = 'REMOTE_ADDR';

        foreach ($keys as $key)
        {
            if (isset($_SERVER[$key]))
            {
                if (preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $_SERVER[$key]) === 1)
                {
                    return $_SERVER[$key];
                }
            }
        }
    }

    return '';
}
