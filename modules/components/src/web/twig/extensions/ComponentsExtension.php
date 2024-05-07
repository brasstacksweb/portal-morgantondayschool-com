<?php

namespace modules\components\web\twig\extensions;

use craft\helpers\StringHelper;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

class ComponentsExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'Components Twig Extension';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('emphasize', [$this, 'emphasize'], [
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function emphasize(?string $content = ''): Markup
    {
        return new Markup(
            preg_replace(
                ['/\*([^\*]*)\*/', '/\~([^\~]*)\~/', '/\^([^\^]*)\^/', '/\_([^\_]*)\_/'],
                ['<strong>$1</strong>', '<em>$1</em>', '<sup>$1</sup>', '<sub>$1</sub>'],
                $content
            ),
            StringHelper::UTF8
        );
    }
}
