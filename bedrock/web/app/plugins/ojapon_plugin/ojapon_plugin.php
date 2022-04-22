<?php
/**
 * O'Japon plugin for WordPress
 *
 * Plugin Name:  Voyage O'Japon
 * Description:  A collection of useful features for our collaborative website around trips to Japan
 * Version:      0.1
 * Author:       Aurélie Cuignet
*/

// additionnal scripts
include plugin_dir_path(__FILE__) . 'scripts/functions.php';
// custom post types
include plugin_dir_path(__FILE__) . 'post-types/travel-guide.php';
include plugin_dir_path(__FILE__) . 'post-types/poi.php';

// custom taxonomies
include plugin_dir_path(__FILE__) . 'taxonomies/location.php';
include plugin_dir_path(__FILE__) . 'taxonomies/genre.php';
include plugin_dir_path(__FILE__) . 'taxonomies/season.php'; 

// custom role
include plugin_dir_path(__FILE__) . 'roles/traveler.php';

// user registration via WP API
include plugin_dir_path(__FILE__) . 'scripts/registration.php';

// custom table to link CPTs
include plugin_dir_path(__FILE__) . 'scripts/custom_table.php';


register_activation_hook(__FILE__, function () {
    // creating custom role
    ojapon_create_roles();

    // defining new role capabilities
    ojapon_add_cap_roles();

    // creating custom table to link travel guides to points of interest
    ojapon_create_custom_table();

});
register_deactivation_hook(__FILE__, function () {
    // removing all plugin additions
    ojapon_remove_roles();
    ojapon_remove_cap_roles();

    // in dev environment, I don't want my custom table to be dropped each time I deactivate the plugin for testing purposes
    // but in live mode, the following line should be uncommented and the user warned about the loss of stored data in this table
    //ojapon_drop_custom_table();
});