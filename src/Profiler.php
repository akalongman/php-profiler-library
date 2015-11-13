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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @package    ProfilerLibrary
 * @author     Avtandil Kikabidze <akalongman@gmail.com>
 * @copyright  Avtandil Kikabidze <akalongman@gmail.com>
 * @license    http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 * @link       http://www.github.com/akalongman/php-profiler-library
 */
class Profiler
{

    //protected $_session;
    protected $start = 0;

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

    protected $gcFreq = 1; // garbage collector frequency

    protected $expiration = 86400; // folder lifetime

    protected $enabled_logs = array('debug', 'error', 'info'); // debug, info, error

    protected $warning_params = array(
        'sql_time'    => array('0.1' => 'warning', '0.3' => 'danger'),
        'page_time'   => array('0.2' => 'warning', '0.5' => 'danger'),
        'page_memory' => array('8' => 'warning', '10' => 'danger'),
    );

    protected $config = [
        'debug_mode'  => false,
        'environment' => 'development',
        'logdata_path' => '',
    ];

    protected $filesystem = null; // symfony filesystem object

    private function __construct($prefix, $config)
    {
        $this->start             = microtime(1);
        $this->memories          = array();
        $this->marks             = array();
        $this->prints            = array();
        $this->tpl_files         = array();
        $this->view_files        = array();
        $this->wrapper_files     = array();
        $this->untranslated_txts = array();
        $this->widget_files      = array();
        $this->logs              = array();
        $this->prefix            = $prefix;
        $this->filesystem = new Filesystem();
        if ($config) {
            $this->setConfig($config);
        }
    }

    public static function getInstance($prefix = 'Application', array $config = null)
    {

        if (empty(self::$instances[$prefix])) {
            self::$instances[$prefix] = new self($prefix, $config);
        }

        return self::$instances[$prefix];
    }

    public function setConfig($config)
    {
        if (is_array($config)) {
            foreach ($config as $k => $v) {
                if (isset($this->config[$k])) {
                    $this->config[$k] = $v;
                }
            }
        }
        return $this;
    }


    public function getWarningParams()
    {
        return $this->warning_params;
    }

    public function getDebugMode()
    {
        return $this->config['debug_mode'];
    }

    public function getEnvironment()
    {
        return $this->config['environment'];
    }

    public function getLogdataPath()
    {
        return $this->config['logdata_path'];
    }

    public function panelEnabled()
    {
        return $this->panel_enabled;
    }

