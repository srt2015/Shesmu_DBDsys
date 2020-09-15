<?php
try
{
	#QR-code read request
	$qr_read_curl_cfile=curl_file_create("test_qr.png");
	$qr_read_curl=curl_init();
	curl_setopt($qr_read_curl, CURLOPT_URL, "https://api.qrserver.com/v1/read-qr-code/");
	curl_setopt($qr_read_curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($qr_read_curl, CURLOPT_POST, true);
	curl_setopt($qr_read_curl, CURLOPT_POSTFIELDS, array("file"=>$qr_read_curl_cfile));
	$qr_read_curl_result=json_decode(curl_exec($qr_read_curl), true, 512, JSON_THROW_ON_ERROR)[0]["symbol"][0]["data"];
	curl_close($qr_read_curl);
	
	if(is_null($qr_read_curl_result)==false)
	{
		//Split the result by '#' (Order: data, iv, tag, encryptedDataKey)
		$temp=explode('#', $qr_read_curl_result);
		if(!isset($temp[0], $temp[1], $temp[2], $temp[3])){throw new Exception("MISSING_ARRAY_INDEX_decryption_test_site_processing: var temp");}
		
		#decryptionService
		$ds_curl_params=array();
		$ds_curl_params["APP_key"]="daad5e9668d3a5d490df764fe1be5c41ff48c20379edca9f502f64095c4b2926e9ff226142e390084c2d538ad3feb5b01dc35015b4f84fb2dcb0f2ae43dc8e09";
		$ds_curl_params["params"]=array("dataset_encrypted"=>$temp[0], "iv"=>$temp[1], "tag"=>$temp[2], "data_key_encrypted"=>$temp[3]);
		$ds_curl_json=json_encode($ds_curl_params, JSON_THROW_ON_ERROR);
		$ds_curl=curl_init();
		curl_setopt($ds_curl, CURLOPT_URL, "https://example.com/webservice/decryptionService.php");
		curl_setopt($ds_curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ds_curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ds_curl, CURLOPT_POST, true);
		curl_setopt($ds_curl, CURLOPT_POSTFIELDS, $ds_curl_json);
		curl_setopt($ds_curl, CURLOPT_HTTPHEADER, array(                                                                          
			"Content-Type: application/json",                                                                                
			"Content-Length: ".strlen($ds_curl_json)));
		$ds_curl_result=json_decode(curl_exec($ds_curl), true, 512, JSON_THROW_ON_ERROR);
		curl_close($ds_curl);
		
		if($ds_curl_result["success"]==true)
		{
			#Return decrypted data set
			var_dump($ds_curl_result["data"]);
		}
		else
		{
			throw new Exception($ds_curl_result["msg"]);
		}
	}
	else
	{
		throw new Exception("QR_CODE_READ_FAILED");
	}
}catch(Exception $e)
{
	echo $e->getMessage();
}
?>