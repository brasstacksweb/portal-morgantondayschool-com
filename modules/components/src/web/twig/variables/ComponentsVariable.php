<?php

namespace modules\components\web\twig\variables;

use craft\base\Element;
use craft\elements\Asset;
use craft\elements\MatrixBlock;
use modules\components\models\Cta;
use modules\components\services\Components;

class ComponentsVariable
{
    public function newCta(?MatrixBlock $cta, array $options = []): ?Cta
    {
        return Components::newCta($cta, $options);
    }

    public static function newLinkFromElement(?Element $el, string $title = ''): ?Cta
    {
        return Components::newLinkFromElement($el, $title);
    }

    public static function newExternalLink(?string $url, string $title = ''): ?Cta
    {
        return Components::newExternalLink($url, $title);
    }

    public static function buildImage(?Asset $asset = null): ?array
    {
        if ($asset === null) {
            return null;
        }

        return array_merge($asset->getAttributes(), [
            'previewText' => $asset->previewText,
            'position' => $asset->position->value,
            'fit' => $asset->fit->value,
            'mobileImage' => self::buildImage($asset->mobileImage[0] ?? null),
            'featuredImage' => self::buildImage($asset->featuredImage[0] ?? null),
            'invertedImage' => self::buildImage($asset->invertedImage[0] ?? null),
            'backgroundVideo' => $asset->backgroundVideo[0] ?? null,
        ]);
    }
}
