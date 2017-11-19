<?php

namespace bongrun\api;

use bongrun\adapter\BrowserAdapter;
use bongrun\adapter\CurlAdapter;
use bongrun\interfaces\CacheInterface;
use bongrun\exception\VkApiException;
use bongrun\interfaces\VkApplicationInterface;
use jumper423\decaptcha\core\DeCaptchaBase;

class VkApi
{
    const BASE_URL = 'https://api.vk.com/method/';
    /** @var string */
    private $token;
    /** @var int */
    private $userId;
    /** @var int */
    private $sex;
    /** @var string */
    private $firstName;
    /** @var string */
    private $lastName;
    private $paramsDefault = [
        'v' => '5.64',
        'lang' => '0',
    ];
    /** @var VkApplicationInterface */
    private $application;
    /** @var CurlAdapter */
    protected $curl;
    /** @var BrowserAdapter */
    private $browser;
    /** @var DeCaptchaBase */
    private $captcha;
    /** @var CacheInterface */
    private $cache;
    /** @var VkApiException */
    private $error;
    /** @var array */
    private $response;
    /** @var array */
    private $scope = [
        'notify', 'friends', 'photos', 'audio', 'video', 'docs', 'pages', 'status', 'wall', 'groups', 'messages', 'email', 'notifications', 'stats', 'offline',
    ];

    /**
     * VkApi constructor.
     *
     * @param CurlAdapter $curl
     * @param DeCaptchaBase $captcha
     * @param BrowserAdapter $browser
     * @param CacheInterface $cache
     * @param VkApplicationInterface $vkApplication
     * @param int $userId
     * @param string $token
     */
    public function __construct($curl = null, $captcha = null, $browser = null, $cache = null, $vkApplication = null, $userId = null, $token = null)
    {
        $this->setCaptcha($captcha);
        $this->setCurl($curl);
        $this->setBrowser($browser);
        $this->setCache($cache);
        $this->setToken($token);
        $this->setParamApplication($vkApplication);
    }

    /**
     * @param CurlAdapter $curl
     *
     * @return $this
     */
    public function setCurl($curl = null)
    {
        if (!($curl instanceof CurlAdapter)) {
            $curl = new CurlAdapter();
        }
        $curl->setBaseUrl(self::BASE_URL);
        $this->curl = $curl;
        return $this;
    }

    /**
     * @param BrowserAdapter $browser
     *
     * @return $this
     */
    public function setBrowser($browser = null)
    {
        if (!($browser instanceof BrowserAdapter)) {
            $browser = new BrowserAdapter();
        }
        $this->browser = $browser;
        return $this;
    }

    /**
     * @return BrowserAdapter
     */
    public function getBrowser()
    {
        return $this->browser;
    }

    /**
     * @param DeCaptchaBase $captcha
     *
     * @return $this
     */
    public function setCaptcha($captcha = null)
    {
        $this->captcha = $captcha;
        return $this;
    }

    /**
     * @return DeCaptchaBase
     */
    public function getCaptcha()
    {
        return $this->captcha;
    }

    /**
     * @param CacheInterface $cache
     * @return $this
     */
    public function setCache($cache = null)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken($token = null)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param $firstName
     *
     * @return $this
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param $lastName
     *
     * @return $this
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param int $userId
     *
     * @return $this
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @param int $sex
     *
     * @return $this
     */
    public function setSex($sex)
    {
        $this->sex = $sex;
        return $this;
    }

    /** todo непонятно нахера*/
    public function setBlock($sex)
    {
        $this->sex = $sex;
        return $this;
    }

    /**
     * @return int
     */
    public function getSex()
    {
        return $this->sex;
    }

    /**
     * @param VkApplicationInterface $vkApplication
     *
     * @return $this
     */
    public function setParamApplication(VkApplicationInterface $vkApplication = null)
    {
        $this->application = $vkApplication;
        return $this;
    }

