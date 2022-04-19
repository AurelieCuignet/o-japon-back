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




add_action('rest_api_init', 'ojapon_rest_link_poi');

function ojapon_rest_link_poi()
{
    // Defines a new route for Guide/POI link management
    // params are regex protected (digits only)
    /* 
    Route : /wp-json/wp/v2//travelguide/74/poi/25 --> 
        POST --> add in `wp_ojapon_guide_poi` table a link between guide #74 and POI #25
        DELETE --> remove the row linking guide #74 and POI #25 
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

    /* 
    //? this method receives its parameters via a form or an object
    register_rest_route('ojapon_plugin/v1', 'link_poi', array(
        'methods' => ['POST', 'DELETE'],
        'callback' => 'ojapon_rest_link_poi_handler',
        'permission_callback' => function () {
            return true;
        }
    )); */
}

function ojapon_rest_link_poi_handler($request)
{
    // $request is an instance of WP_REST_Request
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

    //! if we chose to use the second route (/link_poi), params must be filtered and an error thrown if digits weren't passed as arguments
    
    /* $response['idguideunfiltered'] = $parameters['idguide'];
    $idguide = filter_var($parameters['idguide'], FILTER_SANITIZE_NUMBER_INT);
    $response['idguidefiltered'] = $idguide;
    $idpoi = filter_var($parameters['idpoi'], FILTER_SANITIZE_NUMBER_INT); */

    // Verification du contenu du formulaire
    //! pour exemple, à modifier
    /* if (empty($idguide)) {
        $error->add(400, "The provided parameter for guide is not an integer", array('status' => 400));
        return $error;
    } */

    // si method = POST --> on fait une insertion
    if($http_method == 'POST') {
        // on peut faire une vérification de l'existence du guide et du point d'intérêt passés en param
        $query = "SELECT * FROM `wp_ojapon_guide_poi` WHERE `guide_id` =" .$idguide . " AND `poi_id` = " . $idpoi;
        $result = $wpdb->get_row($query);
        
        // si $result est null, c'est que le lien n'existe pas encore
        if(is_null($result)) {
            // on fait le lien entre les deux CPT travelguide et poi
            $result = $wpdb->insert(
                'wp_ojapon_guide_poi',
                [
                    'guide_id'   => $idguide,
                    'poi_id'   => $idpoi
                ]
            );

            // si insertion ok, on renvoie un 201 Created
            if($result == 1) {
                $response['code'] = 201;
                $response['message'] = "Successfully Linked";
            } 
            // sinon message d'erreur
            else {
                $error->add(400, "Link couldn't be inserted in database", array('status' => 400));
                return $error;
            }
        }
        // sinon on renvoie une erreur
        else {
            $error->add(400, "This Point of interest is already linked to this travel guide", array('status' => 400));
            return $error;
        }
    } 
    
    //si method = DELETE --> on supprime le lien
    elseif ($http_method == 'DELETE') {
        // on peut vérifier en une requête que le lien existe et le supprimer le cas échéant
        // s'il existe, on supprime l'enregistrement correspondant dans la table, sinon on renvoie false
        $result = $wpdb->delete( 'wp_ojapon_guide_poi', array( 'guide_id' => $idguide, 'poi_id' => $idpoi ) );

        // si suppression ok, on renvoie un 200 OK
        if($result >= 1) {
            $response['code'] = 200;
            $response['message'] = "Successfully Unlinked";
        } 
        // sinon message d'erreur
        else {
            $error->add(400, "This Point of interest is not linked to this travel guide", array('status' => 400));
            return $error;
        }
    }
    // si aucune méthode n'est la bonne --> on renvoie une erreur
    // cette condition ne devrait jamais être vérifiée car on n'autorise que POST et DELETE dans la déclaration de la route
    else {
        // message d'erreur (méthode non autorisée)
        $error->add(405, "This method is not allowed for this route", array('status' => 405));
        return $error;
    }

    return new WP_REST_Response($response, 123);
};


add_action('rest_api_init', 'ojapon_rest_get_poi_from_guide');

function ojapon_rest_get_poi_from_guide()
{
    // Defines a new route for our user registration
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

    // Preparation of errors in case of non-validation of data
    $error = new WP_Error();

    //retrieve query params
    $parameters = $request->get_params();
    $guideid = $parameters['idguide'];

    // Prepare HTTP response
    $response = array();

    // sql query to retrieve all POI linked to the specified guide
    $query = "SELECT `posts`.id FROM `wp_posts` AS posts
    INNER JOIN `wp_ojapon_guide_poi` AS links
    ON `posts`.id = `links`.poi_id
    WHERE `links`.guide_id = " . $guideid;

    $sql = $wpdb->prepare(
        "SELECT `posts`.id FROM `wp_posts` AS posts
        INNER JOIN `wp_ojapon_guide_poi` AS links
        ON `posts`.id = `links`.poi_id
        WHERE `links`.guide_id = %d", $guideid);

    $results = $wpdb->get_results($sql);

    
 
    foreach ($results as $result) {
        //call interne à l'api
        // /wp-json/wp/v2/travelguide/76/poi
        $request = new WP_REST_Request( 'GET', '/wp/v2/poi/'.$result->id);
        // Set one or more request query parameters
        $request->set_param( '_embed', 1 );
        $resp = rest_do_request( $request );
        $response[] = rest_get_server()->response_to_data($resp, true);
    }
    /* $request = new WP_REST_Request( 'GET', '/wp/v2/posts/1?_embed' );
    $request->set_param( '_embed', 1 );
    $response = rest_do_request( $request ); */

    // si insertion ok, on renvoie un 200
    /* if(sizeof($results) != 0) {
        $response['code'] = 200;
        $response['message'] = "I have things";
    } 
    // sinon message d'erreur
    else {
        //$error->add(404, "There's nothing here", array('status' => 404));
        //return $error;
    } */

   
    return new WP_REST_Response($response, 123);

}

/*
Existent déjà : 
--------------
/wp-json/wp/v2/travelguide --> liste de tous les guides
/wp-json/wp/v2/travelguide/74 --> le guide qui a l'id 74

Param possibles : 
?_embed
?author=1
?_embed&author=1

Sont à créer : 
---------------
/wp-json/wp/v2/travelguide/74/poi --> liste tous les POI liés au guide 74

/wp-json/wp/v2//travelguide/74/poi/25 --> 
    en POST --> ajoute dans la table le lien entre le guide 74 et le poi 25
    en DELETE --> supprime l'enregistrement qui lie le guide 74 et le poi 25

*/