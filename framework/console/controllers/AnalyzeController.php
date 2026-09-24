<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * Allows you to analyze PHP code and log files.
 *
 * Analyze PHP code for common issues:
 *
 *     yii analyze/code path/to/code
 *
 * Analyze log files for errors and patterns:
 *
 *     yii analyze/logs path/to/logs
 *
 * @author Yii Software LLC
 * @since 2.0
 */
class AnalyzeController extends Controller
{
    /**
     * @var bool whether to output detailed information
     */
    public $verbose = false;

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['verbose']);
    }

    /**
     * Displays available analysis commands.
     */
    public function actionIndex()
    {
        $this->stdout("Available analysis commands:\n\n", Console::BOLD);
        $this->stdout("  yii analyze/code <path>  - Analyze PHP code in the specified directory\n");
        $this->stdout("  yii analyze/logs <path>  - Analyze log files in the specified directory\n");
        return ExitCode::OK;
    }

    /**
     * Analyzes PHP code for common issues.
     *
     * This command scans PHP files in the specified directory and reports:
     * - Total files and lines of code
     * - TODO, FIXME, and XXX comments
     * - Basic code metrics
     *
     * @param string $path the path to the directory containing PHP code
     * @return int the exit code
     */
    public function actionCode($path)
    {
        $path = Yii::getAlias($path);

        if (!is_dir($path)) {
            $this->stderr("Error: Directory '$path' does not exist.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("Analyzing PHP code in: $path\n\n", Console::BOLD);

        $files = FileHelper::findFiles($path, ['only' => ['*.php']]);
        $totalFiles = count($files);
        $totalLines = 0;
        $todos = [];
        $fixmes = [];
        $xxxs = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $totalLines += count($lines);

            foreach ($lines as $lineNum => $line) {
                if (preg_match('/\/\/\s*TODO:?\s*(.+)/i', $line, $matches)) {
                    $todos[] = ['file' => $file, 'line' => $lineNum + 1, 'text' => trim($matches[1])];
                }
                if (preg_match('/\/\/\s*FIXME:?\s*(.+)/i', $line, $matches)) {
                    $fixmes[] = ['file' => $file, 'line' => $lineNum + 1, 'text' => trim($matches[1])];
                }
                if (preg_match('/\/\/\s*XXX:?\s*(.+)/i', $line, $matches)) {
                    $xxxs[] = ['file' => $file, 'line' => $lineNum + 1, 'text' => trim($matches[1])];
                }
            }
        }

        $this->stdout("Summary:\n", Console::BOLD);
        $this->stdout("  Total PHP files: $totalFiles\n");
        $this->stdout("  Total lines: $totalLines\n");
        $this->stdout('  TODOs found: ' . count($todos) . "\n", count($todos) > 0 ? Console::FG_YELLOW : Console::FG_GREEN);
        $this->stdout('  FIXMEs found: ' . count($fixmes) . "\n", count($fixmes) > 0 ? Console::FG_YELLOW : Console::FG_GREEN);
        $this->stdout('  XXXs found: ' . count($xxxs) . "\n\n", count($xxxs) > 0 ? Console::FG_YELLOW : Console::FG_GREEN);

        if ($this->verbose) {
            $this->displayComments('TODO', $todos);
            $this->displayComments('FIXME', $fixmes);
            $this->displayComments('XXX', $xxxs);
        }

        return ExitCode::OK;
    }

    /**
     * Analyzes log files for errors and patterns.
     *
     * This command scans log files in the specified directory and reports:
     * - Total log entries
     * - Error count by level
     * - Most common error messages
     *
     * @param string $path the path to the directory containing log files
     * @return int the exit code
     */
    public function actionLogs($path)
    {
        $path = Yii::getAlias($path);

        if (!is_dir($path)) {
            $this->stderr("Error: Directory '$path' does not exist.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("Analyzing log files in: $path\n\n", Console::BOLD);

        $files = FileHelper::findFiles($path, ['only' => ['*.log']]);

        if (empty($files)) {
            $this->stdout("No log files found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $totalEntries = 0;
        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        $errorMessages = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $totalEntries++;

                // Parse log level (common format: timestamp [level] message)
                if (preg_match('/\[(error|warning|info)\]/i', $line, $matches)) {
                    $level = strtolower($matches[1]);

                    switch ($level) {
                        case 'error':
                            $errorCount++;
                            // Extract error message
                            if (preg_match('/\[error\]\s*(.+)/', $line, $msgMatches)) {
                                $msg = substr($msgMatches[1], 0, 100);
                                $errorMessages[$msg] = ($errorMessages[$msg] ?? 0) + 1;
                            }
                            break;
                        case 'warning':
                            $warningCount++;
                            break;
                        case 'info':
                            $infoCount++;
                            break;
                    }
                }
            }
        }

        $this->stdout("Summary:\n", Console::BOLD);
        $this->stdout('  Total log files: ' . count($files) . "\n");
        $this->stdout("  Total log entries: $totalEntries\n");
        $this->stdout("  Errors: $errorCount\n", $errorCount > 0 ? Console::FG_RED : Console::FG_GREEN);
        $this->stdout("  Warnings: $warningCount\n", $warningCount > 0 ? Console::FG_YELLOW : Console::FG_GREEN);
        $this->stdout("  Info: $infoCount\n\n");

        if ($this->verbose && !empty($errorMessages)) {
            $this->stdout("Most common errors:\n", Console::BOLD);
            arsort($errorMessages);
            $count = 0;
            foreach ($errorMessages as $msg => $occurrences) {
                if ($count++ >= 10) {
                    break;
                }
                $this->stdout("  [$occurrences] $msg\n", Console::FG_RED);
            }
            $this->stdout("\n");
        }

        return ExitCode::OK;
    }

    /**
     * Displays comments found during code analysis.
     *
     * @param string $type the type of comment (TODO, FIXME, XXX)
     * @param array $comments the list of comments
     */
    private function displayComments($type, $comments)
    {
        if (empty($comments)) {
            return;
        }

        $this->stdout("$type comments:\n", Console::BOLD);
        foreach ($comments as $comment) {
            $relativePath = str_replace(Yii::getAlias('@app'), '', $comment['file']);
            $this->stdout("  $relativePath:{$comment['line']}: {$comment['text']}\n", Console::FG_YELLOW);
        }
        $this->stdout("\n");
    }
}
