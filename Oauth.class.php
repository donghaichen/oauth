

<?php
/**
 * 本类继承了QQ、微博、微信公主号登录，使用中如感到头晕眼花等症状，请及时联系作者！
 * Author: donghaichen <chendonghai888@gmail.com>
 * DateTime: 16/1/28 19:11
 */

namespace App\Auth;

class Oauth
{

    protected $loginPath = 'auth/login';
    protected $redirectPath = 'user/';
    protected $username = 'mobile';

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }



    public function showWeiboForm(Request $request)
    {

        $code = $request['code'];
        $state = $request['state'];
        $config = config('app.weibo');
        $appid = $config['appid'];
        $appsecret = $config['appsecret'];
        $redirect_url = urlencode('http://test.com/auth/weibo');

        if ($code && $state == session('state')) {
            $token_url = "https://api.weibo.com/oauth2/access_token";
            $data = "client_id=$appid&client_secret=$appsecret&grant_type=authorization_code&redirect_uri=";
            $data .= "$redirect_url&code=$code";
            $result = json_decode($this->http_request($token_url, $data), true);
            $weibo_token = $result['access_token'];
            $uid = $result['uid'];
            $user_id = User::where('weibo_token', $weibo_token)->first()['id'];
            if (!$user_id) {
                $user_info_url = "https://api.weibo.com/2/users/show.json?access_token=$weibo_token&uid=$uid";
                $user_info = json_decode($this->http_request($user_info_url), true);
                $gender = $user_info['gender'] == 'f' ? '0' : '1';
                $user_info = array(
                    'openid' => $weibo_token,
                    'nickname' => $user_info['screen_name'],
                    'sex' => $gender,
                    'headimgurl' => $user_info['avatar_large'],
                    'description' => $user_info['description'],
                );
                $user_id = $this->authRegister($user_info, 'weibo');
            }
            Auth::loginUsingId($user_id);
            return Auth::check() ? redirect($this->redirectPath()) : redirect($config['redirect_url']);
        } else {
            session(['state' => 'mengniang' . time() . rand(1000, 9999)]);
            $state = session('state');
            $authorization_url = 'https://api.weibo.com/oauth2/authorize?client_id=' . $appid;
            $authorization_url .= "&response_type=code&redirect_uri=$redirect_url&state=$state";
            return redirect($authorization_url);
        }


    }


    public function showQqForm(Request $request)
    {

        $code = $request['code'];
        $state = $request['state'];
        $config = config('app.qq');
        $appid = $config['appid'];
        $appsecret = $config['appsecret'];
        $redirect_url = urlencode('http://test.com/?auth=qq');//测试
//        $redirect_url = urlencode(config('app.url').'auth/qq'); //生产

        if ($code && $state == session('state')) {
            $access_token_url = 'https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&client_id=' . $appid;
            $access_token_url .= "&client_secret=$appsecret&code=$code&redirect_uri=$redirect_url";
            parse_str($this->http_request($access_token_url), $result);
            $openid__url = 'https://graph.qq.com/oauth2.0/me?access_token=' . $result['access_token'];
            $openid = $this->http_request($openid__url);
            $start = strpos($openid, '(');
            $openid = json_decode(trim(substr($openid, $start + 1, strrpos($openid, ')') - $start - 1)), true)['openid'];
            $user_id = User::where('qq_token', $openid)->first()['id'];
            if (!$user_id) {
                $user_info_url = 'https://graph.qq.com/user/get_user_info?access_token=' . $result['access_token'];
                $user_info_url .= "&oauth_consumer_key=$appid&openid=$openid";
                $user_info = json_decode($this->http_request($user_info_url), true);
                $gender = $user_info['gender'] == '女' ? '0' : '1';
                $user_info = array(
                    'openid' => $openid,
                    'nickname' => $user_info['nickname'],
                    'sex' => $gender,
                    'headimgurl' => $user_info['figureurl_qq_2'],
                    'description' => '',
                );
                $user_id = $this->authRegister($user_info, 'qq');
            }
            Auth::loginUsingId($user_id);
            return Auth::check() ? redirect($this->redirectPath()) : redirect($config['redirect_url']);
        } elseif (!$code && $state) {
            $message['msg'] = '您不同意授权，请选择其他方式登录！';
            return response()->json($message);
        } else {
            session(['state' => 'mengniang' . time() . rand(1000, 9999)]);
            $state = session('state');
            $authorization_url = 'https://graph.qq.com/oauth2.0/authorize?response_type=code&';
            $authorization_url .= "redirect_uri=$redirect_url&client_id=$appid&state=$state";
            return redirect($authorization_url);
        }


    }

    /**
     * 微信登录授权接口
     * 获取用户信息日限额500万，access_token，access_token网页授权获取用户信息无单日限额所以暂时不考虑刷新access_token
     * 更多微信接口频率限制说明：http://mp.weixin.qq.com/wiki/0/2e2239fa5f49388d5b5136ecc8e0e440.html
     * @return \Illuminate\Http\Response
     */
    public function showWechatForm(Request $request)
    {
        $code = $request['code'];
        $state = $request['state'];
        $config = config('app.wechat');
        $appid = $config['appid'];
        $appsecret = $config['appsecret'];
        $redirect_url = urlencode(config('app.url') . 'auth/wechat');
        if ($code && $state == session('state')) {
            $access_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
            $access_token_url .= "?appid=$appid&secret=$appsecret&code=$code&grant_type=authorization_code";
            $result = json_decode($this->http_request($access_token_url), true);
            $user_info_url = 'https://api.weixin.qq.com/sns/userinfo';
            $user_info_url .= '?access_token=' . $result['access_token'] . '&openid=' . $result['openid'];
            $user_info = json_decode($this->http_request($user_info_url), true);
            $user_id = User::where('wechat_token', $user_info['openid'])->first()['id'];
            $user_info['description'] = '';
            $user_id = $user_id ? $user_id : $this->authRegister($user_info, 'wechat');
            Auth::loginUsingId($user_id);
            return Auth::check() ? redirect($this->redirectPath()) : redirect($config['redirect_url']);
        } elseif (!$code && $state) {
            $message['msg'] = '您不同意授权，请选择其他方式登录！';
            return response()->json($message);
        } else {
            session(['state' => 'mengniang' . time() . rand(1000, 9999)]);
            $state = session('state');
            $wechat_oauth_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=";
            $wechat_oauth_url .= "$redirect_url&response_type=code&scope=snsapi_userinfo&state=$state#wechat_redirect";
            return redirect($wechat_oauth_url);
        }


    }

    //HTTP请求（支持HTTP/HTTPS，支持GET/POST）

    public function http_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }


    private function authRegister($data, $type)
    {
        $user = User::create([
            $type.'_token'   => $data['openid'],
            'nickname'       => $data['nickname'],
            'gender'         => $data['sex'],
            'description'    => $data['description'],
        ]);
        $avatar =  $data['headimgurl'];
        $user_id = $user->id;
        $avatar = $this->getImage($avatar, public_path()."/upload/avatar/$user_id/",$type.".jpg");
        if($avatar){
            switch($type)
            {
                case 'wechat';
                    $avatar = [2,0,1,0,0];
                    break;
                case 'qq';
                    $avatar = [3,0,0,1,0];
                    break;
                case 'weibo';
                    $avatar = [4,0,0,0,1];
            }

            $avatar = json_encode($avatar);
            User::where('id', $user_id)->update(['avatar' => $avatar ]);
        }
        return $user_id;
    }

    /*
    *功能：php完美实现下载远程图片保存到本地
    *参数：文件url,保存文件目录,保存文件名称，使用的下载方式
    *当保存文件名称为空时则使用远程文件原来的名称
    */
    private function getImage($url,$save_dir,$filename)
    {
        !file_exists($save_dir) ? mkdir($save_dir,0777,true) : '' ;
        $img = http_request($url);
        $fp2 = @fopen($save_dir.$filename,'a');
        fwrite($fp2,$img);
        fclose($fp2);
        unset($img,$url);
        return $filename;
    }



}