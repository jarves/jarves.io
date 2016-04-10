<?php

namespace AppBundle\Documentation;

use Jarves\Formatter\ApiDocFormatter;

class NelmioHtmlFormatter extends ApiDocFormatter
{
    protected function getTemplate()
    {
        return 'AppBundle:ApiDoc:resources.html.twig';
    }
}