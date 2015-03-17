<?php

if (IN_serendipity !== true) {
    die ("Don't hack!");
}

require_once __DIR__ . '/RequestWrapper.php';

@serendipity_plugin_api::load_language(dirname(__FILE__));

/**
 * Class serendipity_event_sharedcount
 *
 * @author Matthias Gutjahr <mattsches@gmail.com>
 */
class serendipity_event_sharedcount extends serendipity_event
{
    /**
     * @var string
     */
    public $title = PLUGIN_SHAREDCOUNT_TITLE;

    /**
     * @param serendipity_property_bag $propbag
     * @return void
     */
    public function introspect(&$propbag)
    {
        global $serendipity;
        $propbag->add('name', PLUGIN_SHAREDCOUNT_TITLE);
        $propbag->add('description', PLUGIN_SHAREDCOUNT_DESC);
        $propbag->add('stackable', false);
        $propbag->add('author', 'Matthias Gutjahr');
        $propbag->add(
            'requirements',
            array(
                'serendipity' => '1.7',
                'smarty' => '3',
                'php' => '5.4'
            )
        );
        $propbag->add('version', '0.0.3');
        $propbag->add(
            'groups',
            version_compare($serendipity['version'], '2.0.beta1') >= 0 ? array(
                'FRONTEND_VIEWS',
                'BACKEND_FEATURES',
            ) : array(
                'FRONTEND_VIEWS',
            )
        );
        $propbag->add(
            'event_hooks',
            array(
                'frontend_display' => true,
                'entry_display' => true,
                'backend_dashboard' => version_compare($serendipity['version'], '2.0.beta1') >= 0 ? true : false,
                'css' => true,
                'css_backend' => true,
                'external_plugin' => true,
            )
        );
        $propbag->add(
            'configuration',
            array(
                'api_domain',
                'api_key',
                'cache_ttl',
                'services',
                'use_icons',
                'logarithmic_cache_ttl',
            )
        );
    }

    /**
     * @param string $title
     * @return void
     */
    public function generate_content(&$title)
    {
        $title = $this->title;
    }

    /**
     * @param string $name
     * @param serendipity_property_bag $propbag
     * @return bool
     */
    public function introspect_config_item($name, &$propbag)
    {
        switch ($name) {
            case 'api_domain':
                $propbag->add('type', 'string');
                $propbag->add('name', PLUGIN_SHAREDCOUNT_API_DOMAIN_TITLE);
                $propbag->add('description', PLUGIN_SHAREDCOUNT_API_DOMAIN_DESC);
                $propbag->add('default', 'free.sharedcount.com');
                break;
            case 'api_key':
                $propbag->add('type', 'string');
                $propbag->add('name', PLUGIN_SHAREDCOUNT_API_KEY_TITLE);
                $propbag->add('description', PLUGIN_SHAREDCOUNT_API_KEY_DESC);
                $propbag->add('default', '');
                break;
            case 'cache_ttl':
                $propbag->add('type', 'string');
                $propbag->add('name', PLUGIN_SHAREDCOUNT_CACHE_TTL_TITLE);
                $propbag->add('description', PLUGIN_SHAREDCOUNT_CACHE_TTL_DESC);
                $propbag->add('default', '86400');
                break;
            case 'services':
                $propbag->add('type', 'sequence');
                $propbag->add('name', PLUGIN_SHAREDCOUNT_SOCIAL_NETWORKS_TITLE);
                $propbag->add('description', PLUGIN_SHAREDCOUNT_SOCIAL_NETWORKS_DESC);
                $propbag->add('checkable', true);
                $values = array(
                    'Facebook:commentsbox_count' => array('display' => 'Facebook Commentsbox'),
                    'Facebook:click_count' => array('display' => 'Facebook Clicks'),
                    'Facebook:total_count' => array('display' => 'Facebook Total'),
                    'Facebook:comment_count' => array('display' => 'Facebook Comments'),
                    'Facebook:like_count' => array('display' => 'Facebook Likes'),
                    'Facebook:share_count' => array('display' => 'Facebook Shared'),
                    'Twitter' => array('display' => 'Twitter'),
                    'Pinterest' => array('display' => 'Pinterest'),
                    'LinkedIn' => array('display' => 'LinkedIn'),
                    'StumbleUpon' => array('display' => 'StumbleUpon'),
                    'GooglePlusOne' => array('display' => 'Google+'),
                );
                $propbag->add('values', $values);
                $propbag->add('default', 'Facebook:like_count,Twitter,GooglePlusOne');
                break;
            case 'use_icons':
                $propbag->add('type', 'boolean');
                $propbag->add('name', PLUGIN_SHAREDCOUNT_USE_ICONS_TITLE);
                $propbag->add('description', PLUGIN_SHAREDCOUNT_USE_ICONS_DESC);
                $propbag->add('default', false);
                break;
            case 'logarithmic_cache_ttl':
                $propbag->add('type', 'boolean');
                $propbag->add('name', PLUGIN_SHAREDCOUNT_LOGARITHMIC_CACHE_TITLE);
                $propbag->add('description', PLUGIN_SHAREDCOUNT_LOGARITHMIC_CACHE_DESC);
                $propbag->add('default', false);
                break;
        }

        return true;
    }

