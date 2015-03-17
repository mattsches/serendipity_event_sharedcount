<?php

require_once S9Y_INCLUDE_PATH . 'tests/plugins/PluginTest.php';
require_once S9Y_INCLUDE_PATH . 'plugins/additional_plugins/serendipity_event_sharedcount/serendipity_event_sharedcount.php';
require_once S9Y_INCLUDE_PATH . 'bundled-libs/Smarty/libs/Smarty.class.php';
require_once S9Y_INCLUDE_PATH . 'bundled-libs/Smarty/libs/sysplugins/smarty_security.php';
require_once S9Y_INCLUDE_PATH . 'include/serendipity_smarty_class.inc.php';

use Patchwork as p;

p\replace("serendipity_smarty_init", function()
{
    return null;
});

/**
 * Class serendipity_event_sharedcountTest
 *
 * @author Matthias Gutjahr <mattsches@gmail.com>
 */
class serendipity_event_sharedcountTest extends PluginTest
{
    /**
     * @var serendipity_event_sharedcount
     */
    protected $object;

    /**
     * @var serendipity_property_bag
     */
    protected $propBag;

    /**
     * @var array
     */
    protected $eventData;

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * Set up
     */
    public function setUp()
    {
        global $serendipity;
        if (!defined('PATH_SMARTY_COMPILE')) {
            define('PATH_SMARTY_COMPILE', 'plugins/additional_plugins/serendipity_event_sharedcount/tests/data');
        }
        $serendipity['version'] = '2.0-rc1';
        $serendipity['baseURL'] = 'http://test.local/';
        $serendipity['rewrite'] = 'foo';
        $serendipity['showFutureEntries'] = false;
        $serendipity['enablePluginACL'] = false;
        $serendipity['template'] = 'TEMPL';
        $serendipity['template_backend'] = 'TEMPLBACKE';
        $smartyMock = Serendipity_Smarty::getInstance();
        $serendipity['smarty'] = $smartyMock;
        parent::setUp();
        $this->object = new serendipity_event_sharedcount('test');
        $this->propBag = new serendipity_property_bag();
    }

