<?php
namespace Xii\Requester;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\base\InvalidValueException;
use yii\base\InvalidConfigException;
use yii\helpers\Json;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Client;

/**
 * Requester
 * This component is design to communicate other system
 * You need transmit configuration like follow format to this
 *
 * Single Request
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 * use Xii\Requester\Requester;
 *
 * $param = [
 *     'config' => [
 *             'interfaceUrl' => 'http://api.xxxxxxx.com',
 *             'method' => 'get',
 *             'protocol' => 'http',
 *             'returnData' => 'json',
 *             'connect_timeout' => 1,
 *             'timeout' => 1
 *         ],
 *     'request' => [
 *             'id' => '1',
 *             'name' => 'aaa'
 *         ],
 *     'response' => [
 *             'status' => 's',
 *             'errorCode' => 'ec',
 *             'errorMsg' => 'em',
 *             'data' => 'd'
 *         ]
 * ]
 *
 * $Request = new Requester(['user' => $param]);
 * $feedback = $Request->run();
 *
 *
 * Async Request
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * $params = ['user' => $param, 'crm' => $param, 'zo' => '$param'];
 * $Request = new Requester($params]);
 * $feedback = $Request->run();
 *
 */
class Requester extends Component
{
    /**
     * @var integer
     * time of connect_timeout
     */
    const CONNECT_TIMEOUT = 1;
    private $_connect_timeout;

    /**
     * @var integer
     * time of timeout
     */
    const TIMEOUT = 1;
    private $_timeout;

    /**
     * @var integer
     * time of retry
     */
    const RETRY_TIME = 3;

    /**
     * @var array
     * method allowed of request
     */
    private $_methodAllow = ['get', 'post'];

    /**
     * @var array
     * type allowed of protocol
     */
    private $_protocolAllow = ['http', 'https'];

    /**
     * @var array
     * type allowed of return data format
     */
    private $_returnDataAllow = ['string', 'json', 'jsonp'];

    /**
     * @var array
     * configs for Request class
     */
    private $_configOfRequest;

    /**
     * @var array
     * configs for Other
     */
    private $_configOfOther;

    /**
     * @var array
     * response format
     */
    private $_formatOfResponse;

    /**
     * @var array
     * config of decrypt
     */
    private $_configOfDecrypt;

    /**
     * @param array
     * get value of attribute by name
     */
    public function __construct($param)
    {
        if(!is_array($param))
        {
            throw new InvalidValueException('Requester Error: param is not array.');
        }

        if(empty($param))
        {
            throw new InvalidValueException('Requester Error: param is emtpy.');
        }

        $this->_preParams($param);
    }

    /**
     * @param array
     * result
     */
    public function run()
    {
        //request interface
        $feedback = $this->_request($this->_configOfRequest, self::RETRY_TIME);

        //decrypt response
        if(!empty($this->_configOfDecrypt))
        {
            foreach ($this->_configOfDecrypt as $key => $v)
            {
                if(!empty($v))
                {
                    $v['params']['data'] = $feedback[$key];
                    $feedback[$key] = $this->_decryptResponse($v);
                }
            }
        }

        //return
        return $this->_setResponseFormat($feedback);
    }

    /**
     * encrypt function
     */
    public static function Encrypt($param)
    {

    }

    /**
     * sign function
     */
    public static function Sign($param)
    {

    }

    /**
     * decrypt function
     */
    public static function Decrypt($param)
    {
        return self::_decryptResponse([$param]);
    }

    /**
     * request function
     * This function is writen by TangYi
     * Thanks for him.
     */
    private function _request($param, $retry)
    {
        $handler = HandlerStack::create(new CurlMultiHandler(['select_timeout' => 1]));
        $client = new Client(['handler' => $handler]);
        $promises = [];

        foreach($params as $k => $item)
        {
            if(!isset($item[0]) || ($item[0] != 'post' && $item[0] != 'get'))
            {
                throw new \UnexpectedValueException('Requester Error: unexpected request method, it must be post or get.');
            }
            $promises[$k] = $client->requestAsync($item[0], (isset($item[1]) ? $item[1] : ''), (isset($item[2]) ? $item[2] : ''));
        }

        $results = \GuzzleHttp\Promise\settle($promises)->wait();
        $fulfilled = $rejected = [];

        foreach($results as $k => $v)
        {
            $value['state'] !== 'fulfilled' ? $rejected[$k] = $params[$k] : $fulfilled[$k] = $v['value']->getBody()->getContents();
        }

        $retry--;

        if($rejected && $retry > 0)
        {
            usleep(100);
            $fulfilled += $this->_request($rejected, $retry);
        }

        foreach($params as $k=>$item)
        {
            if(!isset($fulfilled[$k]))
            {
                $fulfilled[$k] = false;
            }
        }
        return $fulfilled;
    }

