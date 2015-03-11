<?php

/**
 * Class RequestWrapper
 *
 * @author Matthias Gutjahr <mattsches@gmail.com>
 */
class RequestWrapper
{
    /**
     * @var HTTP_Request|HTTP_Request2
     */
    protected $request;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @return mixed|null|string
     */
    public function getJsonResult()
    {
        $jsonResult = null;
        if ($this->getRequest() instanceof HTTP_Request2) {
            $requestUrl = $this->getRequest()->getUrl();
            $requestUrl->setQueryVariables(
                array(
                    'apikey' => $this->getApiKey(),
                    'url' => $this->getUrl(),
                )
            );
            try {
                $res = $this->getRequest()->send();
                if (200 == $res->getStatus()) {
                    $jsonResult = $res->getBody();
                } else {
                    return null;
                }
            } catch (HTTP_Request2_Exception $e) {
                return null;
            }
        } elseif ($this->getRequest() instanceof HTTP_Request) {
            $this->getRequest()->addQueryString('apikey', $this->getApiKey());
            $this->getRequest()->addQueryString('url', $this->getUrl());
            if (PEAR::isError($this->getRequest()->sendRequest()) || $this->getRequest()->getResponseCode() != '200') {
                return null;
            }
            $jsonResult = $this->getRequest()->getResponseBody();
        }
        return $jsonResult;
    }

    /**
     * @param HTTP_Request|HTTP_Request2 $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @return HTTP_Request|HTTP_Request2
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}
