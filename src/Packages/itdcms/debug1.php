<?php

use ITDCMS\System\Common\Http\Controllers\BaseController;

ini_set('max_execution_time', 0);
ini_set('memory_limit', -1);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

/**
 * Debug Controller - class for ITDC Debugger
 */
class DebugController extends BaseController
{
    public $tpl_file = 'debug';
    protected $check_authorization = false;
    private $_themes = array('light', 'dark');


    public $warning_params = array();

    public $chmod_folders = array();

    private $item;
    private $data;


    protected $open_methods = ['login', 'authcallback'];

    protected $google;

    public function __construct()
    {
        parent::__construct();


        if (!DEBUG_MODE) {
            exit('Access denied');
        }


        $this->resloader->clear();
        $this->resloader->setMerge(false);

        $this->warning_params = app('profiler')->getWarningParams();


        $this->chmod_folders = array(
            array('folder'=>'data', 'path'=>data_path(), 'level'=>1),
                array('folder'=>'data/cache', 'path'=>data_path().'/cache', 'level'=>2),
                array('folder'=>'data/logs', 'path'=>data_path().'/logs', 'level'=>2),
                array('folder'=>'data/logs/debug', 'path'=>data_path().'/logs/debug', 'level'=>3),
                array('folder'=>'data/sqls', 'path'=>data_path().'/sqls', 'level'=>2),
                array('folder'=>'data/updates', 'path'=>data_path().'/updates', 'level'=>2),
                array('folder'=>'public_html/cache', 'path'=>public_path().'/cache', 'level'=>1),
                array('folder'=>'public_html/cache/app', 'path'=>public_path().'/cache/app', 'level'=>2),
                array('folder'=>'public_html/cache/app/css', 'path'=>public_path().'/cache/app/css', 'level'=>3),
                array('folder'=>'public_html/cache/app/js', 'path'=>public_path().'/cache/app/js', 'level'=>3),
                array('folder'=>'public_html/cache/captcha', 'path'=>public_path().'/cache/captcha', 'level'=>2),
                array('folder'=>'public_html/cache/core', 'path'=>public_path().'/cache/core', 'level'=>2),
                array('folder'=>'public_html/cache/core/css', 'path'=>public_path().'/cache/core/css', 'level'=>3),
                array('folder'=>'public_html/cache/core/js', 'path'=>public_path().'/cache/core/js', 'level'=>3),
                array('folder'=>'public_html/uploads', 'path'=>public_path().'/uploads', 'level'=>1),
                array('folder'=>'public_html/uploads/avatars', 'path'=>public_path().'/uploads/avatars', 'level'=>2),
                array('folder'=>'public_html/uploads/other', 'path'=>public_path().'/uploads/other', 'level'=>2),
                array('folder'=>'public_html/uploads/photo', 'path'=>public_path().'/uploads/photo', 'level'=>2),
                array('folder'=>'public_html/uploads/photo/cache', 'path'=>public_path().'/uploads/photo/cache', 'level'=>3),
                array('folder'=>'public_html/uploads/photo/main', 'path'=>public_path().'/uploads/photo/main', 'level'=>3),
                array('folder'=>'public_html/uploads/photo/original', 'path'=>public_path().'/uploads/photo/original', 'level'=>3),
                array('folder'=>'public_html/uploads/tmp', 'path'=>public_path().'/uploads/tmp', 'level'=>2),
                array('folder'=>'public_html/uploads/video', 'path'=>public_path().'/uploads/video', 'level'=>2),
        );

        $data = app('profiler')->getDebugInfo();
        $this->data = array_reverse($data, true);
    }

    public function index($theme = 'light', $key = 0)
    {
        if (!in_array($theme, $this->_themes)) {
            $theme = 'light';
        }
        $this->tpl['theme'] = $theme;


        $this->load->css('debug/'.$theme.'/css/debug.css');

        $this->load->js('../css/debug/'.$theme.'/js/debug.js');


        $data = $this->getData();


        $current_key = $this->getCurrentKey();



        if (empty($key) && !empty($current_key)) {
            $key = $current_key;
        }


        $item = $this->getRow($current_key);

        $this->tpl['current_key'] = $current_key;
        $this->tpl['step'] = $key;
        $this->tpl['data'] = $data;
        $this->tpl['item'] = $item;





        $this->tpl['site_title'] = 'dbg: '.$current_key;
        return view('index');
    }

    public function getData()
    {
        return $this->data;
    }

    public function panel()
    {
        if (!in_array($theme, $this->_themes)) {
            $theme = 'light';
        }
        $this->tpl['theme'] = $theme;


        $this->load->css('debug/'.$theme.'/css/debug.css');

        $this->load->js('../css/debug/'.$theme.'/js/debug.js');


        $data = $this->getData();
        $current_key = array_shift(array_keys($data));

        if (empty($key)) {
            $key = $current_key;
        }

        $item = isset($data[$key]) ? $data[$key] : array();

        $this->tpl['item'] = $item;

        return view('panel');
    }

