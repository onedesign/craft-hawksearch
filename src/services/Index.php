<?php
/**
 * Hawksearch plugin Craft CMS 3.x
 */

namespace onedesign\hawksearch\services;

use craft\base\Component;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\records\CategoryGroup;

class Index extends Component
{
    private static $STORAGE_FOLDER = CRAFT_BASE_PATH . '/web';
    private static $TARGET_FOLDER = '/search-indices/';
    private static $ATTRIBUTES_FILE_NAME = 'attributes.txt';
    private static $CONTENT_FILE_NAME = 'content.txt';
    private static $ID_PREFIX = 'craft_';
    private static $HIERARCHY_FILE_NAME = 'hierarchy.txt';

    private static $EXCLUDED_SECTIONS = [
        'homePage',
        'mainNavigation',
        'programFamily',
        'ads',
        'addendum'
    ];

    private static $EXCLUDED_TEMPLATES = [
        'activityFinder',
        'addendum',
        'featured3mr',
        'featuredSaints',
        'featuredSundayConnection',
        'landingPageNest',
        'placeholder'
    ];

    public function generateIndex()
    {
        $entries = Entry::find()
            ->section(array_merge(['not'], self::$EXCLUDED_SECTIONS))
            ->excludeFromHawksearchIndex('not 1')
            ->siteId('*')
            ->with([
                'activityGrade',
                'activityGroup',
                'activitySubject',
                'activityKeywords',
                'audience',
                'image',
                'category',
                'liturgicalYear',
                'scriptureCategory',
                'searchFacet',
                'searchIcons',
                'searchIcons.searchIcon',
                'specificDay',
                'themes',
                'tileImage',
                'topics',
            ])
            ->template(array_merge(['not', self::$EXCLUDED_TEMPLATES]))
            ->all();

        $this->generateContentIndex($entries, self::$CONTENT_FILE_NAME);
        $this->generateContentAttributes($entries, self::$ATTRIBUTES_FILE_NAME);
        $this->generateHierarchyFile();
        $this->generateTimestampFile();
    }

    private function generateHierarchyFile()
    {
        $groups = CategoryGroup::find()->all();
        $lines = [];

        $columns = [
            'hierarchy_id',
            'hierarchy_name',
            'parent_hierarchy_id',
            'sort_order'
        ];

        $this->setColumnHeadings($columns, self::$HIERARCHY_FILE_NAME);


        foreach ($groups as $group) {
            $categories = Category::find()
                ->group($group->handle)
                ->all();

            foreach ($categories as $idx => $category) {
                $parent_id = $category->parent ? $category->parent->id : '';

                $lines[] = [
                    $category->id,
                    $category->title,
                    $parent_id,
                    $idx
                ];
            }
        }

        $this->appendLinesToFile($lines, self::$HIERARCHY_FILE_NAME);
    }

    private function generateContentAttributes($entries, $fileName)
    {
        $attributeCols = ['unique_id', 'key', 'value'];

        $this->setColumnHeadings($attributeCols, $fileName);

        $categoryHandles = [
            'scriptureCategory',
            'audience',
            'category',
            'searchFacet',
            'activityGrade',
            'activitySubject',
            'activityKeywords'
        ];

        foreach ($categoryHandles as $handle) {
            $this->generateAttributesFromCategory($entries, $handle);
        }
    }

    private function generateContentIndex($entries, $fileName)
    {
        $contentCols = ['unique_id', 'name', 'url_detail', 'image', 'description_short'];

        $this->setColumnHeadings($contentCols, $fileName);
        $this->generateEntryRows($entries, $contentCols);
    }

    private function getAuthorIcon(Entry $entry)
    {
        if ($image = $entry->image) {
            return $image[0]->getUrl();
        }

        /**
         * If the entry didn't have an image, fall back to the global
         */
        $authorGlobal = GlobalSet::find()
            ->handle('authors')
            ->with(['image'])
            ->one();

        if ($image = $authorGlobal->image) {
            return $image[0]->getUrl();
        }

        return '';
    }

    private function getCategoryIcon(Entry $entry)
    {
        if ($iconsCategory = $entry->searchIcons) {
            if ($icon = $iconsCategory[0]->searchIcon) {
                return $icon[0]->getUrl();
            }
            return '';
        }

        return '';
    }

