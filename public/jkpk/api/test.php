<?php
$date = date('Y-m-d H:i:00');
echo $date;
echo "<hr>";
$time = strtotime($date);
echo $time;
echo "<hr>";
$fromto = date("Ymd His", $time);
echo $fromto;
echo "<hr>";
echo $xtime = time();
echo "<hr>";
echo $t = $xtime - $xtime % 3;
echo "<hr>";
echo date('Y-m-d H:i:s', $t);