    /**
     * @param      $method
     * @param      $params
     * @param bool $cacheTtl
     * @param int $timeout
     * @param bool $paramApplication
     *
     * @return $this
     */
    public function get($method, $params, $cacheTtl = false, $timeout = 0, $paramApplication = false)
    {
        $this->clear();
        if ($timeout) {
            sleep($timeout);
        }
        if ($paramApplication) {
            if ($this->application instanceof VkApplicationInterface) {
                $params = array_merge($this->paramsDefault, [
                    'client_id' => $this->application->getClientId(),
                    'client_secret' => $this->application->getClientSecret(),
                ], $params);
            } else {
                $this->setError(new VkApiException($this, 'Not VkApplication'));
                return $this;
            }
        } else {
            $params = array_merge($this->paramsDefault, $params);
        }
        if ($this->token) {
            $params['access_token'] = $this->token;
        }
        $key = self::BASE_URL . $method . '?' . self::httpBuildQuery($params);
        if ($cacheTtl !== false && $this->cache) {
            $result = $this->cache->get($key);
            if ($result !== null) {
                $this->setResponse($result);
                return $this;
            }
        }
        $i = 0;
        do {
            if ($i > 0) {
                sleep(2);
            }
            $i++;
            $this->curl->get($method, $params);
            if ($this->curl->getResponseCode() !== 200) {
                $this->setError(new VkApiException('HTTP CODE ' . $this->curl->getResponseCode(), $this->curl->getResponseCode(), 'В ответ пришёл код не 200'));
                return $this;
            }
            $result = json_decode($this->curl->getResponseBody(), true);
            if (isset($result['response'])) {
                if ($cacheTtl !== false && $this->cache) {
                    $this->cache->save($key, $result['response'], $cacheTtl);
                }
                $this->setResponse($result['response']);
                return $this;
            } elseif (isset($result['error'])) {
                switch ($result['error']['error_code']) {
                    case 14:
                        if (!($this->captcha instanceof DeCaptchaBase)) {
                            $this->setError(new VkApiException('Не подключен сервис по распозданию капч'));
                            return $this;
                        }
                        if ($this->captcha->recognize($result['error']['captcha_img'])) {
                            $params['captcha_sid'] = $result['error']['captcha_sid'];
                            $params['captcha_key'] = $this->captcha->getCode();
                        }
                        break;
                    default:
                        $this->setError(new VkApiException($result['error']['error_msg'], $result['error']['error_code'], $result['error']['error_text'] ?? ''));
                        return $this;
                }
            }
        } while (!isset($result['response']) && $i < 3);
        $this->setError(new VkApiException('Not data', 0, 'Шляпа полная. Не удалось получить данные.'));
        return $this;
    }

    /**
     * @return bool
     */
    public function isResponse()
    {
        return $this->response && is_array($this->response) && count($this->response);
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return count($this->response);
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return $this->error instanceof VkApiException;
    }

    /**
     * @return array|int
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->response['items'];
    }

    /**
     * @return array|string
     */
    public function getItemFirst()
    {
        return $this->response['items'][0];
    }

    /**
     * @return int
     */
    public function getItemsCount()
    {
        return $this->response['count'];
    }

    /**
     * @return array
     */
    public function getResponseFirst()
    {
        return $this->response[0];
    }

    /**
     * @return VkApiException
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @throws VkApiException
     */
    public function runError()
    {
        throw $this->error;
    }

    private function clear()
    {
        $this->response = null;
        $this->error = null;
    }

    /**
     * @param VkApiException $error
     */
    public function setError(VkApiException $error)
    {
        $this->error = $error;
    }

    /**
     * @param array|int $response
     */
    private function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @param $params
     *
     * @return string
     */
    private static function httpBuildQuery($params)
    {
        $strings = [];
        foreach ($params as $key => $value) {
            $strings[] = "$key=" . urlencode($value);
        }
        return implode('&', $strings);
    }

    /**
     * @return string
     */
    public function getPageLogin(): string
    {
        return 'https://oauth.vk.com/authorize?client_id=' . $this->application->getClientId() . '&display=mobile&scope=' . implode(',', $this->scope) . '&redirect_uri=https://oauth.vk.com/blank.html&response_type=token&v=' . $this->paramsDefault['v'];
    }
}