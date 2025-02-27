<?php
	include_once('db.php');
	
	if($_POST){

		$data	= $_POST;
		$data	= $_REQUEST;
	$sql = "INSERT INTO testing (data)
					VALUES ('".json_encode($data)."')";
					$conn->query($sql);
		if(!empty($data) && isset($data['first_name'])&& isset($data['email_address']) && isset($data['password']) && isset($data['device_token'])){
			$check_email	= "Select * from users where email_address ='".$data['email_address']."'";
			$result 		= $conn->query($check_email);

			if($result->num_rows==0){
				
				$check_device_token	= "Select * from users where device_token ='".$data['device_token']."'";
				$result 			= $conn->query($check_device_token);
				$row1 			= $result->fetch_assoc();
				$user1 = $row1;

				if($result->num_rows==0){
					$data['password'] 	= md5($data['password']);
					$created_date 		= date('Y-m-d h:i:s'); 
					
					$sql = "INSERT INTO users (first_name, last_name, email_address, device_token, password, created_date)
					VALUES ('".$data['first_name']."', '".$data['last_name']."', '".$data['email_address']."', '".$data['device_token']."', '".$data['password']."', '".$created_date."')";

					if ($conn->query($sql) === TRUE) {

						$select_query	= "Select id, first_name, last_name, email_address  from users where email_address ='".$data['email_address']."'";
						$result 		= $conn->query($select_query);
						$row 			= $result->fetch_assoc();
						$user = $row;
						$result=array('status'=>1,'message'=>'Successfully registered.','user_info'=>$user);
					}
					else{
						$result=array('status'=>0,'message'=>'Something went wrong,try again!');
					}
				}else{
					$result = array('status'=>0,'message'=>'Another user already register from this device using this email id: '.substr($user1['email_address'],0,5)."...");
				}	
			}else{
				$result = array('status'=>0,'message'=>'The email you have entered already exist!');
			}
		}
		else{
			$result = array('status'=>0,'message'=>'Data is Empty!');
		}
	}
	else{
		$result = array('status'=>0,'message'=>'Method Mismatch!');
	}
	 echo json_encode($result); die;