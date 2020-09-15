<?php
if(isset($_POST["recipient_input_Form_Submit"]))
{
	try
	{
		$input_dataset=$_POST;
		
		//VALIDATE ATTRIBUTES
		if(empty($input_dataset["family_name"])){throw new Exception("MISSING_ATTRIBUTE: family_name");}//Missing attribute
		if(empty($input_dataset["last_name"])){throw new Exception("MISSING_ATTRIBUTE: last_name");}//Missing attribute
		if(empty($input_dataset["born_date"])){throw new Exception("MISSING_ATTRIBUTE: born_date");}//Missing attribute
		if(!preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $input_dataset["born_date"]) or !checkdate(substr($input_dataset["born_date"], 5, 2), substr($input_dataset["born_date"], 8, 2), substr($input_dataset["born_date"], 0, 4))){throw new Exception("INVALID_FORMAT: born_date");}//Incorrect format
		if(empty($input_dataset["taj_number"])){throw new Exception("MISSING_ATTRIBUTE: taj_number");}//Missing attribute
		if(!preg_match("/^[0-9]*$/", $input_dataset["taj_number"]) or strlen($input_dataset["taj_number"])!=9){throw new Exception("INVALID_FORMAT: taj_number");}//Incorrect format
		$temp=str_split($input_dataset["taj_number"]);
		if(($temp[0]*3+$temp[1]*7+$temp[2]*3+$temp[3]*7+$temp[4]*3+$temp[5]*7+$temp[6]*3+$temp[7]*7) % 10 != $temp[8]){throw new Exception("TAJ_NUMBER_NOT_VALID");}//Incorrect TAJ number based on Act. 1996/XX. (Hungary)
		if(empty($input_dataset["blood_type"])){throw new Exception("MISSING_ATTRIBUTE: blood_type");}//Missing attribute
		if(!in_array($input_dataset["blood_type"], array("0+", "0-", "A+", "A-", "B+", "B-", "AB+", "AB-"))){throw new Exception("INVALID_VALUE: blood_type");}//Not a valid blood type value
		if(empty($input_dataset["hospital_city"])){throw new Exception("MISSING_ATTRIBUTE: hospital_city");}//Missing attribute
		if(empty($input_dataset["hospital_name"])){throw new Exception("MISSING_ATTRIBUTE: hospital_name");}//Missing attribute
		if(empty($input_dataset["hospital_department"])){throw new Exception("MISSING_ATTRIBUTE: hospital_department");}//Missing attribute
		if(empty($input_dataset["authority_by"])){throw new Exception("MISSING_ATTRIBUTE: authority_by");}//Missing attribute
		if(!in_array($input_dataset["authority_by"], array("S01", "M01"))){throw new Exception("INVALID_VALUE: authority_by");}//Not a valid authority by value
		
		//ICAO 9303 VIZ recommendation: textual information should only be upper case
		$input_dataset["family_name"]=mb_strtoupper($input_dataset["family_name"]);
		$input_dataset["last_name"]=mb_strtoupper($input_dataset["last_name"]);
		$input_dataset["blood_type"]=mb_strtoupper($input_dataset["blood_type"]);
		$input_dataset["hospital_city"]=mb_strtoupper($input_dataset["hospital_city"]);
		$input_dataset["hospital_name"]=mb_strtoupper($input_dataset["hospital_name"]);
		$input_dataset["hospital_department"]=mb_strtoupper($input_dataset["hospital_department"]);
		
		#encryptionService
		$es_curl_params=array();
		$es_curl_params["APP_key"]="daad5e9668d3a5d490df764fe1be5c41ff48c20379edca9f502f64095c4b2926e9ff226142e390084c2d538ad3feb5b01dc35015b4f84fb2dcb0f2ae43dc8e09";
		$es_curl_params["params"]=array("family_name"=>$input_dataset["family_name"], "last_name"=>$input_dataset["last_name"], "born_date"=>$input_dataset["born_date"], "taj_number"=>$input_dataset["taj_number"], "blood_type"=>$input_dataset["blood_type"], "hospital_city"=>$input_dataset["hospital_city"], "hospital_name"=>$input_dataset["hospital_name"], "hospital_department"=>$input_dataset["hospital_department"], "authority_by"=>$input_dataset["authority_by"]);
		$es_curl_json=json_encode($es_curl_params, JSON_THROW_ON_ERROR);
		$es_curl=curl_init();
		curl_setopt($es_curl, CURLOPT_URL, "https://example.com/webservice/encryptionService.php");
		curl_setopt($es_curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($es_curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($es_curl, CURLOPT_POST, true);
		curl_setopt($es_curl, CURLOPT_POSTFIELDS, $es_curl_json);
		curl_setopt($es_curl, CURLOPT_HTTPHEADER, array(                                                                          
			"Content-Type: application/json",                                                                                
			"Content-Length: ".strlen($es_curl_json)));
		$es_curl_result=json_decode(curl_exec($es_curl), true, 512, JSON_THROW_ON_ERROR);
		curl_close($es_curl);
		
		if($es_curl_result["success"]==true)
		{
			#QR-code generation request
			$qr_gen_curl=curl_init();
			curl_setopt($qr_gen_curl, CURLOPT_URL, "https://api.qrserver.com/v1/create-qr-code/");
			curl_setopt($qr_gen_curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($qr_gen_curl, CURLOPT_POST, true);
			curl_setopt($qr_gen_curl, CURLOPT_POSTFIELDS, array("size"=>"200x200", "data"=>$es_curl_result["data"]["dataset_encrypted"]."#".$es_curl_result["data"]["iv"]."#".$es_curl_result["data"]["tag"]."#".$es_curl_result["data"]["data_key_encrypted"]));
			$qr_gen_curl_result=curl_exec($qr_gen_curl);//qr-code binary
			curl_close($qr_gen_curl);
			
			#QR-code usability ck.
			$qr_ck_curl_tmph=tmpfile();
			fwrite($qr_ck_curl_tmph, $qr_gen_curl_result);
			$qr_ck_curl_tmpf=stream_get_meta_data($qr_ck_curl_tmph)['uri'];
			$qr_ck_curl_cfile=curl_file_create($qr_ck_curl_tmpf);
			$qr_ck_curl=curl_init();
			curl_setopt($qr_ck_curl, CURLOPT_URL, "https://api.qrserver.com/v1/read-qr-code/");
			curl_setopt($qr_ck_curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($qr_ck_curl, CURLOPT_POST, true);
			curl_setopt($qr_ck_curl, CURLOPT_POSTFIELDS, array("file"=>$qr_ck_curl_cfile));
			$qr_ck_curl_result=json_decode(curl_exec($qr_ck_curl), true, 512, JSON_THROW_ON_ERROR)[0]["symbol"][0]["data"];
			curl_close($qr_ck_curl);
			fclose($qr_ck_curl_tmph);
			unset($qr_ck_curl_tmph, $qr_ck_curl_tmpf);
			
			if(is_null($qr_ck_curl_result)==false)
			{
				#QR-code file download
				header("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename=".$input_dataset["family_name"]."_".$input_dataset["last_name"]."qr_code.png");
				header("Content-Transfer-Encoding: binary");
				echo $qr_gen_curl_result;
			}
			else
			{
				throw new Exception("QR_CODE_FAILED_IN_USABILITY_CK");
			}
		}
		else
		{
			throw new Exception($es_curl_result["msg"]);
		}
	}catch(Exception $e)
	{
		echo $e->getMessage();
	}
}
?>