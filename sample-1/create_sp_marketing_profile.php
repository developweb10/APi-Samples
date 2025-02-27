<?php

/*
|-----------------------------------|
|==== API Sp Work Order Details ====|
|-----------------------------------|
*/

include('../wp-load.php');
include('Helpers.php');

class SPMarketingProfile extends Helper_Functions 
{

	public $request;
	public $wpdb;
	
	public function __construct($request) {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->request = $request;
		
		/* parent::__construct($request);
		if ($this->get_response()['success'] === 0) {
			return;
		} */
       
        $this->currentUserId = $this->request->currentUserId;
        $this->created_service_id = $this->request->created_service_id;
        $this->serviceStructure = $this->request->serviceStructure;
        $this->service_type_id = $this->request->service_type_id;
        $this->serviceHourlyRate = $this->request->serviceHourlyRate;
        $this->description = $this->request->description;
        $this->existing_time_range = $this->request->existing_time_range;
        $this->specific_time_range = $this->request->specific_time_range;        
        $this->spDetails = managerMaster($this->currentUserId);
		$this->currentUserCustomId = $this->spDetails->manager_id;
        $this->prepare_item_for_response('createService');
	}
	
	/* public function get_response() {
		return $this->response;
	} */

    /*
	|-------------------------------------|
	|==== Create SP Marketing Profile ====|
	|-------------------------------------|
	*/

    public function createService() {    	
        $currentUserId = $this->currentUserId;
        $currentUserCustomId = $this->currentUserCustomId;
        $created_service_id = $this->created_service_id;
        $serviceStructure = strtolower($this->serviceStructure);
        $service_type_id = $this->service_type_id;
        $description =  $this->description;
        $service_hourly_rate = $this->serviceHourlyRate;
        $global_check = false;

        $service_rate_cal = calculateServiceCosts($this->serviceHourlyRate);
        $service_fee = $service_rate_cal['je_service_fees'];
        $finalServiceRate = $service_rate_cal['charge_to_client'];

    	/// Get Stripe Details
		global $StripeMode;
		$spStripe = spStripeDetails($this->spDetails->je_users_id, $StripeMode);
		$stripe_onboarding = [];
		if($spStripe->stripe_account_id) {
			$stripe_onboarding = checkStripeOnboarding($spStripe->stripe_account_id); 
		}
		
        try {
			
			$temDetailsCount = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * 
					FROM {$this->wpdb->prefix}associates_service_types 
					WHERE assoc_id = %d 
					AND service_structure = %s
					AND service_type_id = %d
					ORDER BY id DESC",
					$currentUserId,   // %d for integer
					$serviceStructure, // %s for string
					$service_type_id     // %d for integer
				)
			);
			$teamMember = listSpTeams($currentUserId, null, null, $temDetailsCount->team_id, false);
			$countTeamMember = "";
			foreach ($teamMember as $key => $value) {
				if($value->reg_status == 'active') {
					$countTeamMember++;
				}
			}
			
			if($serviceStructure == 'team') {
				if($countTeamMember >= '2') {
					$service_status = 'active';
				} else {
					$service_status = 'inactive';
				}
			} else if($serviceStructure == 'individual') {
				$service_status = 'active';
			}
			