    /**
     * Tear down
     */
    public function tearDown()
    {
        $templateCacheDir = S9Y_INCLUDE_PATH . 'plugins/additional_plugins/serendipity_event_sharedcount/tests/data/TEMPL';
        $this->deleteCopies();
        $this->delTree($templateCacheDir, true);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testIntrospect()
    {
        $this->object->introspect($this->propBag);
        $this->assertEquals('0.0.2', $this->propBag->get('version'));
        $this->assertFalse($this->propBag->get('stackable'));
    }

    /**
     * @test
     */
    public function testEntryDisplaySingleFromCache()
    {
        $this->copyFixtures(array(1419229143, 1419229143));
        $mockRequestWrapper = $this->getMock('RequestWrapper', array('setUrl', 'getUrl'));
        $mockRequestWrapper->expects($this->once())->method('getUrl')->will($this->returnValue('http://test.local/archives/55-Test-Title.html'));
        $this->object->introspect($this->propBag);
        $this->object->set_config('api_key', 'mock_api_key');
        $eventData = array(
            0 => array(
                'id' => 55,
                'title' => 'Test Title',
                'add_footer' => 'Test Footer',
                'last_modified' => 1419229143,
            ),
        );
        $addData = array(
            'requestWrapper' => $mockRequestWrapper,
            'currentTimestamp' => 1419289143,
        );
        $this->object->event_hook('entry_display', $this->propBag, $eventData, $addData);
        $expected = array(
            0 => array(
                'id' => 55,
                'title' => 'Test Title',
                'add_footer' => 'Test Footer<div class="serendipity_sharedcount">Reactions: 2 FB Likes | 11 Tweets | 4 +1</div>',
                'last_modified' => 1419229143,
            ),
        );
        $this->assertEquals($expected, $eventData);
    }

    /**
     * Should read data from cache even if
     * logarithmic_cache_ttl = true
     *
     * time         = 1419230143
     * filemtime    = 1419229143
     * lastmodified = 1419229143
     * cache_ttl    = 86400
     *
     * @test
     */
    public function testEntryDisplaySingleFromLogarithmicCache()
    {
        $this->copyFixtures(array(1419229143, 1419229143));
        $mockRequestWrapper = $this->getMock('RequestWrapper', array('setUrl', 'getUrl', 'getRequest'));
        $mockRequestWrapper->expects($this->any())->method('getUrl')->will($this->returnValue('http://test.local/archives/55-Test-Title.html'));
        $this->object->introspect($this->propBag);
        $this->object->set_config('api_key', 'mock_api_key');
        $this->object->set_config('logarithmic_cache_ttl', true);
        $eventData = array(
            0 => array(
                'id' => 55,
                'title' => 'Test Title',
                'add_footer' => 'Test Footer',
                'last_modified' => 1419229143,
            ),
        );
        $addData = array(
            'requestWrapper' => $mockRequestWrapper,
            'currentTimestamp' => (1419229143 + 1000),
        );
        $this->object->event_hook('entry_display', $this->propBag, $eventData, $addData);
        $expected = array(
            0 => array(
                'id' => 55,
                'title' => 'Test Title',
                'add_footer' => 'Test Footer<div class="serendipity_sharedcount">Reactions: 2 FB Likes | 11 Tweets | 4 +1</div>',
                'last_modified' => 1419229143,
            ),
        );
        $this->assertEquals($expected, $eventData);
    }

    /**
     * @test
     * @dataProvider dataProvider
     * @param int $lastModified
     * @param int $currentTimestamp
     * @param string $expected
     */
    public function testEntryDisplaySingleFromLogarithmicRequest($lastModified, $currentTimestamp, $expected)
    {
        $this->copyFixtures(array(1419229143, 1419229143));
        $mockBody = '{"StumbleUpon":null,"Facebook":{"commentsbox_count":10,"click_count":5,"total_count":25,"comment_count":5,"like_count":2,"share_count":3},"GooglePlusOne":4,"Twitter":21,"Pinterest":1,"LinkedIn":2}';
        $mockNetUrl = $this->getMock('Net_URL2', array('setQueryVariables'));
        $mockResponse = $this->getMock('HTTP_Request2_Response', array('getStatus', 'getBody'));
        $mockResponse->expects($this->once())->method('getStatus')->will($this->returnValue(200));
        $mockResponse->expects($this->once())->method('getBody')->will($this->returnValue($mockBody));
        $mockRequest = $this->getMock('HTTP_Request2', array('getUrl', 'send'));
        $mockRequest->expects($this->once())->method('getUrl')->will($this->returnValue($mockNetUrl));
        $mockRequest->expects($this->once())->method('send')->will($this->returnValue($mockResponse));
        $mockRequestWrapper = $this->getMock('RequestWrapper', array('setUrl', 'getUrl', 'getRequest'));
        $mockRequestWrapper->expects($this->exactly(3))->method('getUrl')->will($this->returnValue('http://test.local/archives/55-Test-Title.html'));
        $mockRequestWrapper->expects($this->exactly(3))->method('getRequest')->will($this->returnValue($mockRequest));
        $this->object->introspect($this->propBag);
        $this->object->set_config('api_key', 'mock_api_key');
        $this->object->set_config('logarithmic_cache_ttl', true);
        $eventData = array(
            0 => array(
                'id' => 55,
                'title' => 'Test Title',
                'add_footer' => 'Test Footer',
                'last_modified' => $lastModified,
            ),
        );
        $addData = array(
            'requestWrapper' => $mockRequestWrapper,
            'currentTimestamp' => $currentTimestamp,
        );
        $this->object->event_hook('entry_display', $this->propBag, $eventData, $addData);
        $expected = array(
            0 => array(
                'id' => 55,
                'title' => 'Test Title',
                'add_footer' => $expected,
                'last_modified' => $lastModified,
            ),
        );
        $this->assertEquals($expected, $eventData);
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return array(
            array(1419229143, (1419229143 + 50000), 'Test Footer<div class="serendipity_sharedcount">Reactions: 2 FB Likes | 21 Tweets | 4 +1</div>'),
            array(1419229143, (1419229143 + 259200), 'Test Footer<div class="serendipity_sharedcount">Reactions: 2 FB Likes | 21 Tweets | 4 +1</div>'),
            array(1419229143, (1419229143 + 9259200), 'Test Footer<div class="serendipity_sharedcount">Reactions: 2 FB Likes | 21 Tweets | 4 +1</div>'),
        );
    }

    /**
     * @test
     */
    public function getMultipleEntriesFromCacheForBackendDashboard()
    {
        $this->copyFixtures(array(1419229143, 1419229143));
        p\replace("serendipity_fetchEntries", function()
        {
            return array(
                0 => array(
                    'id' => 55,
                    'title' => 'Test Title',
                    'add_footer' => 'Test Footer',
                    'last_modified' => 1419229143,
                ),
                1 => array(
                    'id' => 54,
                    'title' => 'Test Title 2',
                    'add_footer' => 'Test Footer 2',
                    'last_modified' => 1419229943,
                ),
            );
        });
        define('INCLUDE_ANY', true);
        $mockRequestWrapper = $this->getMock('RequestWrapper', array('setUrl', 'getUrl'));
        $mockRequestWrapper->expects($this->any())->method('getUrl')->will($this->onConsecutiveCalls(
            'http://test.local/archives/55-Test-Title.html',
            'http://test.local/archives/54-Test-Title-2.html'
        ));
        $this->object->introspect($this->propBag);
        $this->object->set_config('api_key', 'mock_api_key');
        $eventData = array();
        $addData = array(
            'requestWrapper' => $mockRequestWrapper,
            'currentTimestamp' => 1419289143,
        );
        $this->object->event_hook('backend_dashboard', $this->propBag, $eventData, $addData);
        $expected = '<ol class="plainList">
        <li class="clearfix" >
        <a href="?serendipity[action]=admin&amp;serendipity[adminModule]=entries&amp;serendipity[adminAction]=edit&amp;serendipity[id]=55" title="#55: Test Title">Test Title</a ><br/>
        <small id="sharedcount_entry_55"><div class="serendipity_sharedcount">Reactions: 2 FB Likes | 11 Tweets | 4 +1</div></small>
    </li >
        <li class="clearfix" >
        <a href="?serendipity[action]=admin&amp;serendipity[adminModule]=entries&amp;serendipity[adminAction]=edit&amp;serendipity[id]=54" title="#54: Test Title 2">Test Title 2</a ><br/>
        <small id="sharedcount_entry_54"><div class="serendipity_sharedcount">Reactions: 2 FB Likes | 11 Tweets | 4 +1</div></small>
    </li >
    </ol>';
        $this->assertContains($expected, $this->getActualOutput());
    }

    public function foobar($input)
    {
        switch($input) {
            default:
                return null;
        }
    }

    /**
     * Delete a directory recursively
     *
     * @param string $dir
     * @param boolean $deleteRoot
     * @return bool
     */
    protected function delTree($dir, $deleteRoot = false)
    {
        if (!file_exists($dir)) {
            return false;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file", $deleteRoot) : unlink("$dir/$file");
        }

        if ($deleteRoot) {
            rmdir($dir);
        }
        return true;
    }

    /**
     * @param array $timestamps
     */
    protected function copyFixtures($timestamps = array())
    {
        global $serendipity;
        $source = $serendipity['serendipityPath'] . PATH_SMARTY_COMPILE . '/../fixtures';
        $destination = $serendipity['serendipityPath'] . PATH_SMARTY_COMPILE . '/sharedcount';
        $files = glob($source . "/*");
        foreach ($files as $file) {
            $destFile = str_replace($source, $destination, $file);
            copy($file, $destFile);
            $timestamp = array_shift($timestamps);
            if (!touch($destFile, $timestamp)) {
                throw new PHPUnit_Framework_Exception('Could not touch file!');
            }
        }
    }

    /**
     *
     */
    protected function deleteCopies()
    {
        global $serendipity;
        $this->delTree($serendipity['serendipityPath'] . PATH_SMARTY_COMPILE . '/sharedcount');
    }
}
