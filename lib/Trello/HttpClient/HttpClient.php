<?php

namespace Trello\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Trello\Exception\ErrorException;
use Trello\Exception\RuntimeException;

class HttpClient implements HttpClientInterface
{
    protected $options = [
        'base_uri' => 'https://api.trello.com/',
        'user_agent' => 'php-trello-api (http://github.com/cdaguerre/php-trello-api)',
        'timeout' => 10,
        'api_version' => 1,
    ];

    /**
     * @var ClientInterface
     */
    protected $client;

    protected $headers = [];

    private $lastResponse;
    private $lastRequest;

    /**
     * @param array $options
     * @param ClientInterface $client
     */
    public function __construct(array $options = [], ClientInterface $client = null)
    {
        $this->options = array_merge($this->options, $options);
        $client = $client ?: new Client($this->options);
        $this->client = $client;

        $this->clearHeaders();
    }

    /**
     * {@inheritDoc}
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function setHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
    }

    /**
     * Clears used headers
     */
    public function clearHeaders()
    {
        $this->headers = [
            'Accept' => sprintf('application/vnd.orcid.%s+json', $this->options['api_version']),
            'User-Agent' => sprintf('%s', $this->options['user_agent']),
        ];
    }

    /**
     * @param string $eventName
     * @param callable $listener
     */
    public function addListener($eventName, $listener)
    {
        $this->client->getEmitter()->on($eventName, $listener);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->client->getEmitter()->attach($subscriber);
    }

    /**
     * {@inheritDoc}
     */
    public function get($path, array $parameters = [], array $headers = [])
    {
        return $this->request($path, $parameters, 'GET', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function post($path, $body = null, array $headers = [])
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $this->request($path, $body, 'POST', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function patch($path, $body = null, array $headers = [])
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $this->request($path, $body, 'PATCH', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function delete($path, $body = null, array $headers = [])
    {
        return $this->request($path, $body, 'DELETE', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function put($path, $body, array $headers = [])
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $this->request($path, $body, 'PUT', $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function request($path, $body = null, $httpMethod = 'GET', array $headers = [], array $options = [])
    {
        $request = $this->createRequest($httpMethod, $path, $body, $headers, $options);

        try {
            $response = $this->client->request($request['httpMethod'], $request['path'], $request['options']);
        } catch (\LogicException $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        } catch (\RuntimeException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        $this->lastRequest = $request;
        $this->lastResponse = $response;

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate($tokenOrLogin, $password)
    {
        $this->setHeaders([
            'Authorization' => 'OAuth oauth_consumer_key="' . $tokenOrLogin . '", oauth_token="' . $password . '"'
        ]);
    }

    /**
     * @return mixed
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return mixed
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @param $httpMethod
     * @param $path
     * @param $body
     * @param array $headers
     * @param array $options
     * @return array
     */
    protected function createRequest($httpMethod, $path, $body = null, array $headers = [], array $options = [])
    {
        $path = $this->options['api_version'] . '/' . $path;

        if ($httpMethod === 'GET' && $body) {
            $path .= (false === strpos($path, '?') ? '?' : '&');
            $path .= utf8_encode(http_build_query($body, '', '&'));
        }

        $options['body'] = $body ? http_build_query($body) : null;
        $options['headers'] = array_merge($this->headers, $headers);

        return [
            'httpMethod' => $httpMethod,
            'path' => $path,
            'options' => $options
        ];
    }
}
