<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

abstract class API {

    /**
    * Property: method
    * The HTTP method this request was made in, either GET, POST, PUT or DELETE
    */
    protected $method = '';

    /**
    * Property: endpoint
    * The Model requested in the URI. eg: /files
    */
    protected $endpoint = '';

	/**
    * Property: verb
    * An optional additional descriptor about the endpoint, used for things that can
    * not be handled by the basic methods. eg: /files/process
    */
    protected $verb = ''; 

    /**
    * Property: args
    * Any additional URI components after the endpoint and verb have been removed, in our
    * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
    * or /<endpoint>/<arg0>
    */
    protected $args = Array();
    
    /** 
    * Property: file
    * Stores the input of the PUT request
    */
    protected $file = Null;
    

    /**
    * Constructor: __construct
    * Allow for CORS, assemble and pre-process the data
    */
    public function __construct() {

        header("Access-Control-Allow-Origin: *"); //any origin can be processed by this page
        header("Access-Control-Allow-Methods: *"); //any HTTP method can be accepted
        header("Content-Type: application/json");

        $this->args = explode('/', rtrim($_REQUEST['request'], '/'));

        $this->endpoint = array_shift($this->args);

        if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
            $this->verb = array_shift($this->args);
        }

        $this->method = $_SERVER['REQUEST_METHOD'];    


        if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
            if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
                $this->method = 'DELETE';
            } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
                $this->method = 'PUT';
            } else {
                throw new Exception("Unexpected Header");
            }
        }
        
        switch($this->method) {
	        case 'DELETE':
	        case 'POST':
	            $this->request = $this->_cleanInputs($_POST);
	            break;
	        case 'GET':
	            $this->request = $this->_cleanInputs($_GET);
	            break;
	        case 'PUT':
	            $this->request = $this->_cleanInputs($_GET);
	            $this->file = file_get_contents("php://input");
	            break;
	        default:
	            $this->_response(array("error" => "Invalid Method"), 405);
	            break;
        }
        
        unset($this->request['request']);
    }

    
    public function processAPI()
    {
        if (method_exists($this, $this->endpoint) === true)
        {
            
	        $log = new Log();
	        $log->write_log("info", $this->method." ".$this->endpoint.": ".json_encode($this->request));
            
            try {
                return $this->_response($this->{$this->endpoint}($this->args));
            }
            catch (Exception $e) {
                //var_dump($e->getMessage()); exit;
                // http://tools.ietf.org/html/draft-pbryan-http-json-resource-01
                return $this->_response(
                    array(
                        'error' => ($e->getMessage() ? $e->getMessage() : $this->_requestStatus($e->getCode()))
                    ),
                    ($e->getCode() ? $e->getCode() : 500)
                );
            }
        }
        return $this->_response("No Endpoint: $this->endpoint", 404);
    }
    

    private function _response($data, $status = 200)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " " . $status . " " . $this->_requestStatus($status));

		$result = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

		$result = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
		    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
		}, $result);
        
        return $result;
    }
    

    private function _cleanInputs($data)
    {
        $clean_input = Array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }
    

    private function _requestStatus($code) {
        $status = array(  
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        );
        return ($status[$code])?$status[$code]:$status[500]; 
    }
    
}
