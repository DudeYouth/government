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


// $start_url = "http://thzwb.thnet.gov.cn/appointment/getOrderDivisionList.action";
// $url1 = "http://thzwb.thnet.gov.cn/bsy/Service/listServiceItemSimple.action";
// $url2 = "http://thzwb.thnet.gov.cn/appointment/getlistServiceOrder.action";
// $json = request($start_url,['division_code'=>'440106']);
// file_put_contents('./dist/DivisionList.json',json_encode($json));
// $arr = [];
// foreach($json as $s){
//     $res = request($url1,['org_code'=>$s["divisionCode"],'Return_records'=>100]);
//     foreach($res['list'] as $r){
//         $arr[] = $r;
//     }
// }
// file_put_contents('./dist/ServiceItemSimple.json',json_encode($arr));


//file_put_contents('./main/data.json',json_encode($arr));

class Main{
    function __construct(){
        $this ->arr = [];
        $this ->get_time_url = "http://thzwb.thnet.gov.cn/appointment/getlistServiceOrder.action";
        $this ->submit_url = "http://thzwb.thnet.gov.cn/appointment/getOrderServiceItem.action";
        $item = file_get_contents('./dist/ServiceItemSimple.json');
        $list = file_get_contents('./dist/DivisionList.json');
        $this ->item = json_decode($item,true);
        $this ->list = json_decode($list,true);
        $this ->count = 0;
        $this ->get_data();
    }
    public function get_data(){
        $src = "./main/data.txt";
        $file = fopen($src, "r");
        while(! feof($file))
        {
            $str = fgets($file);
            $time = time();
            $date = date('Y-m-d',$time);
            $dir = './history/'.$date;
            if( !empty($str) ){
                $data = explode(' ',$str);
                if( count($data)==6 ){
                    $this ->arr[]= $data;//fgets()函数从文件指针中读取一行
                }
            }
            if( !file_exists($dir) ){
                mkdir($dir);
            }   
            file_put_contents($dir.'/'.$time.'.txt',file_get_contents($src)); // 缓存文件
            //unlink($src);
        }
    }
    // 执行接口
    public function exec(){
        $time = time();
        foreach( $this ->arr as &$v ){
            $service_code = null;
            $division_code = null;
            if( !empty($v) ){
                $t = strtotime($v[5])+86400;
                // 已经过期
                if( $time>=$t ){
                    $this ->echo_file("fail",$v);
                    $v = null;
                }else{
                    foreach( $this ->list as $l ){
                        if( $l['divisionName']==$v[3] ){
                            $division_code = $l['divisionCode'];
                            break;
                        }
                    }
                    foreach( $this ->item as $i ){
                        if( $i['name']==$v[4] ){
                            $service_code = $i['service_code'];
                            break;
                        }
                    }
                    // 获取预约的时间
                    if( !empty($service_code)&&!empty($division_code) ){
                        $t_data = $this ->get_time($service_code,$division_code);
                        echo json_encode($t_data);
                        if( !empty($t_data) ){
                            // $res = $this ->submit($service_code,$division_code,$t_data,$v);
                            // if( $res["is_order"]=='Y' ){
                            //     $this ->echo_file("success",$v);
                            // }else{
                            //     $res = $this ->submit($service_code,$division_code,$t_data,$v);
                            //     if( $res["is_order"]=='Y' ){
                            //         $this ->echo_file("success",$v);
                            //     }else{
                            //         $this ->echo_file("fail",$v);
                            //     }
                            // }
                        }else{
                            $this ->echo_file("fail",$v);
                            $v = null;
                        }
                    }
                }
            }

        }
    }
    // 获取预约时间表
    private function get_time($division_code,$service_code){
        $res = request($this ->get_time_url,['org_code'=>$division_code,'daySize'=>30,'dayIndex'=>1,'service_item_code'=>$service_code]);
        if( !empty($res) ){
            foreach( $res as $key=>$value ){
                if( $key!=='apDate' ){
                    foreach( $value as $v ){
                        if($v['is_free']=='Y'){
                            return $v;
                        }
                    }
                }
            }   
        }
        return false;
    }
    // 提交预约
    private function submit($service_code,$division_code,$t_data,$v){
        return request($this ->submit_url,[
            'address'=>'政务中心',
            'addressMsg'=>'政务中心',
            'addressPhone'=>37690333,
            'certificate'=>$v[1],
            'org_code'=>$division_code,
            'phone'=>$v[2],
            'predate'=>$t_data['apdate'],
            'service_item_code'=>$service_code,
            'service_item_name'=>$v['4'],
            'time'=>$t_data['office_hour'],
        ]);
    }
    // 输出文件
    private function echo_file($type='fail',$data){
        if( $type=='fail' ){
            $src = './fail/'.date('Y-m-d',time());
        }else{
            $src = './success/'.date('Y-m-d',time());
        }
        file_put_contents($src,implode(' ',$data).'\n',FILE_APPEND);

    }
}
$app = new Main();
$app ->exec();
