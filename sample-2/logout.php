<?php
	include_once('db.php');

	$data	= $_REQUEST;
	$sql = "INSERT INTO testing (data)
					VALUES ('".json_encode($data)."')";

	$conn->query($sql);
	if(!empty($data) && isset($data['email_address']) && isset($data['device_token'])){
		
		
		$check_email	= "Select * from users where email_address ='".$data['email_address']."'";
		$result 		= $conn->query($check_email);

		if($result->num_rows==1){
						
			$row = $result->fetch_assoc();
			$sql = "UPDATE users SET login_status ='0' where id =".$row['id'];

			if ($conn->query($sql) === TRUE) {
				
				$result=array('status'=>1,'message'=>'Successfully logout.');
			}
			else{
				$result=array('status'=>0,'message'=>'Something went wrong,try again!');
			}
		}else{
			$result = array('status'=>0,'message'=>'User does not exist.');
		}
	}
	else{
		$result = array('status'=>0,'message'=>'Data is Empty!');
	}
	
	echo json_encode($result); die;