    /**
     * @param string $event
     * @param serendipity_property_bag $bag
     * @param array $eventData
     * @param null|array $addData
     * @return bool
     * @throws Exception
     * @throws SmartyException
     */
    public function event_hook($event, &$bag, &$eventData, $addData = NULL)
    {
        global $serendipity;
        $hooks = &$bag->get('event_hooks');
        $apiKey = $this->get_config('api_key');
        if (empty($apiKey)) {
            return false;
        }
        $currentTimestamp = time();
        if (isset($addData['currentTimestamp'])) {
            $currentTimestamp = $addData['currentTimestamp'];
        }
        if (isset($addData['requestWrapper'])) {
            $requestFactory = $addData['requestWrapper'];
        } else {
            $httpDirname = (defined('S9Y_PEAR_PATH') ? S9Y_PEAR_PATH : S9Y_INCLUDE_PATH . 'bundled-libs/') . 'HTTP/';
            set_include_path(get_include_path() . PATH_SEPARATOR . $httpDirname . '/..');
            $curl = 'https://' . $this->get_config('api_domain') . '/url';
            if (file_exists($httpDirname . 'Request2.php')) {
                require_once $httpDirname . 'Request2.php';
                $request = new HTTP_Request2(
                    $curl,
                    HTTP_Request2::METHOD_GET,
                    array('follow_redirects' => true, 'max_redirects' => 3, 'ssl_verify_peer' => false)
                );
            } else {
                // Fallback to old solution
                require_once $httpDirname . 'Request.php';
                $request = new HTTP_Request($curl, array('allowRedirects' => true, 'maxRedirects' => 3));
            }
            $requestFactory = new RequestWrapper();
            $requestFactory->setRequest($request);
            $requestFactory->setApiKey($this->get_config('api_key'));
        }

        if (isset($hooks[$event])) {
            switch ($event) {
                case 'entry_display':
                    if (count($eventData) === 1 && isset($eventData[0]['add_footer'])) {
                        $entryUrl = serendipity_archiveURL($eventData[0]['id'], $eventData[0]['title']);
                        $requestFactory->setUrl($entryUrl);
                        $result = $this->getSharedCountForUrl($requestFactory, $currentTimestamp, $eventData[0]['last_modified']);
                        $result = json_decode($result);
                        $sharedCountString = $this->getResultString($result);
                        $eventData[0]['add_footer'] .= $sharedCountString;
                    }

                    return true;

                case 'backend_dashboard':
                    if (version_compare($serendipity['version'], '2.0.beta1') < 0) {
                        return false;
                    }
                    $latestEntries = serendipity_fetchEntries(false, false, 8);
                    if (count($latestEntries) === 0) {
                        return false;
                    }
                    foreach ($latestEntries as $key => $entry) {
                        $entryUrl = serendipity_archiveURL($entry['id'], $entry['title']);
                        $requestFactory->setUrl($entryUrl);
                        $result = $this->getSharedCountForUrl($requestFactory, $currentTimestamp, $entry['last_modified']);
                        $latestEntries[$key]['sharedcount'] = $this->getResultString(json_decode($result));
                    }
                    serendipity_smarty_init();
                    /** @var Serendipity_Smarty $serendipitySmarty */
                    $serendipitySmarty = $serendipity['smarty'];
                    $serendipitySmarty->assign(
                        array(
                            'sharedcount_entries' => $latestEntries,
                        )
                    );
                    $tfile = serendipity_getTemplateFile('backend_dashboard.tpl', 'serendipityPath');
                    if (!$tfile || $tfile == 'backend_dashboard.tpl') {
                        $tfile = dirname(__FILE__) . '/backend_dashboard.tpl';
                    }
//                    $inclusion = $serendipitySmarty->security_settings[INCLUDE_ANY];
//                    $serendipitySmarty->security_settings[INCLUDE_ANY] = true;
                    $content = $serendipitySmarty->fetch('file:' . $tfile);
//                    $serendipitySmarty->security_settings[INCLUDE_ANY] = $inclusion;
                    echo $content;
                    return true;

                case 'css_backend':
                case 'css':
                    if ($this->get_config('use_icons')) {
                        $out = serendipity_getTemplateFile('serendipity_event_sharedcount.css', 'serendipityPath');
                        if ($out && $out != 'serendipity_event_sharedcount.css') {
                            $eventData .= file_get_contents($out);
                        } else {
                            $eventData .= file_get_contents(dirname(__FILE__) . '/serendipity_event_sharedcount.css');
                        }
                        $pluginpath = pathinfo(dirname(__FILE__));
                        $pluginpath = basename(rtrim($pluginpath['dirname'], '/')) . '/serendipity_event_sharedcount/';
                        $search = array('{PLUGIN_PATH}');
                        $replace = array('plugins/' . $serendipity['serendipityHTTPPath'] . $pluginpath);
                        $eventData = str_replace($search, $replace, $eventData);
                    }
                    return true;
                    break;

                case 'external_plugin':
                    if ($eventData !== 'sharedcount_refresh') {
                        return false;
                    }
                    $latestEntries = serendipity_fetchEntries(false, false, 8);
                    if (count($latestEntries) === 0) {
                        return false;
                    }
                    $result = array();
                    foreach ($latestEntries as $key => $entry) {
                        $entryUrl = serendipity_archiveURL($entry['id'], $entry['title']);
                        $requestFactory->setUrl($entryUrl);
                        $result[$entry['id']] = $this->getSharedCountForUrl($requestFactory, $currentTimestamp, $entry['last_modified'], true);
//                        $latestEntries[$key]['sharedcount'] = $this->getResultString(json_decode($result));
                    }
                    echo json_encode($result);
                    break;

                default:
                    return false;
            }
        }
        return false;
    }