			/// Update Service if already exists
			if($created_service_id > 0) {
		        $availability_url = "";			
				
		        if ($this->existing_time_range == true) {
		        	
		        	$this->wpdb->query( 
					    $this->wpdb->prepare("UPDATE {$this->wpdb->prefix}associates_weekly_schedule SET services_timeframe1 = CONCAT(services_timeframe1, ',%d') WHERE associate_id = %d",
					        $created_service_id, $currentUserId)
					);

					$this->wpdb->update(
			        	$this->wpdb->prefix."associates_service_types",[
				        	'resume_section'=> strlen($description) == 0 ? null : $description,
				        	'service_status'=> $service_status
				        ],[
				        	"id"=> $created_service_id
				        ]
				    );
		        } 
		        else if ($this->specific_time_range == true) {		        	
		        	$this->wpdb->update(
			        	$this->wpdb->prefix."associates_service_types",[
				        	'resume_section'=> strlen($description) == 0 ? null : $description,
				        	'service_status'=> $service_status
				        ],[
				        	"id"=> $created_service_id
				        ]
				    );
		        } else {
		        	$this->wpdb->update(
			        	$this->wpdb->prefix."associates_service_types",[
				        	'resume_section'=> strlen($description) == 0 ? null : $description,
				        	'service_status'=> $service_status
				        ],[
				        	"id"=> $created_service_id
				        ]
				    );
		        }

				//// Update Service Meta
		       	update_user_meta($this->spDetails->je_users_id, 'setup-add-service', '1');

				$currentProfileSettings = spProfileSettings($this->spDetails->manager_id);
				if($currentProfileSettings && !empty($currentProfileSettings->doc_image)) {
					$profile_photo = profile_url($this->spDetails->je_users_id)['url'].$currentProfileSettings->doc_image;
				}

				// Save settings
				$Onboarding = [
				   "add_profile_picture" => $currentProfileSettings && $currentProfileSettings->doc_image ? true : false,
					"select_a_service" => (countServices($this->spDetails->je_users_id) && $this->spDetails->shirt_size) ? true : false,
					"background_check" => $this->spDetails && !empty($this->currentUserDetails->applicant_invite_url) ? true : false,
					"availability" => countWeeklySchedules($this->spDetails->je_users_id) ? true : false,
					"service_area" => countServiceArea($this->spDetails->je_users_id) ? true : false,
					"payout_setup" => $stripe_onboarding ? true : false,
					"marketing_page" => checkMarketingPage($this->spDetails->je_users_id) ? true : false
				];        			      
				saveOnboarding($this->spDetails->je_users_id, $Onboarding);	

			    // =============== weekly schedule services ends here ===============			    
			    $checkService = $this->wpdb->get_row("select *, count(*) as total_services from ".$this->wpdb->prefix."associates_service_types where id=$created_service_id order by id desc");

				$response = array(
					'status'=> 1,
					'message'=>'Success.',
					'team_id'=> $checkService->team_id,
					'service_id'=> $created_service_id		
				);
				return $response;							
			}


			/// Create New Service if not exists
			$service_structure = ($serviceStructure) ? $serviceStructure : "Individual";
			$takehome_pay_per_hour = ($takehome_pay_per_hour) ? $takehome_pay_per_hour : $service_hourly_rate;

			$checkService = $this->wpdb->get_row("select *, count(*) as total_services from ".$this->wpdb->prefix."associates_service_types where assoc_id=$currentUserId  and service_structure='$service_structure' order by id desc");
			if($checkService && $checkService->total_services >= 10) {
				$response = array(
					'status'=> 0,
					'message'=> "You can not add more than 10 Services to your ".ucfirst($service_structure)." Service Structure.",				
				);	
				return $response;
			}

			$lowest_service_amount = $this->wpdb->get_row("select * from ".$this->wpdb->prefix."options where option_name='lowest_service_amount'");
	        if ($lowest_service_amount && $lowest_service_amount->option_value > 0) {        	
	            if ($finalServiceRate < $lowest_service_amount->option_value) {                                
	                $response = array(
						'status'=> 0,
						'message'=> "Your hourly rate must be higher than $".$lowest_service_amount->option_value.".",				
					);	
					return $response;
				}
	        }

			$checkServiceAvailable = checkIfServiceExists(["service_type_id" => $this->service_type_id, "assoc_id" => $this->currentUserId, "service_structure" => $this->serviceStructure],);
			if($checkServiceAvailable) {
				$response = array(
					'status'=> 0,
					'message'=>'This service already exist.'
				);
				wp_send_json($response);
			}         		     
			
			/// Create New Service
			$spRandomTeamId = SpRandomTeamId($this->spDetails);
			$team_id = $spRandomTeamId['max_value'];
			$next_random_id = $spRandomTeamId['next_random_id'];
			$service_status = (strlen($description) >= 100) ? 'active' : 'inactive';
			if($service_structure == 'team') {
				$service_status = 'inactive';
			}
			
	        $this->wpdb->insert(
	        	$this->wpdb->prefix."associates_service_types",[
	        		"assoc_id"=> $this->spDetails->je_users_id,
		        	"associate_id"=> $this->spDetails->manager_id,
		        	"team_id"=> $team_id,
		        	"service_type_id"=> $service_type_id,
		        	"service_structure"=> $service_structure,
		        	'resume_section'=> strlen($description) == 0 ? null : $description,
		        	"service_hourly_rate"=> $finalServiceRate,
		        	"card_fees"=> 0,
		        	"je_service_fees"=> $service_fee,
		        	"takehome_pay_per_hour"=> $takehome_pay_per_hour,	
		        	'service_status'=> $service_status,	        	
		        	"created_at"=> date('Y-m-d H:i:s')
		        ]
		    );

		    $current_service_id = $this->wpdb->insert_id;

			$this->wpdb->insert(
				$this->wpdb->prefix."team_master",[
			    	"team_id"=> $team_id,
			    	"service_type_id"=> $service_type_id,
			    	"team_leader_id"=> $this->spDetails->je_users_id,
			    	"parent_service_id"=> $current_service_id,
			    	"service_structure"=> $service_structure,		    	
			    	"je_random_id"=> $next_random_id,
			    	"created_at"=> date('Y-m-d H:i:s')
			    ]
			);
			$current_service_id = $this->wpdb->insert_id;

