<?php
namespace FluidPlayerPlugin\Debug;

class Debug
{
	public $debug;
	
	public function __construct()
	{
	
	}
	
	public static function output($message)
	{
		echo "<p>" . $message . "</p>";
	}
	
	public static function print_header($header) 
	{
		echo "<p style='font-weight:bold;'>{$header}</p>";
	}
	public static function print_headers($headers)
	{
		echo "<p style='font-weight:bold;'>{$header}</p>";
	}
	public static function print_table(array $array)
	{
		if( empty($array) ) {
			$html = "<p>Table is empty</p>";
			return $html;
		}
		$html  = '<table border="1">';
		$html .= '<tr>';
		foreach($array[0] as $key=>$value) {
			$html .= '<th>' . htmlspecialchars($key) . '</th>';
		}
		$html .= '</tr>';
		foreach( $array as $key=>$value){
			$html .= '<tr>';
			foreach($value as $key2=>$value2){
				$html .= '<td>' . htmlspecialchars($value2) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table>';
		echo $html;
		
		return $html;
	}
	/*
	* Wrap content within a toggle (can be hidden/visible) for better viewing
	* **UNTESTED**
	*/
	public static function wrap_toggle($toggle_content)
	{
		$css = <<<CSS
<style>
div input {
  margin-right: 100px;
}

.check-btn label {
  display: inline-block;
}

.check-btn input {
  display: none;
}

.clicker {
  background: green;
  padding: 5px 10px;
}

.toggle-content {
  background: #000;
  display: none;
}

.check-btn input:checked ~ .hiddendiv {
  display: block;
}
</style>
CSS;
		$random_int = rand(0, 99999999);
		$random_id = "toggle-content-{$random_int}";
		$html  = "<div class='check-btn'>";
			$html .= "<input id='{$random_id}' type='checkbox'>";
			$html .= "<label for='{$random_id}' class='clicker'>Toggle Me</label>";
			$html .= "<div class='toggle-content'>";
			$html .= $toggle_content;
			$html .= "</div>";
		$html .= "</div>";
	}
	
	public static function pretty_dump($array, string $title="")
	{
		if( !empty($title) ) {
			static::output("<h4>{$title}</h4>");
		}
		echo '<pre>' . var_export($array, true) . '</pre>';
	}
	
	public static function dump($array)
	{
		var_dump($array);
	}
	
	public static function log($message)
	{
	
	}
};

?>