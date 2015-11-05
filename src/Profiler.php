<?php
/*
 * This file is part of the ProfilerLibrary package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/
namespace Longman\ProfilerLibrary;

if (!defined('BASEPATH')) exit('No direct script access allowed');

class Profiler
{

    //protected $_session;
    protected $_start = 0;
    protected $memories = null;
    protected $marks = null;
    protected $prints = null;
    protected $previousTime = 0.0;
    protected $previousMem = 0.0;
    protected $tpl_files = null;
    protected $view_files = null;
    protected $wrapper_files = null;
    protected $widget_files = null;
    protected $untranslated_txts = null;
    protected $prefix = '';
    protected $logs = null;
    protected $source = null;
    protected static $instances = array();

    protected $history_count = 10;

    protected $data = array();
    protected $panel_enabled = false;

    protected $driver = 'file'; // file or session
    protected $gcFreq = 10; // garbage collector frequency
    protected $expiration = 86400; // folder lifetime
    protected $enabled_logs = array('debug', 'error', 'info'); // debug, info, error

    protected $warning_params = array(
            'sql_time' => array('0.1'=>'warning', '0.3'=>'danger'),
            'page_time' => array('0.2'=>'warning', '0.5'=>'danger'),
            'page_memory' => array('8'=>'warning', '10'=>'danger')
        );


    private function __construct($prefix = '')
    {
        $this->_start = microtime(1);
        $this->memories = array();
        $this->marks = array();
        $this->prints = array();
        $this->tpl_files = array();
        $this->view_files = array();
        $this->wrapper_files = array();
        $this->untranslated_txts = array();
        $this->widget_files = array();
        $this->logs = array();
        $this->prefix = $prefix;
    }

    public static function getInstance($prefix = 'Application')
    {
        if (empty(self::$instances[$prefix]))
        {
            self::$instances[$prefix] = new self($prefix);
        }

        return self::$instances[$prefix];
    }

    public function getWarningParams()
    {
        return  $this->warning_params;
    }


    public function panelEnabled()
    {
        if (!DEBUG_MODE) {
            return false;
        }
        if (ENVIRONMENT == 'testing') {
            return false;
        }
        if (empty(App::$CI)) {
            return false;
        }
        if (App::$CI->router->class == 'debug') {
            return false;
        }
        return $this->panel_enabled;
    }

    public function mark($label)
    {
        if (!DEBUG_MODE) {
            return false;
        }

        $current = microtime(true) - $this->_start;
        $currentMem = memory_get_usage(true);

        $m = array(
            'prefix' => $this->prefix,
            'time' => ($current > $this->previousTime ? '+' : '-') . ($current - $this->previousTime),
            'totalTime' => $current,
            'memory' => ($currentMem > $this->previousMem ? '+' : '-') . ($currentMem - $this->previousMem),
            'totalMemory' => $currentMem,
            'label' => $label
        );
        $this->marks[] = $m;

        $mark = sprintf(
            '%s %.3f seconds (%.3f); %0.2f MB (%0.3f) - %s',
            $m['prefix'],
            $m['totalTime'],
            $m['time'],
            $m['totalMemory'],
            $m['memory'],
            $m['label']
        );
        //$this->memories[] = $mark;

        $this->previousTime = $current;
        $this->previousMem = $currentMem;

        return $mark;
    }



    public function getMarks()
    {
        return $this->marks;
    }

    public function getMemories()
    {
        return $this->memories;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    public function addTplFile($name)
    {
        $this->tpl_files[] = $name;
        return $this;
    }

    public function addViewFile($name)
    {
        $this->view_files[] = $name;
        return $this;
    }

    public function addWrapperFile($name)
    {
        $this->wrapper_files[] = $name;
        return $this;
    }

    public function addWidget($name)
    {
        $this->widget_files[] = $name;
        return $this;
    }


    public function addUntranslatedTxt($txt)
    {
        $this->untranslated_txts[] = $txt;
        return $this;
    }

    public function addLog($type, $message)
    {
        if (!in_array($type, $this->enabled_logs)) {
            return false;
        }

        $log = array();
        $log['msg'] = $message;

        $this->logs[$type][] = $log;
        return $this;
    }

    public function addSource($html)
    {
        if (!DEBUG_MODE) {
            return false;
        }
        $this->source = $html;
        return $this;
    }


    public function addPrint($data, $depth = 10, $tabname = null)
    {
        if (!DEBUG_MODE) {
            return false;
        }


        /*ob_start();
        if (is_bool($data) || $data === NULL) {
            var_dump($data);
        } else if (is_object($data) || is_array($data)) {
            $data = SystemHelper::clean($data);
            print_r($data);
        } else {
            print_r($data);
        }
        $fdata = ob_get_clean();*/


        $fdata = DumpHelper::getDump($data, $depth, true);
        $type = DumpHelper::getType();

        $exception = new Exception();
        $trace = $exception->getTrace();
        $curtrace = !empty($trace[1]) ? $trace[1] : array();
        $file = isset($curtrace['file']) ? $curtrace['file'] : '';
        $line = isset($curtrace['line']) ? $curtrace['line'] : '';

        $variable = '';
        if (!empty($file)) {
            $lines = @file($file);
            $ln = $line - 1;
            if (!empty($lines[$ln])) {
                $var_name = preg_match('#p\((.+?)\);#', $lines[$ln], $match);
                if (!empty($match[1])) {
                    $variable = strtok($match[1], ',');
                }
            }
        }

        $arr = array();
        $arr['name'] = $tabname;
        $arr['data'] = $fdata;
        $arr['type'] = $type;
        $arr['file'] = $file;
        $arr['line'] = $line;
        $arr['variable'] = $variable;
        $this->prints[] = $arr;
        //unset($data);
        return $this;
    }

    public function getPrints()
    {
        return $this->prints;
    }

    public function legalExtension($url)
    {
        $array = array('css', 'js', 'eot', 'woff', 'ttf', 'svg', 'map');
        $ex = explode('.', $url);
        $ext = array_pop($ex);

        if (in_array($ext, $array)) {
            return false;
        }
        return true;
    }


    public function finish()
    {
        if (!DEBUG_MODE) {
            return false;
        }
        if (ENVIRONMENT == 'testing') {
            return false;
        }

        if (mt_rand(1, $this->gcFreq) == 1) {
            $this->_gc();
        }

        if (empty(App::$CI)) {
            return false;
        }
        if (App::$CI->router->class == 'debug') {
            return false;
        }
        if (!empty($this->data)) {
            return false;
        }

        $disabled_functions_str = @ini_get('disable_functions');
        $disabled_functions = !empty($disabled_functions_str) ? explode(', ', $disabled_functions_str) : array();

        $data = array();

        $uniqid = uniqid();
        $microtime = $this->getMicrotime();

        $data['microtime'] = $microtime;
        $data['uniqid'] = $uniqid;

        $data['url'] = current_url();

        if (!$this->legalExtension($data['url'])) {
            return false;
        }


        if (empty(App::$CI->date)) {
            App::$CI->date = new Date();
        }

        $data['user'] = !empty(App::$CI->user) ? App::$CI->user->getData(true, true) : array();

        $data['date'] = App::$CI->date->toSql();
        $data['ip'] = $_SERVER['REMOTE_ADDR'];
        $data['request_method'] = $_SERVER['REQUEST_METHOD'];
        $data['request_type'] = IS_AJAX ? 'ajax' : false;





        $data['memories'] = $this->getMarks();
        $data['proc_load'] = !in_array('sys_getloadavg', $disabled_functions) ? sys_getloadavg() : 'UNKNOWN';

        $data['prints'] = $this->getPrints();
        $data['logs'] = $this->getLogs();


        $data['included_files'] = get_included_files();
        $data['declared_classes'] = get_declared_classes();
        $data['defined_functions'] = get_defined_functions();

        $dbqueries = App::$CI->db->queries;
        $dbtimes = App::$CI->db->query_times;
        $dbcaches = App::$CI->db->query_caches;
        $dbcalls = App::$CI->db->query_calls;



        $queries = array();
        foreach($dbqueries as $k=>$q) {
            $time = isset($dbtimes[$k]) ? $dbtimes[$k] : 0;
            $cached = isset($dbcaches[$k]) ? $dbcaches[$k] : 0;
            $call = isset($dbcalls[$k]) ? $dbcalls[$k] : 0;
            $query = array();
            $query['query'] = $q;
            $query['time'] = $time;
            $query['cached'] = $cached;
            $query['file'] = $call['file'];
            $query['line'] = $call['line'];
            $query['stack'] = $call['stack'];
            $queries[] = $query;
        }
        $data['queries'] = $queries;


        $data['txts'] = array();
        $data['txts']['lang'] = App::$CI->lang_abbr;
        $data['txts']['untranslated'] = $this->untranslated_txts;



        $data['environment']['post'] = $_POST;
        $data['environment']['get'] = $_GET;
        $data['environment']['cookie'] = $_COOKIE;
        $data['environment']['server'] = $_SERVER;
        $data['environment']['files'] = $_FILES;

        // request headers
        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
        } elseif (function_exists('http_get_request_headers')) {
            $requestHeaders = http_get_request_headers();
        } else {
            $requestHeaders = array();
        }


        // response headers
        $responseHeaders = array();
        foreach (headers_list() as $header) {
            if (($pos = strpos($header, ':')) !== false) {
                $name = substr($header, 0, $pos);
                $value = trim(substr($header, $pos + 1));
                if (isset($responseHeaders[$name])) {
                    if (!is_array($responseHeaders[$name])) {
                        $responseHeaders[$name] = array($responseHeaders[$name], $value);
                    } else {
                        $responseHeaders[$name][] = $value;
                    }
                } else {
                    $responseHeaders[$name] = $value;
                }
            } else {
                $responseHeaders[] = $header;
            }
        }

        $data['headers'] = array();
        $data['headers']['request'] = $requestHeaders;
        $data['headers']['response'] = $responseHeaders;

        $data['source'] = $this->source;

        $data['controller'] = App::$CI->router->class;
        $data['method'] = App::$CI->router->method;
        $data['tpl_files'] = $this->tpl_files;
        $data['view_files'] = $this->view_files;
        $data['wrapper_files'] = $this->wrapper_files;
        $data['widget_files'] = $this->widget_files;


        $data['php']['version'] = @phpversion();
        $data['mysql']['client_version'] = @mysql_get_client_info();
        $data['mysql']['server_version'] = App::$CI->db->version();

        $config = get_config();


        $data['config']['main'] = $config;
        $data['config']['project'] = App::$CI->conf->getData();

        ob_start();
        @phpinfo();
        $phpinfo = ob_get_clean();
        $data['config']['phpinfo'] = $phpinfo;

        $data['config']['uname'] = !in_array('php_uname', $disabled_functions) ? php_uname() : 'UNKNOWN';


        $data['config']['server']['phpversion'] = $data['php']['version'];
        $data['config']['server']['xdebug'] = extension_loaded('xdebug');
        $data['config']['server']['apc'] = extension_loaded('apc');
        $data['config']['server']['memcached'] = extension_loaded('memcached');
        $data['config']['server']['curl'] = extension_loaded('curl');
        $data['config']['server']['mcrypt'] = extension_loaded('mcrypt');
        $data['config']['server']['gd'] = function_exists('gd_info');
        $data['config']['server']['mysql'] = function_exists('mysql_connect');
        $data['config']['server']['pdo'] = class_exists('PDO');



        $data['cms']['version'] = $config['version']['name'];
        $data['cms']['code'] = $config['version']['code'];
        $data['cms']['branch'] = $config['branch'];


        $data['cache'] = array();
        if (!empty(App::$CI->cache_obj)) {
            $data['cache']['adapter'] = App::$CI->cache_obj->getType();
            $data['cache']['info'] = App::$CI->cache_obj->cache_info();
            $data['cache']['version'] = App::$CI->cache_obj->getVersion();
        }

        $session_id = App::$CI->session->getId();
        $data['session_id'] = $session_id;
        $this->data = &$data;
        $this->set($microtime, $data);
        return $this;
    }

    public function getMicrotime()
    {
        $microtime = microtime(true);
        $microtime = str_replace(array(',', '.'), '', $microtime);
        $microtime = MathHelper::addZeros($microtime, 15, 'right');
        return $microtime;
    }

    public function set($microtime, $data)
    {
        if ($this->driver == 'session') {
            $sesdata = App::$CI->session->get('debug', array());
            $sesdata[$microtime] = $data;
            $count = count($sesdata);
            if ($count > $this->history_count) {
                $sesdata = array_slice($sesdata, $count - $this->history_count);
            }
            $status = App::$CI->session->set('debug', $sesdata);
        } else {
            $session_id = App::$CI->session->getId();
            if (empty($session_id)) {
                return false;
            }

            $folder = DATAPATH.'logs/debug/'.$session_id;
            if (!FolderHelper::exists($folder)) {
                $status = FolderHelper::create($folder, 0777);
                if (!$status) {
                    return false;
                }
            }
            $file_name = $microtime.'.data';
            $data = $this->encode($data);

            $status = file_put_contents($folder.'/'.$file_name, $data);
        }

        return $status;
    }


    public function get($key = 0)
    {
        if (!DEBUG_MODE) {
            return false;
        }
        if (empty(App::$CI)) {
            return false;
        }

        $data = array();
        if ($this->driver == 'session') {
            $data = App::$CI->session->get('debug', array());
        } else {
            $session_id = App::$CI->session->getId();
            $folder = DATAPATH.'logs/debug/'.$session_id;
            $files = FolderHelper::files($folder);
            if (empty($files)) {
                return array();
            }

            if ($key) {
                $index = array_search($key.'.data', $files);
                if (!empty($files[$index])) {
                    $file = $files[$index];
                }
            } else {
                $file = end($files);
            }

            $file = $folder.'/'.$file;
            if (file_exists($file)) {
                $data = file_get_contents($file);
                $data = $this->decode($data);
            }
        }
        return $data;
    }


    public function getDebugInfo()
    {
        if (!DEBUG_MODE) {
            return false;
        }
        if (empty(App::$CI)) {
            return false;
        }

        $data = array();
        if ($this->driver == 'session') {
            $data = App::$CI->session->get('debug', array());
        } else {
            $session_id = App::$CI->session->getId();
            $folder = DATAPATH.'logs/debug/'.$session_id;
            $files = FolderHelper::files($folder);
            if (empty($files)) {
                return array();
            }

            $files_list = array();
            foreach($files as $file) {
                $file_path = $folder.'/'.$file;
                if (file_exists($file_path)) {
                    $index = strtok($file, '.');
                    $files_list[$index] = $file;
                }
            }


            if ($this->history_count) {
                $count = count($files_list);
                $slice = $count > $this->history_count ? $count - $this->history_count : 0;
                $files_list = array_slice($files_list, $slice, null, true);
            }


            foreach($files_list as $mtime=>$file) {
                $file_path = $folder.'/'.$file;
                if (file_exists($file_path)) {
                    $content = file_get_contents($file_path);
                    $content = $this->decode($content);
                    $data[$mtime] = $content;
                }
            }

        }
        return $data;
    }



    public function encode($data)
    {
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $data;
    }

    public function decode($data)
    {
        $data = json_decode($data, true);


        return $data;
    }

    private function _gc()
    {
        if ($this->driver != 'file') {
            return false;
        }
        $folder = DATAPATH.'logs/debug';
        if (!FolderHelper::exists($folder)) {
            return false;
        }

        $expire = time() - $this->expiration;

        $path = new DirectoryIterator($folder);
        foreach ($path as $file) {
            if ($file->isDot() || !$file->isDir()) {
                continue;
            }
            if ($file->getMTime() < $expire) {
                FolderHelper::delete($file->getPathname());
            }
        }
    }


    public function getPanel()
    {
        if (!$this->panelEnabled()) {
            return false;
        }

        ob_start();
        ?>
        <style type="text/css">
            #debug_panel {
                position: fixed;
                bottom: 0px;
                left: 0px;
                right: 0px;
                width: 100%;
                display: block;
                border: 0;
                height: 50px;
                box-shadow: 0 0 10px grey;
                z-index: 100000000;
                /*opacity: 0.8;*/
            }
            body {
                padding-bottom: 60px;
            }
        </style>

        <iframe src="<?php echo site_url('itdc/debug/panel')?>" id="debug_panel"></iframe>
        <?php
        $html = ob_get_clean();
        return $html;
    }

}
