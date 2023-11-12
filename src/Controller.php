<?php

declare(strict_types=1);

namespace AcmeCorptest\ReferenceExtension;

use Bolt\Extension\ExtensionController;
use Symfony\Component\HttpFoundation\Response;

class Controller extends ExtensionController
{
    public function index($name = 'foo'): Response
    {
        $context = [
            'title' => 'AcmeCorptest Reference Extension',
            'name' => $name,
        ];

        return $this->render('@reference-extension/page.html.twig', $context);
    }
}
