<?php

include "config.php";

$conn = oci_connect($username,$password,$db);

//set up the Bot API token
$website = "https://api.telegram.org/".$botToken;

//Grab the info from the webhook, parse it and put it into $message
$content = file_get_contents("php://input");
$update = json_decode($content, TRUE);
error_log($content);
$message = @$update["message"];

//Make some helpful variables
$chatId = $message["chat"]["id"];
$text = $message["text"];

//Remove botname
$botname = "@aisv2bot";
$text = str_replace($botname, "", $text);

$callback_query = @$update["callback_query"];

if(!$conn){
   //https://apps.timwhitlock.info/emoji/tables/unicode
   $emoji[0] = "\xE2\x9C\xA8";
   $emoji[1] = "\xF0\x9F\x8E\x89";
   $emoji[2] = "\xF0\x9F\x8E\x8A";
   $emoji[3] = "\xF0\x9F\x8E\xB6";
   $emoji[4] = "\xF0\x9F\x91\x84";
   $emoji[5] = "\xF0\x9F\x8E\x86";
   $emoji[6] = "\xF0\x9F\x8E\x87";

   $message[0] = "Database is under maintenance, try again later. Contact @ririzkia";
   $message[1] = "Database is under maintenance, contact @ririzkia";
   $message[2] = "Database is undergoing harsh maintenance, contact @ririzkia";
   $message[2] = "Database is unavailable for service, contact @ririzkia";
   $pre_emoji = null; $post_emoji = null;
   for($i=0; $i< rand(2,4); $i++) {
   	$pre_emoji .= $emoji[rand(0, 6)];
   }

   for($i=0; $i< rand(2,4); $i++) {
   	$post_emoji .= $emoji[rand(0, 6)];
   }

   $message = urlencode($pre_emoji." ".$message[rand(0, 2)]. " ".$post_emoji);
   $url = $website."/sendmessage?chat_id=".$chatId."&parse_mode=HTML"."&text=".$message;

	$ch = curl_init();
    $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
    );
    curl_setopt_array($ch, $optArray);
    $result = curl_exec($ch);
    curl_close($ch);

    $sticker = array("CAADBQADbgADT7BPAsmBH63xSKFTAg", "CAADBQADvQADT7BPAh2pnZGRMBR1Ag");
    $url = $website."/sendsticker?chat_id=".$chatId."&sticker=".$sticker[rand(0,1)]."&parse_mode=HTML"."&text=".$message;

	$ch = curl_init();
    $optArray = array(CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true);
    curl_setopt_array($ch, $optArray);
    $result = curl_exec($ch);
    curl_close($ch);
    die();
}

function getVesselInfo($mmsi){
	global $conn;
	$sql="select mmsi, name, lon, lat, destination, eta, sog, cog, size_a, size_b,
			to_char(last_posdate,'FMMonth ddth, YYYY HH24:MI:SS') last_posdate_,
			to_char(last_posdate,'Day') day  
				, (SELECT name from COUNTRYMID WHERE COUNTRYMID.ID = SUBSTR(vessel_info.mmsi,1,3)) country
	from vessel_info WHERE mmsi='".$mmsi."'";
	//error_log($sql);
   //die();
   //error_log("=====================".$sql);
   $rs = oci_parse($conn, $sql) or die(oci_error());
   oci_execute($rs);

   while($value = oci_fetch_assoc($rs)) {
   	 //"\xF0\x9F\x98\x81".
     $message = "<b>MMSI :</b> ".$value['MMSI']."\n";
     $message .= "<b>IMO :</b> ".$value['IMO']."\n";
     $message .= "<b>Vessel Name :</b> ".$value['NAME']."\n";
     $message .= "<b>Date :</b> ".trim($value['DAY']). ", ".$value['LAST_POSDATE_']." (UTC)"."\n";
     $message .= "<b>Type :</b> ".$value['TIPE']."\n";
     $message .= "<b>Size :</b> ".$value['SIZE_A']. "x".$value['SIZE_B']."\n";
     $message .= "<b>Country :</b> ".$value['COUNTRY']."\n";
     $message .= "<b>Location :</b> ".$value['LAT']. ", " .$value['LON']."\n";
     $message .= "<b>Destination :</b> ".$value['DESTINATION']."\n";
     $message .= "<b>ETA :</b> ".$value['ETA']."\n";
     $message .= "<b>Speed :</b> ".$value['SOG']." knots\n";
	   $message .= "<b>Course :</b> ".$value['COG']." degrees\n";

     $lon = $value['LON'];
     $lat = $value['LAT'];
   }

   return array("message" => $message, "lon"=>$lon, "lat"=>$lat);
}