    public function getCurrentKey()
    {
        $data = $this->getData();
        $keys = array_keys($data);
        $current_key = array_shift($keys);
        return $current_key;
    }


    public function getRow($key)
    {
        $data = $this->getData();
        $item = isset($data[$key]) ? $data[$key] : array();
        return $item;
    }


    public function getCacheData()
    {
        $data = $this->cache_obj->getKeys();
        $prefix = $this->cache_obj->getPrefixKey();
        $length = strlen($prefix);
        $now_time = time();


        $return = [];
        foreach($data as $key) {
            if (substr($key, 0, $length) == $prefix) {
                $key = substr($key, $length);
            }

            if (substr($key, 0, 5) == 'sess_') {
                continue;
            }

            $info = $this->cache_obj->get_metadata($key);
            $expire_time = isset($info['expire']) ? $info['expire'] : 0;
            $mtime = isset($info['mtime']) ? $info['mtime'] : 0;


            $cdata = isset($info['data']) ? $info['data'] : null;
            $cdata_type = gettype($cdata);

            $cdata_length = 0;
            switch($cdata_type) {
                default:
                    $cdata_length = null;
                    break;

                case 'string':
                    $cdata_length = strlen($cdata);
                    break;

                case 'integer':
                case 'double':
                    $cdata_length = strlen($cdata);
                    break;

                case 'array':
                    $cdata_length = count($cdata);
                    break;

                case 'object':
                    $cdata_length = count(get_object_vars($cdata));
                    break;
            }





            $ex_time = $expire_time - $now_time;

            $arr = [];
            $arr['key'] = $key;
            $arr['data_type'] = $cdata_type;
            $arr['data_length'] = $cdata_length;
            $arr['mtime'] = date('Y-m-d H:i:s', $mtime);
            $arr['expire'] = date('Y-m-d H:i:s', $expire_time);
            $arr['expire_in'] = gmdate("G:i:s", $ex_time);

            $return[$key] = $arr;
        }

        ksort($return);

        $data['prefix'] = $prefix;
        $data['data'] = $return;



        return $data;
    }

    public function getCacheKeyData($key)
    {
        $info = $this->cache_obj->get_metadata($key);

        $expire_time = isset($info['expire']) ? $info['expire'] : 0;
        $mtime = isset($info['mtime']) ? $info['mtime'] : 0;
        $cdata = isset($info['data']) ? $info['data'] : null;

        $data = DumpHelper::getDump($cdata, 10, true);

        $html = '
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">'.$key.'</h4>
            </div>
            <div class="modal-body">
                '.$data.'
            </div>
        ';

        echo $html;
        exit();
    }

    public function getTab($tab, $key = null)
    {
        switch($tab) {
            default:
                $data = $this->getRow($key);
                break;

            case 'cache':
                $data = $this->getCacheData();
                break;
        }


        $this->load->view($this->router->class.'/tabs/'.$tab, array('row'=>$data));
    }




    public function clearCache($key)
    {
        if (empty($this->cache->memcached)) {
            $this->load->driver('cache');
        }
        $json = array();
        $json['status'] = 'ERROR';
        if (empty($key)) {
            exit(json_encode($json));
        }
        $status = $this->cache->memcached->delete($key);
        if (!$status) {
            exit(json_encode($json));
        }
        $json['status'] = 'OK';
        exit(json_encode($json));
    }


    public function clearApplicationCache()
    {
        sleep(2);
        $json = array();
        $json['status'] = 'ERROR';

        $status = $this->cache_obj->cleanApplicationCache();

        if ($status) {
            $json['status'] = 'OK';
        }

        exit(json_encode($json));
    }


    public function clearAllSessions()
    {
        $json = array();
        $json['status'] = 'ERROR';
        die('@TODO: Implement via new session class');
        $status = $this->session->deleteKeys(true);

        if ($status) {
            $json['status'] = 'OK';
        }

        exit(json_encode($json));
    }


    public function getAllSessions()
    {
        die('@TODO: Implement via new session class');

        $keys = $this->session->getAllKeys();

        return $return;
    }

    public function loadModal($key, $modal)
    {
        $row = $this->getRow($key);
        $this->tpl['row'] = $row;

        $html = '';
        switch($modal) {
            case 'phpinfo':
                $html = view('modals/phpinfo', array('wrapper'=>'none', 'tpl_file'=>false));
                break;

            case 'settings':
                $html = view('modals/settings', array('wrapper'=>'none', 'tpl_file'=>false));
                break;

            case 'user':
                $html = view('modals/user', array('wrapper'=>'none', 'tpl_file'=>false));
                break;

            case 'tools':
                $html = view('modals/tools', array('wrapper'=>'none', 'tpl_file'=>false));
                break;
        }
        die($html);
    }



