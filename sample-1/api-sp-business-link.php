<?php

/*
|------------------------------|
|==== API My services list ====|
|------------------------------|
*/

include('../wp-load.php');
include("sp-helpers.php");

class CreateBusinessLink extends Sp_Helpers {

	public $wpdb;
	public $prefix;
	public $request;

	public function __construct($request) {

		global $wpdb;
		$this->wpdb = $wpdb;
		$this->prefix = $wpdb->prefix;		
		$this->request = $request;		
		$this->user_id = $this->request->user_id;
		
		if($this->request && $this->request->action && !empty($this->request->action)) {
			$this->prepare_item_for_response($this->request->action);
		}
		else if($this->request->user_id) {
			$this->prepare_item_for_response('ListDetails');
		}
	}

	/*
	|-------------------------------|
	|===== Set Default Service =====|
	|-------------------------------|
	*/	
	public function setDefaultService() {	

		/// Update Service 
        $this->wpdb->update(
        	$this->wpdb->prefix."associates_service_types",[
	        	'isglobal'=> 0
	        ],[
	        	"assoc_id"=> $this->user_id
	        ]
	    );	

        $this->wpdb->update(
        	$this->wpdb->prefix."associates_service_types",[
	        	'isglobal'=> 1
	        ],[
	        	"id"=> $this->request->service_id
	        ]
	    );	

		$response = array(
			'status'=> 1,
			'message'=>'Success.'		
		);	
		return $response;
	}

	/*
	|--------------------------------|
	|===== My Schedule Settings =====|
	|--------------------------------|
	*/	
	public function ListDetails() {		
		// Get service provider details
		$spDetails = spDetails($this->user_id);
		
		// Fetch active services with a limit of 10
		$activeServices = getRegisteredServices($this->user_id, 'object', ['limit' => 20, 'tab' => 'active', 'urlPrefix' => 'tools']);
		$activeServiceCount = ($activeServices && $activeServices[0]->current_services) ? $activeServices[0]->current_services : '0';
		
		// Loop through active services and modify data
		foreach ($activeServices as $service) {
			// Append team member count if the service is a team-based structure
			if ($service->service_structure === "team") {
				// $service->name .= ' (Team of ' . $service->total_members . ')';
				$service->name .= ' (Team)';
			}
			
			$service->service_unique_id = ($service->service_structure=='team') ? "{$service->id}" : $service->id;
			$service->service_unique_ids = ($service->service_structure=='team') ? "{$service->service_type_id}T" : "{$service->service_type_id}I";
			
			// Remove unnecessary fields
			$fieldsToUnset = [
				'service_image', 'rotation_angle', 'profile_position', 'points', 'resume_section', 'service_video_resume',
				 'assoc_id', 'associate_id', 'team_id', 'service_hourly_rate', 'je_service_fees', 'takehome_pay_per_hour',
				'card_fees', 'workmans_comp_ins', 'gross_pay_per_hour', 'fed_fica_taxes', 'created_at', 'current_services'
			];
			foreach ($fieldsToUnset as $field) {
				unset($service->$field);
			}
		}

		// Construct response based on active services availability
		if (!empty($activeServices)) {
			$response = [
				'status' => 1,
				'message' => 'Success.',
				'profilename' => $spDetails->user_profile_id,
				'statecode' => strtolower($spDetails->state),
				'profileurl' => site_url(strtolower($spDetails->state) . '/' . $spDetails->user_profile_id),
				'activeCount' => $activeServiceCount,
				'activeServices' => $activeServices,
			];	
		} else {
			$response = [
				'status' => 0,
				'message' => 'Error! No active services found.',
				'activeCount' => $activeServiceCount,
			];
		}

		return $response;
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
new CreateBusinessLink(json_decode($json));