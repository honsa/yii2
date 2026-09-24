<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yiiunit\framework\console\controllers;

use Yii;
use yii\console\controllers\AnalyzeController;
use yii\console\ExitCode;
use yii\helpers\FileHelper;
use yiiunit\TestCase;

/**
 * Unit test for [[\yii\console\controllers\AnalyzeController]].
 * @see AnalyzeController
 *
 * @group console
 */
class AnalyzeControllerTest extends TestCase
{
    /**
     * @var BufferedAnalyzeController
     */
    private $_controller;

    /**
     * @var string
     */
    private $_testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();

        $this->_controller = new BufferedAnalyzeController('analyze', Yii::$app);
        $this->_controller->interactive = false;

        // Create a temporary test directory
        $this->_testDir = Yii::getAlias('@yiiunit/runtime/analyze-test-' . time());
        FileHelper::createDirectory($this->_testDir);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->_testDir)) {
            FileHelper::removeDirectory($this->_testDir);
        }
        parent::tearDown();
    }

    public function testActionIndex(): void
    {
        $result = $this->_controller->actionIndex();
        $this->assertEquals(ExitCode::OK, $result);
    }

    public function testActionCodeWithNonExistentDirectory(): void
    {
        $result = $this->_controller->actionCode('/non/existent/path');
        $output = $this->_controller->flushStdOutBuffer();

        $this->assertEquals(ExitCode::DATAERR, $result);
    }

    public function testActionCodeAnalyzesPhpFiles(): void
    {
        // Create test PHP files
        $file1 = $this->_testDir . '/test1.php';
        $file2 = $this->_testDir . '/test2.php';

        file_put_contents($file1, "<?php\n// TODO: implement this\necho 'test';");
        file_put_contents($file2, "<?php\n// FIXME: bug here\nclass Test {}");

        $result = $this->_controller->actionCode($this->_testDir);
        $output = $this->_controller->flushStdOutBuffer();

        $this->assertEquals(ExitCode::OK, $result);
        $this->assertStringContainsString('Total PHP files: 2', $output);
        $this->assertStringContainsString('TODOs found: 1', $output);
        $this->assertStringContainsString('FIXMEs found: 1', $output);
    }

    public function testActionCodeWithVerboseOption(): void
    {
        // Create test PHP file with TODO
        $file = $this->_testDir . '/test.php';
        file_put_contents($file, "<?php\n// TODO: implement this feature\necho 'test';");

        $this->_controller->verbose = true;

        $result = $this->_controller->actionCode($this->_testDir);
        $output = $this->_controller->flushStdOutBuffer();

        $this->assertEquals(ExitCode::OK, $result);
        $this->assertStringContainsString('TODO comments:', $output);
        $this->assertStringContainsString('implement this feature', $output);
    }

    public function testActionLogsWithNonExistentDirectory(): void
    {
        $result = $this->_controller->actionLogs('/non/existent/path');
        $output = $this->_controller->flushStdOutBuffer();

        $this->assertEquals(ExitCode::DATAERR, $result);
    }

    public function testActionLogsAnalyzesLogFiles(): void
    {
        // Create test log files
        $logFile = $this->_testDir . '/app.log';
        $logContent = <<<LOG
2023-01-01 10:00:00 [error] Database connection failed
2023-01-01 10:01:00 [warning] Deprecated function used
2023-01-01 10:02:00 [info] Application started
2023-01-01 10:03:00 [error] File not found
LOG;
        file_put_contents($logFile, $logContent);

        $result = $this->_controller->actionLogs($this->_testDir);
        $output = $this->_controller->flushStdOutBuffer();

        $this->assertEquals(ExitCode::OK, $result);
        $this->assertStringContainsString('Total log files: 1', $output);
        $this->assertStringContainsString('Errors: 2', $output);
        $this->assertStringContainsString('Warnings: 1', $output);
        $this->assertStringContainsString('Info: 1', $output);
    }

    public function testActionLogsWithNoLogFiles(): void
    {
        $result = $this->_controller->actionLogs($this->_testDir);
        $output = $this->_controller->flushStdOutBuffer();

        $this->assertEquals(ExitCode::OK, $result);
        $this->assertStringContainsString('No log files found', $output);
    }

    public function testActionLogsWithVerboseOption(): void
    {
        // Create test log file with errors
        $logFile = $this->_testDir . '/app.log';
        $logContent = <<<LOG
2023-01-01 10:00:00 [error] Database connection failed
2023-01-01 10:01:00 [error] Database connection failed
2023-01-01 10:02:00 [error] File not found
LOG;
        file_put_contents($logFile, $logContent);

        $this->_controller->verbose = true;

        $result = $this->_controller->actionLogs($this->_testDir);
        $output = $this->_controller->flushStdOutBuffer();

        $this->assertEquals(ExitCode::OK, $result);
        $this->assertStringContainsString('Most common errors:', $output);
        $this->assertStringContainsString('Database connection failed', $output);
    }
}
