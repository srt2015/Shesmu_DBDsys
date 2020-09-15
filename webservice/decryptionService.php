<?php
//This service requires Amazon AWS SDK for PHP; GitHub: https://github.com/aws/aws-sdk-php
require "*/AWS/vendor/autoload.php";
/**
decryptionService
	@params are returned by the encryptionService, all of the following parameters are base64 encoded strings
		JSON array
			dataset_encrypted
			iv
			tag
			data_key_encrypted
	@return decrypted DBD_attributes in a JSON key-value paired array:
		family_name
		last_name
		born_date
		TAJ_number
		blood_type
		hospital_city
		hospital_name
		hospital_department
		authority_by
		timestamp
**/

function getStatusDescription(&$code)
{
	$status = array(  
		200 => "OK",
		400 => "Bad Request",
		401 => "Unauthorized",
		404 => "Not Found",   
		405 => "Method Not Allowed",
		500 => "Internal Server Error"); 
	if(!array_key_exists($code, $status))
	{
		$code=500;
	}
	return $status[$code];
}

$app_keys=array("daad5e9668d3a5d490df764fe1be5c41ff48c20379edca9f502f64095c4b2926e9ff226142e390084c2d538ad3feb5b01dc35015b4f84fb2dcb0f2ae43dc8e09"=>"DBD_sys website");

try
{
	$status=200;
	/**
	json input: key-value paired array
		-APP_key: caller authorization code
		-params: key-value paired array for the called function
	**/
	$params=file_get_contents('php://input');
	$params=json_decode($params, true, 512, JSON_THROW_ON_ERROR);
	
	//Validating the caller authority
	if(empty($params["APP_key"]) or !array_key_exists($params["APP_key"], $app_keys))
	{
		$status=401;
		throw new Exception("MISSING_APP_KEY_UNAUTHORIZED_CALL");
	}
	
	/**
	Decrypt the concatenated DBD_attributes string
		@result $decrypted_temp is the decrypted string (attributes are separated by a '#')
	**/
	//Create a KMSClient
	$KmsClient=new Aws\Kms\KmsClient(array("profile"=>"default", "version"=>"latest", "region"=>"eu-west-2"));
	
	$cipher="aes-256-gcm";
	
	//Decrypt the DataKey for local decryption : binary
	$data_key_plaintext=$KmsClient->decrypt(array("CiphertextBlob"=>base64_decode($params["params"]["data_key_encrypted"])))["Plaintext"];
	
	//Decryption : string
	if(($decrypted_temp=openssl_decrypt(base64_decode($params["params"]["dataset_encrypted"]), $cipher, $data_key_plaintext, OPENSSL_RAW_DATA, base64_decode($params["params"]["iv"]), base64_decode($params["params"]["tag"])))===false){throw new Exception("DECRYPTION_FAILED");}
	
	//Build the decrypted result array
	$dataset_decrypted=array();
	$temp=explode('#', $decrypted_temp);
	if(!isset($temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5], $temp[6], $temp[7], $temp[8], $temp[9])){throw new Exception("MISSING_ARRAY_INDEX_decryptionService: var temp");}
	$dataset_decrypted["family_name"]=$temp[0];
	$dataset_decrypted["last_name"]=$temp[1];
	$dataset_decrypted["born_date"]=$temp[2];
	$dataset_decrypted["TAJ_number"]=$temp[3];
	$dataset_decrypted["blood_type"]=$temp[4];
	$dataset_decrypted["hospital_city"]=$temp[5];
	$dataset_decrypted["hospital_name"]=$temp[6];
	$dataset_decrypted["hospital_department"]=$temp[7];
	$dataset_decrypted["authority_by"]=$temp[8];
	$dataset_decrypted["timestamp"]=$temp[9];
	
	/**
	@return array
		success: true/false
		data: IF success is true THEN it is a key-value paired array ELSE it is NULL
		msg: IF success is false THEN it is a string error message code ELSE it is NULL
	**/
	$result=array();
	$result["success"]=true;
	$result["data"]=$dataset_decrypted;
	$result["msg"]=null;
}catch(Exception $e)
{
	$result=array();
	$result["success"]=false;
	$result["data"]=null;
	$result["msg"]=$e->getMessage();
}
header("HTTP/1.1 " . $status . " " . getStatusDescription($status));
header("Content-Type: application/json");
echo json_encode($result);
exit();
?>