<?php

include('../wp-load.php');    
include("Helpers.php");

class UserSessionData extends Helper_Functions {
    
    public $wpdb;
    public $prefix;
    public $request;
    
    public function __construct($request) {
        
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
        $this->request = $request;
        
        // Prepare the response by calling the appropriate method
        $this->prepare_item_for_response("UserSessionDetails");        
    }

    /*
    |-----------------------------------|
    |===== Load User Session Details =====|
    |-----------------------------------|
    */
    
    public function UserSessionDetails(){
        try {
            // Ensure we have user ID from the request
            if (empty($this->request->userID)) {
                return array(
                    'success' => 0,
                    'message' => 'User ID is required.'
                );
            }
            
            // Generate and store the session ID for the user
            $session_id = $this->generate_and_store_session_id($this->request->userID);
            $table_name = $this->wpdb->prefix . 'user_sessions';

            // Check if the user already has 3 sessions (or less)
            $existing_sessions = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_ids = %d",
                $this->request->userID
            ));

            // If the user already has 3 sessions, we will log out the earliest session
            if (count($existing_sessions) >= 3) {
                // Get the oldest session for the user
                $oldest_session = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT * FROM $table_name WHERE user_ids = %d ORDER BY login_time ASC LIMIT 1",
                    $this->request->userID
                ));
                
                if ($oldest_session) {
                    // Log out the user associated with the oldest session
                    $this->wp_logout_user($oldest_session->session_token);
                }
            }

            // Insert the new session for the user
            $this->wpdb->insert(
                $table_name,
                array(
                    'user_ids' => $this->request->userID,
                    'session_token' => $session_id,
                    'login_time' => current_time('mysql')
                )
            );

            // Return success response
            return array(
                'success' => 1,
                'message' => 'Session created successfully.'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => 0,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => new stdClass()
            );
        }
    }

    /*
    |--------------------------------------|
    |===== Generate and Store Session ID =====|
    |--------------------------------------|
    */
    
    function generate_and_store_session_id($user_id) {
        // Generate a unique session ID
        $session_id = md5(uniqid(rand(), true));

        // Store the session ID in user meta
        update_user_meta($user_id, '_current_session_id', $session_id);

        // Optionally, store session timestamp for expiration check or other purposes
        update_user_meta($user_id, '_last_login_time', current_time('timestamp'));

        return $session_id;
    }

    /*
    |--------------------------------------------|
    |===== Log Out User Based on Token =====|
    |--------------------------------------------| 
    */
    
    function wp_logout_user($session_token) {
        // Retrieve user by session token
        $user = $this->get_user_by_session_token($session_token);
        
        if ($user) {
            // Log out the user from WordPress
            wp_logout();
            
            // Optionally, delete the session record from the table for this user
            global $wpdb;
            $table_name = $wpdb->prefix . 'user_sessions';
            
            $wpdb->delete(
                $table_name,
                array('session_token' => $session_token)
            );
        }
    }
    
    /*
    |--------------------------------------------|
    |===== Get User by Session Token Method =====|
    |--------------------------------------------| 
    */
    
    function get_user_by_session_token($session_token) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_sessions';
        
        // Get user ID by session_token
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_ids FROM $table_name WHERE session_token = %s",
            $session_token
        ));
        
        if ($user_id) {
            // Get the WP user by ID
            return get_user_by('id', $user_id);
        }
        
        return null; // If no user found for the session token
    }

    /*
    |------------------------------------|
    |===== Prepare Json Response =====|
    |------------------------------------|
    */
    
    public function prepare_item_for_response($route){
        wp_send_json($this->$route());
    }    
}

$json = file_get_contents('php://input'); 
new UserSessionData(json_decode($json));

?>
