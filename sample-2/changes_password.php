<?php
	include_once('db.php');

	if($_POST){

		$data	= $_POST;
		if(!empty($data) && isset($data['email_address']) && isset($data['password']) && isset($data['new_password'])){
			
			$check_email	= "Select * from users where email_address ='".$data['email_address']."'";
			$result 		= $conn->query($check_email);

			if($result->num_rows==1){
				
				$check_email	= "Select * from users where email_address ='".$data['email_address']."' and password ='".md5($data['password'])."'";
				$result 		= $conn->query($check_email);

				if($result->num_rows==1){
					$row = $result->fetch_assoc();
					if($row['status']=='1'){

						
						$sql = "UPDATE users SET password = '".md5($data['new_password'])."' where id =".$row['id'];

						if ($conn->query($sql) === TRUE) {

							$select_query	= "Select id, first_name, last_name, email_address  from users where email_address ='".$data['email_address']."'";
							$result 		= $conn->query($select_query);
							$row 			= $result->fetch_assoc();
							$user = $row;
							$result=array('status'=>1,'message'=>'Password updated successfully.','user_info'=>$user);
						}
						else{
							$result=array('status'=>0,'message'=>'Something went wrong,try again!');
						}
					}else{
						$result = array('status'=>0,'message'=>'Your account is deactivated by the admin');
					}	
				}else{
					$result = array('status'=>0,'message'=>'Invalid password!');
				}
			}else{
				$result = array('status'=>0,'message'=>'User does not exist.');
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