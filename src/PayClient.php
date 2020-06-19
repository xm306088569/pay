<?php
namespace Ycpfzf\Pay;
use Ycpfzf\Pay\Drives\Alipay;
use Ycpfzf\Pay\Drives\Wechat;


class PayClient
{
    protected $config;
    protected $money=0;
    protected $subject='订单支付';
    protected $out_trade_no;
    protected $type='alipay';
    protected $openid;  //JSAPI支付必须传openid

    protected $methods=[
        'wechat'=>['mp','miniapp','wap','scan','pos','app','transfer','redpack','groupRedpack'],
        'alipay'=>['web','wap','app','pos','scan','transfer','mini']
    ];
    function __construct($config=[])    {
        $this->config=$config;
    }

    protected function getConfig(){
        $config=$this->config[$this->type];
        if(empty($config)){
            throw new \Exception('bad config');
        }         
        if($this->type==='alipay'){
            $config['ali_public_key']=$this->getAliKey($config['ali_public_key']);
            $config['private_key']=$this->getAliKey($config['private_key']);
        }
        return $config;
    }


    protected function getAliKey($key){
        if(substr($key,-4)==='.crt'){
            if(is_file($key)){
                $key=file_get_contents($key);
            }else{
                throw new \Exception($key.' does not exist');
            }
        }
        return $key;
    }

    protected function getPayInstance(){
        if($this->type=='wechat'){
            return new Wechat($this->getConfig());
        }
        return new Alipay($this->getConfig());
    }

    function money($money)    {
        $this->money=abs(floatval($money));
        return $this;
    }

    function subject($str){
        $this->subject=$str;
        return $this;
    }

    function outTradeNo($outid){
        $this->out_trade_no=$outid;
        return $this;
    }

    function wechat(){
        $this->type='wechat';
        return $this;
    }

    function alipay(){
        $this->type='alipay';
        return $this;
    }

    function openid($openid){
        $this->openid=$openid;
        return $this;
    }

    function find($order=[]){
        if($order){
            return $this->getPayInstance()->find($order);
        }
        if(empty($this->out_trade_no)){
            throw new \Exception('Undefined out_trade_no');
        }

       // $order['out_trade_no']=$this->out_trade_no;
        return $this->getPayInstance()->find($this->out_trade_no);
    }

    function cancel($order=[]){
        if($order){
            return $this->getPayInstance()->cancel($order);
        }
        if(empty($this->out_trade_no)){
            throw new \Exception('Undefined out_trade_no');
        }
        return $this->getPayInstance()->cancel($this->out_trade_no);
    }

    function close($order=[]){
        if($order){
            return $this->getPayInstance()->close($order);
        }
        if(empty($this->out_trade_no)){
            throw new \Exception('Undefined out_trade_no');
        }
        return $this->getPayInstance()->close($this->out_trade_no);
    }


    function __call($name, $arguments)
    {
        if(in_array($name,$this->methods[$this->type])){
            if(empty($this->out_trade_no)){
                throw new \Exception('Undefined out_trade_no');
            }
            if($this->type=='wechat'){
                $order = [
                    'out_trade_no' => $this->out_trade_no,
                    'total_fee' => $this->money*100,
                    'body' => $this->subject,
                ];
                if(!empty($this->openid)){ //JSAPI支付必须传openid
                    $order['openid']=$this->openid;
                }
            }else{
                $order = [
                    'out_trade_no' => $this->out_trade_no,
                    'total_amount' => $this->money,
                    'subject' => $this->subject,
                ];
            }
            return $this->getPayInstance()->pay($name,$order);
        }else{
            return $this->getPayInstance()->$name($arguments);
        }
    }

    function refund($type='app',$orderTotalFee=0){
        if(empty($this->out_trade_no)){
            throw new \Exception('Undefined out_trade_no');
        }
        if($this->type=='wechat'){
            if(empty($orderTotalFee)){
                $orderTotalFee=$this->money;
            }
            $order = [
                'out_trade_no' => $this->out_trade_no,
                'out_refund_no' => time().rand(100,999),  //退款单号，多次退款时不能重复
                'total_fee' => $orderTotalFee*100, //这里是订单金额，单位是分
                'refund_fee' => $this->money*100, //申请退款金额,不能大于订单金额，单位是分
                'refund_desc' => $this->subject,
                'type' => $type, //手机app是app 小程序是miniapp 公众号不要
                'refund_account'=>'REFUND_SOURCE_RECHARGE_FUNDS'  //使用余额退款,否则不要
            ];

        }elseif($this->type='alipay'){
            $order = [
                'out_trade_no' => $this->out_trade_no,
                'refund_amount' => $this->money,
            ];

        }
        return $this->getPayInstance()->refund($order);
    }

    function notify($callback){
        $this->getPayInstance()->notify($callback);
    }

}