<?php
set_time_limit(0);
date_default_timezone_set('PRC');
// 请求方法
function request($url,$param){
    $headers = [
        "Host"              => "thzwb.thnet.gov.cn",
        "Referer"           => "http://thzwb.thnet.gov.cn/web/jsp/bsy/appointment.jsp",
        "User-Agent"        => "Mozilla/5.0 (Windows NT 10.0; …) Gecko/20100101 Firefox/58.0",
        "Accept"            => "application/json, text/javascript, */*; q=0.01",
        "X-Requested-With"  => "XMLHttpRequest",
        "Accept-Encoding"   => "gzip, deflate",
        "Accept-Language"   => "zh-CN,zh;q=0.8",
        "Cookie"            => "JSESSIONID=5045249BAC0CE77EA3079C986104A047; Hm_lvt_e7d4cfc620a3c5671317bc61d90cfb31=1523543444,1523628628,1523715719,1523763727; Hm_lpvt_e7d4cfc620a3c5671317bc61d90cfb31=1523764110"
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
        $this ->cache = [];
        $this ->get_time_url = "http://thzwb.thnet.gov.cn/appointment/getlistServiceOrder.action";
        $this ->get_data_url = "http://thzwb.thnet.gov.cn/queryDeptBespeakList.action";
        $this ->get_record_url = "http://thzwb.thnet.gov.cn/getRecordCount.action";
        $this ->submit_url = "http://thzwb.thnet.gov.cn/appointment/getOrderServiceItem.action";
        $item = file_get_contents('./dist/ServiceItemSimple.json');
        $list = file_get_contents('./dist/DivisionList.json');
        $this ->item = json_decode($item,true);
        $this ->list = json_decode($list,true);
        $this ->status = 0;
        $this ->count = 0;
    }
    public function get_data(){
        $src = "./main/".date('Y-m-d',time()).'.txt';
        if( !file_exists($src) ){
            file_put_contents('error.log',$src.'文件不存在！'."\n",FILE_APPEND);
            return;
        }
        $file = fopen($src, "r");
        $arr = [];
        while(! feof($file))
        {
            $arr[] = fgets($file);
        }
        fclose($file);
        $arr = array_filter($arr);
        foreach( $arr as $k=>$str ){
            $time = time();
            $date = date('Y-m-d',$time);
            $dir = './history/'.$date;
            if( $str ){
                $data = explode(' ',$str);
                $data = array_filter($data);
                if( count($data)>=5 ){
                    $this ->arr[$k]= $data;//fgets()函数从文件指针中读取一行
                }
            }
            if( !file_exists($dir) ){
                mkdir($dir);
            }   
            file_put_contents($dir.'/'.$time.'.txt',file_get_contents($src)); // 缓存文件
            
        }
        unlink($src);
    }
    // 执行接口
    public function exec(){
        $this ->get_data();
        foreach( $this ->arr as &$v ){
            $service_code = null;
            $division_code = null;
            $type = 1;
            $data = [];
            if( !empty($v) ){
                $dep = trim($v[3]);
                $op = trim($v[4]);
                foreach( $this ->list as $l ){
                    if( trim($l['divisionName'])==$dep ){
                        $division_code = $l['divisionCode'];
                        break;
                    }
                }
                foreach( $this ->item as $i ){
                    if( trim($i['name'])==$op ){
                        $service_code = $i['service_code'];
                        break;
                    }
                }
                // 如果service_code不存在则采用特殊方式请求
                if( empty($service_code) ){
                    if( isset($this ->cache[$dep]) ){
                        $data = $this ->cache[$dep];
                    }else{
                        $data = file_get_contents($this ->get_data_url."?deptName=".$dep);
                        $data = json_decode($data,true);
                        $this ->cache[$dep] = $data;
                    }
                    if( $rd = $this ->get_date($data,$v,$op) ){
                        $service_code = $rd['service_code'];
                        $t_data = $rd['date'];
                        $type = 2;
                    }
                // 正常情况的处理方式
                }else{
                    $type = 1;
                    $t_data = $this ->get_time($division_code,$service_code);
                }
                if( !empty($t_data) ){
                    // 正常的提交方式
                    if( $type == 1 ){
                        $res = $this ->submit($division_code,$service_code,$t_data,$v);
                    }else{
                        $res = $this ->_submit($division_code,$service_code,$t_data,$v);
                    }
                    if( $res["is_order"]=='Y' ){
                        // $this ->status = 1;
                        $v[5] =  trim($t_data['apdate']).' '.trim($t_data['office_hour']);
                        $this ->echo_file("success",$v);
                    }else{
                        $res = $this ->submit($division_code,$service_code,$t_data,$v);
                        if( $res["is_order"]=='Y' ){
                            // $this ->status = 1;
                            $this ->echo_file("success",$v);
                        }else{
                            $this ->echo_file("fail",$v);
                        }
                    }
                }else{
                    $this ->echo_file("fail",$v);
                    $v = null;
                }
            }

        }
        // if( $this ->status==1 ){
        //     exit;
        // }
    }
    private function get_date($data,$v,$op){
        if( isset($data['Biz']) ){
            $t_data = [];
            foreach( $data['Biz'] as $dv ){
                if( trim($dv['BizName'])==$op ){
                    $flag = true;
                    for( $i=0;$i<30;$i++ ){
                        $time = strtotime('+'.$i.' day');
                        $week = date('w',$time);
                        $day = date('d',$time);
                        // 过滤周末
                        if( $week==0||$week==6 ){
                            continue;
                        }
                        if( $day==30||$day==1||$day=="01" ){
                            continue;
                        }
                        $d = date('Y-m-d',$time);
                        $record = [];
                        // 如果获取记录失败，将不再获取记录
                        if( $flag ){
                            $record = file_get_contents($this ->get_record_url.'?BizId='.$dv['BizID'].'&date='.$d);
                            $record = json_decode($record,true);
                        }
                        if( isset($record['Record']) ){
                            if( isset($record['Record'][0]) ){
                                if( isset($record['Record'][0]['TimeRecord']) ){
                                    $time_record = $record['Record'][0]['TimeRecord'];
                                    foreach( $dv['TimeConfig'] as $tc ){
                                        foreach( $time_record as $tr ){
                                            if( $tc['YYSTime']==$tr['Time'] ){
                                                $arr = explode(":",$tr['Time']);
                                                if( $d==date('Y-m-d')&&(int)date('H')>=(int)$arr[0]+1 ){
                                                    break;
                                                }
                                                if( (int)$tc['YYMax']>(int)$tr['Count'] ){
                                                    return  ['date'=>['apdate'=>$d,'office_hour'=>$tc['YYSTime']],'service_code'=>$dv['BizID']];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        // 获取记录失败
                        }else{
                            $flag = false;  // 关闭开关，不再获取记录
                            foreach( $dv['TimeConfig'] as $tc ){
                                return  ['date'=>['apdate'=>$d,'office_hour'=>$tc['YYSTime']],'service_code'=>$dv['BizID']];
                            }
                        }
                    }
                }
            }
        }
        return false;
    }
    // 获取预约时间表
    private function get_time($division_code,$service_code){
        $res = request($this ->get_time_url,['org_code'=>$division_code,'daySize'=>30,'dayIndex'=>1,'service_item_code'=>$service_code]);
        if( !empty($res) ){
            for($i=0;$i<=29;$i++){
                foreach( $res as $key=>$value ){
                    if( $key!=='apDate' ){
                        if( isset($value[$i]) ){
                            if($value[$i]['is_free']=='Y'){
                                return $value[$i];
                            }
                        }
                    }
                }   
            }

        }
        return false;
    }
    // 提交预约
    private function submit($division_code,$service_code,$t_data,$v){
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
    // 特殊事项的提交预约
    private function _submit($division_code,$service_code,$t_data,$v){
        return request($this ->submit_url,[
            'address'=>'天河北路894号（咨询电话：020-38470847）',
            'addressMsg'=>3,
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
            $src = './fail/'.date('Y-m-d',time()).'.txt';
        }else{
            $src = './success/'.date('Y-m-d',time()).'.txt';
        }
        file_put_contents($src,implode(' ',$data)."\n",FILE_APPEND);

    }
}
$app = new Main();
$app ->exec();

while( true ){
    sleep(3);
    if( date("H")==0&&date('i')==0 ){
        $app ->exec();
    }
}