<?php

namespace modules\components\services;

use craft\base\Element;
use craft\elements\MatrixBlock;
use modules\components\models\Cta;
use modules\components\models\Link;

class Components
{
    public const TYPE_CTA_PAGE = 'pageCta';
    public const TYPE_LINK_PAGE = 'pageLink';
    public const TYPE_CTA_EXTERNAL = 'externalCta';
    public const TYPE_LINK_EXTERNAL = 'externalLink';

    public static function newCta(?MatrixBlock $cta = null, array $options = []): ?Cta
    {
        if ($cta === null) {
            return null;
        }

        return match ($cta->type->handle) {
            self::TYPE_CTA_PAGE, self::TYPE_LINK_PAGE => new Link(array_merge($options, [
                'title' => $cta->ctaTitle ?? $cta->linkTitle ?? $options['title'] ?? '',
                'body' => $cta->ctaBody ?? $cta->linkBody ?? '',
                'url' => ($cta->ctaPage ?? $cta->linkPage ?? [])[0]->url ?? '',
            ])),
            self::TYPE_CTA_EXTERNAL, self::TYPE_LINK_EXTERNAL => new Link(array_merge($options, [
                'title' => $cta->ctaTitle ?? $cta->linkTitle ?? $options['title'] ?? '',
                'url' => $cta->ctaUrl ?? $cta->linkUrl ?? '',
                'target' => '_blank',
                'rel' => 'noopener',
            ])),
        };
    }

    public static function newLinkFromElement(?Element $el, string $title = ''): ?Link
    {
        if ($el === null) {
            return null;
        }

        return new Link([
            'title' => $title ?: $el->title,
            'url' => $el->url,
        ]);
    }

    public static function newExternalLink(?string $url, string $title = ''): ?Link
    {
        if ($url === null) {
            return null;
        }

        return new Link([
            'title' => $title,
            'url' => $url,
            'target' => '_blank',
            'rel' => 'noopener',
        ]);
    }
}
