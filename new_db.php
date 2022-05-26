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
for ($i = 0; $i < 7; $i++) {
    $count1[$i] = 0;
    $count2[$i] = 0;
    $count3[$i] = 0;
}
$todayIdle = 0; $todayOffline = 0; $todayOnline = 0;
for ($i = 0; $i < 7; $i++) {
    $count[$i] = 0;
    }
if (mysqli_num_rows($result) > 0) {
    // output data of each row
    while($row = mysqli_fetch_array($result)) {
       //echo "id: " . $row["imei"]. "<br>";
 
        for ($i=1; $i < 7; $i++) { 
            $date = "-" . $i . " days";
            $days_ago = date('m/d/Y', strtotime($date, strtotime('today')));
            $sql1 = "SELECT * FROM gs_object_data_" . $row["imei"] . " WHERE dt_server LIKE '" . $days_ago .  "%'"; // its correct
        
            $result1 = $conn->query($sql1);
            //echo $sql1;
            //var_dump($row['imei'],$days_ago, mysqli_num_rows($result1));
            if (mysqli_num_rows($result1) > 0) {
                $count1[$i]++;
            } else {
                $count2[$i]++;
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
}

$count1[0] = $todayOnline;
$count2[0] = $todayOffline;
$count3[0] = $todayIdle;

$conn->close();
$data = array("online" => $count1, "offline" => $count2, "todayOnline" => $todayOnline, "todayOffline" => $todayOffline, "Idle" => $count3);
header("Content-Type: application/json");
echo json_encode($data);
?>