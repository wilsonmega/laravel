<?php

namespace api\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use api\Http\Requests;
use api\Http\Controllers\Controller;

class GetList extends Controller
{
    private $ErrMsg = 'OK';
    private $ResultCount = 0;
    private $ChannelList = array();
    private $TOKEN = 'EagleFlyFree!';
    private $Vendor = '327c84b3-3a8b-5e93-8582-89478b0202b4';  // FLY

    protected $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    private function token_str ($ripstr, $secretstr, $expirestr) {
      return rtrim(strtr(base64_encode(md5($ripstr.$secretstr.$expirestr, true)), '+/', '-_'), '=');
    }

    public function index() {
      $secret = 'UseTheForce!';
      $rip = ($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'127.0.0.1';
      $expire = time() + 21500;	// 6 hours
      $str_a = dechex($expire);
      $str_b = $this->token_str($rip, $secret, $expire);
      $this->TOKEN='a='.$str_a.'&b='.$str_b;

      $this->Vendor=($this->request->exists('vendor')&($this->request->input('vendor')!=null))?$this->request->input('vendor'):$this->Vendor;

      try {
        DB::connection()->getPdo();
      } catch (\Exception $e) {
        $this->ErrMsg='Database error!';
        $this->disposeResult();	
      }

      $VD = DB::table('Vendor')
              ->where('vdid', '=', $this->Vendor)
              ->get();
      if (count($VD)<>1) {
        $this->ErrMsg='601 '.$this->Vendor.' Error!';
        $this->disposeResult();
      }

      $LiveClass=array();
      $LC = DB::table('LiveClass')->get();
      $LC = json_decode(json_encode($LC), true);  // Obj type to Array
      $arraycount=count($LC);
      for ($i=0; $i<$arraycount;$i++) {
        $LiveClass[$LC[$i]['lcid']]=$LC[$i]['title'];
      }
      unset($LC);
      // select * from LiveSource where status = 1 and vdid=$Vendor;
      $LiveSource = DB::table('LiveSource')
                      ->where('status', '=', 1)
                      ->where('vdid', '=', $this->Vendor)
                      ->get();
      $LiveSource = json_decode(json_encode($LiveSource), true);
      // select * from LiveMeta where status = 1 and vdid=$Vendor order by lcn;
      $ChList = DB::table('LiveMeta')
                    ->where('status', '=', 1)
                    ->where('vdid', '=', $this->Vendor)
                    ->orderby('lcn')->get();
      $ChList = json_decode(json_encode($ChList), true);
      $arraycount=count($ChList);
      for ($i=0; $i<$arraycount;$i++) {
         if ($ChList[$i]['status']) {
           $d0=$d1=$d2=$d3=$d4=0;
           $source = array();
           foreach ($LiveSource as $ls) {
             if ($ChList[$i]['lmid'] === $ls['lmid']){
               switch($ls['definition']) {
                 case 1:
                   $d1++;
                   $type='標清';
                   if ($d1>1) $type=$type.$d1;
                   break;
                 case 2:
                   $d2++;
                   $type='高清';
                   if ($d2>1) $type=$type.$d2;
                   break;
                 case 3:
                   $d3++;
                   $type='超清';
                   if ($d3>1) $type=$type.$d3;
                   break;
                 default:
                   $d0++;
                   $type='不明';
                   if ($d0>1) $type=$type.$d0;
               }
               $source[] = array(
                 'type'=> $type,
                 'url'=> $ls['url'].$this->TOKEN
               );
             }
           }
           $this->ChannelList[] = array(
             'class' => $LiveClass[$ChList[$i]['lcid']],
             'name' => $ChList[$i]['title'],
             'number' => $ChList[$i]['lcn'],
             'logo' => ($ChList[$i]['logo'])?$ChList[$i]['logo']:'',
             'current_show' => '',
             'next_show' => '',
             'source' => $source
           );
           unset($source);
         }
      }
      $this->disposeResult();
    }

    public function disposeResult() {
      $data = array(
		'timestamp' => time(),
		'message' => $this->ErrMsg,
		'count' => count($this->ChannelList),
		'channel_list' => $this->ChannelList
      );
      header('Content-Type: application/json; charset=utf-8');
      ob_start('ob_gzhandler');
      echo json_encode($data);
      ob_end_flush();
      exit();
    }

}
