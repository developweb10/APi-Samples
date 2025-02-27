<?php

/*
|------------------------------|
|==== API My services list ====|
|------------------------------|
*/

include('../wp-load.php');
include("sp-helpers.php");

class SendInviteToTeamApi extends Sp_Helpers {

	public $wpdb;
	public $prefix;
	public $request;

	public function __construct($request) {

		global $wpdb;
		$this->wpdb = $wpdb;	
		$this->request = $request;
		
		$this->user_id = $this->request->user_id;
		$this->fname = $this->request->fname;
		$this->lname = $this->request->lname;
		$this->email = $this->request->email;
		$this->serviceTypeId = $this->request->serviceTypeId;
		
		$this->sp = $this->managerDetails($this->user_id);
		
		if($this->request->user_id && $this->request->email) {
			$this->prepare_item_for_response('InvitationSendData');
		}else {
			$this->prepare_item_for_response('badMethod');
		}
	}

	/*
	|--------------------------------|
	|===== My Schedule Settings =====|
	|--------------------------------|
	*/	
	public function InvitationSendData() {
		$currentUserId = $this->user_id;
		$fname = $this->fname;
		$lname = $this->lname;
		$email = $this->email;
		$serviceId = $this->serviceTypeId;
		
		$service = getServiceDetails(['assoc_id'=> $currentUserId, 'service_type_id'=> $serviceId, 'service_structure'=> 'team']);
		$service_text = $service->name;
		$service_id = $service->id;
			
		$checkEmail = $this->wpdb->get_row("select * from {$this->wpdb->prefix}users where user_email='$email'");
		if(!$checkEmail) {

			$token = openssl_random_pseudo_bytes(16);
			$token = bin2hex($token);	

			$invitation = $this->wpdb->get_row("select * from {$this->wpdb->prefix}member_invitation where team_leader_id = $currentUserId and service_id = $service_id and email='$email'");
			
			if(!$invitation) {	

				$this->wpdb->insert(
					$this->wpdb->prefix."member_invitation",[
						'team_leader_id'=> $currentUserId,
						'service_id'=> $serviceId ? $service_id : "",
						'service_name' => $serviceId ? $service_text : "none",
						'category_id'=> $serviceId ? $serviceId : NULL,
						'fname'=> $fname,
						'lname'=> $lname,
						'email'=> $email,
						'invitation_id'=> $token,
						'created_on'=> date('Y-m-d H:i:s')
					]
				);
			}
			else {
				$this->wpdb->update(
					$this->wpdb->prefix."member_invitation",[
						'invitation_id'=> $token,
						'revoked'=> 0 
					],[
						'team_leader_id'=> $currentUserId,
						'email'=> $email,
					]					
				);				
			}

			$invitation_url = site_url("/team-member-setup/?token={$token}");
			$manager = $this->sp;
			$managerName = ucwords($manager->fname.' '.substr($manager->lname,0,1));			
			$displayname = ucwords($fname.' '.substr($lname,0,1));
            unset($emailData);
            $emailData['managerName'] = $managerName;
            $emailData['email'] = $email;
            $emailData['displayname'] = $displayname;
            $emailData['invitation_url'] = $invitation_url;

            try {
            	SendEmailTemplate($emailData, 'invitation-reminder'); 
				$response = array(
					'status'=> 1,
					'invitation_url'=> $invitation_url,
					'message'=>'Invitation sent successfully.'
				);
            }
            catch(Exception $e) {
				$response = array(
					'status'=> 0,
					'message'=> $e->getMessage()
				);            	
            }	
		}
		else {

			$response = array(
				'status'=> 0,
				'message'=>'The email address is already in use, please try another email address.'
			);			
		}

		return $response;
	}

//// Bad Method	
	public function badMethod(){
		return array (
		'status' => 0, 
		'message' => "Bad request method!",
		'req' => $this->request
		);	
	}
	/*
	|---------------------------------|
	|===== Prepare Json Response =====|
	|---------------------------------|
	*/	
	public function prepare_item_for_response($route){
		wp_send_json($this->$route());
	}

}

$json = file_get_contents('php://input');
new SendInviteToTeamApi(json_decode($json));