<?php
	include_once('db.php');

	if($_POST){

		$data	= $_POST;
		if(!empty($data) && isset($data['email_address'])){
			
			$check_email	= "Select * from users where email_address ='".$data['email_address']."'";
			$result 		= $conn->query($check_email);

			if($result->num_rows==1){
				$row = $result->fetch_assoc();

				if($row['status']=='1'){
					
					$target_path = 'images/'.$row['id'];
					if ($handle = opendir($target_path )) {
						$files= array();
						while (false !== ($entry = readdir($handle))) {

							if ($entry != "." && $entry != "..") {

								//echo "$entry\n";
								$files[] = "https://monitoring.live/API/images/".$row['id']."/".$entry;
							}
						}

						closedir($handle);
					}
					$result = array('status'=>1,'message'=>'Images List','response'=>$files);
					
				}else{
					$result = array('status'=>0,'message'=>'Your account is deactivated by the admin');
				}
			}else{
				$result = array('status'=>0,'message'=>'Invalid email address.');
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