    /**
     * @param RequestWrapper $request
     * @param string|int $currentTimestamp Evil hackery for unit tests :\
     * @param string|null $lastModifiedTimestamp
     * @param boolean $forceReload
     * @return string mixed
     */
    protected function getSharedCountForUrl(RequestWrapper $request, $currentTimestamp, $lastModifiedTimestamp = null, $forceReload = false)
    {
        $result = null;
        if (!$forceReload) {
            $lastModifiedDiff = $this->getLastModifiedDiff($lastModifiedTimestamp, $currentTimestamp);
            $result = $this->readCache($request->getUrl(), $lastModifiedDiff, $currentTimestamp, $forceReload);
        }
        if ($result !== null) {
            return $result;
        }

        $httpDirname = (defined('S9Y_PEAR_PATH') ? S9Y_PEAR_PATH : S9Y_INCLUDE_PATH . 'bundled-libs/') . 'HTTP/';
        set_include_path(get_include_path() . PATH_SEPARATOR . $httpDirname . '/..');
        $json = $request->getJsonResult();
        $this->writeCache($request->getUrl(), $json);
        return $json;
    }

    /**
     * Reads the result from cache.
     *
     * @param string $url
     * @param int $lastModifiedDiff
     * @param string|null $currentTimestamp Evil hackery for unit tests :\
     * @return null|string
     */
    protected function readCache($url, $lastModifiedDiff, $currentTimestamp = null)
    {
        $filename = $this->getCacheFilename($url);
        if (!file_exists($filename)) {
            return null;
        }
        $filemtimeDiff = $this->getFilemtimeDiff($currentTimestamp, $filename);
        $cacheTTL = $this->getCacheTtl($lastModifiedDiff);
        if ($filemtimeDiff < $cacheTTL) {
            $result = file_get_contents($filename);
            return $result;
        }
        return null;
    }

