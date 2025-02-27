<?php

$host= 'ftp.webappsitesdemo.com';
$port= 'ftp.webappsitesdemo.com';
$timeout= 'ftp.webappsitesdemo.com';
$user= 'inext@webappsitesdemo.com';
$pass= 'b*l1Y1V$d{Z5';

$host= 'monitoring.page';
$user= 'developer@monitoring.page';
$pass= 'uwXTzY@eqV$H';

$ftp = ftp_connect($host) or die("Could not connect to $ftp_server");

$login_result = ftp_login($ftp, $user, $pass);
 
	include_once('db.php');

	if($_POST){

		$data	= $_POST;
		if(!empty($data) && isset($data['email_address']) && isset($_FILES['image']['name'])){
			
			$check_email	= "Select * from users where email_address ='".$data['email_address']."'";
			$result 		= $conn->query($check_email);

			if($result->num_rows==1){
				$row = $result->fetch_assoc();

				if($row['status']=='1'){

					if(isset($_FILES['image'])){
						//upload the img
						
						$target_path = 'images/'.$row['id']; //exit;
						
						if ( !file_exists( $target_path ) && !is_dir( $target_path ) ) {
							mkdir( $target_path );       
						}
						
						$file_name = $_FILES['image']['name'];
						$parts = explode(".", $file_name);
						$to_path = $target_path .'/'. $file_name; //set the target path with a new name of image

						if ($file_name != '') {
							
							ftp_pasv($ftp, true);
							$ret = ftp_put($ftp, 'images/'.$file_name, $_FILES['image']['tmp_name'], FTP_BINARY);

							if ($ret) {
								$result = array('status'=>1,'message'=>'Image uploaded successfully','response'=>$file_name);
							} else {
								$result = array('status'=>0,'message'=>'There was a error in upload a image');
							}
						}else {
							$result = array('status'=>0,'message'=>'Please add image data to continue');
						}
					}else {
						
						$result = array('status'=>0,'message'=>'Please add image data to continue');
					}
					
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

 
 
 
 
 

$ret = ftp_nb_put($ftp, 'images/Best_Bootcamp_Troy_Group.jpg', 'images/1/Best_Bootcamp_Troy_Group.jpg', FTP_BINARY);
 echo "<br>dgdgdf2233     ";
 print_r( $ret);
while (FTP_MOREDATA == $ret)
{
	echo "khkhj";
	// display progress bar, or something
	$ret = ftp_nb_continue($ftp);
}

