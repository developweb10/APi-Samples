<?php

include('../wp-load.php');  
include("Helpers.php");

class GlobalData extends Helper_Functions {
    
    public $wpdb;
    public $prefix;
    public $request;
    
    public function __construct($request) {
        
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
        $this->request = $request;
        
        if ($this->request && isset($this->request->action)) {
            $this->action = $this->request->action;
        } else {
            $this->action = 'clientLogin';
        }
        
        $this->prepare_item_for_response($this->action);    
    }

    /*
    |-----------------------------------|
    |===== Load Hear About Us Data =====|
    |-----------------------------------|
    */
    
    public function clientLogin() {
        try {
            $login_data = [];
            $login_data['user_login'] = $this->request->username;
            $login_data['user_password'] = $this->request->password;

            // Login user using wp_signon
            $user_verify = wp_signon($login_data, false);
            
            if (is_wp_error($user_verify)) {
                return $this->generate_error_response('Invalid login credentials.');
            }
            
            $clientDetails = $this->clientDetails($user_verify->ID);
            
            if (!$clientDetails) {
                return $this->generate_error_response('Invalid login credentials.');
            }

            $data = [
                'u' => $user_verify->ID,
                'role' => 'client'
            ];
            $base64Data = base64_encode(json_encode($data));
            $currentProfileSettings = ClientProfileSettings($clientDetails->client_id);

            $timeZoneCity = customUserTimeZone($clientDetails->ac_state);
            $clientDetails->profile_webview = site_url("adjust-profile-photo-web-view-app/?u={$base64Data}&t=mob");

            $profile_photo = profile_url($clientDetails->je_user_id)['url'];
            $profile_photo .= $currentProfileSettings->croped_image ? $currentProfileSettings->croped_image : $currentProfileSettings->doc_image;
            $clientDetails->profile_photo = $profile_photo;
            
            if ($user_verify && $user_verify->ID) {
                // Update device token
                $updateData = ['device_token' => $this->request->device_token];
                $this->UpdateQuery($this->prefix . 'client_master', $updateData, ['je_user_id' => $user_verify->ID]);

                // Generate session ID
                $session_id = $this->generate_and_store_session_id($user_verify->ID);
                $table_name = $this->wpdb->prefix . 'user_sessions';
				$deviceID = $this->request->device_id;
				
				if($deviceID && $user_verify->ID) {
					$existDeviceID = $this->wpdb->get_row($this->wpdb->prepare(
						"SELECT * FROM $table_name WHERE user_ids = %d AND device_id = %s",
						$user_verify->ID,
						$deviceID
					));
					
					if($existDeviceID) {
						$this->wpdb->delete(
							$table_name,
							array('user_ids' => $existDeviceID->user_ids, 'device_id' => $deviceID)
						);
					}
				}
				
                // Check and manage sessions
                $existing_sessions = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT * FROM $table_name WHERE user_ids = %d",
                    $user_verify->ID
                ));
				
                // If the user already has 3 sessions, log out the earliest session
                if (count($existing_sessions) >= 3) {
                    // Get the oldest session
                    $oldest_session = $this->wpdb->get_row($this->wpdb->prepare(
                        "SELECT * FROM $table_name WHERE user_ids = %d ORDER BY login_time ASC LIMIT 1",
                        $user_verify->ID
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
                        'user_ids' => $user_verify->ID,
                        'session_token' => $session_id,
                        'device_id' => $deviceID,
                        'login_time' => current_time('mysql')
                    )
                );
                
                // Prepare the success response
                $jsonResponse = [
                    'success' => 1,
                    'message' => 'Success',
                    'data' => [
                        'userId' => $user_verify->ID,
                        'session_id' => $session_id,
                        'device_id' => $this->request->device_id,
                        'username' => $this->clientDetails($user_verify->ID)->fname . ' ' . $this->clientDetails($user_verify->ID)->lname,
                        'niceName' => $this->clientDetails($user_verify->ID)->fname . ' ' . substr($this->clientDetails($user_verify->ID)->lname, 0, 1),
                        'userEmail' => $user_verify->user_email,
                        'userData' => $clientDetails,
                        'timeZoneCity' => $timeZoneCity
                    ]
                ];
            } else {
                $jsonResponse = $this->generate_error_response('Invalid login credentials.');
            }
        } catch (Throwable $e) {
            $jsonResponse = [
                'success' => 0,
                'message' => 'Please check your credentials and try again.',
                'data' => new stdClass()
            ];
        }
        
        return $jsonResponse;            
    }
    
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
    |------------------------|
    |===== Update Query =====|
    |------------------------|
    */        
    public function UpdateQuery($table, $data, $where) {
        return $this->wpdb->update($table, $data, $where);
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
            
            $table_name = $this->wpdb->prefix . 'user_sessions';
            
            $this->wpdb->delete(
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
    |---------------------------------|
    |===== Prepare Json Response =====|
    |---------------------------------|
    */        
    public function prepare_item_for_response($route) {
        wp_send_json($this->$route());
    }

    /*
    |--------------------------------------|
    |===== Helper Method for Error =====|
    |--------------------------------------|
    */
    
    private function generate_error_response($message) {
        return [
            'success' => 0,
            'message' => $message,
            'data' => new stdClass()
        ];
    }
}

$json = file_get_contents('php://input'); 
new GlobalData(json_decode($json));