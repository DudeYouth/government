<?php

// 请求方法
function request($url,$param){
    $headers = [
        "Host"              => "thzwb.thnet.gov.cn",
        "Referer"           => "http://thzwb.thnet.gov.cn/web/jsp/bsy/appointment.jsp",
        "User-Agent"        => "Mozilla/5.0 (Windows NT 10.0; …) Gecko/20100101 Firefox/58.0",
    ];
    $con = curl_init((string)$url);
    $param = http_build_query($param);
    curl_setopt($con, CURLOPT_HEADER, false);
    
    curl_setopt($con, CURLOPT_HEADER, 0);    
    curl_setopt($con, CURLOPT_POSTFIELDS, $param);
    curl_setopt($con, CURLOPT_POST,true);
    curl_setopt($con, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($con, CURLOPT_TIMEOUT,3);
    curl_setopt($con, CURLOPT_HTTPHEADER, $headers);
    $data = curl_exec($con); 
    curl_close($con);
    return json_decode($data,true);
}


$start_url = "http://thzwb.thnet.gov.cn/appointment/getOrderDivisionList.action";
$url1 = "http://thzwb.thnet.gov.cn/bsy/Service/listServiceItemSimple.action";
$url2 = "http://thzwb.thnet.gov.cn/appointment/getlistServiceOrder.action";
$json = request($start_url,['division_code'=>'440106']);
file_put_contents('./dist/DivisionList.json',json_encode($json));
$arr = [];
foreach($json as $s){
    $res = request($url1,['org_code'=>$s["divisionCode"],'Return_records'=>100]);
    foreach($res as $r){
        $arr[] = $r;
    }
}
file_put_contents('./dist/ServiceItemSimple.json',json_encode($arr));