			if($service_structure =='team') {		        
		        $this->wpdb->insert(
                    $this->wpdb->prefix.'team_details',[
			        	"team_id"=> $team_id,
			        	"team_member_id"=> $this->spDetails->je_users_id,
			        	"user_type"=> 'sp',
			        	"created_at"=> date('Y-m-d H:i:s')
			        ]
			    );              

		        $this->wpdb->insert(
                    $this->wpdb->prefix.'team_member_rate',[
			        	"team_leader_id"=> $this->spDetails->je_users_id,
			        	"team_member_id"=> $this->spDetails->je_users_id,
			        	'serviceid'=> $service_type_id,
			        	'rate'=> $service_hourly_rate,
			        	'inviteRowId'=> $this->wpdb->insert_id,
			        	"user_type"=> 'sp',
			        	"status"=> 'active',
			        	"created_at"=> date('Y-m-d H:i:s')
			        ]
			    );				    		            			
			}

	        $this->wpdb->update(
                $this->wpdb->prefix.'associates_library',[
		        	"service_id"=> $current_service_id
		        ],[
					"assoc_auto_id"=> $this->spDetails->manager_id,
					"service_id"=> $service_type_id
		        ]
		    );	

	        $this->wpdb->update(
                $this->wpdb->prefix.'service_suggested_booking',[
		        	"service_id"=> $current_service_id
		        ],[
					"user_id"=> $this->spDetails->je_users_id,
					"service_id"=> $service_type_id
		        ]
		    );

			//// Update Service Meta
	       	update_user_meta($this->spDetails->je_users_id, 'setup-add-service', '1');

			$currentProfileSettings = spProfileSettings($this->spDetails->manager_id);
			if($currentProfileSettings && !empty($currentProfileSettings->doc_image)) {
				$profile_photo = profile_url($this->spDetails->je_users_id)['url'].$currentProfileSettings->doc_image;
			}

			// Save settings
			$Onboarding = [
			    "add_profile_picture" => $currentProfileSettings && $currentProfileSettings->doc_image ? true : false,
			    "select_a_service" => (countServices($this->spDetails->je_users_id) && $this->spDetails->shirt_size) ? true : false,
			    "background_check" => $this->spDetails && !empty($this->currentUserDetails->applicant_invite_url) ? true : false,
			    "availability" => countWeeklySchedules($this->spDetails->je_users_id) ? true : false,
			    "service_area" => countServiceArea($this->spDetails->je_users_id) ? true : false,
				"payout_setup" => $stripe_onboarding ? true : false,
			    "marketing_page" => checkMarketingPage($this->spDetails->je_users_id) ? true : false
			];        			      
			saveOnboarding($this->spDetails->je_users_id, $Onboarding);
			
			$response = array(
				'status'=> 1,
				'message'=>'Success.',
				'service_id'=> $current_service_id,
				'serviceHourlyRate'=> $takehome_pay_per_hour,
				'je_service_fees'=> $service_fee,
				'charge_to_client'=> $finalServiceRate			
			);

			// Save Stripe Card Details If Payment Intent Id Exists
			if($this->request->paymentIntentId && !empty($this->request->paymentIntentId)) {
				$curl = curl_init();
				curl_setopt_array($curl, array(
				  CURLOPT_URL => 'https://happytask.com/api/api-sp-bg-payment.php',
				  CURLOPT_RETURNTRANSFER => true,
				  CURLOPT_ENCODING => '',
				  CURLOPT_MAXREDIRS => 10,
				  CURLOPT_TIMEOUT => 0,
				  CURLOPT_FOLLOWLOCATION => true,
				  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				  CURLOPT_CUSTOMREQUEST => 'POST',
				  CURLOPT_POSTFIELDS =>'{
				    "action":"retrievePaymentMethod",    
				    "je_users_id": '.$this->spDetails->je_users_id.',
				    "paymentIntentId":"'.$this->request->paymentIntentId.'"
				}',
				  CURLOPT_HTTPHEADER => array(
				    'Content-Type: application/json',
				    'Cookie: PHPSESSID=0a838362a28d167694950df2fc297814'
				  ),
				));

				$result = curl_exec($curl);
				curl_close($curl);
				//echo $result;
			}

		}
		catch(Throwable $e){
			$response = array(
				'status'=> 0,
				'message'=> $e->getMessage(),
			);					
		}        
        return $response;
    }
	public function prepare_item_for_response($route) {
		wp_send_json($this->$route());
	}
}
$json = file_get_contents('php://input');
new SPMarketingProfile(json_decode($json));