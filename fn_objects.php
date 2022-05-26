<?
	session_start();
	include ('../init.php');
	include ('fn_common.php');
	checkUserSession();
	
	loadLanguage($_SESSION["language"], $_SESSION["units"]);
	
	// check privileges
	if ($_SESSION["privileges"] == 'subuser')
	{
		$user_id = $_SESSION["manager_id"];
	}
	else
	{
		$user_id = $_SESSION["user_id"];
	}

	if(@$_POST['cmd'] == 'load_object_data')
	{		
		if (isset($_POST['imei']))
		{
			$imei = strtoupper(@$_POST['imei']); // get imei
			
			// check privileges
			if ($_SESSION["privileges"] == 'subuser')
			{			
				$q = "SELECT * FROM `gs_user_objects`
				WHERE `user_id`='".$user_id ."' AND `imei`='".$imei."' AND `imei` IN (".$_SESSION["privileges_imei"].")";
			}
			else
			{
				$q = "SELECT * FROM `gs_user_objects` WHERE `user_id`='".$user_id ."' AND `imei`='".$imei."'";
			}	
		}
		else
		{
			// check privileges
			if ($_SESSION["privileges"] == 'subuser')
			{
				$q = "SELECT * FROM `gs_user_objects`
				WHERE `user_id`='".$user_id ."' AND `imei` IN (".$_SESSION["privileges_imei"].") ORDER BY `imei` ASC";
			}
			else
			{
				$q = "SELECT * FROM `gs_user_objects` WHERE `user_id`='".$user_id ."' ORDER BY `imei` ASC";
			}	
		}
		
		$r = mysqli_query($ms, $q);
		
		$result = array();
		
		while($row = mysqli_fetch_array($r))
		{
			$imei = $row['imei'];
			
			$q2 = "SELECT * FROM `gs_objects` WHERE `imei`='".$imei."'";
			$r2 = mysqli_query($ms, $q2);
			$row2 = mysqli_fetch_array($r2);
			
			if ($row2['active'] == 'true')
			{
				$result[$imei] = array();
				$result[$imei]['v'] = true;
				$result[$imei]['f'] = false;
				$result[$imei]['s'] = false;
				$result[$imei]['evt'] = false;
				$result[$imei]['evtac'] = false;
				$result[$imei]['evtohc'] = false;
				$result[$imei]['a'] = '';
				$result[$imei]['l'] = array();
				$result[$imei]['d'] = array();
				
				$dt_server = $row2['dt_server'];
				$dt_tracker = $row2['dt_tracker'];
				$lat = $row2['lat'];
				$lng = $row2['lng'];
				$altitude = $row2['altitude'];
				$angle = $row2['angle'];
				$speed = $row2['speed'];
				$params = json_decode($row2['params'],true);
				
				$speed = convSpeedUnits($speed, 'km', $_SESSION["unit_distance"]);
				$altitude = convAltitudeUnits($altitude, 'km', $_SESSION["unit_distance"]);
				
				// status
				$result[$imei]['st'] = false;
				
				$result[$imei]['ststr'] = '';
				
				$dt_last_stop = strtotime($row2['dt_last_stop']);
				$dt_last_idle = strtotime($row2['dt_last_idle']);
				$dt_last_move = strtotime($row2['dt_last_move']);
				
				if (($dt_last_stop > 0) || ($dt_last_move > 0))
				{
					// stopped and moving
					if ($dt_last_stop >= $dt_last_move)
					{
						$result[$imei]['st'] = 's';
						$result[$imei]['ststr'] = $la['STOPPED'].' '.getTimeDetails(strtotime(gmdate("Y-m-d H:i:s")) - $dt_last_stop, true);
					}
					else
					{
						$result[$imei]['st'] = 'm';
						$result[$imei]['ststr'] = $la['MOVING'].' '.getTimeDetails(strtotime(gmdate("Y-m-d H:i:s")) - $dt_last_move, true);
					}
					
					// idle
					if (($dt_last_stop <= $dt_last_idle) && ($dt_last_move <= $dt_last_idle))
					{
						$result[$imei]['st'] = 'i';
						$result[$imei]['ststr'] = $la['ENGINE_IDLE'].' '.getTimeDetails(strtotime(gmdate("Y-m-d H:i:s")) - $dt_last_idle, true);
					}
				}
				
				// protocol
				$result[$imei]['p'] = $row2['protocol'];
				
				// connection/loc valid check
				$dt_now = gmdate("Y-m-d H:i:s");
				$dt_difference = strtotime($dt_now) - strtotime($dt_server);
				if($dt_difference < $gsValues['CONNECTION_TIMEOUT'] * 60)
				{
					$loc_valid = $row2['loc_valid'];
					
					if ($loc_valid == 1)
					{
						$conn = 2;
					}
					else
					{
						$conn = 1;
					}	
				}
				else
				{
					// offline status
					if (strtotime($dt_server) > 0)
					{
						$result[$imei]['st'] = 'off';
						$result[$imei]['ststr'] = $la['OFFLINE'].' '.getTimeDetails(strtotime(gmdate("Y-m-d H:i:s")) - strtotime($dt_server), true);
					}
					
					$conn = 0;
					$speed = 0;
				}
				
				$result[$imei]['cn'] = $conn;
				
				// location data
				if (($lat != 0) && ($lng != 0))
				{
					$result[$imei]['d'][] = array(	convUserTimezone($dt_server),
									convUserTimezone($dt_tracker),
									$lat,
									$lng,
									$altitude,
									$angle,
									$speed,
									$params);
				}
				
				// odometer and engine_hours				
				$odometer = floor(convDistanceUnits($row2['odometer'], 'km', $_SESSION["unit_distance"]));
				$engine_hours = floor($row2['engine_hours'] / 60 / 60);
				
				$result[$imei]['o'] = $odometer;
				$result[$imei]['eh'] = $row2['engine_hours']; // we do not use conversion, because we need engine hours in seconds
				
				// service
				$result[$imei]['sr'] = array();
				
				$q3 = "SELECT * FROM `gs_object_services` WHERE `imei`='".$imei."' ORDER BY name asc";
				$r3 = mysqli_query($ms, $q3);	
				
				while($row3 = mysqli_fetch_array($r3)) {
					$left_arr = array();
					$expired_arr = array();
					
					if ($row3['odo'] == 'true')
					{
						$row3['odo_interval'] = floor(convDistanceUnits($row3['odo_interval'], 'km', $_SESSION["unit_distance"]));
						$row3['odo_last'] = floor(convDistanceUnits($row3['odo_last'], 'km', $_SESSION["unit_distance"]));
				
						$odo_diff = $odometer - $row3['odo_last'];
						$odo_diff = $row3['odo_interval'] - $odo_diff;
				
						if ($odo_diff <= 0)
						{
							$expired_arr[] = abs($odo_diff).' '.$la["UNIT_DISTANCE"];
						}
						else
						{
							$left_arr[] = $odo_diff.' '.$la["UNIT_DISTANCE"];
						}
					}
					
					if ($row3['engh'] == 'true')
					{
						$engh_diff = $engine_hours - $row3['engh_last'];
						$engh_diff = $row3['engh_interval'] - $engh_diff;
				
						if ($engh_diff <= 0)
						{
							$expired_arr[] = abs($engh_diff).' '.$la["UNIT_H"];
						}
						else
						{
							$left_arr[] = $engh_diff.' '.$la["UNIT_H"];
						}
					}
					
					if ($row3['days'] == 'true')
					{
						$days_diff = strtotime(gmdate("M d Y ")) - (strtotime($row3['days_last']));
						$days_diff = floor($days_diff/3600/24);
						$days_diff = $row3['days_interval'] - $days_diff;
				
						if ($days_diff <= 0)
						{
							$expired_arr[] = abs($days_diff).' '.$la["UNIT_D"];
						}
						else
						{
							$left_arr[] = $days_diff.' '.$la["UNIT_D"];
						}
					}
					
					$status = '';
					
					if (count($left_arr) > 0)
					{
						$status = $la["LEFT"].' ('.implode(", ", $left_arr).')';
					}
					
					if (count($expired_arr) > 0)
					{
						$status = '<font color="red">'.$la["EXPIRED"].' ('.implode(", ", $expired_arr).')</font>';
					}
					
					if ($status != '')
					{
						$result[$imei]['sr'][] = array(	'name' => $row3['name'], 'data_list' => $row3['data_list'], 'popup' => $row3['popup'], 'status' => $status);	
					}
				}
			}
		}
		
		mysqli_close($ms);
		
		ob_start();
		header('Content-type: application/json');
		echo json_encode($result);
		header("Connection: close");
		header("Content-length: " . (string)ob_get_length());
		ob_end_flush();
		die;
	}
?>