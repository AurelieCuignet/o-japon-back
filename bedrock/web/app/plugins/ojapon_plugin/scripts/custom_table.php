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
    // Defines a new route for our user registration
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

    /* register_rest_route('ojapon_plugin/v1', 'link_poi', array(
        'methods' => ['POST', 'DELETE'],
        'callback' => 'ojapon_rest_link_poi_handler',
        'permission_callback' => function () {
            return true;
        }
    )); */
}

function ojapon_rest_link_poi_handler($request)
{
    // $request est une instance de WP_REST_Request
    $http_method = $request->get_method();

    global $wpdb;

    // Preparation of errors in case of non-validation of data
    $error = new WP_Error();

    //retrieve query params
    $parameters = $request->get_params();

    // Preparation response HTTP
    //todo exemple, à modifier
    $response = array(
        'parameters'   => $parameters,
        'httpMethod'    => $http_method
    );
    

    //no need to filter, as the regex only accepts digits as argument
    $idguide = $parameters['idguide'];
    $idpoi = $parameters['idpoi'];

    //vérifie que les id sont strictement supérieurs à zéro
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

/wp-json/wp/v2/link_poi
body : 
    idguide: 74
    idpoi: 25
*/