    public function explainQuery()
    {
        $json = array();
        $json['status'] = 'ERROR';

        $query = $this->input->post('query');
        if (!preg_match('#^select#isU', $query)) {
            exit(json_encode($json));
        }
        $data = $this->db->query('EXPLAIN ' . $query)->result_array();

        $json['explain'] = $this->_explainHtml($data);
        $status = 1;
        if ($status) {
            $json['status'] = 'OK';
        }
        exit(json_encode($json));
    }



    public function toolsCheckPorts()
    {
        $return = array();
        $return['status'] = 'error';
        $return['msg'] = '';
        $return['html'] = '';

        $post = $this->input->post();

        if (empty($post['url']) || empty($post['ports'])) {
            $return['msg'] = 'Data not specified';
            $this->_json($return);
        }

        $host = $post['url'];
        $ports = explode(',', $post['ports']);

        ob_start();
        ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">Port</th>
                        <th style="text-align:center;">Service Name</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php


                foreach ($ports as $port)
                {
                    $connection = @fsockopen($host, $port, $errno, $errstr, 2);

                    $status = false;
                    $trclass = 'danger';
                    $service_name = '-';
                    if (is_resource($connection)) {
                        $status = true;
                        $service_name = getservbyport($port, 'tcp');
                        $trclass = 'success';
                        fclose($connection);
                    }
                    ?>
                    <tr class="<?php echo $trclass?>">
                        <td><?php echo $port?></td>
                        <td><?php echo $service_name?></td>
                        <td><?php echo $status?'Open':'Close'?></td>
                    </tr>
                    <?php
                }

                ?>
                </tbody>
            </table>
        </div>
        <?php
        $html = ob_get_clean();
        $return['status'] = 'ok';
        $return['html'] = preg_replace('#\s+#', ' ', $html);
        $this->_json($return);
    }

    public function toolsSendMail()
    {
        $return = array();
        $return['status'] = 'error';
        $return['msg'] = '';
        $return['address'] = '';
        $return['debuginfo'] = '';
        $post = $this->input->post();


        if (empty($post['type']) || empty($post['address'])) {
            $return['msg'] = 'Data not specified';
            $this->_json($return);
        }


        $type = $post['type'];
        $address = $post['address'];
        $subject = $post['subject'];
        $body = $post['body'];

        $return['address'] = $address;

        $config = array();

        switch($type) {
            case 'mail':
                $config['protocol'] = 'sendmail';
                $this->mailer->initialize($config);
                break;

            case 'smtp':
                $config['protocol'] = 'smtp';
                $config['smtp_host'] = $post['smtp_host'];
                $config['smtp_port'] = $post['smtp_port'];
                $config['smtp_user'] = $post['smtp_user'];
                $config['smtp_pass'] = $post['smtp_pass'];
                $config['smtp_secure'] = $post['smtp_secure'];
                $config['smtp_keepalive'] = false;
                $config['debug'] = 1;
                $this->mailer->initialize($config);
                break;
        }

        $this->mailer->addAddress($address);
        $this->mailer->addSubject($subject);
        $this->mailer->addBody($body);

        ob_start();
        $status = $this->mailer->send();
        $debuginfo = ob_get_clean();

        $return['status'] = $status ? 'ok' : 'error';
        $return['debuginfo'] = $debuginfo;

        $this->_json($return);
    }

