<?php

namespace Longman\ProfilerLibrary;

/*
 * This file is part of the ProfilerLibrary package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Exception;
use DateTime;
use InvalidArgumentException;
use Longman\ProfilerLibrary\Exception\ProfilerException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package    ProfilerLibrary
 * @author     Avtandil Kikabidze <akalongman@gmail.com>
 * @copyright  Avtandil Kikabidze <akalongman@gmail.com>
 * @license    http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 * @link       http://www.github.com/akalongman/php-profiler-library
 */
class Profiler
{

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

    protected $history_count = 20;

    protected $data = array();

    protected $panel_enabled = false;

    protected $driver = 'file'; // file or session

    protected $gcFreq = 10; // garbage collector frequency

    protected $expiration = 86400; // folder lifetime

    protected $enabled_logs = array('debug', 'error', 'info'); // debug, info, error

    protected $warning_params = array(
        'sql_time'    => array('0.1' => 'warning', '0.3' => 'danger'),
        'page_time'   => array('0.2' => 'warning', '0.5' => 'danger'),
        'page_memory' => array('8' => 'warning', '10' => 'danger'),
    );

    protected $config = [
        'debug_mode'   => false,
        'environment'  => 'development',
        'logdata_path' => '',
    ];

    protected $filesystem = null; // symfony filesystem object

    protected $request = null; // symfony Request object

    protected $response = null; // symfony Response object

    protected $cache = null; // cache object

    protected $session;

    protected $dont_track = false;


    private function __construct($prefix, $config)
    {
        $this->start             = microtime(true);
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
        $this->filesystem        = new Filesystem();

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

    public function setStartTime($time)
    {
        $this->start = $time;
        return $this;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }



    public function setCache($cache)
    {
        $this->cache = $cache;
        return $this;
    }

    public function setSession($session)
    {
        $this->session = $session;

        return $this;
    }

    public function dontTrack()
    {
        $this->dont_track = true;
        return $this;
    }

    public function setConfig(array $config)
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
        $currentMem = memory_get_peak_usage(true);

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

        $output = Dumper::doDump($data);

        $type = gettype($data);

        $exception = new Exception();
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
        $arr['data']     = $output;
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

        $controller = app('controller');

        if (empty($controller)) {
            return false;
        }

        if ($this->dont_track) {
            return false;
        }
        if (!empty($this->data)) {
            return false;
        }

        $disabled_functions_str = @ini_get('disable_functions');
        $disabled_functions     = !empty($disabled_functions_str) ? explode(', ', $disabled_functions_str) : [];

        $data = [];

        $uniqid    = uniqid();
        $microtime = $this->getMicrotime();

        $data['microtime'] = $microtime;
        $data['uniqid']    = $uniqid;

        $data['url'] = is_callable('\current_url') ? \current_url() : 'unknown';

        if (!$this->legalExtension($data['url'])) {
            return false;
        }

        $data['user'] = !empty($controller->user)
        ? $controller->user->getData() : array();

        $data['date']           = (new DateTime())->format('Y-m-d H:i:s');
        $data['ip']             = $this->request->server('REMOTE_ADDR');
        $data['request_method'] = $this->request->getMethod();
        $data['request_type']   = $this->request->isXmlHttpRequest() ? 'ajax' : false;

        $data['memories']  = $this->getMarks();
        $data['proc_load'] = !in_array('sys_getloadavg', $disabled_functions)
        ? sys_getloadavg() : 'UNKNOWN';

        $data['prints'] = $this->getPrints();
        $data['logs']   = $this->getLogs();

        $data['included_files']    = get_included_files();
        $data['declared_classes']  = get_declared_classes();
        $data['defined_functions'] = get_defined_functions();
        //$data['defined_constants'] = get_defined_constants();
        $data['declared_interfaces'] = get_declared_interfaces();

        if (!empty($controller->db)) {
            $dbqueries = $controller->db->queries;
            $dbtimes   = $controller->db->query_times;
            $dbcaches  = $controller->db->query_caches;
            $dbcalls   = $controller->db->query_calls;
        }



        $queries = [];
        if (!empty($dbqueries)) {
            foreach ($dbqueries as $k => $q) {
                $time            = isset($dbtimes[$k]) ? $dbtimes[$k] : 0;
                $cached          = isset($dbcaches[$k]) ? $dbcaches[$k] : false;
                $call            = isset($dbcalls[$k]) ? $dbcalls[$k] : [];
                $query           = array();
                $query['query']  = $q;
                $query['time']   = $time;
                $query['cached'] = $cached;
                $query['file']   = isset($call['file']) ? $call['file'] : 'unknown';
                $query['line']   = isset($call['line']) ? $call['line'] : 0;
                $query['stack']  = isset($call['stack']) ? $call['stack'] : [];
                $queries[]       = $query;
            }
        }
        $data['queries'] = $queries;


        $data['txts']                 = [];
        $data['txts']['lang']         = !empty($controller->lang_abbr) ? $controller->lang_abbr : '';
        $data['txts']['untranslated'] = $this->untranslated_txts;

        $data['environment']['post']   = $this->request->request->all();
        $data['environment']['get']    = $this->request->query->all();
        $data['environment']['cookie'] = $this->request->cookies->all();
        $data['environment']['server'] = $this->request->server->all();
        $data['environment']['files']  = $this->request->files->all();

        $data['headers']             = array();
        $data['headers']['request']  = $this->request->headers->all();
        $data['headers']['response'] = !empty($this->response) ? $this->response->headers->all() : [];

        $data['source'] = !empty($this->response) ? $this->response->getContent() : '';

        $data['controller']    = $controller->router->class;
        $data['method']        = $controller->router->method;
        $data['tpl_files']     = $this->tpl_files;
        $data['view_files']    = $this->view_files;
        $data['wrapper_files'] = $this->wrapper_files;
        $data['widget_files']  = $this->widget_files;

        $data['php']['version']          = @phpversion();
        $data['mysql']['client_version'] = @mysql_get_client_info();
        $data['mysql']['server_version'] = !empty($controller->db)
        ? $controller->db->version() : 'unknown';

        $config = is_callable('config') ? config()->all() : [];

        $data['config'] = $config;

        ob_start();
        @phpinfo();
        $data['config']['phpinfo'] = ob_get_clean();

        $data['config']['uname'] = is_callable('php_uname') ? php_uname() : 'UNKNOWN';

        $data['config']['server']['phpversion'] = $data['php']['version'];
        $data['config']['server']['xdebug']     = extension_loaded('xdebug');
        $data['config']['server']['apc']        = extension_loaded('apc');
        $data['config']['server']['memcached']  = extension_loaded('memcached');
        $data['config']['server']['curl']       = extension_loaded('curl');
        $data['config']['server']['mcrypt']     = extension_loaded('mcrypt');
        $data['config']['server']['gd']         = function_exists('gd_info');
        $data['config']['server']['mysql']      = function_exists('mysql_connect');
        $data['config']['server']['pdo']        = class_exists('PDO');

        $data['cms']['version'] = isset($config['version']['name']) ? $config['version']['name'] : '';
        $data['cms']['code']    = isset($config['version']['code']) ? $config['version']['code'] : '';
        $data['cms']['branch']  = isset($config['branch']) ? $config['branch'] : '';

        $data['cache'] = array();
        if (!empty($controller->cache_obj)) {
            $data['cache']['adapter'] = $config['cache']['default'];
            $data['cache']['info']    = ''; //$controller->cache_obj->getStats();
            $data['cache']['version'] = ''; //$controller->cache_obj->getVersion();
        }

        $data['session_id'] = $this->session->getId();
        $this->data         = &$data;
        $this->save($microtime, $data);
        return $this;
    }

