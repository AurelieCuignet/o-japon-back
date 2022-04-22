<?php

function ojapon_create_custom_table()
{
    global $wpdb;
    $table_name = "wp_ojapon_guide_poi";
    $collation = $wpdb->collate;
    $sql = "
    CREATE TABLE `$table_name` (
        `id` mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `guide_id` mediumint(9) NOT NULL,
        `poi_id` mediumint(9) NOT NULL
        ) COLLATE '" . $collation . "';";

    /* New table structure - query tested and working
    CREATE TABLE `wp_ojapon_guide_poi` (
        `id` mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `guide_id` BIGINT(20) UNSIGNED,
        `poi_id` BIGINT(20) UNSIGNED,
        CONSTRAINT guide_fk
            FOREIGN KEY (`guide_id`)
            REFERENCES `wp_posts`(`ID`)
            ON DELETE CASCADE,
        CONSTRAINT poi_fk
            FOREIGN KEY (`poi_id`)
            REFERENCES `wp_posts`(`ID`)
            ON DELETE CASCADE
        )
    */
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    //dbDelta() is used for everything: insertion, update, etc.
    dbDelta($sql);
}

function ojapon_drop_custom_table() 
{
    global $wpdb;
    $table_name = "wp_ojapon_guide_poi";
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

/* adds a custom callback function on hook rest_api_init */
add_action('rest_api_init', 'ojapon_rest_link_poi');

function ojapon_rest_link_poi()
{
    // Defines a new route for Guide/POI link management
    // params are regex protected (digits only)
    /* 
    Route : /wp-json/wp/v2//travelguide/74/poi/25 --> 
        endpoint POST --> add in `wp_ojapon_guide_poi` table a link between guide #74 and POI #25
        endpoint DELETE --> remove the row matching guide #74 and POI #25 
    */
    register_rest_route('wp/v2', '/travelguide/(?P<idguide>\d+)/poi/(?P<idpoi>\d+)', array(
        'methods' => ['POST', 'DELETE'],
        'callback' => 'ojapon_rest_link_poi_handler',
        'args' => [
            'idguide',
            'idpoi'
        ],
        'permission_callback' => function () {
            return true;
        }
    ));
}

function ojapon_rest_link_poi_handler($request)
{
    // $request is an instance of WP_REST_Request
    // method is needed to know which action to take
    $http_method = $request->get_method();

    // Get current database connection
    global $wpdb;

    // Create a WP_Error instance in case of invalid data
    $error = new WP_Error();

    // Retrieve query params
    $parameters = $request->get_params();

    // Prepare HTTP response
    $response = array();

    // No need to filter, as the regex only accepts digits as argument
    $idguide = $parameters['idguide'];
    $idpoi = $parameters['idpoi'];

    // Check if ids are strictly positive numbers
    if($idguide <= 0 || $idpoi <= 0) {
        $error->add(400, "IDs should be positive integers", array('status' => 400));
        return $error;
    }

    // if method = POST --> insert link
    if($http_method == 'POST') {
        // checks if there is already a link between the specified guide and point of interest
        $query = "SELECT * FROM `wp_ojapon_guide_poi` WHERE `guide_id` =" .$idguide . " AND `poi_id` = " . $idpoi;
        $result = $wpdb->get_row($query);
        
        // if $result is null, the link doesn't exist yet
        if(is_null($result)) {
            $result = $wpdb->insert(
                'wp_ojapon_guide_poi',
                [
                    'guide_id'   => $idguide,
                    'poi_id'   => $idpoi
                ]
            );

            // if insertion is ok, sending back a 201 Created code
            if($result == 1) {
                $response['code'] = 201;
                $response['message'] = "Successfully Linked";
            } 
            // else throwing error message
            else {
                $error->add(400, "Link couldn't be inserted in database", array('status' => 400));
                return $error;
            }
        }
        // else throwing error message
        else {
            $error->add(400, "This Point of interest is already linked to this travel guide", array('status' => 400));
            return $error;
        }
    } 
    
    //if method = DELETE --> remove link
    elseif ($http_method == 'DELETE') {
        // we can check in one go if the link exists and delete it if necessary
        // if it exists, this method removes the matching record in the table 
        // and returns the number of rows affected, otherwise returns false
        $result = $wpdb->delete( 'wp_ojapon_guide_poi', array( 'guide_id' => $idguide, 'poi_id' => $idpoi ) );

        // if deletion ok, sending back a 200 OK code
        if($result >= 1) {
            $response['code'] = 200;
            $response['message'] = "Successfully Unlinked";
        } 
        // else throwing error message
        else {
            $error->add(400, "This Point of interest is not linked to this travel guide", array('status' => 400));
            return $error;
        }
    }
    // if the method doesn't match one of the authorized ones --> throwing error message
    // this condition should never be true because we only allow POST and DELETE in the route declaration
    else {
        // error message (method not allowed)
        $error->add(405, "This method is not allowed for this route", array('status' => 405));
        return $error;
    }
    // sending back HTTP response
    return new WP_REST_Response($response, 123);
};


add_action('rest_api_init', 'ojapon_rest_get_poi_from_guide');

function ojapon_rest_get_poi_from_guide()
{
    // Defines a new route to get only linked POI for a specific guide
    register_rest_route('wp/v2', '/travelguide/(?P<idguide>\d+)/poi', array(
        'methods' => ['GET'],
        'callback' => 'ojapon_rest_get_poi_from_guide_handler',
        'args' => [
            'idguide'
        ],
        'permission_callback' => function () {
            return true;
        }
    ));
}

function ojapon_rest_get_poi_from_guide_handler(WP_REST_REQUEST $request) {
    global $wpdb;

    // Create a WP_Error instance in case of invalid data
    $error = new WP_Error();

    //retrieve query params
    $parameters = $request->get_params();
    $guideid = $parameters['idguide'];

    // Prepare HTTP response
    $response = array();

    // sql query to retrieve all POI linked to the specified guide
    $sql = $wpdb->prepare(
        "SELECT `posts`.id FROM `wp_posts` AS posts
        INNER JOIN `wp_ojapon_guide_poi` AS links
        ON `posts`.id = `links`.poi_id
        WHERE `links`.guide_id = %d", $guideid);

    $results = $wpdb->get_results($sql);
 
    foreach ($results as $result) {
        // internal call to API
        $request = new WP_REST_Request( 'GET', '/wp/v2/poi/'.$result->id);
        // Set one or more request query parameters
        $request->set_param( '_embed', 1 );
        // do a REST request, used primarily to route internal requests through WP_REST_Server.
        $resp = rest_do_request( $request );
        // Retrieves the current REST server instance 
        //! declaring a new WP_REST_Server instance instead of using the current one
        //! will result in 404 no route found error on embedded elements)
        //response_to_data() converts a response to data (obviously), true being the argument value for $embed (to embed all links)
        // the expected response should provide _links and _embed attributes, just like a classic call to the endpoint GET /wp/v2/poi
        $response[] = rest_get_server()->response_to_data($resp, true);
    }   
    return new WP_REST_Response($response, 123);
}