<?php

namespace modules\site\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use GuzzleHttp\Promise\Utils;
use yii\web\Response;

class SitemapController extends Controller
{
    protected array|int|bool $allowAnonymous = ['index'];

    public function actionIndex(): Response
    {
        $entries = Entry::find()->limit(null)->where(['not', ['elements_sites.uri' => null]])->all();
        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>'
        );

        foreach ($entries as $entry) {
            $url = $xml->addChild('url');
            $url->addChild('loc', $entry->url);
            $url->addChild('lastmod', $entry->dateUpdated->format(\DateTime::W3C));
            $url->addChild('priority', $entry->uri === '__home__' ? 0.75 : 0.5);
        }

        Craft::$app->response->format = Response::FORMAT_RAW;
        Craft::$app->response->headers->add('Content-Type', 'text/xml');

        return $this->asRaw($xml->asXml());
    }

    /**
     * Helper function to test at least one entry in each section for success
     * This can take a long time to run.
     */
    public function actionTest(): Response
    {
        $sections = Craft::$app->getSections()->getAllSections();
        $query = Entry::find();
        $client = Craft::createGuzzleClient();

        $entries = [];
        $reqs = [];
        $count = 0;
        $errors = [];

        foreach ($sections as $section) {
            switch ($section->handle) {
                case 'pages':
                    $pages = $query->section($section)->all();

                    foreach ($pages as $page) {
                        $entries[$page->id] = $page;
                    }

                    break;

                default:
                    if ($entry = $query->section($section)->one()) {
                        $entries[$entry->id] = $entry;
                    }
            }
        }

        // Build multi-request array using entry ID to link back to entry data for error printing
        foreach ($entries as $entry) {
            if ($entry->url) {
                $count++;
                $reqs[$entry->id] = $client->headAsync($entry->url, [
                    'http_errors' => false, // Don't throw exceptions for 404
                ]);
            }
        }

        // Wait for the requests to complete, even if some of them fail
        $ress = Utils::settle($reqs)->wait();

        // Handle responses
        foreach ($ress as $id => $res) {
            $entry = $entries[$id] ?? null;
            if ($entry === null) {
                continue;
            }

            $res = $res['value'];
            if ($res->getStatusCode() !== 200) {
                $errors[] = sprintf('%d Error requesting <a href="%s">%s</a>', $res->getStatusCode(), $entry->url, $entry->title);
            }
        }

        if (empty($errors)) {
            exit(sprintf('%d URLs tested. All is well with the world :)', $count));
        }

        exit(sprintf(
            '%d URLs tested. %d errors found.<br />%s',
            $count,
            count($errors),
            implode('<br />', $errors)
        ));
    }
}