    private function getEcIcon(Entry $entry)
    {
        if ($image = $entry->image) {
            return $image[0]->getUrl();
        }

        return '';
    }

    private function getEntryImage(Entry $entry)
    {
        $entryType = $entry->type->handle;
        switch ($entryType) {
            case 'author':
                return $this->getAuthorIcon($entry);
            case 'Item':
            case 'item':
            case 'activities':
            case 'threeMinuteRetreats':
            case 'saintStories':
            case 'sundayConnections':
                return $this->getCategoryIcon($entry);
            case 'educationalConsultant':
                return $this->getEcIcon($entry);
            default:
                return '';
        }
    }

    private function getColumnContent(Entry $entry, $columnKey)
    {
        switch ($columnKey) {
            case 'unique_id':
                return self::$ID_PREFIX . $entry->siteId . '_' . $entry->id;
            case 'name':
                return $entry->title;
            case 'url_detail':
                return $entry->url;
            case 'image':
                return $this->getEntryImage($entry);
            case 'description_short':
                return $entry->shortDescription ? $entry->shortDescription : '';
        }
    }

    private function generateEntryRows($entries, $columns)
    {
        $lines = [];
        foreach ($entries as $entry) {
            $entryData = [];
            foreach ($columns as $column) {
                $entryData[] = $this->getColumnContent($entry, $column);
            }

            $lines[] = $entryData;
        }

        $this->appendLinesToFile($lines, self::$CONTENT_FILE_NAME);
    }

    private function generateAttributesFromCategory($entries, $categoryHandle)
    {
        $lines = [];
        $categoryFieldLabel = \Craft::$app->fields->getFieldByHandle($categoryHandle);

        foreach ($entries as $entry) {

            if ($entry{$categoryHandle}) {
                $categories = $entry{$categoryHandle};

                foreach ($categories as $category) {
                    $uniqueId = self::$ID_PREFIX . $entry->siteId . '_' . $entry->id;
                    $categoryRow = [$uniqueId, $categoryFieldLabel->name, $category->title];
                    $lines[] = $categoryRow;
                }
            }
        }

        $this->appendLinesToFile($lines, self::$ATTRIBUTES_FILE_NAME);
    }

    private function appendLinesToFile($lines, $fileName)
    {
        $storagePath = self::$STORAGE_FOLDER;
        $folder = $storagePath . self::$TARGET_FOLDER;
        $exportFile = fopen($folder . $fileName, 'ab');
        foreach ($lines as $line) {
            fputcsv($exportFile, $line);
        }

        fclose($exportFile);
    }

    private function setColumnHeadings(array $fields, string $fileName)
    {
        $storagePath = self::$STORAGE_FOLDER;
        $folder = $storagePath . self::$TARGET_FOLDER;
        if (!file_exists($folder) && !mkdir($folder, 0744, true) && !is_dir($folder)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
        }

        $exportFile = fopen($folder . $fileName, 'wb', true);

        fwrite($exportFile, "\xEF\xBB\xBF");
        fputcsv($exportFile, $fields);

        fclose($exportFile);
    }

    private function generateTimestampFile()
    {
        $timeStamp = new \DateTime('now');
        $isoStamp = $timeStamp->format(\DateTime::ATOM);
        $storagePath = self::$STORAGE_FOLDER;
        $folder = $storagePath . self::$TARGET_FOLDER;
        $file = $folder . 'timestamp.txt';
        $entriesIndex = $folder . self::$CONTENT_FILE_NAME;
        $attributesIndex = $folder . self::$ATTRIBUTES_FILE_NAME;
        $attributesCount = count(file($attributesIndex));
        $entriesCount = count(file($entriesIndex));
        $fileContent = $isoStamp . "\n";
        $fileContent .= "dataset\tfull\n";
        $fileContent .= self::$ATTRIBUTES_FILE_NAME . "\t" . $attributesCount . "\n";
        $fileContent .= self::$CONTENT_FILE_NAME . "\t" . $entriesCount . "\n";

        file_put_contents($file, $fileContent);
    }
}