function mmsi($mmsi){
	
	global $website;
	global $chatId;

   $vesselInfo = getVesselInfo($mmsi);
	///error_log(json_encode($vesselInfo));
   $message = urlencode($vesselInfo["message"]);
   file_get_contents($website."/sendmessage?chat_id=".$chatId."&parse_mode=HTML"."&text=".$message);
   file_get_contents($website."/sendLocation?chat_id=".$chatId."&latitude=".$vesselInfo["lat"]."&longitude=".$vesselInfo["lon"]);
}

function mmsi_update($mmsi,$chatId,$messageId){
	global $website;
	
	$vesselInfo = getVesselInfo($mmsi);

  $message = urlencode($vesselInfo["message"]);
  $url = $website."/editMessageText?chat_id=".$chatId."&message_id=".$messageId."&parse_mode=HTML"."&text=".$message;
  //error_log($url);
  //file_get_contents($website."/editMessageText?message_id=".$messageId."&parse_mode=HTML"."&text=".$message);
  $ch = curl_init();
  $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
  );
  curl_setopt_array($ch, $optArray);
  $result = curl_exec($ch);
  curl_close($ch);

  file_get_contents($website."/sendLocation?chat_id=".$chatId."&latitude=".$vesselInfo["lat"]."&longitude=".$vesselInfo["lon"]);
}

if(isset($callback_query["message"]["text"])) {

	if($callback_query["message"]["text"] == "Search Result"){
		$mmsi = $callback_query["data"];
		$chatId = $callback_query["message"]["chat"]["id"];
		$messageId = $callback_query["message"]["message_id"];
		mmsi_update($mmsi,$chatId,$messageId);
	}

}

if(isset($update["inline_query"])) {
   //error_log($update["inline_query"]["query"]);
	$query = $update["inline_query"]["query"];
	$inline_query_id = $update["inline_query"]["id"];

	if(strlen($query) > 4) {
		//$word = str_replace("/search ", "", $text);;
		$sql="select mmsi, name, lon, lat, to_char((last_posdate + INTERVAL '7' HOUR),'DD-MM-YYYY HH24:MI:SS') last_posdate_ 
				FROM vessel_info 
				WHERE (upper(name) like upper('%".$query."%') OR (MMSI LIKE '%".$query."%'))
				and rownum < 5";

	   	$rs = oci_parse($conn, $sql) or die(oci_error());
	   	oci_execute($rs);

	   	$i = 1;
	   	$collection = array();
		while($value = oci_fetch_assoc($rs)) {
	    $collection[] = array("type" => "location", "id" => "a"."$i", 
	    						"title" => $value['NAME'] . " (".$value['MMSI']. ") \n". $value['LAST_POSDATE_']
	    						, "longitude" => $value['LON'], "latitude" =>$value['LAT']
	    						//,"input_message_content"	=> array("message_text" => "MMSI : ".$value['MMSI']."\n"."VESSEL NAME : ".$value['NAME'])
	    				);
	    $i++;
	  }

	  error_log(json_encode($collection));
	  $url = $website."/answerInlineQuery?inline_query_id=".$inline_query_id."&results=".json_encode($collection, JSON_NUMERIC_CHECK);
	  //error_log($url);
	  //file_get_contents($website."/answerInlineQuery?inline_query_id=".$inline_query_id."&results=".json_encode($collection));

	  $ch = curl_init();
		    $optArray = array(
		            CURLOPT_URL => $url,
		            CURLOPT_RETURNTRANSFER => true
		    );
		    curl_setopt_array($ch, $optArray);
		    $result = curl_exec($ch);
		    curl_close($ch);
  }
}

if(substr($text, 0, 5 ) == "/mmsi" || $text == "/mmsi@aisv2bot") {

	$mmsi = str_replace("/mmsi", "", $text);
	$mmsi = trim($mmsi);
	if($mmsi==""){
		$message = "Please enter mmsi for a ship. Example: /mmsi 371798000";
		file_get_contents($website."/sendmessage?chat_id=".$chatId."&parse_mode=HTML"."&text=".$message);
	} 
	else 
	{
		mmsi($mmsi);
	}
	
}

