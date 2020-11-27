<?php
if (!defined("WHMCS"))
{
    die("This file cannot be accessed directly");
}

// Database handler
use WHMCS\Database\Capsule;

// Include Tools
require_once realpath(dirname(__FILE__)) . '/assets/toolbox.php';

// AddOn Configuration
function oneall_social_login_config()
{
    $configarray = array(
        "name" => "OneAll Social Login",
        "description" => "Social Login for WHMCS allows your users to login and register with 40+ social networks (Facebook, Twitter, Google, Pinterest, LinkedIn ...). It increases your user registration rate by simplifying the registration process and provides permission-based social data retrieved from the social network profiles.",
        "version" => "1.4.0",
        "author" => "OneAll",
        "language" => "english",
        "fields" => array());

    // Subdomain
    $configarray['fields']['subdomain'] = array(
        "FriendlyName" => "OneAll API Subdomain",
        "Type" => "text",
        "Size" => "30",
        "Description" => 'API Subdomain obtained from the Site settings in your <a href="https://app.oneall.com/" target="_blank"><strong>OneAll account</strong></a>.',
        "Default" => "");

    // Public Key
    $configarray['fields']['public_key'] = array(
        "FriendlyName" => "OneAll API Public Key",
        "Type" => "text",
        "Size" => "30",
        "Description" => 'API Public Key obtained from the Site settings in your <a href="https://app.oneall.com/" target="_blank"><strong>OneAll account</strong></a>.',
        "Default" => "");

    // Private Key
    $configarray['fields']['private_key'] = array(
        "FriendlyName" => "OneAll API Private Key",
        "Type" => "text",
        "Size" => "30",
        "Description" => 'API Private Key obtained from the Site settings in your <a href="https://app.oneall.com/" target="_blank"><strong>OneAll account</strong></a>.',
        "Default" => "");

    // Handler
    $configarray['fields']['handler'] = array(
        "FriendlyName" => "API Handler",
        "Type" => "dropdown",
        "Options" => "CURL, FSOCKOPEN",
        "Description" => "Using CURL is recommended but it might be disabled on some servers.",
        "Default" => "CURL");

    // Port
    $configarray['fields']['port'] = array(
        "FriendlyName" => "API Port",
        "Type" => "dropdown",
        "Options" => "443, 80",
        "Description" => "Your firewall must allow outgoing request on the selected port.",
        "Default" => "443");

    // Embedded
    $configarray['fields']['embedded_title'] = array(
        "FriendlyName" => "Embedded Title",
        "Type" => "text",
        "Size" => "40",
        "Description" => "Social Login caption for embedded display. Add {\$oneall_social_login_embedded} to your template to display.",
        "Default" => "Connect using a social network:");

    // Popup
    $configarray['fields']['popup_link_title'] = array(
        "FriendlyName" => "Popup Link Title",
        "Type" => "text",
        "Size" => "40",
        "Description" => "Social Login link title for popup display. Add {\$oneall_social_login_popup} to your template to display.",
        "Default" => "Login using a social network");

    // Custom CSS Url
    $configarray['fields']['custom_css_uri'] = array(
        "FriendlyName" => "Custom CSS URL",
        "Type" => "text",
        "Size" => "40",
        "Description" => 'URL to a custom CSS file to change the buttons. Requires a <a href="https://www.oneall.com/pricing-and-plans/" target="_blank"><strong>OneAll Starter</strong></a> plan.',
        "Default" => "");

    // Automatic Link
    $configarray['fields']['automatic_link'] = array(
        "FriendlyName" => "Automatic Link",
        "Type" => "yesno",
        "Description" => "Tick to automatically link social network accounts with a verified email address to existing accounts.",
        "Default" => "1");

    // Read Providers
    $providers = oneall_social_login_get_all_providers();

    // Add to settings
    foreach ($providers as $key => $data)
    {
        // Layout
        $configarray['fields']['provider_' . $key] = array(
            "FriendlyName" => $data['name'],
            "Type" => "yesno",
            "Description" => "Tick to enable " . $data['name'],
            "Default" => (!empty($data['enabled_default']) ? '1' : '0'));
    }

    // Done

    return $configarray;
}

// Activate AddOn
function oneall_social_login_activate()
{
    // user_token storage
    if (!Capsule::schema()->hasTable('tbloneall_user_token'))
    {
        Capsule::schema()->create('tbloneall_user_token', function ($table)
        {
            $table->increments('id');
            $table->integer('userid');
            $table->string('user_token');
        });
    }

    // identity_token storage
    if (!Capsule::schema()->hasTable('tbloneall_identity_token'))
    {
        Capsule::schema()->create('tbloneall_identity_token', function ($table)
        {
            $table->increments('id');
            $table->integer('user_tokenid');
            $table->string('identity_token');
            $table->string('identity_provider');
        });
    }

    return array(
        'status' => 'success',
        'description' => 'Social Login has successfully been activated. Please setup your API keys in order to enable the addon.');
}

// Deactivate AddOn
function oneall_social_login_deactivate()
{
    // Don't remove the tables, otherwise the customer looses all client Social Login information
    // Capsule::schema()->dropIfExists('tbloneall_user_token');
    // Capsule::schema()->dropIfExists('tbloneall_identity_token');

    return array(
        'status' => 'success',
        'description' => 'Social Login has successfully been deactivated.');
}

// Upgrade AddOn
function oneall_social_login_upgrade($vars)
{
}

// AddOn Admin Area Content/Output
function oneall_social_login_output($vars)
{
    echo 'Version: ' . $vars['version'] . '<br /><br />';
    echo 'Please ensure you have entered your OneAll API Subdomain and Private/Public Keys into the settings under Setup->Addon Modules<br />';
    echo 'Place the template variable {$oneall_social_login_embedded} in your login.tpl file where you would like the Social Login icons to appear.<br />';
}

// AddOn Sidebar Output
function oneall_social_login_sidebar($vars)
{
    return '';
}
