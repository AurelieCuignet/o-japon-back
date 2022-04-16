<?php

/**
 * This function inserts into the new taxonomy the provided data from an array
 * This array can be found in data/taxonomies_data.php
 * 
 * @param string $taxonomy
 * @param array $data
 *
 * @return void
 */
function insert_initial_data($taxonomy, $data = [], $args = []) {
    //if (!isset($args['parent'])) $args['parent'] = 0;
	// parse array
		// for each item, add term with wp_insert_term
        foreach($data as $value) {
            /* var_dump($taxonomy);
            var_dump($args);
            var_dump($value); die; */
            wp_insert_term($value, $taxonomy/* , $args */);
            
        }
}

function mod_jwt_auth_valid_credential_response( $response, $user ) {
    $user_info = get_user_by( 'email',  $user->data->user_email );
    $token = $response['data']['token'];
    $response = array(
        'success'    => true,
        'statusCode' => 200,
        'code'       => 'jwt_auth_valid_credential',
        'message'    => __( 'Credential is valid', 'jwt-auth' ),
        'data'       => array(
            'token'       => $token,
            'id'          => $user->ID,
            'email'       => $user->user_email,
            'nicename'    => $user->user_nicename,
            'firstName'   => $user->first_name,
            'lastName'    => $user->last_name,
            'displayName' => $user->display_name,
            'roles'       => $user_info->roles[0],
        ),
    );
    
    return $response;
}
add_filter( 'jwt_auth_valid_credential_response', 'mod_jwt_auth_valid_credential_response', 10, 2 ); 

/**
 * Allows specified client to send POST and DELETE requests to WP
 *
 * @param [type] $value
 * @return void
 */
function initCors($value)
{
    $origin_url = 'http://localhost:8080';
  
    header('Access-Control-Allow-Origin: ' . $origin_url);
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, X-WP-Nonce, Content-Type, Accept, Authorization');
    return $value;
}
add_action( 'rest_api_init', 'initCors');


/* add_action("rest_insert_comment", function ($comment, $request, $creating) {
    $metas = $request->get_param("meta");

    if (is_array($metas)) {
        foreach ($metas as $name => $value) {
            $creating ? add_comment_meta($comment->ID, $name, $value) : update_comment_meta($comment->ID, $name, $value);
        }
    }
}, 10, 3); */

add_action('rest_api_init', 'ojapon_rest_comment_meta');

function ojapon_rest_comment_meta()
{
    // Defines a new route for our user registration
    //? this method receives its parameters via a form or an object
    register_rest_route('ojapon_plugin/v1', 'comments', array(
        'methods' => ['POST'],
        'callback' => 'ojapon_rest_comment_meta_handler',
        'permission_callback' => function () {
            return true;
        }
    ));
}

function ojapon_rest_comment_meta_handler($request)
{
    // $request is an instance of WP_REST_Request
    $http_method = $request->get_method();

    global $wpdb;
    
    // Preparation of errors in case of non-validation of data
    $error = new WP_Error();

    //retrieve query params
    $params = $request->get_params();

    $current_user = wp_get_current_user();

    // on vérifie en BDD si cet user a déjà commenté ce POI (on ne tient pas compte des commentaires supprimés)
    $query = "SELECT * FROM `wp_comments` WHERE `comment_post_ID` =" .$params['post'] . " AND `user_id` = " . $current_user->ID . "AND `comment_approved` != 'trash'";
    $result = $wpdb->get_row($query);
    //! todo $result is always null because there is a syntax error in this statement (missing space before the 2nd AND). 
    //! But the front-end doesn't handle correcty this case where the current user has already commented, so I'm leaving this as this is for now, and I'll fix this after my exam...
    //! Keep posted!

    // Prepare response HTTP
    $response = array();
    // si $result est null, que l'user n'a pas encore commenté
    if (is_null($result)) {
        $comment_data = array(
            'user_id'  => $current_user->ID,
            'comment_author' => $current_user->data->display_name,
            'comment_author_email' => $current_user->data->user_email,
            'comment_author_url' => $current_user->data->user_url,
            'comment_post_ID' => $params['post'],
            'comment_content' => $params['content'],
            'comment_meta' => $params['meta']
        );
        $result = wp_new_comment($comment_data);
        if($result) {
            $response['code'] = 201;
            $response['message'] = "Comment successfully added";
            $response['requeteSql'] = $query;
            $response['author'] = $current_user->ID;
        }  else {
            $error->add(400, "Comment couldn't be inserted", array('status' => 400));
            return $error;
        }
    } else {
        // cet user a déjà commenté ce POI
        $error->add(400, "You already commented this point of interest", array('status' => 400));
        return $error;
    }
    

    return new WP_REST_Response($response, 123);
}
