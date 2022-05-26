<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracking";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
$sql = "SELECT * FROM gs_user_objects WHERE user_id = 5";
$result = $conn->query($sql);
for ($i = 0; $i <= 14; $i++) {
    $count1[$i] = 0;
    $count2[$i] = 0;
    $count3[$i] = 0;
}
$todayIdle = 0; $todayOffline = 0; $todayOnline = 0;
if ($result->num_rows > 0) {
  // output data of each row
  while($row = $result->fetch_assoc()) {
    //echo "id: " . $row["imei"]. "<br>";
    $sql1 = "SELECT * FROM gs_object_data_" . $row['imei'];
    for ($i = 1; $i <= 14; $i++) {
        $count[$i] = 0;
    }
    $result1 = $conn->query($sql1);
    if ($result1->num_rows > 0) {
        while($row1 = $result1->fetch_assoc()) {
            //$date[] = explode(" ",$row1['dt_server'])[0];
            //echo strtotime(explode(" ",$row1['dt_server'])[0]);
            $days_ago = date('m/d/Y', strtotime('-1 days', strtotime('2022-04-01')));
            for ($x = 1; $x <= 14; $x++) {
                $date = "-" . $x . " days";
                if (strtotime($date, strtotime('today')) == strtotime(explode(" ",$row1['dt_server'])[0])){
                    $count[$x] = 1;
                }
              }
        }

        for ($i = 1; $i <= 14; $i++) {
            if ($count[$i] == 1){
                $count1[$i] ++;
            } else {
                $count2[$i] ++;
            }
        }
    } else {
        for ($i = 1; $i <= 14; $i++) {
            $count2[$i] ++;            
        }
    }

    $sql3 = "SELECT * FROM gs_objects WHERE imei = " . $row['imei'];

    $result3 = $conn->query($sql3);
    $online = 0; $offline = 0; $idle = 0;
    while($row3 = $result3->fetch_assoc()) {
        $dt_server = strtotime($row3['dt_server']);
        $dt_last_stop = strtotime($row3['dt_last_stop']);
		$dt_last_idle = strtotime($row3['dt_last_idle']);
		$dt_last_move = strtotime($row3['dt_last_move']);
        
        //online
        if ( (strtotime("now") - $dt_server) < 20*60) {
            $online = 1;
        } elseif (($dt_last_stop <= $dt_last_idle) && ($dt_last_move <= $dt_last_idle)) {
            $idle = 1;
        } elseif ( (strtotime("now") - $dt_server) > 20*60 ) {
            $offline = 1;
        }
    }

    if ($online == 1){
        $todayOnline ++;
    } elseif ($offline == 1){
        $todayOffline ++;
    } elseif ($idle == 1){
        $todayIdle ++;
    }
  }
  //var_dump($count);
} else {
  //echo "0 results";
}

$count1[0] = $todayOnline;
$count2[0] = $todayOffline;
$count3[0] = $todayIdle;

$conn->close();
$data = array("online" => $count1, "offline" => $count2, "todayOnline" => $todayOnline, "todayOffline" => $todayOffline, "Idle" => $count3);
header("Content-Type: application/json");
echo json_encode($data);
?>