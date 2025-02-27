<?php

include('../wp-load.php');
use Plivo\RestClient;
include("Helpers.php");

class HireMe extends Helper_Functions {
	
	public $wpdb; public $table_manager; public $table_client;
	public $table_workOrder; public $table_post; public $table_sp_schedule;		
	public $request; public $colunms; public $clientId; public $providerId;
	public $preferred_sp_id; public $rand_value; public $serviceId;	public $ac_state;
	public $service_start_date;	public $startTime; public $strtostartTime; public $woesthrs;
	public $stopTime; public $strtostopTime; public $searched_day; public $autoWoId; public $WoData;
	public $insertId; public $postId; public $eventId; public $timeZone; public $teamid; public $prefix;
	
	public function __construct($request) {
		
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->prefix = $wpdb->prefix;
		$this->table_manager = $this->prefix.'manager_master';
		$this->table_sp_schedule = $this->prefix.'associates_weekly_schedule';
		$this->table_wo_rec_days = $this->prefix.'associates_wo_rec_days';
		$this->table_client  = $this->prefix.'client_master';		
		$this->table_workOrder = $this->prefix.'work_order';
		$this->table_post = $this->prefix.'posts';
		
		$this->colunms = '';
		$this->rand_value = '';				
		$this->ac_state = '';	
		$this->autoWoId = '';
		$this->WoData = [];
		$this->insertId = 0;
		$this->postId = 0;
		$this->eventId = 0;
		$this->timeZone = '';
		
		$this->request = $request;
		$this->clientId = ($this->request->clientid)? $this->request->clientid : 0;
		$this->providerId = ($this->request->userId)? $this->request->userId : 0;
		$this->preferred_sp_id = ($this->request->preferred_sp_id)? $this->request->preferred_sp_id :"";
		$this->teamid = explode('T',$this->preferred_sp_id);
		$this->serviceId = ($this->request->service_type_id)? $this->request->service_type_id : 0;

		$this->service_start_date = $this->request->woserdate;
		$this->searched_day = date('l',strtotime($this->service_start_date));
		$this->startTime = $this->request->woserstart;
		$this->woesthrs = $this->request->woesthrs;				
		
		$this->stopTime = date("g:i A", strtotime('+'.$this->woesthrs.' hour', strtotime($this->startTime)));
		$this->strtostartTime = strtotime($this->request->woserdate.' '.$this->request->woserstart);
		$this->strtostopTime = strtotime($this->request->woserdate.' '.$this->stopTime);
		
		$this->ac_state = $this->clientProfile()->ac_state;			
		$seltimezone = $this->wpdb->get_row("select timezonecity from je_usa_state where abbreviation='".$this->ac_state."'");
		$this->timeZone = $seltimezone->timezonecity;

		$this->spDetails = managerMaster($this->providerId);
		
		$this->prepare_item_for_response($this->request->action);		
	}


	/*
	|------------------------------------|
	|===== Load Client Profile Data =====|
	|------------------------------------|
	*/		
	public function clientProfile(){
		$this->colunms='je_user_id, client_id, fname, lname, ac_street_address1, ac_state, ac_city, ac_zip';
		$clientProfile = $this->wpdb->get_row("SELECT $this->colunms FROM $this->table_client where je_user_id=".$this->clientId."");			
		return $clientProfile;
	}
	
	/*
	|--------------------------------|
	|===== Load SP Profile Data =====|
	|--------------------------------|
	*/		
	public function spProfile(){
		$this->colunms='*';
		$spProfile = $this->wpdb->get_row("SELECT $this->colunms FROM $this->table_manager where je_users_id=".$this->providerId."");
		return $spProfile;
	}
	
	/*
	|-----------------------------------|
	|===== Load SP Weekly Schedule =====|
	|-----------------------------------|
	*/		
	public function weeklySchedule(){
		$this->colunms = 'schdule.*';
		$schedule =  $this->wpdb->get_row("SELECT $this->colunms FROM $this->table_sp_schedule as schdule LEFT JOIN $this->table_manager as mster ON schdule.associate_id = mster.je_users_id where mster.manager_id = '".$this->spProfile()->manager_id."' and schdule.week_day='$this->searched_day' order by id DESC");
		return $schedule;
	}
	
	
	/*
	|---------------------------------|
	|===== Create New Work Order =====|
	|---------------------------------|
	*/		
	public function randWorkOrderId(){
		
		date_default_timezone_set($this->timeZone);	
			
		$rand_id = $this->wpdb->get_row("select max(wo_random) as autoWoId from $this->table_workOrder where service_state='".$this->ac_state."'");			
		if( !$rand_id ) {
			$this->rand_value = get_random_value("1");	
		}
		else {
			$this->rand_value = get_random_value(intval($rand_id->autoWoId)+1);
		}

		if( $this->weeklySchedule() && $this->weeklySchedule()->frtime2!='00:00:00' && $this->weeklySchedule()->totime2!='00:00:00' && strtotime($this->stopTime) > strtotime($this->weeklySchedule()->totime2) )
		{
			// $this->stopTime = date("g:i A", strtotime($this->weeklySchedule()->totime2));
			// $this->woesthrs = get_time_diffrence($this->service_start_date,$this->weeklySchedule()->frtime2,$this->weeklySchedule()->totime2,false)['hours'];
		}
		else if( $this->weeklySchedule() && $this->weeklySchedule()->frtime2=='00:00:00' && $this->weeklySchedule()->totime2=='00:00:00' && strtotime($this->stopTime) > strtotime($this->weeklySchedule()->totime1) )
		{
			// $this->stopTime = date("g:i A", strtotime($this->weeklySchedule()->totime1));
			// $this->woesthrs = get_time_diffrence($this->service_start_date,$this->weeklySchedule()->frtime1,$this->weeklySchedule()->totime1,false)['hours'];
		}
		
		$this->autoWoId = date('y').$this->ac_state.$this->rand_value;
		return array(
		'autoWoId'=> $this->autoWoId,
		'startTime'=> $this->startTime,
		'stopTime'=> $this->stopTime,
		'woesthrs'=> $this->woesthrs
		);

	}
	
