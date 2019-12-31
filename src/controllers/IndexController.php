<?php

namespace onedesign\hawksearch\controllers;

use craft\web\Controller;
use onedesign\hawksearch\Hawksearch;

class IndexController extends Controller
{

    public function actionGenerate()
    {
        Hawksearch::getInstance()->index->generateIndex();
        \Craft::$app->getSession()->setNotice('The index was successfully generated.');
    }
}