    /**
     * decrypt function
     */
    private static function _decryptResponse($param)
    {
        if(!is_array($param))
        {
            return false;
        }

        $class = ArrayHelper::getValue($param, 'class', null);
        if ($class === null)
        {
            throw new InvalidConfigException('Requester Error: decrypt class must be set.');
        }

        $params = ArrayHelper::getValue($param, 'params', null);
        if ($params === null)
        {
            throw new InvalidConfigException('Requester Error: params must be set.');
        }

        $decrypter = Yii::createObject($class);
        if (!($decrypter instanceof \Xii\Requester\Decrypt\DecryptInterface))
        {
            throw new InvalidValueException('Requester Error: you must use a valid adapter.');
        }

        return $decrypter->decryptData($params);
    }

    /**
     * param process function when try to create object
     */
    private function _preParams($param)
    {
        if(!is_array($param))
        {
            throw new InvalidValueException('Requester Error: param is not array.');
        }

        foreach ($param as $k => $v)
        {
            $interfaceUrl = ArrayHelper::getValue($v, 'config.interfaceUrl', null);
            if ($interfaceUrl === null)
            {
                throw new InvalidConfigException('Requester Error: interfaceUrl must be set.');
            }

            $method = ArrayHelper::getValue($v, 'config.method', 'get');
            if (!in_array(strtolower($method), $this->_methodAllow))
            {
                throw new InvalidConfigException('Requester Error: ' . $method . ' is not allowed.');
            }

            $protocol = ArrayHelper::getValue($v, 'config.protocol', 'http');
            if (!in_array(strtolower($protocol), $this->_protocolAllow))
            {
                throw new InvalidConfigException('Requester Error: ' . $protocol . ' is not allowed.');
            }

            $returnData = ArrayHelper::getValue($v, 'config.returnData', 'json');
            if (!in_array(strtolower($returnData), $this->_returnDataAllow))
            {
                throw new InvalidConfigException('Requester Error: ' . $returnData . ' is not allowed.');
            }

            $connect_timeout = ArrayHelper::getValue($v, 'config.connect_timeout', self::CONNECT_TIMEOUT);

            $timeout = ArrayHelper::getValue($v, 'config.timeout', self::TIMEOUT);

            $request = ArrayHelper::getValue($v, 'request', []);

            if(strtolower($method) == 'get')
            {
                $this->_configOfRequest[$k] = [
                    $method,
                    $interfaceUrl,
                    [
                        'connect_timeout' => $connect_timeout,
                        'timeout' => $timeout
                    ]
                ];
            }
            if(strtolower($method) == 'post')
            {
                $this->_configOfRequest[$k] = [
                    $method,
                    $interfaceUrl,
                    [
                        'connect_timeout' => $connect_timeout,
                        'timeout' => $timeout,
                        'form_params' => $request
                    ]
                ];
            }
            $this->_configOfOther[$k] = [
                    'protocol' => strtolower($protocol),
                    'returnData' => strtolower($returnData)
                ];
            $this->_formatOfResponse[$k] = ArrayHelper::getValue($v, 'response', []);
            $this->_configOfDecrypt[$k] = ArrayHelper::getValue($v, 'decrypt', []);
        }
    }

    /**
     * response format process function
     */
    private function _setResponseFormat($param)
    {
        if(!is_array($param))
        {
            throw new InvalidValueException('Requester Error: param is not array.');
        }

        $feedback = [];

        foreach ($param as $key => $value)
        {
            if($this->_configOfOther[$key]['returnData'] == 'json')
            {
                $tmp = Json::decode($value);

                if(isset($this->_formatOfResponse[$key]) && !empty($this->_formatOfResponse[$key]))
                {
                    foreach ($this->_formatOfResponse[$key] as $k => $v)
                    {
                        $feedback[$key][$k] = ArrayHelper::getValue($tmp, $v, null);
                        if ($feedback[$key][$k] === null)
                        {
                            throw new InvalidValueException('Requester Error: ' .  $v . ' is not find.');
                        }
                    }
                }
                else
                {
                    $feedback[$key] = $value;
                }
            }

            if($this->_configOfOther[$key]['returnData'] == 'jsonp')
            {
                $feedback[$key] = $value;
            }

            if($this->_configOfOther[$key]['returnData'] == 'string')
            {
                $feedback[$key] = $value;
            }
        }

        return $feedback;
    }
}