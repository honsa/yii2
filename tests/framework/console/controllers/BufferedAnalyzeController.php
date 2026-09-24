<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yiiunit\framework\console\controllers;

use yii\console\controllers\AnalyzeController;

/**
 * BufferedAnalyzeController is a version of AnalyzeController for testing that captures output.
 */
class BufferedAnalyzeController extends AnalyzeController
{
    use StdOutBufferControllerTrait;
}
