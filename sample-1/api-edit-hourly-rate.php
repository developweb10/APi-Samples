<?php

/*
|------------------------------|
|==== API Edit hourly rate ====|
|------------------------------|
*/

include('../wp-load.php');
include("sp-helpers.php");

class EditHourlyRateApi extends Sp_Helpers {

	public $wpdb;
	public $prefix;
	public $request;

	public function __construct($request) {

		global $wpdb;
		$this->wpdb = $wpdb;	
		$this->request = $request;
		
		$this->user_id = $this->request->user_id;
		$this->service_id = $this->request->service_id;
		$this->hourrate = $this->request->hourrate;
		
		$this->sp = $this->managerDetails($this->user_id);
		
		if($this->request->user_id && $this->request->service_id && $this->request->hourrate) {
			$this->prepare_item_for_response('setNewRate');
		}else {
			$this->prepare_item_for_response('badMethod');
		}
	}

	/*
	|-------------------------|
	|===== Data Settings =====|
	|-------------------------|
	*/	
	public function setNewRate() {
		$currentUserId = $this->user_id;
		$service_id = $this->service_id;
		$hourrate = $this->hourrate;
		
		$lowest_service_amount = $this->wpdb->get_row("select option_value from {$this->wpdb->prefix}options where option_name='lowest_service_amount'");
		if(!is_numeric($hourrate)) {	
			$response = array(
				'status'=> 0,
				'message'=>'Please enter numeric values!'
			);
			return $response;			
		}
		else if($lowest_service_amount && $lowest_service_amount->option_value >0) {
			if($hourrate < $lowest_service_amount->option_value) {
				$response =  array(
					'status'=> 0,
					'message'=> 'Your hourly rate must be higher than $'.$lowest_service_amount->option_value.''
				);
				return $response;
			}
		}
		
		$service = spGetService($currentUserId, $service_id);
		if($service) {
			/// Deduct Previous Service Rate
			if($service->takehome_pay_per_hour > 0) {
				$service_rate = calculateServiceCosts($service->takehome_pay_per_hour);	
				$hourly_Rate = ($service->service_hourly_rate) - ($service_rate['charge_to_client']);
				$admin_fees = ($service->je_service_fees) - ($service_rate['je_service_fees']);	
			}

			/// Calculate and Add New Service Rate
			$service_rate = calculateServiceCosts($hourrate);
			$hourly_Rate = ($hourly_Rate) + ($service_rate['charge_to_client']);
			$admin_fees = ($admin_fees) + ($service_rate['je_service_fees']);
			
	    	$this->wpdb->update(
				$this->wpdb->prefix.'associates_service_types', [
					'service_hourly_rate'=> $hourly_Rate,
					'takehome_pay_per_hour'=> $hourrate,
					'je_service_fees'=> $admin_fees,			
				],[
					"assoc_id"=> $currentUserId,
					"id"=> $service_id,
				]
			);	
			$this->wpdb->update(
				$this->wpdb->prefix.'team_member_rate', [
					'rate'=> $hourrate			
				],[
					"team_member_id"=> $currentUserId,
					"serviceid"=> $service->service_type_id,
				]
			);
			$response = array(
				'status'=> 1,
				'message'=>'Success.',
				'hourly_Rate'=> $hourly_Rate,
				'admin_fees'=> $admin_fees
			);				
		} else {
			$response = array(
				'status'=> 0,
				'message'=>'Error!'
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
new EditHourlyRateApi(json_decode($json));