<?php

namespace modules\forms\web\twig\variables;

use modules\forms\models\Form;
use modules\forms\services\Forms;

class FormsVariable
{
    public function newForm(string $handle, array $attrs = [], string $scenario = ''): Form
    {
        return Forms::newForm($handle, $attrs, $scenario);
    }
}
