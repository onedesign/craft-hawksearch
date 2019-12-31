<?php

namespace onedesign\hawksearch;

use craft\base\Plugin;
use onedesign\hawksearch\services\Index;

/**
 * Hawksearch Index Plugin
 *
 * @property Index $index
 */
class Hawksearch extends Plugin
{
    public $hasCpSection = true;

    public function init()
    {
        parent::init();

        $this->setComponents([
            'index' => Index::class
        ]);
    }
}