    public function toolsSendPush()
    {
        $return = array();
        $return['status'] = 'error';
        $return['msg'] = '';
        $return['address'] = '';
        $return['debuginfo'] = '';
        $post = $this->input->post();


        if (empty($post['api_key']) || empty($post['devices'])) {
            $return['msg'] = 'Data not specified';
            $this->_json($return);
        }



        $API_KEY = $post['api_key'];
        $devices = explode("\n", $post['devices']);




        $rand = mt_rand(10000000, 99999999);
        $title = 'title: '.$rand;
        $text = 'text '.$rand;
        $subtext = 'subtext '.$rand;
        $tickerText = 'tickerText '.$rand;

        // settings
        $vibrate = 1;
        $sound = 1;
        $light = 1;



        // prep the bundle
        $msg = array
        (
            'text'             => $text,
            'title'            => $title,
            'subtext'        => $subtext,
            'tickerText'    => $tickerText,
            'vibrate'        => $vibrate,
            'sound'        => $sound,
            'light'            => $light,
        );

        $fields = array
        (
            'registration_ids'     => $devices,
            'data'                => $msg
        );

        $headers = array
        (
            'Authorization: key=' . $API_KEY,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://android.googleapis.com/gcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        curl_close($ch);

        // let's check the response
        $pushdata = json_decode($result, true);
        if (empty($pushdata)) {
            $return['msg'] = 'Unknown error';
            $this->_json($return);
        }


        $return['status'] = 'ok';
        $return['debuginfo'] = print_r($pushdata, true);
        $this->_json($return);
    }

    public function adminer()
    {
        require_once(core_path().'/views/debug/extra/adminer.php');
    }


    public function toolsSetChmod()
    {
        $return = array();
        $return['status'] = 'error';
        $return['msg'] = '';
        $return['perms'] = '';
        $post = $this->input->post();


        if (empty($post['folder']) || empty($post['type'])) {
            $return['msg'] = 'Data not specified';
            $this->_json($return);
        }


        if (!is_numeric($post['type'])) {
            $return['msg'] = 'Chmod type is wrong!';
            $this->_json($return);
        }

        $type = $post['type'];
        $folder = $post['folder'];
        $chmod = $type == 1 ? 755 : 777;

        $found = false;
        foreach($this->chmod_folders as $cfolder) {
            if ($folder == $cfolder['path']) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $return['msg'] = 'Chmod folder '.$folder.' not allowed!';
            $this->_json($return);
        }



        $stat = @chmod($folder, $chmod);
        if ($stat) {
            $status = 1;
        } else {
            @exec('sudo chmod '.$chmod.' '.$folder, $output, $res);
            if (!$res) {
                $status = 1;
            } else {
                $return['msg'] = 'Chmod folder '.$folder.' not allowed!';
                $this->_json($return);
            }
        }
        clearstatcache();
        $perms = PathHelper::getPermissions($folder, true);
        $return['perms'] = $perms['int'].' ('.$perms['str'].')';
        $return['msg'] = 'Successfully chmoded folder '.$folder.' to '.$chmod;
        $return['status'] = $status ? 'ok' : 'error';
        $this->_json($return);
    }




    /**
     * @param Request $request
     *
     * RPC handler
     *
     * @return array
     */
    public function actionRpc()
    {
		$post = file_get_contents('php://input');
		if (empty($post)) {
			die;
		}
		$json = json_decode($post, true);
		if (empty($json)) {
			die;
		}

  		$jsonrpc = $json['jsonrpc'];
  		$id = $json['id'];
  		$command = $json['method'];
  		$params = (array)$json['params'];



        header('Content-Type: application/json');
        $output = '';
        switch ($command) {
            default:
            	$output = ''.$command.': Command Not Found';
        		die(json_encode(['result' => $output]));
            	break;

            case 'jigar':
                $command = 'php jigar --ansi';
                array_unshift($params, $command);
                break;

            case 'ls':
                $command = 'ls --color=always';
                array_unshift($params, $command);
                break;

            case 'chmod':
                array_unshift($params, $command);
                break;


        }
        if (in_array('&&', $params)) {
        	die(json_encode(['result' => '&& not allowed']));
        }

        list($status, $output) = $this->runCommand(implode(' ', $params));


        die(json_encode(['result' => $output]));
    }

    /**
     * Runs console command.
     *
     * @param string $command
     *
     * @return array [status, output]
     */
    private function runCommand($command)
    {
        if (!is_callable('popen')) {
        	return ['0', 'popen() is not available'];
        }


        $cmd = 'cd '.BASEPATH.' && '.$command.' 2>&1';

        $handler = popen($cmd, 'r');
        $output = '';
        while (!feof($handler)) {
            $output .= fgets($handler);
        }
        $output = trim($output);
        $status = pclose($handler);

        return [$status, $output];
    }



    private function _explainHtml($data)
    {
        $buffer = '<table id="explain-sql">';
        $buffer .= '<thead>';
        $kk = 0;
        $first = true;
        foreach($data as $row)
        {
            if ($first)
            {
                $buffer .= '<tr>';
                foreach ($row as $k=>$v)
                {
                    $buffer .= '<th>'.$k.'</th>';
                }
                $buffer .= '</tr>';
                $buffer .= '</thead><tbody>';
                $first = false;
            }
            $style = $kk ? 'odd' : 'even';
            $buffer .= '<tr class="'.$style.'">';
            foreach ($row as $k=>$v)
            {
                $v = $v === null ? '<span style="font-style: italic;">NULL</span>' : $v;
                $buffer .= '<td>'.$v.'</td>';
            }
            $buffer .= '</tr>';
            $kk = 1 - $kk;
        }
        $buffer .= '</tbody></table>';
        return $buffer;
    }

    private function _json($data)
    {
        $json = json_encode($data);
        die($json);
    }

}
