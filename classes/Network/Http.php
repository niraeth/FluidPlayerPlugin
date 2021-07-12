<?php
namespace FluidPlayerPlugin\Network;

class Http
{

	/*
	* Supports HTTPS / SSL
	*
	*/
	public static function get_url_contents($url)
	{
		$arrContextOptions=array(
			  "ssl"=>array(
					"verify_peer"=>false,
					"verify_peer_name"=>false,
				),
			);  

		$response = file_get_contents($url, false, stream_context_create($arrContextOptions));
		return $response;
	}
	
	/*
	* Get http response code 
	*/
	public static function get_http_response_code($url)
	{
		$ch = curl_init($url);					 // remove this option and test again to be sure
		curl_setopt($ch, CURLOPT_NOBODY, true ); //exclude body/content from output  // does this make the curl longer????
		curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		return $code;
	}
	
	
	/*
	* Is url a redirect?
	*/
	public static function is_redirect($url) 
	{
		//return true; // ok this is the problem
		
		$code = static::get_http_response_code($url);
		if (($code == 301) || ($code == 302)) {
			return true;
		}
		return false;
	}
	
	/*
	* Get redirected url / final destination.
	* May not work in every case because of CURLINFO_EFFECTIVE_URL. See More : https://stackoverflow.com/questions/1439040/how-can-i-get-the-destination-url-using-curl
	*/
	public static function get_redirected_url($redirect_url)
	{
		$ch = curl_init($redirect_url);
		curl_setopt($ch, CURLOPT_NOBODY, true ); //exclude body/content from output
		curl_setopt($ch, CURLOPT_HEADER, true );
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirect 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$headers = curl_exec($ch);

		$redirected_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		return $redirected_url;		
		//return str_replace("www3", "www4", $redirect_url);
	}
	
	public static function is_valid_url($url)
	{
		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
			return false;
		}
		return true;
	}

	/*
	* Check for 40x response code.
	*/
	public static function url_exists($url)
	{
		$exists = true;
		$file_headers = @get_headers($url);
		$InvalidHeaders = array('404', '403', '500');
		foreach($InvalidHeaders as $HeaderVal)
		{
			if(strstr($file_headers[0], $HeaderVal))
			{
				$exists = false;
				break;
			}
		}
		return $exists;		
	}
	
}

?>