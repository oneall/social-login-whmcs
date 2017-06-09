<?php
if (!defined("WHMCS"))
{
    die("This file cannot be accessed directly");
}

// Include Tools
require_once (realpath(dirname(__FILE__)) . '/assets/toolbox.php');

// Callback Handler
function oneall_social_login_callback()
{
    global $CONFIG;

    // Callback Handler
    if (isset($_POST) and !empty($_POST['oa_action']) and $_POST['oa_action'] == 'social_login' and !empty($_POST['connection_token']))
    {
        // OneAll Connection token
        $connection_token = trim($_POST['connection_token']);

        // Read Settings
        $settings = oneall_social_login_get_settings();

        // Without the API settings we cannot establish a connection
        if (!empty($settings['subdomain']) && !empty($settings['public_key']) && !empty($settings['private_key']))
        {
            // See: http://docs.oneall.com/api/resources/connections/read-connection-details/
            $api_resource_url = ($settings['api_use_https'] ? 'https' : 'http') . '://' . $settings['subdomain'] . '.api.oneall.com/connections/' . $connection_token . '.json';

            // API Credentials
            $api_opts = array();
            $api_opts['api_key'] = $settings['public_key'];
            $api_opts['api_secret'] = $settings['private_key'];

            // Retrieve connection details
            $result = oneall_social_login_do_api_request($settings['handler'], $api_resource_url, $api_opts);

            // Check result
            if (is_object($result) and property_exists($result, 'http_code') and $result->http_code == 200 and property_exists($result, 'http_data'))
            {
                // Extract Data
                if (($social_data = oneall_social_login_extract_social_network_profile($result->http_data)) !== false)
                {
                    // Read userid for user_token
                    $userid = oneall_social_login_get_userid_by_token($social_data['user_token']);

                    // No user found
                    if (!is_numeric($userid))
                    {
                        // This is a new user
                        $new_registration = true;

                        // Linking enabled?
                        if (!empty($settings['automatic_link']))
                        {
                            // Only if email is verified
                            if (!empty($social_data['user_email']) && $social_data['user_email_is_verified'] === true)
                            {
                                // Read existing user
                                $userid = oneall_social_login_get_userid_by_email($social_data['user_email']);
                            }
                        }
                    }

                    // New User
                    if (!is_numeric($userid))
                    {
                        // Build Client Data: https://developers.whmcs.com/api-reference/addclient/
                        $client_data = array();
                        $client_data['firstname'] = (!empty($social_data['user_first_name']) ? $social_data['user_first_name'] : $social_data['user_login']);
                        $client_data['lastname'] = (!empty($social_data['user_last_name']) ? $social_data['user_last_name'] : '');
                        $client_data['password2'] = oneall_social_login_generate_hash(10);
                        $client_data['clientip'] = oneall_social_login_get_client_ip();

                        // Pass as true to ignore required fields validation.
                        $client_data['skipvalidation'] = true;

                        // Email Provided?
                        if (!empty($social_data['user_email']))
                        {
                            $client_data['email'] = $social_data['user_email'];
                        }
                        else
                        {
                            // Pass as true to skip sending welcome email.
                            $client_data['noemail'] = true;
                        }

                        // Admin Username.
                        $admin_username = oneall_social_login_get_admin_username();

                        // Add Client.
                        $result = localAPI('AddClient', $client_data, $admin_username);
                        if (is_array($result) && !empty($result['clientid']))
                        {
                            $userid = $result['clientid'];
                        }
                    }

                    // Now we should have a user
                    if (!empty($userid))
                    {
                        // Link tokens to user
                        oneall_social_login_link_tokens_to_userid($userid, $social_data['user_token'], $social_data['identity_token'], $social_data['identity_provider']);

                        // Read USER IP
                        $user_ip_address = oneall_social_login_get_client_ip();

                        // Login user
                        if (oneall_social_login_login_userid($userid, $user_ip_address))
                        {
                            if (!empty($_GET['return_url']))
                            {
                                $redirect_to = $CONFIG['SystemURL'] . htmlentities($_GET['return_url']);
                            }
                            else
                            {
                                $redirect_to = rtrim($CONFIG['SystemURL'], ' /') . '/clientarea.php';
                            }

                            // Redirect
                            header("Location: " . $redirect_to);
                        }
                    }
                }
            }
        }
    }
}
add_hook("ClientAreaPage", 1, "oneall_social_login_callback");