if(substr($text, 0, 7 ) == "/search" || $text === "/search@aisv2bot") {

	$word = str_replace("/search", "", $text);
	$word = trim($word);

	if($word==""){
		$message = "What are you searching for?. Example: /search tangguh jaya";
		file_get_contents($website."/sendmessage?chat_id=".$chatId."&parse_mode=HTML"."&text=".$message);
		die();
	}
	$sql="select mmsi, name, lon, lat, to_char((last_posdate + INTERVAL '7' HOUR),'DD-MM-YYYY HH24:MI:SS') last_posdate_ from vessel_info WHERE upper(name) like upper('%".$word."%') and rownum < 5";

   $rs = oci_parse($conn, $sql) or die(oci_error());
   oci_execute($rs);

   $message = null; 
   $no = 1;
   $inlineKeyBoard["inline_keyboard"] = array();
   while($value = oci_fetch_assoc($rs)) {

     //$message .= $no.". ".$value['NAME']." ".$value['MMSI']."\n";
     //$inlineKeyBoard["inline_keyboard"][] = array("text" => $value['NAME']." ".$value['MMSI']);
     array_push($inlineKeyBoard["inline_keyboard"], array(array("text" => $value['NAME']." ".$value['MMSI'], "callback_data" => $value['MMSI'])));
   	//$inlineKeyBoard["inline_keyboard"][]["text"] =  $value['NAME']." ".$value['MMSI'];
    
     $no++;
   }
   $message = "Search Result";
   $message = urlencode($message);
   //$jsonResult = json_encode($inlineKeyBoard);
   $jsonResult =json_encode($inlineKeyBoard,true);
   //$jsonResult = str_replace( array( '['), '{', $jsonResult);  
   //$jsonResult = str_replace( array( ']'), '}', $jsonResult);  

   $final_url = $website."/sendmessage?chat_id=".$chatId."&text=".$message."&reply_markup=".$jsonResult;
   error_log($final_url);
   //file_get_contents($final_url);
   $ch = curl_init();
   $optArray = array(
            CURLOPT_URL => $final_url,
            CURLOPT_RETURNTRANSFER => true
   );
   curl_setopt_array($ch, $optArray);
   $result = curl_exec($ch);
   curl_close($ch);
  
}

if(substr($text, 0, 13 ) == "/lastposdate" || $text === "/lastposdate@aisv2bot") {

	//$word = str_replace("/lastposdate ", "", $text);;
   $sql="select to_char(max(last_posdate), 'DD-MM-YYYY HH24:MI:SS') last_posdate from vessel_track";

   $rs = oci_parse($conn, $sql) or die(oci_error());
   oci_execute($rs);

   $message = null; 
   while($value = oci_fetch_assoc($rs)) {
   	 $lastposdate = $value['LAST_POSDATE'];
   }

   $message = '<b>Last Data</b> : '.$lastposdate . ' (UTC)';

   $thisTime = gmdate("d-m-Y H:i:s");
   $intervalFromNow = date_diff(DateTime::createFromFormat("d-m-Y H:i:s",$lastposdate), DateTime::createFromFormat("d-m-Y H:i:s",$thisTime));
   
   $intervalFromNow = $intervalFromNow->format("%h");

   $message .= "\n"."<b>Now</b> : " .  $thisTime . ' (UTC)';
   $message .= "\n"."<b>Delay</b> : ".$intervalFromNow. " hours";

   $message = urlencode($message);
   file_get_contents($website."/sendmessage?chat_id=".$chatId."&parse_mode=HTML"."&text=".$message);
  
}

if(substr($text, 0, 18 ) == "/globallastposdate" || $text === "/globallastposdate@aisv2bot") {

   $sql="select * from view_get_last_posdate";

   $rs = oci_parse($conn, $sql) or die(oci_error());
   oci_execute($rs);

   $message = null; 
   while($value = oci_fetch_assoc($rs)) {
	   $message .= "\n"."<b>Now</b> : " .  $value['NOW'];
	   $message .= "\n";
	   $message .= "\n"."<b>Maria (192.168.20.52)</b>";
	   $message .= "\n"."<b>Max ID Maria</b> : " .  $value['MAX_ID_MARIA_DB'];
	   $message .= "\n"."<b>Last Posdate Maria</b> : " .  $value['LAST_POSDATE_MARIA_DB'];
	   $message .= "\n"."<b>Delay Maria</b> : ".$value['DELAY_MARIA_DB_FROM_NOW'];

	   $message .= "\n";
	   $message .= "\n"."<b>Oracle (192.168.20.51/202.95.130.70)</b>";
	   $message .= "\n"."<b>Max ID Oracle</b> : " .  $value['MAX_ID_ORA_DB'];
	   $message .= "\n"."<b>Last Posdate Oracle</b> : " .  $value['LAST_POSDATE_ORA_DB'];
	   $message .= "\n"."<b>Delay Oracle</b> : ".$value['DELAY_ORA_DB_FROM_NOW'];

	   $message .= "\n";
	   $message .= "\n"."<b>Delay Oracle to Maria</b> : ".$value['DELAY_ORA_DB_FROM_MARIA_DB'];

	   if($value['DELAY_ORA_MARIA'] > 0.5){
	   		$message .= " (".marahin("@arasismanoer Ras").")";
	   }
	   else {
			$message .= "\xF0\x9F\x91\x8D";
	   }
   }
  
   $message = urlencode($message);
   file_get_contents($website."/sendmessage?chat_id=".$chatId."&parse_mode=HTML"."&text=".$message);
  
}

function marahin($nama_orang) {
	$pesan = array("telat nih datanya", "datanya telat nih", "gimana nih");
	return $nama_orang.", ".$pesan[rand(0, count($pesan) - 1)];
}
?>