    public function getMicrotime()
    {
        $microtime = microtime(true);
        $microtime = str_replace(array(',', '.'), '', $microtime);
        $microtime = $this->addZeros($microtime, 15, 'right');
        return $microtime;
    }

    protected function save($microtime, $data)
    {

        $session_id = $this->session->getId();
        if (empty($session_id)) {
            throw new ProfilerException('Session ID is empty');
        }

        $logdata_path = $this->getLogdataPath();
        if (!$logdata_path) {
            throw new ProfilerException('Log data path is empty');
        }

        $folder = $logdata_path . '/debug/' . $session_id;
        if (!$this->filesystem->exists($folder)) {
            try {
                $status = $this->filesystem->mkdir($folder, 0777);
            } catch (IOException $e) {
                throw new ProfilerException($e->getMessage());
            }
        }
        $file_name = $microtime . '.data';

        $encoded_data = $this->encode($data);
        if (!empty($data) && empty($encoded_data)) {
            throw new ProfilerException('json_encode data problem');
        }
        $status = file_put_contents($folder . '/' . $file_name, $encoded_data);
        if (!$status) {
            throw new ProfilerException('Can not save profiling data in ' . $folder . '/' . $file_name);
        }

        return $status;
    }


    public function getDebugData()
    {
        if (!$this->getDebugMode()) {
            return false;
        }
        if (empty(app('controller'))) {
            return false;
        }

        $data = [];

        $logdata_path = $this->getLogdataPath();
        if (!$logdata_path) {
            throw new ProfilerException('Log data path is empty');
        }
        $session_id = $this->session->getId();
        $folder     = $logdata_path . '/debug/' . $session_id;

        $finder = new Finder();
        try {
            $iterator = $finder
                ->files()
                ->name('*.data')
                ->depth(0)
                ->in($folder);
        } catch (InvalidArgumentException $e) {
            return [];
        }

        if ($iterator->count() == 0) {
            return [];
        }

        $files_list = [];
        foreach ($iterator as $file) {
            $file_path                               = $file->getRealpath();
            $name                                    = $file->getFilename();
            $files_list[$file->getBasename('.data')] = $file;
        }

        if ($this->history_count) {
            $count      = count($files_list);
            $slice      = $count > $this->history_count ? $count - $this->history_count : 0;
            $files_list = array_slice($files_list, $slice, null, true);
        }

        foreach ($files_list as $mtime => $file) {
            try {
                $content      = $file->getContents();
                $content      = $this->decode($content);
                $data[$mtime] = $content;
            } catch (\RuntimeException $e) {
                trigger_error($e->getMessage());
                continue;
            }
        }

        ksort($data);

        return $data;
    }

    public function encode($data)
    {
        $data = json_encode($data,
            JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK
             | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES);
        return $data;
    }

    public function decode($data)
    {
        $data = json_decode($data, true);
        return $data;
    }

    private function gc()
    {

        $logdata_path = $this->getLogdataPath();
        if (!$logdata_path) {
            throw new ProfilerException('Log data path is empty');
        }

        $folder = $logdata_path . '/debug';
        if (!$this->filesystem->exists($folder)) {
            return false;
        }

        $expire = time() - $this->expiration;

        $finder   = new Finder();
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
        $diff  = $final_length - $length;
        $value = $dir == 'left' ? str_repeat('0', $diff) . $value : $value . str_repeat('0', $diff);
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

        <iframe src="<?php echo site_url('itdc/debug/panel') ?>" id="debug_panel"></iframe>
        <?php
$html = ob_get_clean();
        return $html;
    }
}
