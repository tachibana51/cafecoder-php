<!DOCTYPE HTML>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <title>ランキング</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/t/bs-3.3.6/jqc-1.12.0,dt-1.10.11/datatables.min.css" />
    <script src="https://cdn.datatables.net/t/bs-3.3.6/jqc-1.12.0,dt-1.10.11/datatables.min.js"></script>
    <script src="https://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js"></script>
    <script>
        jQuery(function($) {
            $.extend($.fn.dataTable.defaults, {
                language: {
                    url: "http://cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                }
            });
            $("#result-table").DataTable({
                order: [
                    [0, "desc"]
                ]
            });
        });
    </script>
</head>

<body>
<?php 
include_once("../template/nav.php");
include_once("../util/util.php");
echo_nav_card($_GET["contest_id"]);
?>
<table class="table table-bordered">
<thead>
    <tr>
        <th>RANK</th>
        <th>Username</th>
        <th>Point</th>
        <th>A</th>
        <th>B</th>
        <th>C</th>
        <th>D</th>
        <th>E</th>
        <th>F</th>
    </tr>
</thead>
<tbody>

<?php

if(!isset($_GET["contest_id"])){
    echo "contest_idを指定してください。";
    exit();
}

$contest_id = $_GET["contest_id"];
try{
include_once "../database/connection.php";
include_once "../util/util.php";
$con = new DBC();
}catch(Exception $e){
    echo "RANKING INIT ERROR";
    exit();
}
//update all user submit
try{
$rec = $con->prepare_execute("SELECT username, user_id, problem, code_session FROM uploads LEFT JOIN users ON uid=user_id WHERE contest_id=?",array($contest_id));
}catch(Exception $e){
    echo "DB SELECT ERROR 1";
    exit();
}
$all_path = array();
foreach($rec as $line){
    $user_code_path = get_uploaded_session_path($line["username"], $contest_id, $line["problem"], $line["code_session"]).".result";
    if(!file_exists($user_code_path)){
        continue;
    }
    try{
    $fp = fopen($user_code_path,"r");
    $csv = fgetcsv($fp);
    $result = $csv[3];
    }catch(Exception $e){
        echo "csv load error";
    }
    try{
    $con->prepare_execute("UPDATE uploads SET result=? WHERE code_session=?",array($result, $line["code_session"]),array($result));
    }catch(Exception $e){
        echo("DB UPDATE ERROR");
    }
}
//get first ac
try{
$con->prepare_execute("DROP VIEW IF EXISTS first_ac",array());
$con->prepare_execute("CREATE VIEW first_ac AS SELECT user_id, upload_date, result, problem  FROM uploads a WHERE a.upload_date BETWEEN (SELECT start_time FROM contests WHERE contest_id=?) AND (SELECT end_time FROM contests WHERE contest_id=?) GROUP BY user_id, problem,result,upload_date HAVING a.result='AC' AND upload_date=(SELECT MIN(upload_date) FROM uploads b WHERE a.user_id=b.user_id AND a.problem=b.problem) ORDER BY upload_date ASC",array($contest_id,$contest_id));
}catch(Exception $e){
    echo("DB VIEW ERROR");
    var_dump($e);
    exit();
}
//get point
try{
    $rec=$con->prepare_execute("SELECT username,user_id, SUM(point) AS sum_point FROM first_ac,users,problem WHERE user_id=uid AND problem.problem_id=first_ac.problem GROUP BY user_id ORDER BY sum_point DESC",array($contest_id,$contest_id));
    // var_dump($rec);
    $enum_problem = array("A"=>0,"B"=>1,"C"=>2,"D"=>3,"E"=>4,"F"=>5);
    foreach ($rec as $rank => $line) {
        $now_state=$con->prepare_execute("SELECT first_ac.problem,result,point FROM first_ac,problem WHERE user_id=? AND first_ac.problem=problem_id ORDER BY problem ASC",array($line["user_id"]));
        echo '<tr><th>';
        echo (int)($rank)+1;
        echo '</th>';
        echo '<th>';
        echo $line["username"];
        echo '</th>';
        echo '<th>';
        echo $line["sum_point"];
        echo '</th>';
        for($i=0,$j=0; $i < 6; $i++){
            if($enum_problem[$now_state[$j]["problem"]] == $i){
                echo '<th>';
                echo $now_state[$j]["point"];
                echo '</th>';
                $j++;
            }else{
                echo '<th>';
                echo " - ";
                echo '</th>';

            }
        }
        echo '</tr>';
    }
}catch(Exception $e){
    var_dump($e);
    echo "DB SELECT ERROR 2";
}
?>
</tbody>
</table>
<?php
include_once("../util/util.php");
echo_nav_card_footer();
?>
</body>