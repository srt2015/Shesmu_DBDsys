<?php
//This service requires Amazon AWS SDK for PHP; GitHub: https://github.com/aws/aws-sdk-php
require "*/AWS/vendor/autoload.php";
/**
encryptionService
	@params
		JSON array
			family_name
			last_name
			born_date
			taj_number
			blood_type
			hospital_city
			hospital_name
			hospital_department
			authority_by
	@return JSON array
		dataset_encrypted = encrypted DBD_attributes in a single base64 encoded binary string
		iv = in a single base64 encoded binary string
		tag = in a single base64 encoded binary string
		data_key_encrypted = in a single base64 encoded binary string
	
	In the encryption, attributes are concatenated into a single string with a '#' separator in the following order
		family_name
		last_name
		born_date
		taj_number
		blood_type
		hospital_city
		hospital_name
		hospital_department
		authority_by
		timestamp: a Unix timestamp
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
	
	//Concatenate the DBD_attributes with a '#' separator
	$temp=$params["params"]["family_name"]."#".$params["params"]["last_name"]."#".$params["params"]["born_date"]."#".$params["params"]["taj_number"]."#".$params["params"]["blood_type"]."#".$params["params"]["hospital_city"]."#".$params["params"]["hospital_name"]."#".$params["params"]["hospital_department"]."#".$params["params"]["authority_by"]."#".time();
	
	/**
	Encrypt the concatenated DBD_attributes string ($temp)
		$data_key_plaintext, $iv are binary values from the Amazon AWS KMS HSM infrastructure
		@result $encrypted_temp is the encrypted $temp, binary
	**/
	//Create a KMSClient
	$KmsClient=new Aws\Kms\KmsClient(array("profile"=>"default", "version"=>"latest", "region"=>"eu-west-2"));
	
	$cipher="aes-256-gcm";
	
	/**
	Request a cryptographically secure random number for local encryption from the Amazon AWS KMS HSM infrastructure : binary
	https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-kms-2014-11-01.html#generaterandom
		$client->generateRandom([
			"CustomKeyStoreId" => "<string> an AWS CloudHSM cluster ID",
			"NumberOfBytes" => <integer>,
		]);
	**/
	$iv=$KmsClient->generateRandom(array("CustomKeyStoreId"=>an AWS CloudHSM cluster ID, "NumberOfBytes"=>openssl_cipher_iv_length($cipher)))["Plaintext"];

	//Request a DataKey for local encryption from the Amazon AWS KMS HSM infrastructure, contains encrypted and plaintext key as well : binary
	$data_key_object=$KmsClient->generateDataKey(array("KeyId"=>an AWS symmetric CMK ID, "KeySpec"=>"AES_256"));
	$data_key_encrypted=$data_key_object["CiphertextBlob"];
	$data_key_plaintext=$data_key_object["Plaintext"];
	
	//Encryption : binary
	if(($encrypted_temp=openssl_encrypt($temp, $cipher, $data_key_plaintext, OPENSSL_RAW_DATA, $iv, $tag))===false){throw new Exception("ENCRYPTION_FAILED");}
	
	
	/**
	@return array
		success: true/false
		data: IF success is true THEN it is a key-value paired array ELSE it is NULL
		msg: IF success is false THEN it is a string error message code ELSE it is NULL
	**/
	$result=array();
	$result["success"]=true;
	$result["data"]=array("dataset_encrypted"=>base64_encode($encrypted_temp), "iv"=>base64_encode($iv), "tag"=>base64_encode($tag), "data_key_encrypted"=>base64_encode($data_key_encrypted));
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