    /**
     * Writes the result to cache.
     *
     * @param string $url
     * @param string $result
     */
    protected function writeCache($url, $result)
    {
        $filename = $this->getCacheFilename($url);
        $cache_dir = dirname($filename);
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir);
        }
        file_put_contents($filename, $result);
    }

    /**
     * Gets the path to the cache file.
     *
     * @param string $url
     * @return string
     */
    protected function getCacheFilename($url)
    {
        global $serendipity;
        if (!defined('PATH_SMARTY_COMPILE')) {
            return '';
        }
        return $serendipity['serendipityPath']  . PATH_SMARTY_COMPILE . '/sharedcount/' . sha1($url);
    }

    /**
     * @param stdClass $result
     * @return string
     */
    protected function getResultString(stdClass $result)
    {
        $templates = array(
            'Facebook:commentsbox_count' => '%d FB Commentsbox',
            'Facebook:click_count' => '%d FB Clicks',
            'Facebook:total_count' => '%d FB Count',
            'Facebook:comment_count' => '%d FB Comments',
            'Facebook:like_count' => '%d FB Likes',
            'Facebook:share_count' => '%d FB Shares',
            'Twitter' => '%d Tweets',
            'Pinterest' => '%d Pins',
            'LinkedIn' => '%d LinkedIn Dings',
            'StumbleUpon' => '%d StumbleUpons',
            'GooglePlusOne' => '%d +1',
        );
        $separator = ' | ';
        $icons = array();
        if ($this->get_config('use_icons')) {
            $icons = array(
                'Facebook:commentsbox_count' => 'icon-facebook2',
                'Facebook:click_count' => 'icon-facebook2',
                'Facebook:total_count' => 'icon-facebook2',
                'Facebook:comment_count' => 'icon-facebook2',
                'Facebook:like_count' => 'icon-facebook2',
                'Facebook:share_count' => 'icon-facebook2',
                'Twitter' => 'icon-twitter2',
                'Pinterest' => 'icon-pinterest2',
                'LinkedIn' => 'icon-linkedin',
                'StumbleUpon' => 'icon-stumbleupon',
                'GooglePlusOne' => 'icon-google-plus2',
            );
            $separator = '&nbsp;&nbsp;';
        }
        $sharedCounts = array();
        $services = explode(',', $this->get_config('services'));
        foreach ($services as $service) {
            if (strpos($service, ':') !== false) {
                $parts = explode(':', $service);
                $r2 = $result->{$parts[0]};
                $r = $r2->{$parts[1]};
            } else {
                $r = $result->{$service};
            }
            if ($this->get_config('use_icons')) {
                $sharedCounts[] = sprintf('%d', $r) . '&nbsp;<span aria-hidden="true" class="' . $icons[$service] . '"></span><span class="screen-reader-text">' . sprintf($templates[$service], $r) . '</span>';
            } else {
                $sharedCounts[] = sprintf($templates[$service], $r);
            }
        }
        $sharedCountString = '<div class="serendipity_sharedcount">' . PLUGIN_SHAREDCOUNT_REACTIONS . ' ' . implode($separator, $sharedCounts) . '</div>';
        return $sharedCountString;
    }

    /**
     * @param $lastModifiedTimestamp
     * @param $currentTimestamp
     * @return int
     */
    protected function getLastModifiedDiff($lastModifiedTimestamp, $currentTimestamp)
    {
        if ($currentTimestamp !== null && $lastModifiedTimestamp !== null) {
            $lastModifiedDiff = $currentTimestamp - $lastModifiedTimestamp;

            return $lastModifiedDiff;
        } elseif ($currentTimestamp === null && $lastModifiedTimestamp !== null) {
            $lastModifiedDiff = time() - $lastModifiedTimestamp;

            return $lastModifiedDiff;
        } else {
            $lastModifiedDiff = $currentTimestamp;

            return $lastModifiedDiff;
        }
    }

    /**
     * @param $lastModifiedDiff
     * @return float|int
     */
    protected function getCacheTtl($lastModifiedDiff)
    {
        $cacheTTL = (int)$this->get_config('cache_ttl');
        if ($this->get_config('logarithmic_cache_ttl') == true) {
            if ($lastModifiedDiff <= $cacheTTL) {
                $cacheTTL = $cacheTTL / 24;

                return $cacheTTL; // eg. 1 hour
            } elseif ($lastModifiedDiff <= ($cacheTTL * 7)) { // eg. 1 week
                $cacheTTL = $cacheTTL / 4;

                return $cacheTTL; // eg. 6 hours
            }

            return $cacheTTL;
        }

        return $cacheTTL;
    }

    /**
     * @param $currentTimestamp
     * @param $filename
     * @return int
     */
    protected function getFilemtimeDiff($currentTimestamp, $filename)
    {
        if ($currentTimestamp === null) {
            $filemtimeDiff = time() - filemtime($filename);

            return $filemtimeDiff;
        } else {
            $filemtimeDiff = $currentTimestamp - filemtime($filename);

            return $filemtimeDiff;
        }
    }
}