// Builds the HTML code to embed the Social Login library
function oneall_social_login_library_html()
{
    // HTML Contents
    $html = '';

    // Read Settings
    $settings = oneall_social_login_get_settings();
    if (!empty($settings['subdomain']))
    {
        // Forge library path.
        $library = ((oneall_social_login_is_https_on() ? 'https' : 'http') . '://' . $settings['subdomain'] . '.api.oneall.com/socialize/library.js');

        // JavaScript Method Reference: http://docs.oneall.com/api/javascript/library/methods/
        $output = array();
        $output[] = '';
        $output[] = " <!-- OneAll.com / Social Login " . $settings['version'] . " for WHMCS -->";
        $output[] = '<script data-cfasync="false" type="text/javascript">';
        $output[] = " (function() {";
        $output[] = "  var oa = document.createElement('script'); oa.type = 'text/javascript';";
        $output[] = "  oa.async = true; oa.src = '" . $library . "';";
        $output[] = "  var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(oa, s);";
        $output[] = " })();";
        $output[] = "</script>";
        $output[] = '';

        // Build HTML
        $html = implode("\n", $output);
    }

    // Done
    return $html;
}
add_hook("ClientAreaHeadOutput", 1, "oneall_social_login_library_html");

// Builds the HTML code to display the Social Login icons
function oneall_social_login_icons_html($type = 'embedded')
{
    // HTML Contents
    $html = '';

    // Read Settings
    $settings = oneall_social_login_get_settings();
    if (!empty($settings['subdomain']) && count($settings['enabled_providers']) > 0)
    {
        // Rand id for multiple occurences.
        $containerid = 'oneall_social_login_providers_' . mt_rand(99999, 9999999);

        // Build Social Login
        $output = array();
        $output[] = '';
        $output[] = " <!-- OneAll.com / Social Login " . $settings['version'] . " for WHMCS -->";
        $output[] = '<div class="oneall_social_login">';

        // Popup
        if ($type == 'popup')
        {
            $link_title = (!empty($settings['popup_link_title']) ? $settings['popup_link_title'] : 'Login using a social network');
            $output[] = ' <a class="oneall_social_login_providers" id="' . $containerid . '">' . $link_title . '</div>';
        }
        // Embedded
        else
        {
            $embedded_title = (!empty($settings['embedded_title']) ? $settings['embedded_title'] : '');
            $output[] = ' <h4 class="oneall_social_login_caption">' . $embedded_title . '</h4>';
            $output[] = ' <div class="oneall_social_login_providers" id="' . $containerid . '"></div>';
        }

        $output[] = ' <script data-cfasync="false" type="text/javascript">';
        $output[] = "  var _oneall = _oneall || [];";
        $output[] = "  _oneall.push(['social_login', 'set_providers', ['" . implode("','", $settings['enabled_providers']) . "']]);";
        $output[] = "  _oneall.push(['social_login', 'set_callback_uri', window.location.href ]);";
        $output[] = "  _oneall.push(['social_login', 'set_custom_css_uri', '" . $settings['custom_css_uri'] . "']);";

        // Popup
        if ($type == 'popup')
        {
            $output[] = "  _oneall.push(['social_login', 'attach_onclick_popup_ui', '" . $containerid . "']);";
        }
        else
        {
            $output[] = "  _oneall.push(['social_login', 'do_render_ui', '" . $containerid . "']);";
        }

        $output[] = " </script>";
        $output[] = '</div>';

        // Build HTML
        $html = implode("\n", $output);
    }

    // Done
    return $html;
}

// Adds the shortcodes to display the Social Login icons
function oneall_social_login_shortcodes()
{
    return array(
        'oneall_social_login_library' => oneall_social_login_library_html(),
        'oneall_social_login_embedded' => oneall_social_login_icons_html('embedded'),
        'oneall_social_login_popup' => oneall_social_login_icons_html('popup'));
}
add_hook("ClientAreaPage", 1, "oneall_social_login_shortcodes");