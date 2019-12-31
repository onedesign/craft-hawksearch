<?php
/**
 * Hawksearch plugin for Craft CMS 3.x
 *
 * Custom console commands for Hawksearch plugin
 *
 * @link      https://onedesigncompany.com/
 * @copyright Copyright (c) 2019 One Design Company
 */

namespace onedesign\hawksearch\console\controllers;

use onedesign\hawksearch\Hawksearch;
use yii\console\Controller;

/**
 * Allows you to control Hawksearch indexes
 *
 * Commands associated with the Hawksearch index
 *
 * Craft can be invoked via commandline console by using the `./craft` command
 * from the project root.
 *
 * Console Commands are just controllers that are invoked to handle console
 * actions. The segment routing is plugin-name/controller-name/action-name
 *
 * The actionIndex() method is what is executed if no sub-commands are supplied, e.g.:
 *
 * ./craft hawksearch
 *
 * @author    One Design Company
 * @package   LoyolaPress
 * @since     1.0.0
 */
class IndexController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Rebuild the Hawksearch index
     *
     * Rebuilds the Hawksearch index files. Files are ouput in the
     * storage/search-indices folder
     *
     */
    public function actionGenerate()
    {
        try {
            Hawksearch::getInstance()->index->generateIndex();
            $this->stdout('Index succesfully generated.' . PHP_EOL);
        } catch (\Exception $e) {
            var_dump($e);
            $this->stderr('Failed to generate index.' . PHP_EOL);
        }
    }
}