	/*
	|---------------------------------|
	|===== Prepare Json Response =====|
	|---------------------------------|
	*/		
	public function createWorkOrder() {
		$woesthrs = explode(".", $this->woesthrs); 
		if ($woesthrs[1] > 1) {
			$stopTime = date("g:i A", strtotime('+'.$woesthrs[0].' hour +30 minutes', strtotime($this->startTime)));
		} else {
			$stopTime = date("g:i A", strtotime('+'.$woesthrs[0].' hour', strtotime($this->startTime)));
		}
		date_default_timezone_set('UTC');
		$checkService = getServiceDetails(["service_type_id" => $this->serviceId, "assoc_id" => $this->providerId, "service_structure" => (count($this->teamid) >1) ? 'team' : "individual"]);
		$this->wpdb->insert(
			$this->table_workOrder,[
				'wo_id'=> $this->randWorkOrderId()['autoWoId'],
				'client_id'=> $this->clientProfile()->client_id,
				'sp_id' => $this->spProfile()->manager_id,
				'preferred_associate'=> $this->preferred_sp_id,	
				'sp_type'=>  (count($this->teamid) >1) ? 'team' : "individual",				
				'service_hourly_rate'=>  $checkService->service_hourly_rate,				
				'je_service_fees'=>  $checkService->je_service_fees,				
				'takehome_pay_per_hour'=>  $checkService->takehome_pay_per_hour,				
				'service_type_id'=> $this->serviceId,
				'order_date'=> date("Y-m-d H:i:s"),
				'service_zipcode'=> ($this->request->zipcode) ? $this->request->zipcode : $this->clientProfile()->ac_zip,
				'service_date_requested'=> date('Y-m-d',strtotime($this->service_start_date)),
				'service_start_date' => date('Y-m-d H:i:s',strtotime($this->service_start_date.' '.$this->startTime)),
				'no_of_asc'=> 1,
				'est_hrs_needed'=> $this->randWorkOrderId()['woesthrs'],
				'service_start_time'=> date('H:i:s',strtotime($this->startTime)),
				'service_start_tm'=> date('H:i:s',strtotime($this->startTime)),
				'service_end_tm'=> $stopTime,
				'service_addr1'=> $this->clientProfile()->ac_street_address1,
				'service_addr2'=> ($this->clientProfile()->ac_street_address2) ? $this->clientProfile()->ac_street_address2 : '',
				'service_city'=> $this->clientProfile()->ac_city,
				'service_state'=> $this->clientProfile()->ac_state,
				'service_zip'=> ($this->request->zipcode) ? $this->request->zipcode : $this->clientProfile()->ac_zip,
				'wo_status'=>'draft',
				'wo_random'=>$this->rand_value 
			]
		);
		$this->insertId = ($this->wpdb->insert_id) ? $this->wpdb->insert_id : 0;
		
		if( $this->insertId ) {
			
			$this->wpdb->insert(
				$this->prefix.'posts',[	
					'post_author'=> $this->providerId, 
					'post_content'=> "WO($this->autoWoId)", 
					'post_title'=> "Client WO($this->autoWoId)",
					'post_excerpt'=> $this->insertId,
					'post_status'=> 'draft',
					'post_name'=> $this->autoWoId,
					'post_type'=> 'ai1ec_event',
					'guid'=> site_url('event').'/'.$this->autoWoId,
					'post_date' => date('Y-m-d H:i:s') 
				]
			);
			$this->postId = $this->wpdb->insert_id;

			$this->wpdb->insert(
				$this->prefix.'ai1ec_events',[
					'post_id'=> $this->postId,
					'start'=> $this->strtostartTime,
					'end'=> $this->strtostopTime,
					'timezone_name'=> $this->timeZone,
					'ical_uid'=> "ai1ec-".$this->postId."@www.service-outlet.com.",
					'show_coordinates'=> 0,
					'allday'=> 0,
					'allday'=> 0
				]
			);
			
			$this->wpdb->insert(
				$this->prefix.'ai1ec_event_instances',[
					'post_id'=> $this->postId,
					'start'=> $this->strtostartTime,
					'end'=> $this->strtostopTime
				]
			);
			$this->instance_id =  $this->wpdb->insert_id;
			
			$this->wpdb->insert(
				$this->prefix.'assoc_book_day',[
					'assoc_id'=> $this->spProfile()->manager_id,
					'wo_id'=> $this->autoWoId,
					'event_day'=> date('Y-m-d',strtotime($this->service_start_date)),
					'event_from'=> date('H:i:s',strtotime($this->startTime)),
					'event_to'=> $stopTime,
					'type' => 'reg-wo',
					'teamid' => (count($this->teamid) >1) ? $this->preferred_sp_id :"",
					'status' => '1'
				]
			);
			
			$this->wpdb->insert(
				$this->prefix.'associates_wo',[ 
					'wo_id'=> $this->autoWoId,
					'associate_id'=> $this->spProfile()->manager_id,
					'service_start_tm'=> date('H:i:s',strtotime($this->startTime)),
					'service_end_tm'=> $stopTime,
					'wo_type'=> 'regular',
					'wo_status'=> 'accepted',
					'instance_id'=> $this->instance_id,
					'teamid'=> (count($this->teamid) >1) ? $this->preferred_sp_id :""
				]
			);

			$this->wpdb->insert(
				$this->table_wo_rec_days,[ 
					'wo_id'=> $this->autoWoId,
					'associate_id'=> $this->spProfile()->manager_id,
					'service_start_date' => date('Y-m-d',strtotime($this->service_start_date)),
					'service_start_time'=> date('H:i:s',strtotime($this->startTime)),
					'est_hrs_needed'=> $this->woesthrs,
					'service_from'=> date('Y-m-d',strtotime($this->service_start_date)),
					'service_to'=> date('Y-m-d',strtotime($this->service_start_date)),
					'status'=> 'fullfill',
					'assoc_status'=> "accepted"
				]
			);				
		}
		

		
		return array(
		'autoWoId'=> $this->autoWoId,
		'insert_id'=> ($this->insertId) ? $this->insertId :0,
		'service_addr1'=> $this->clientProfile()->ac_street_address1,
		'service_addr2'=> ($this->clientProfile()->ac_street_address2) ? $this->clientProfile()->ac_street_address2 : '',
		'service_city'=> $this->clientProfile()->ac_city,
		'service_state'=> $this->clientProfile()->ac_state,
		'service_zip'=> $this->request->zipcode,
		'payment_method'=> ''
		);			
	} 
	