    public function mark($label)
    {
        if (!$this->getDebugMode()) {
            return false;
        }

        $current    = microtime(true) - $this->start;
        $currentMem = memory_get_usage(true);

        $m = array(
            'prefix'      => $this->prefix,
            'time'        => ($current > $this->previousTime ? '+' : '-')
            . ($current - $this->previousTime),
            'totalTime'   => $current,
            'memory'      => ($currentMem > $this->previousMem ? '+' : '-')
            . ($currentMem - $this->previousMem),
            'totalMemory' => $currentMem,
            'label'       => $label,
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
        $this->previousMem  = $currentMem;

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

        $log        = array();
        $log['msg'] = $message;

        $this->logs[$type][] = $log;
        return $this;
    }

    public function addSource($html)
    {
        if (!$this->getDebugMode()) {
            return false;
        }
        $this->source = $html;
        return $this;
    }

    public function addPrint($data, $depth = 10, $tabname = null)
    {
        if (!$this->getDebugMode()) {
            return false;
        }


        $fdata = Dumper::getDump($data, $depth, true);
        $type  = Dumper::getType();

        $exception = new \Exception();
        $trace     = $exception->getTrace();
        $curtrace  = !empty($trace[1]) ? $trace[1] : array();
        $file      = isset($curtrace['file']) ? $curtrace['file'] : '';
        $line      = isset($curtrace['line']) ? $curtrace['line'] : '';

        $variable = '';
        if (!empty($file)) {
            $lines = @file($file);
            $ln    = $line - 1;
            if (!empty($lines[$ln])) {
                $var_name = preg_match('#p\((.+?)\);#', $lines[$ln], $match);
                if (!empty($match[1])) {
                    $variable = strtok($match[1], ',');
                }
            }
        }

        $arr             = array();
        $arr['name']     = $tabname;
        $arr['data']     = $fdata;
        $arr['type']     = $type;
        $arr['file']     = $file;
        $arr['line']     = $line;
        $arr['variable'] = $variable;
        $this->prints[]  = $arr;
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
        $ex    = explode('.', $url);
        $ext   = array_pop($ex);

        if (in_array($ext, $array)) {
            return false;
        }
        return true;
    }

    public function finish()
    {
        if (!$this->getDebugMode()) {
            return false;
        }
        if ($this->getEnvironment() == 'testing') {
            return false;
        }

        if (mt_rand(1, $this->gcFreq) == 1) {
            $this->gc();
        }

        if (empty(\App::$CI)) {
            return false;
        }
        if ('debug' == \App::$CI->router->class) {
            return false;
        }
        if (!empty($this->data)) {
            return false;
        }

        $disabled_functions_str = @ini_get('disable_functions');
        $disabled_functions     = !empty($disabled_functions_str)
        ? explode(', ', $disabled_functions_str) : array();

        $data = array();

        $uniqid    = uniqid();
        $microtime = $this->getMicrotime();

        $data['microtime'] = $microtime;
        $data['uniqid']    = $uniqid;

        $data['url'] = is_callable('\current_url') ? \current_url() : 'unknown';

        if (!$this->legalExtension($data['url'])) {
            return false;
        }

        $data['user'] = !empty(\App::$CI->user) ? \App::$CI->user->getData(true, true) : array();

        $data['date']           = (new \DateTime())->format('Y-m-d H:i:s');
        $data['ip']             = $_SERVER['REMOTE_ADDR'];
        $data['request_method'] = $_SERVER['REQUEST_METHOD'];
        $data['request_type']   = defined('\IS_AJAX') && \IS_AJAX ? 'ajax' : false;

        $data['memories']  = $this->getMarks();
        $data['proc_load'] = !in_array('sys_getloadavg', $disabled_functions)
        ? sys_getloadavg() : 'UNKNOWN';

        $data['prints'] = $this->getPrints();
        $data['logs']   = $this->getLogs();

        $data['included_files']    = get_included_files();
        $data['declared_classes']  = get_declared_classes();
        $data['defined_functions'] = get_defined_functions();

        if (!empty(\App::$CI->db)) {
            $dbqueries = \App::$CI->db->queries;
            $dbtimes   = \App::$CI->db->query_times;
            $dbcaches  = \App::$CI->db->query_caches;
            $dbcalls   = \App::$CI->db->query_calls;
        }

        $queries = array();
        if (!empty($dbqueries)) {
            foreach ($dbqueries as $k => $q) {
                $time            = isset($dbtimes[$k]) ? $dbtimes[$k] : 0;
                $cached          = isset($dbcaches[$k]) ? $dbcaches[$k] : 0;
                $call            = isset($dbcalls[$k]) ? $dbcalls[$k] : 0;
                $query           = array();
                $query['query']  = $q;
                $query['time']   = $time;
                $query['cached'] = $cached;
                $query['file']   = $call['file'];
                $query['line']   = $call['line'];
                $query['stack']  = $call['stack'];
                $queries[]       = $query;
            }
        }
        $data['queries'] = $queries;

        $data['txts']                 = array();
        $data['txts']['lang']         = \App::$CI->lang_abbr;
        $data['txts']['untranslated'] = $this->untranslated_txts;

        $data['environment']['post']   = $_POST;
        $data['environment']['get']    = $_GET;
        $data['environment']['cookie'] = $_COOKIE;
        $data['environment']['server'] = $_SERVER;
        $data['environment']['files']  = $_FILES;

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
                $name  = substr($header, 0, $pos);
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

        $data['headers']             = array();
        $data['headers']['request']  = $requestHeaders;
        $data['headers']['response'] = $responseHeaders;

        $data['source'] = $this->source;

        $data['controller']    = \App::$CI->router->class;
        $data['method']        = \App::$CI->router->method;
        $data['tpl_files']     = $this->tpl_files;
        $data['view_files']    = $this->view_files;
        $data['wrapper_files'] = $this->wrapper_files;
        $data['widget_files']  = $this->widget_files;

        $data['php']['version']          = @phpversion();
        $data['mysql']['client_version'] = @mysql_get_client_info();
        $data['mysql']['server_version'] = !empty(\App::$CI->db) ? \App::$CI->db->version() : 'unknown';

        $config = is_callable('\get_config') ? \get_config() : [];

        $data['config']['main']    = $config;
        $data['config']['project'] = !empty(\App::$CI->conf) ? \App::$CI->conf->getData() : [];

        ob_start();
        @phpinfo();
        $phpinfo                   = ob_get_clean();
        $data['config']['phpinfo'] = $phpinfo;

        $data['config']['uname'] = !in_array('php_uname', $disabled_functions)
        ? php_uname() : 'UNKNOWN';

        $data['config']['server']['phpversion'] = $data['php']['version'];
        $data['config']['server']['xdebug']     = extension_loaded('xdebug');
        $data['config']['server']['apc']        = extension_loaded('apc');
        $data['config']['server']['memcached']  = extension_loaded('memcached');
        $data['config']['server']['curl']       = extension_loaded('curl');
        $data['config']['server']['mcrypt']     = extension_loaded('mcrypt');
        $data['config']['server']['gd']         = function_exists('gd_info');
        $data['config']['server']['mysql']      = function_exists('mysql_connect');
        $data['config']['server']['pdo']        = class_exists('PDO');

        $data['cms']['version'] = $config['version']['name'];
        $data['cms']['code']    = $config['version']['code'];
        $data['cms']['branch']  = $config['branch'];

        $data['cache'] = array();
        if (!empty(\App::$CI->cache_obj)) {
            $data['cache']['adapter'] = \App::$CI->cache_obj->getType();
            $data['cache']['info']    = \App::$CI->cache_obj->cache_info();
            $data['cache']['version'] = \App::$CI->cache_obj->getVersion();
        }

        $data['session_id'] = session_id();
        $this->data         = &$data;
        $this->set($microtime, $data);
        return $this;
    }

    public function getMicrotime()
    {
        $microtime = microtime(true);
        $microtime = str_replace(array(',', '.'), '', $microtime);
        $microtime = $this->addZeros($microtime, 15, 'right');
        return $microtime;
    }

    public function set($microtime, $data)
    {
        if ('session' == $this->driver) {
            $sesdata             = !empty($_SESSION['debug']) ? $_SESSION['debug'] : [];
            $sesdata[$microtime] = $data;
            $count               = count($sesdata);
            if ($count > $this->history_count) {
                $sesdata = array_slice($sesdata, $count - $this->history_count);
            }
            $_SESSION['debug'] = $sesdata;
            $status = true;
        } else {
            $session_id = session_id();
            if (empty($session_id)) {
                return false;
            }
            $logdata_path = $this->getLogdataPath();
            if (!$logdata_path) {
                trigger_error('Log data path is empty!');
                return false;
            }


            $folder = $logdata_path . '/debug/' . $session_id;
            if (!$this->filesystem->exists($folder)) {
                try {
                    $status = $this->filesystem->mkdir($folder, 0777);
                } catch (IOException $e) {
                    trigger_error($e->getMessage());
                    return false;
                }
            }
            $file_name = $microtime . '.data';
            $data      = $this->encode($data);

            $status = file_put_contents($folder . '/' . $file_name, $data);
        }

        return $status;
    }

/*    public function get($key = 0)
    {
        if (!$this->getDebugMode()) {
            return false;
        }
        if (empty(\App::$CI)) {
            return false;
        }

        $data = array();
        if ('session' == $this->driver) {
            $data = \App::$CI->session->get('debug', array());
        } else {
            $session_id = App::$CI->session->getId();
            $folder     = DATAPATH . 'logs/debug/' . $session_id;

            $finder = new Finder();

            $iterator = $finder
                ->files()
                ->name('*.data')
                ->depth(0)
                ->in($folder);

            if ($iterator->count() == 0) {
                return array();
            }
  var_dump($iterator);
  die;

            $files = $iterator->toArray();

            if ($key) {
                $index = array_search($key . '.data', $files);
                if (!empty($files[$index])) {
                    $file = $files[$index];
                }
            } else {
                $file = end($files);
            }

            $file = $folder . '/' . $file;
            if (file_exists($file)) {
                $data = file_get_contents($file);
                $data = $this->decode($data);
            }
        }
        return $data;
    }*/

    public function getDebugInfo()
    {
        if (!$this->getDebugMode()) {
            return false;
        }
        if (empty(\App::$CI)) {
            return false;
        }

        $data = array();
        if ('session' == $this->driver) {
            $data = !empty($_SESSION['debug']) ? $_SESSION['debug'] : [];
        } else {
            $session_id = session_id();

            $logdata_path = $this->getLogdataPath();
            if (!$logdata_path) {
                trigger_error('Log data path is empty!');
                return false;
            }

            $folder     = $logdata_path . '/debug/' . $session_id;

            $finder = new Finder();

            $iterator = $finder
                ->files()
                ->name('*.data')
                ->depth(0)
                ->in($folder);

            if ($iterator->count() == 0) {
                return array();
            }

            $files_list = array();
            foreach ($iterator as $file) {
                $file_path = $file->getRealpath();
                $name = $file->getFilename();
                $files_list[$file->getBasename('.data')] = $file;
            }

            if ($this->history_count) {
                $count      = count($files_list);
                $slice      = $count > $this->history_count ? $count - $this->history_count : 0;
                $files_list = array_slice($files_list, $slice, null, true);
            }

            foreach ($files_list as $mtime => $file) {
                try {
                    $content = $file->getContents();
                    $content = $this->decode($content);
                    $data[$mtime] = $content;
                } catch (\RuntimeException $e) {
                    trigger_error($e->getMessage());
                    continue;
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

    private function gc()
    {
        if ('file' != $this->driver) {
            return false;
        }

        $logdata_path = $this->getLogdataPath();
        if (!$logdata_path) {
            trigger_error('Log data path is empty!');
            return false;
        }

        $folder = $logdata_path . '/debug';
        if (!$this->filesystem->exists($folder)) {
            return false;
        }

        $expire = time() - $this->expiration;


        $finder = new Finder();
        $iterator = $finder
            ->directories()
            ->depth(0)
            ->in($folder);

        if ($iterator->count()) {
            foreach ($iterator as $folder) {
                $path = $folder->getRealpath();

                if (!$this->filesystem->exists($path)) {
                    continue;
                }

                if ($folder->getMTime() >= $expire) {
                    continue;
                }
                try {
                    $this->filesystem->remove($path);
                } catch (IOException $e) {
                    //trigger_error($e->getMessage());
                    // do nothing
                }
            }
        }

    }

    protected function addZeros($value, $final_length = 2, $dir = 'left')
    {
        $length = strlen($value);
        if ($length >= $final_length) {
            return $value;
        }
        $diff = $final_length - $length;
        $value = $dir == 'left' ? str_repeat('0', $diff).$value : $value.str_repeat('0', $diff);
        return $value;
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
