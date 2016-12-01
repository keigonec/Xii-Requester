<?php
namespace Xii\Requester\Decrypt;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\base\InvalidValueException;
use yii\base\InvalidConfigException;
use Xii\Requester\Decrypt\DecryptInterface;

/**
 * Decrypt by use openssl
 */
class OpenSSL extends Component implements DecryptInterface
{
    /**
     * the allow list of type
     */
    private $_typeAllow = ['public', 'private'];

    /**
     * $param = [
     *         'key' => 'key value',
     *         'type' => 'type of key',
     *         'data' => 'the data will be decrypted'
     *     ]
     * decrypt data by use openssl
     */
    public function decryptData($param)
    {
        $keyValue = ArrayHelper::getValue($param, 'key', null);
        if ($keyValue === null)
        {
            throw new InvalidConfigException('OpenSSL Error: the key must be set.');
        }

        $type = ArrayHelper::getValue($param, 'type', 'public');
        if (!in_array(strtolower($type), $this->_typeAllow))
        {
            throw new InvalidValueException('OpenSSL Error: value of type must be private or public.');
        }

        $data = ArrayHelper::getValue($param, 'data', null);
        if ($data === null)
        {
            throw new InvalidValueException('OpenSSL Error: data is null.');
        }

        $keyResource = $this->_readKey($keyValue, $type);

        return $this->_decrypt($data, $keyResource);
    }

    /**
     * Extract key from certificate and prepare it for use
     */
    private function _readKey($key, $type = 'public')
    {
        return (strtolower($type) == 'public') ? openssl_pkey_get_public($key) : openssl_pkey_get_private($key);
    }

    /**
     * decrypt data through the given key resource
     */
    private function _decrypt($data, $keyResource, $type = 'public')
    {
        $text = base64_decode($data);

        $data = '';
        $len = strlen($text);
        if($len > 128)
        {
            $tmp = array();
            $num = $len / 128;
            for($i = 0; $i < $num; $i++)
            {
                $tmp_text = substr($text, $i*128,128);
                (strtolower($type) == 'public') ? openssl_public_decrypt($tmp_text, $tmp[], $keyResource) : openssl_private_decrypt($tmp_text, $tmp[], $keyResource);
            }
            $data = implode('', $tmp);
        }
        else
        {
            (strtolower($type) == 'public') ? openssl_public_decrypt($text, $data, $keyResource) : openssl_private_decrypt($text, $data, $keyResource);
        }
        return $data;
    }
}