	/*
	|------------------------|
	|===== Wo Next Step =====|
	|------------------------|
	*/
	public function woNextStep() {
		$payment_method_default = clientStripeCardsApi($this->clientId,'ARRAY_A', true);
		if($payment_method_default) {
			$payment_method_default->card_number = md5_decrypt($payment_method_default->card_number, md5Decrypt, 16);
		} else {
			$payment_method_default = new stdClass();
			$payment_method_default->id ="";
			$payment_method_default->client_id ="";
			$payment_method_default->card_name ="";
			$payment_method_default->card_type ="";
			$payment_method_default->card_number ="";
			$payment_method_default->exp_date ="";
			$payment_method_default->cvv ="";
			$payment_method_default->is_default ="";
		}
		
		$payment_method_others = clientStripeCardsApi($this->clientId,'ARRAY_A');
		$payment_others = [];
		if($payment_method_others) {
			foreach($payment_method_others as $details_others) {
				$details_others['card_number'] = md5_decrypt($details_others['card_number'], md5Decrypt, 16);
				$payment_others[] = $details_others;
			}
		}
		else {
			$others = new stdClass();
			$others->id ="";
			$others->client_id ="";
			$others->card_name ="";
			$others->card_type ="";
			$others->card_number ="";
			$others->exp_date ="";
			$others->cvv ="";
			$others->is_default ="";
			$payment_others[] = $others;
		}

		$client_photo_required = $this->spDetails->client_photo_required;
		$boolean_photo = filter_var($client_photo_required, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		$integer_photo = (int)$boolean_photo;

		$job_description_required = $this->spDetails->job_description_required;
		$boolean_desc = filter_var($job_description_required, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		$integer_desc = (int)$boolean_desc;

		$profilePhoto = ClientProfileSettings($this->clientProfile()->client_id);
		if (!empty($profilePhoto->doc_image)) {
			$isProfilePhoto = true;
		} else {
			$isProfilePhoto = false;
		}
		
		$this->WoData = $this->createWorkOrder();	
		if($this->WoData['insert_id'] > 0) {
			$response = [
				'success'=> 1,
				'message'=> "Work Order created successfully.",
				'data'=> []
			];
			$response['data'] =  [
					'insert_id'=> $this->WoData['insert_id'],
					'wo_id'=> $this->WoData['autoWoId'],
					'service_address'=> $this->WoData['service_addr1'] ? $this->WoData['service_addr1'] : "", 
					'service_address2'=> $this->WoData['service_addr2'] ? $this->WoData['service_addr2'] : "",
					'service_city'=> $this->WoData['service_city'] ? $this->WoData['service_city'] : "", 
					'service_state'=>$this->WoData['service_state'], 
					'service_zipcode'=>($this->WoData['service_zip']) ? $this->WoData['service_zip'] :''					
			];
			$response['data']['isProfilePhoto'] = $isProfilePhoto;
			$response['data']['client_photo_required'] = $boolean_photo;
			$response['data']['job_description_required'] = $boolean_desc;	
			$response['data']['payment_method_default'] = $payment_method_default;
			$response['data']['payment_method_others'] = $payment_others;
			$response['data']['value'] = listAllStates();
			
			
		}
		else {
			$response = [
				'success'=> 0,
				'message'=> "Something went wrong, please try again later!.",
				'insert_id'=> 0,
				'wo_id'=> ''
			];
		}


		return $response;
	}
	
	/*
	|------------------------------|
	|===== Confirm Work Order =====|
	|------------------------------|
	*/		
	public function confirmWorkOrder() {
		global $defaultSpPhoto;
		date_default_timezone_set('UTC');

		$workId = $this->request->woId;
		$service_project_description = ($this->request->description!='') ? $this->request->description :"";

		 $clidetails = clientMaster($this->request->clientid);
		    if(!$clidetails) {
		        $response = array(
		            'status'=> 0,
		            'message'=> 'Bad request method!'
		        );
		        wp_send_json($response);                    
		    }

		    //// Function location 'e-zhire/includes/helper-functions.php'
		    $workOrder = getWorkOrderDetails($this->request->woId);

		    if(!$workOrder || $workOrder->wo_status!='draft') {
		        $this->wpdb->delete(
		            $this->wpdb->prefix."work_order_tempdata",[
		                "wo_id"=> $this->request->woId
		            ]
		        );            
		        $response = array(
		            'status'=> 0,
		            'title'=> 'This work order has already been submitted!',
		            'message'=> 'Bad request method!',
		            'workOrderId' => $workId
		        );
		        wp_send_json($response);            
		    }

		try 
		{
			
		    $workOrderId = $workOrder->id;
		    $autoWoId = $workOrder->wo_id;
		    $modified_start_date_time = $workOrder->service_date_requested.' '.$workOrder->service_start_tm; 
		    $modified_end_date_time = $workOrder->service_date_requested.' '.$workOrder->service_end_tm;

		    //// SP Profile Details
		    $preferred_sp = explode('T', $workOrder->preferred_associate ?? '');
		    $type_sp = $workOrder->sp_type; 
		    if($workOrder->sp_id) {
		        $spDetails = managerMaster(null, $workOrder->sp_id);
		    }
		    else {
		        $spDetails = managerMaster(null, explode('T', $preferred_sp[0] ?? '')[0]);
		    }     

		    $team_members = [];
		    if($type_sp =='team') {
		        $team_members = TeamDetails($workOrder->preferred_associate);
		    }

		    //// Update WO Status      
		    $this->wpdb->update(
		        $this->wpdb->prefix."work_order",[
		            "wo_status" => 'accepted',		   
		            'sp_type'=> $type_sp,		     
		            "no_of_asc" => (count($team_members)> 1) ? count($team_members) : 1,
		            'service_start_date'=> date('Y-m-d H:i:s',strtotime($modified_start_date_time)),
		            "service_project_description" => preg_replace("/\\\\/", "",$service_project_description),
		            'service_addr1'=> $this->request->new_address
		        ],[
		            'id'=> $workOrderId
		        ]
		    );
			
			// $userTimeZone = customUserTimeZone($spDetails->city);
			// $data_args['recent'] = array('order' =>'DESC', 'customTimeZone'=> 'UTC', 'userTimeZone'=> $userTimeZone);
			// $UserNotificationsNew = clientNotificationsApi($clidetails->je_user_id, $data_args);
			
			$ClientDeviceToken = $clidetails->device_token;
			if($ClientDeviceToken) {
				$title = 'New Work Order created!';
				$body = 'Your new work order starts at '.date('g:i a',strtotime($workOrder->service_start_tm)).' on '.date('l F j',strtotime($workOrder->service_date_requested)).'. Click here to see the work order.';
				$deviceToken = $ClientDeviceToken;
				$notify_type = 'NewWorkOrder';
				$orderID = $workOrderId;
				$totalCount = '10';
				$client_id = $clidetails->je_user_id;
				$wo_id = $workOrder->wo_id;
				$associate_id = $workOrder->sp_id;
				$notfyType = 'NewWorkOrder';
				$click_action = 'https://happytask.com/push-notification';
				sendPushNotificationss($title, $body, $deviceToken, $notify_type, $orderID, $totalCount, $click_action, $client_id, $wo_id, $associate_id, $notfyType);
			}
			
			$SpDeviceToken = $this->spProfile()->device_token;
			if($SpDeviceToken) {
				$title = 'You’ve been hired!';
				$body = 'Your new work order starts at '.date('g i:a',strtotime($workOrder->service_start_tm)).' on '.date('l F j',strtotime($workOrder->service_date_requested)).'. Click here to see the work order.';
				$deviceToken = $SpDeviceToken;
				$notify_type = 'NewWorkOrder';
				$orderID = $workOrderId;
				$totalCount = '10';
				$client_id = $clidetails->je_user_id;
				$wo_id = $workOrder->wo_id;
				$associate_id = $workOrder->sp_id;
				$notfyType = 'NewWorkOrder';
				$click_action = 'https://happytask.com/push-notification';
				sendPushNotificationss($title, $body, $deviceToken, $notify_type, $orderID, $totalCount, $click_action, $client_id, $wo_id, $associate_id, $notfyType);
			}
			

		    //// Create Calendar Event        
		    $this->wpdb->insert(
		            $this->wpdb->prefix.'available',[
		            "id" => $item->id,
		            "je_user_id" => $spDetails->je_users_id,
		            "title" => "#WO{$autoWoId}",
		            "className" => "work-order",
		            "start" => date('Y-m-d H:i:s',strtotime($modified_start_date_time)),
		            "end" => date('Y-m-d H:i:s',strtotime($modified_end_date_time)),
		            "split_time" => "no",
		            "url" => $workOrderId,
		            "allDay" => "false"
		        ]            
		    );

		    //// Create a chat instance
			$new_chat_id = get_chat_id($this->clientProfile()->client_id, $this->spProfile()->manager_id);	
		    $this->wpdb->insert(
		        $this->wpdb->prefix."wo_messages",[
					'chat_id'=> $new_chat_id,
		            "wo_id" => $workOrder->wo_id,
		            "client_id" => $workOrder->client_id,
		            "associate_id" => $workOrder->sp_id,
		            'comments'=> 'Work Order Created',
		            "message_type" => 'order',
		            "text_type" => 'chat',
		            "sender" => 'client',
		            "receiver" => 'sp',
		            "created_at" => date('Y-m-d H:i:s'),
		            "message_time" => date('Y-m-d H:i:s')            
		        ]
		    );

		    $_SESSION['openModel'] = true;

		    //// Update WO Status for Recurring Work Orders
		    $getSubWorkOrders = getSubWorkOrders($workId);
		    if($getSubWorkOrders) {
		        $this->wpdb->update(
		            $this->wpdb->prefix."work_order",[
		                "wo_status" => 'accepted',
		                'sp_type'=> $type_sp,
		                "no_of_asc" => (count($team_members)> 1) ? count($team_members) : 1,
		                "service_project_description" => $service_project_description
		            ],[
		                'parent_work_id'=> $workOrderId
		            ]
		        ); 
		    }

		    //// Client Notification
		    if($workOrderId  > 0) 
		    {
		        //// Send Notification To SP 
		        unset($emailData);
		        $spDetails = managerMaster(null, $workOrder->sp_id);
		        $emailData['email'] = $clidetails->emailid;
		        $emailData['client_name'] = userInitialName($clidetails->fname, $clidetails->lname);
		        $emailData['hired_name'] = userInitialName($spDetails->fname, $spDetails->lname);
		        $emailData['work_id'] = $workOrderId;
		        $emailData['getwork_dtls'] = $workOrder;
		        $emailData['custom_directory'] = "/email/custom-emails";
		        if($sendMail === true){
		            SendEmailTemplate($emailData, 'client-work-order');   
		        }
				
		        if($type_sp =='team')
		        {
		            //// Save Work Order Data For The Service Provider working with more than One Team Members
		            if(count($team_members) > 0)
		            {
		                foreach($team_members as $member)
		                {
		                    $this->wpdb->insert(
		                        $this->wpdb->prefix."posts",[
		                            "post_author"=> $member->je_users_id,
		                            "post_title"=> "Client WO($autoWoId)",
		                            "post_content"=> "WO($autoWoId)",
		                            "post_name"=> $autoWoId,
		                            "post_type"=> 'ai1ec_event',
		                            "post_excerpt"=> $workOrderId,
		                            "post_status"=> 'publish',
		                            "post_date_gmt"=> date('Y-m-d H:i:s'),
		                            "post_date"=> date('Y-m-d H:i:s'),
		                            "post_modified"=> date('Y-m-d H:i:s'),
		                        ]
		                    );
							
		                    $post_id = $this->wpdb->insert_id;
		                    $icaluid = "ai1ec-".$post_id."@$customWebsiteName.";
		                    $this->wpdb->insert(
		                        $this->wpdb->prefix."ai1ec_events",[
		                            'post_id' => $post_id,
		                            'start' => strtotime($modified_start_date_time),
		                            'end' => strtotime($modified_end_date_time),
		                            'timezone_name' => date_default_timezone_get(),
		                            'ical_uid' => $icaluid,
		                        ]
		                    );
		                    
		                    $this->wpdb->insert(
		                        $this->wpdb->prefix."ai1ec_event_instances",[
		                            "post_id"=> $post_id,
		                            "start"=> strtotime($modified_start_date_time),
		                            "end"=> strtotime($modified_end_date_time)
		                        ]
		                    );
		                    $instanceidreg = $this->wpdb->insert_id;
							
		                    //// Add Wo Instance
		                    $this->wpdb->insert(
		                        $this->wpdb->prefix.'associates_wo',[
		                            'wo_id'=> $autoWoId,
		                            'associate_id'=> $member->manager_id,
		                            'service_start_tm'=> $workOrder->service_start_tm,
		                            'service_end_tm'=> $workOrder->service_start_tm, 
		                            'wo_type'=> 'regular', 
		                            'wo_status'=> 'accepted',
		                            'instance_id'=> $instanceidreg,
		                            'teamid'=> $workOrder->preferred_associate
		                        ]
		                    );
							
		                    //// Add Service Provider Work Order
		                    $this->wpdb->insert(
		                        $this->wpdb->prefix."assoc_book_day",[
		                            "assoc_id"=> $member->manager_id,
		                            "wo_id"=> $autoWoId,
		                            "event_day"=> $workOrder->service_date_requested,
		                            "event_from"=> $workOrder->service_start_tm,
		                            "event_to"=> $workOrder->service_end_tm,
		                            "type"=> 'reg-wo',
		                            "teamid"=> $workOrder->preferred_associate,
		                            "status"=> 1
		                        ]
		                    );
							
		                    //// Add Wo Instance        
		                    $this->wpdb->insert(
		                        $this->wpdb->prefix."associates_wo_rec_days",[
		                            "wo_id"=> $autoWoId,
		                            "associate_id"=> $member->manager_id,
		                            "service_start_date"=> $workOrder->service_date_requested,
		                            "service_start_time"=> $workOrder->service_start_tm,
		                            "est_hrs_needed"=> $workOrder->est_hrs_needed,
		                            "service_from"=> $workOrder->service_date_requested,
		                            "service_to"=> $workOrder->service_date_requested,
		                            "status"=> 'fullfill',
		                            "assoc_status"=> 'accepted'
		                        ]
		                    );
							
		                    if($member->je_users_id != $spDetails->je_users_id ) {
								
		                        //// Send Notification To Team Members
		                        unset($emailData);
		                        $emailData['email'] = $member->emailid;
		                        $emailData['getclietinfo'] = $clidetails;
		                        $emailData['client_name'] = userInitialName($clidetails->fname, $clidetails->lname);
		                        $emailData['hired_name'] = userInitialName($spDetails->fname, $spDetails->lname);
		                        $emailData['dynamicwo'] = $autoWoId;
		                        $emailData['work_id'] = $workOrderId;
		                        $emailData['getwork_dtls'] = $workOrder;
		                        $emailData['custom_directory'] = "/email/custom-emails";
		                        if($sendMail === true) {
		                            SendEmailTemplate($emailData, 'team-work-order');
		                        }
								
		                        //// Set Team Member Notification
		                        $userNotifications['user_id'] = $member->je_users_id;
		                        $userNotifications['data_id'] = $workOrderId;
		                        $userNotifications['notfy_status'] = 0;
		                        $userNotifications['notify_user'] = 'team';
		                        $userNotifications['notfy_type'] = 'order';
		                        /*
		                        $userNotifications['title'] = 'You’ve been hired!';
		                        $userNotifications['message'] = 'Your new work order starts at '.date('g i:a',strtotime($workOrder->service_start_tm)).' on '.date('l F j',strtotime($workOrder->service_date_requested)).'. Click here to see the work order.';
		                        */
		                        $userNotifications['title'] = 'Congrats! Your team has been hired for a service.';
		                        $userNotifications['message'] = "The best part of {$customWebsiteName} is getting hired AND paid to do what you love. Click this notification to view your new Work Order details.";                        
		                        $userNotifications['published_status'] = 'true';
		                        $userNotifications['created_on'] = date('Y-m-d H:i:s'); 
		                        saveNotification($userNotifications);    

		                    }
		                }
		            }

		            //// Send Notification To Team Leader
		            unset($emailData);
		            // $spDetails->emailid = 'test0000000015@gmail.com';
		            $emailData['email'] = $spDetails->emailid;
		            $emailData['dynamicwo'] = $autoWoId;
		            $emailData['workid'] = $workOrderId;
		            $emailData['getclietinfo'] = $clidetails;
		            $emailData['client_name'] = userInitialName($clidetails->fname, $clidetails->lname);
		            $emailData['hired_name'] = userInitialName($spDetails->fname, $spDetails->lname);
		            $emailData['getwork_dtls'] = $workOrder;
		            $emailData['custom_directory'] = "/email/custom-emails";            
		            if($sendMail === true){
		                SendEmailTemplate($emailData, 'sp-work-order');
		            }            
		        } 
		        else 
		        {
		            //// Save Work Order Data For Individual Service Provider
		            $this->wpdb->insert(
		                $this->wpdb->prefix."posts",[
		                    "post_author"=> $spDetails->je_users_id,
		                    "post_title"=> "Client WO($autoWoId)",
		                    "post_content"=> "WO($autoWoId)",
		                    "post_name"=> $autoWoId,
		                    "post_type"=> 'ai1ec_event',
		                    "post_excerpt"=> $workOrderId,
		                    "post_status"=> 'publish',
		                    "post_date_gmt"=> date('Y-m-d H:i:s'),
		                    "post_date"=> date('Y-m-d H:i:s'),
		                    "post_modified"=> date('Y-m-d H:i:s'),
		                ]                
		            );

		            ////            
		            $post_id = $this->wpdb->insert_id;
		            global $siteName;

		            $icaluid = "ai1ec-".$post_id."@$siteName.";
		            $this->wpdb->insert(
		                $this->wpdb->prefix."ai1ec_events",[
		                    'post_id' => $post_id,
		                    'start' => strtotime($modified_start_date_time),
		                    'end' => strtotime($modified_end_date_time),
		                    'timezone_name' => date_default_timezone_get(),
		                    'ical_uid' => $icaluid,
		                ]                
		            );  

		            ////            
		            $this->wpdb->insert(
		                $this->wpdb->prefix."ai1ec_event_instances",[
		                    "post_id"=> $post_id,
		                    "start"=> strtotime($modified_start_date_time),
		                    "end"=> strtotime($modified_end_date_time)
		                ]                
		            );                              
		            $instanceidreg = $this->wpdb->insert_id;

		            //// Add Wo Instance        
		            $this->wpdb->insert(
		                $this->wpdb->prefix.'associates_wo',[ 
		                    'wo_id'=> $autoWoId,
		                    'associate_id'=> $spDetails->manager_id,
		                    'service_start_tm'=> $workOrder->service_start_tm,
		                    'service_end_tm'=> $workOrder->service_end_tm, 
		                    'wo_type'=> 'regular', 
		                    'wo_status'=> 'accepted',
		                    'instance_id'=> $instanceidreg,
		                    'teamid'=> $workOrder->preferred_associate
		                ]                
		            );

		            //// Add Service Provider Work Order
		            $this->wpdb->insert(
		                $this->wpdb->prefix."assoc_book_day",[
		                    "assoc_id"=> $spDetails->manager_id,
		                    "wo_id"=> $autoWoId,
		                    "event_day"=> $workOrder->service_date_requested,
		                    "event_from"=> $workOrder->service_start_tm,
		                    "event_to"=> $workOrder->service_end_tm,
		                    "type"=> 'reg-wo',
		                    "teamid"=> $workOrder->preferred_associate,
		                    "status"=> 1
		                ]                
		            );

		            //// Add Wo Instance        
		            $this->wpdb->insert(
		                $this->wpdb->prefix."associates_wo_rec_days",[
		                    "wo_id"=> $autoWoId,
		                    "associate_id"=> $spDetails->manager_id,
		                    "service_start_date"=> $workOrder->service_date_requested,
		                    "service_start_time"=> $workOrder->service_start_tm,
		                    "est_hrs_needed"=> $workOrder->est_hrs_needed,
		                    "service_from"=> $workOrder->service_date_requested,
		                    "service_to"=> $workOrder->service_date_requested,
		                    "status"=> 'fullfill',
		                    "assoc_status"=> 'accepted'
		                ]                
		            );  

		            //// Send Notification To Team Members
		            unset($emailData);
					// $spDetails->emailid = 'test0000000015@gmail.com';
		            $emailData['email'] = $spDetails->emailid;
		            $emailData['dynamicwo'] = $autoWoId;
		            $emailData['workid'] = $workOrderId;
		            $emailData['getclietinfo'] = $clidetails;
		            $emailData['client_name'] = userInitialName($clidetails->fname, $clidetails->lname);
		            $emailData['hired_name'] = userInitialName($spDetails->fname, $spDetails->lname);
		            $emailData['getwork_dtls'] = $workOrder;
		            $emailData['custom_directory'] = "/email/custom-emails";            
		            if($sendMail === true){
		                SendEmailTemplate($emailData, 'sp-work-order');
		            }
		        }
		    }

		    //// Set Client Notification
		    $userNotifications['user_id'] = $clidetails->je_user_id;
		    $userNotifications['data_id'] = $workOrderId;
		    $userNotifications['notfy_status'] = 0;
		    $userNotifications['notify_user'] = 'client';
		    $userNotifications['notfy_type'] = 'order';
		    $userNotifications['wo_status'] = 'in-progress'; 
		    $userNotifications['title'] = 'New Work Order created!';
		    $userNotifications['message'] = 'Your new work order starts at '.date('g:i a',strtotime($workOrder->service_start_tm)).' on '.date('l F j',strtotime($workOrder->service_date_requested)).'. Click here to see the work order.';
		    $userNotifications['published_status'] = 'true';
		    $userNotifications['created_on'] = date('Y-m-d H:i:s');     
		    saveNotification($userNotifications); 

		    //// Set Team Member Notification
		    unset($userNotifications);
		    $userNotifications['user_id'] = $spDetails->je_users_id;
		    $userNotifications['data_id'] = $workOrderId;
		    $userNotifications['notfy_status'] = 0;
		    $userNotifications['notify_user'] = 'sp';
		    $userNotifications['notfy_type'] = 'order';
		    $userNotifications['title'] = 'You’ve been hired!';
		    $userNotifications['message'] = 'Your new work order starts at '.date('g:i a',strtotime($workOrder->service_start_tm)).' on '.date('l F j',strtotime($workOrder->service_date_requested)).'. Click here to see the work order.';
		    $userNotifications['published_status'] = 'true';
		    $userNotifications['created_on'] = date('Y-m-d H:i:s');
		    saveNotification($userNotifications); 

		    // Save Recurring Service Dates If any selected
		    if($selected_dates && count($selected_dates) >0 ){
		        for($i = 0;$i<count($selected_dates); $i++) {
		            $request['user_id'] = $currentUserId;
		            $request['sp_id'] = $workOrder->preferred_associate;
		            $request['state'] = $workOrder->service_state;
		            $request['service_zipcode'] = $workOrder->service_zipcode;
		            $request['wo_date'] = $selected_dates[$i];
		            $request['wo_time'] = $start_time[$i];
		            $request['end_time'] = $end_time[$i];
		            $request['estimated_hours'] = round(abs(strtotime($start_time[$i]) - strtotime($end_time[$i])) / 3600,2);
		            $request['service_type_id'] = $workOrder->service_type_id;
		            $request['job_description'] = $service_project_description;             
		            $recurring_wo = NewWorkOrderConfirmation($workOrderId, (object)$request);   
		        }
		    }

		    $this->wpdb->delete(
		        $this->wpdb->prefix."work_order_tempdata",[
		            "wo_id"=> $workOrderId
		        ]
		    );
			
			

		    // ================ work order details ==================
			$workOrderDetails = getWorkOrderDetails($this->request->woId);	
			if($workOrderDetails) {
				$workOrderDetails->review_day = $workOrderDetails->service_date_requested;
				$workOrderDetails->service_date_requested = date('D, M d', strtotime($workOrderDetails->service_date_requested));
				$workOrderDetails->service_start_tm = date('g:i A', strtotime($workOrderDetails->service_start_tm));
				$workOrderDetails->service_end_tm = date('g:i A', strtotime($workOrderDetails->service_end_tm));
				$workOrderDetails->display_day_time = $workOrderDetails->service_date_requested . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-dot" viewBox="0 0 16 16"><path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>' . $workOrderDetails->service_start_tm . ' - ' . $workOrderDetails->service_end_tm . ' (' . $workOrderDetails->est_hrs_needed . 'h)';
				
				/* custom details Profile Settings */
				//$workOrderDetails->snName = $workOrderDetails->spFname;
				$workOrderDetails->snName = userInitialName($workOrderDetails->spFname, $workOrderDetails->spLname);
				$workOrderDetails->spPhoto = $defaultSpPhoto;
				$workOrderDetails->clientName = userInitialName($workOrderDetails->fname, $workOrderDetails->lname);

				// $workOrderDetails->serviceInDays = serviceAfterDays($workOrderDetails->service_date_requested);
	            $getWorkOrderStatus = getWorkOrderStatus($workOrderDetails->service_start_date, date('Y-m-d H:i:s'));
	            $workOrderDetails->serviceInDays = $getWorkOrderStatus['wo_in_time'];

				$workOrderDetails->serviceDayCustom = $workOrderDetails->service_date_requested;
				$workOrderDetails->servicetimeCustom = $workOrderDetails->service_start_tm . ' - ' . $workOrderDetails->service_end_tm;
				$workOrderDetails->workOrderStatus = $workOrderDetails->wo_status;
				
				if($workOrderDetails->wo_status =='completed') {
					$workOrderDetails->workOrderStatus = 'completed';
				}
				else if($workOrderDetails->wo_status =='canceled') {
					$workOrderDetails->workOrderStatus = 'cancelled';
				}
				else {
					$workOrderDetails->workOrderStatus = $getWorkOrderStatus['wo_status']; 
				}

				$workOrderDetails->teamMembers = [];
				$workOrderDetails->manager_name = "";
				if($workOrderDetails && $workOrderDetails->sp_type =='team'){
					$workOrderDetails->teamMembers = listTeamMembers(null, $workOrderDetails->preferred_associate);
					$workOrderDetails->manager_name .= 'Team of ' . count($workOrderDetails->teamMembers) . ' Service Provider';				
				}	

				$loggedStamps = service_logged_stamps($workOrderDetails);
		        if ($loggedStamps) {
		            $loggedStamps['days'] = $loggedStamps['days'] ?? 0;
		            $loggedStamps['hours'] = $loggedStamps['hours'] ?? 0;
		            $loggedStamps['mins'] = $loggedStamps['mins'] ?? 0;
		            $loggedStamps['secs'] = $loggedStamps['secs'] ?? 0;
		            $workOrderDetails->logged_stamps = $loggedStamps;
		        } else {
		            $workOrderDetails->logged_stamps = [];
		        }
				$workOrderDetails->order_received = date('m/d/y • g:i A', strtotime($workOrderDetails->order_date));
				
				$spPhotoSettings = spProfileSettings($workOrderDetails->sp_id);
				if ($spPhotoSettings && !empty($spPhotoSettings->doc_image)) {
					$workOrderDetails->spPhoto = profile_url($workOrderDetails->je_users_id)['url'] . $spPhotoSettings->doc_image;
				}	
				$clientProfileSettings = ClientProfileSettings($workOrderDetails->client_id);
				$workOrderDetails->clientPhoto = $defaultSpPhoto;
				if ($clientProfileSettings && !empty($clientProfileSettings->doc_image)) {
					$workOrderDetails->clientPhoto = profile_url($workOrderDetails->je_user_id)['url'] . $clientProfileSettings->doc_image;	
				}
			}

			$client = clientDetails($this->request->clientid);
			$client_id  = $client->client_id;

			$upCommingWorkOrders  = listWorkOrders(
				$client_id,[
					'currentUserRole'=> 'client',
					'wo_status'=>'accepted',
					'service_status'=> 'feature_day_services',
					'paginated' => 'true',
					'items_per_page'=> 6,
					'count'=> 'true',
					'tab'=> 'up-comming',
					'ajax_call'=> 'true',
					'page'=> ''			
				]
			);
			//echo"<pre>";print_r($upCommingWorkOrders);echo"<pre>";
			$completedWorkOrders  = listWorkOrders(
				$client_id,[
					'currentUserRole'=> 'client',
					'wo_status'=> 'complete,canceled',
					'paginated' => 'true',
					'items_per_page'=> 6,
					'count'=> 'true',
					'tab'=> 'completed',
					'ajax_call'=> 'true',
					'page'=> ''		
				]
			);

			$total_record_upcoming = $upCommingWorkOrders;
			$total_record_completed = $completedWorkOrders;
			
			// -================ work order details ENDS ==================

			return array(
				'success' => 1,
				'message' => 'Your work order was created successfully!',
				'message2' => 'You should receive an email and text confirmation shortly. You may review work orders from your Client Service Center or by clicking the button below.',
				'available_time'=> contact_timings,
				'call_us'=> contact_number_2,
				'text_us'=> contact_number_3,
				'email_us'=> mailto,
				'total_record_upcoming'=> $total_record_upcoming['num_rows'],
				'total_record_completed'=> $total_record_completed['num_rows'],
				'data'=>$workOrderDetails
			);	
		}
		catch(Throwable $e){
	        $response = array(
	            'success'=> 0,
	            'title'=> 'Error!',
	            'message'=> $e->getMessage(),
	            'workOrderId' => $workId
	        );
	        wp_send_json($response);   			
		}		
	}	


	/*
	|------------------------------|
	|===== Confirm Work Order =====|
	|------------------------------|
	*/		
	public function updateQuery($table,$data,$where) {
		return $this->wpdb->update($table,$data,$where);
	}
	
	/*
	|------------------------------|
	|===== Confirm Work Order =====|
	|------------------------------|
	*/		
	public function sendSms($data) {
		$sms = new RestClient(PlivoId, PlivoSec);
		try	{
			$sms->messages->create(
				''.PlMobileNo.'',
				[''.$data['cell_phone'].''], $data['smsText'], #text
				["url" => "http://foo.com/sms_status/"]
			);
			$response = array('status'=> 1,'msg'=>'Sms sent successfully!');
		}
		catch(Error $ex) {
			$response = array('status'=>0,'msg'=> $data['error_msg']);
		}	
		return $response;			
	}
	
	/*
	|----------------------------------------------|
	|===== Delete Test Work Order & Temp Data =====|
	|----------------------------------------------|
	*/
	public function deleteTestWorkOrder() 
	{			
		$this->WoData = $this->getWorkOrder($this->request->woId);
		$this->manager_id =  explode("T",$this->WoData->preferred_associate);			
		/// Delete WO events
		$posts = $this->wpdb->get_results("select ID from ".$this->prefix."posts where post_excerpt=".$this->WoData->id." order by ID DESC");			
		if($posts)	{				
			foreach($posts as $pst)	{
				$instances = $this->wpdb->get_results("select id from ".$this->prefix."ai1ec_event_instances where post_id=".$pst->ID." order by id DESC");
				foreach($instances as $instance)	{
					$this->wpdb->delete( 
						$this->prefix.'associates_wo',[
							'instance_id' => $instance->id
						] 
					);
				}
				/// Delete WO events
				$this->wpdb->delete( 
					$this->prefix.'ai1ec_event_instances',[
						'post_id' => $pst->ID
					]
				);
				/// Delete WO events
				$this->wpdb->delete( 
					$this->prefix.'ai1ec_events',[
						'post_id' => $pst->ID
					]
				);
			}
		}
		/// Delete WO entries
		$this->wpdb->delete( 
			$this->table_wo_rec_days, [
				'wo_id' => $this->WoData->wo_id,
				'associate_id'=> $this->manager_id[0]
			]
		);
		/// Delete WO entries				
		$this->wpdb->delete( 
			$this->prefix.'assoc_book_day', [
				'assoc_id'=> $this->manager_id[0],
				'wo_id'=> $this->WoData->wo_id
			]
		);
		/// Delete WO entries	
		$this->wpdb->delete( 
			$this->prefix.'assoc_book_day', [
				'teamid'=> $this->WoData->preferred_associate,
				'wo_id'=> $this->WoData->wo_id
			]
		);
		/// Delete WO rec days
		$this->wpdb->delete( 
			$this->prefix.'associates_wo_rec_days', [
				'wo_id'=> $this->WoData->wo_id
			]
		);
		/// Delete WO events
		$instance_id = $this->wpdb->get_results("select instance_id from ".$this->prefix."associates_wo where wo_id='".$this->WoData->wo_id."' and associate_id='".$this->WoData->preferred_associate."'  order by ID ASC");
		if($instance_id) {
			foreach($instance_id as $instance) {
				$this->wpdb->delete( 
					$this->prefix.'ai1ec_event_instances', [
						'post_id' => $pst->instance 
					]
				);	
			}
		}
		/// Delete WO entries
		$this->wpdb->delete( 
			$this->prefix.'associates_wo', [
				'wo_id'=> $this->WoData->wo_id,
				'associate_id'=> $this->manager_id[0]
			]
		);	
		/// Delete WO entries
		$this->wpdb->delete( 
			$this->prefix.'associates_wo', [
				'wo_id'=> $this->WoData->wo_id,
				'teamid'=> $this->WoData->preferred_associate
			]
		);
		/// Delete WO posts			
		$this->wpdb->delete( 
			$this->prefix.'posts', [
				'post_excerpt' => $this->WoData->id
			]
		);
		/// Delete WO temp data
		$this->wpdb->delete( 
			$this->prefix.'work_order_tempdata', [
				'wo_id' => $this->WoData->id
			]
		);
		/// Delete WO Messages
		$this->wpdb->delete( 
			$this->prefix.'wo_messages', [
				'wo_id' => $this->WoData->wo_id,
				'client_id'=> $this->WoData->client_id
			]
		);
		$review_ratings = $this->wpdb->get_results("select id from ".$this->prefix."associates_review_final_questions where workorder='".$this->WoData->wo_id."' order by id DESC");
		if($review_ratings){
			foreach($review_ratings as $item) {
				$this->wpdb->delete( 
					$this->prefix.'user_notifications', [
						'data_id' => $item->id,
						'notfy_type' => 'review'
					]
				);	
			}			
		}
		/// Delete WO messages
		$this->wpdb->delete( 
			$this->prefix.'wo_messages', [
				'wo_id' => $this->WoData->wo_id
			]
		);				
		/// Delete WO messages and ratings
		$this->wpdb->delete( 
			$this->prefix.'associates_review_final_questions', [
				'workorder' => $this->WoData->wo_id
			]
		);	
		/// Delete WO ratings
		$this->wpdb->delete( 
			$this->prefix.'outlet_ratings', [
				'wo_id' => $this->WoData->id
			]
		);
		/// Delete WO notifications
		$this->wpdb->delete( 
			$this->prefix.'user_notifications', [
				'data_id' => $this->WoData->id,
				'notfy_type' => 'order'
			]
		);
		/// Delete WO Messages
		$this->wpdb->delete( 
			$this->prefix.'wo_messages', [
				'wo_id' => $this->WoData->wo_id,
			]
		);
		/// Delete WO Notes
		$this->wpdb->delete(
		    $wpdb->prefix . 'wo_notes',
		    [
		        'wo_id' => $this->WoData->id,
		        'for_future' => NULL
		    ],
		    [
		        '%d',
		        'NULL'
		    ]
		);

		/// Delete Availability
		$this->wpdb->delete( 
			$this->prefix.'available', [
				'url' => $this->request->woId,
				'className' => 'work-order'
			]
		);	

		/// Delete final Work Order Entry
		$this->wpdb->delete( 
			$this->prefix.'work_time_tracker', [
				'work_id' => $this->request->woId
			]
		);	

		/// Delete final Work Order Entry
		$this->wpdb->delete( 
			$this->prefix.'payment_invoices', [
				'wo_id' => $this->WoData->id
			]
		);	

		/// Delete final Work Order Entry
		$this->wpdb->delete( 
			$this->prefix.'work_order', [
				'id' => $this->WoData->id
			]
		);			
		$response =  array('status' => 1, 'message' => "Work order deleted.");
		return $response;
	}	

	
	/*
	|------------------------------|
	|===== Work Order Details =====|
	|------------------------------|
	*/		
	public function workOrderDetails() {
		global $defaultSpPhoto;

		$workOrder = getWorkOrderDetails($this->request->woId);	

		$workOrder->review_day = $workOrder->service_date_requested;
		$workOrder->service_date_requested = date('D, M d', strtotime($workOrder->service_date_requested));
		$workOrder->service_start_tm = date('g:i A', strtotime($workOrder->service_start_tm));
		$workOrder->service_end_tm = date('g:i A', strtotime($workOrder->service_end_tm));
		$workOrder->display_day_time = $workOrder->service_date_requested . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-dot" viewBox="0 0 16 16"><path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>' . $workOrder->service_start_tm . ' - ' . $workOrder->service_end_tm . ' (' . $workOrder->est_hrs_needed . 'h)';
		
		/* custom details Profile Settings */
		//$workOrder->snName = $workOrder->spFname;
		$workOrder->snName = userInitialName($workOrder->spFname, $workOrder->spLname);
		$workOrder->spPhoto = $defaultSpPhoto;
		$workOrder->clientName = userInitialName($workOrder->fname, $workOrder->lname);
		$workOrder->serviceDayCustom = $workOrder->service_date_requested;
		$workOrder->servicetimeCustom = $workOrder->service_start_tm . ' - ' . $workOrder->service_end_tm;
		$workOrder->workOrderStatus = $workOrder->wo_status;

		$workOrder->teamMembers = [];
		$workOrder->manager_name = "";
		if($workOrder && $workOrder->sp_type =='team'){
			$workOrder->teamMembers = listTeamMembers(null, $workOrder->preferred_associate);
			$workOrder->manager_name .= 'Team of ' . count($workOrder->teamMembers) . ' Service Provider';				
		}	

		$loggedStamps = service_logged_stamps($workOrder);
        if ($loggedStamps) {
            $loggedStamps['days'] = $loggedStamps['days'] ?? 0;
            $loggedStamps['hours'] = $loggedStamps['hours'] ?? 0;
            $loggedStamps['mins'] = $loggedStamps['mins'] ?? 0;
            $loggedStamps['secs'] = $loggedStamps['secs'] ?? 0;
            $workOrder->logged_stamps = $loggedStamps;
        } else {
            $workOrder->logged_stamps = [];
        }
		$workOrder->order_received = date('m/d/y • g:i A', strtotime($workOrder->order_date));

		//// SP Profile Settings
		//$workOrder->snName = $workOrder->spFname;
		//$workOrder->spPhoto = $defaultSpPhoto;
		
		$spPhotoSettings = spProfileSettings($workOrder->sp_id);
		if ($spPhotoSettings && !empty($spPhotoSettings->doc_image)) {
			$workOrder->spPhoto = profile_url($workOrder->je_users_id)['url'] . $spPhotoSettings->doc_image;
		}	
		$clientProfileSettings = ClientProfileSettings($workOrder->client_id);
		$workOrder->clientPhoto = $defaultSpPhoto;
		if ($clientProfileSettings && !empty($clientProfileSettings->doc_image)) {
			$workOrder->clientPhoto = profile_url($workOrder->je_user_id)['url'] . $clientProfileSettings->doc_image;	
		}					
		$response = [
			'status'=> 1,
			'message'=> 'Success',
			'available_time'=> contact_timings,
            'call_us'=> contact_number_2,
            'text_us'=> contact_number_3,
            'email_us'=> mailto,
			//'request'=> $this->request,
			'workOrder'=> $workOrder,
			'support'=>[]
		];				
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
new HireMe(json_decode($json));

