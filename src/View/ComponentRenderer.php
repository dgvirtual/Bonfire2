<?php

/**
 * This file is part of Bonfire.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bonfire\View;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class ComponentRenderer
{
    public function __construct()
    {
        helper('inflector');
        ini_set('pcre.backtrack_limit', '-1');
    }

    /**
     * Examines the given string and parses any view components,
     * returning the modified string.
     *
     * Called by the View class' render method.
     */
    public function render(?string $output): string
    {
        if (empty($output)) {
            return $output;
        }

        // Load the HTML into DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML($output, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Process self-closing tags
        $this->renderSelfClosingTags($dom);

        // Process paired tags
        $this->renderPairedTags($dom);

        // Return the modified HTML
        return $dom->saveHTML();
    }

    /**
     * Finds and renders self-closing tags, i.e. <x-foo />
     */
    private function renderSelfClosingTags(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//x-*');

        foreach ($nodes as $node) {
            $name = $node->nodeName;
            $view = $this->locateView(substr($name, 2));
            $attributes = $this->parseAttributes($node);
            $component = $this->factory(substr($name, 2), $view);

            $replacement = $component instanceof Component
                ? $component->withView($view)->render()
                : $this->renderView($view, $attributes);

            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML($replacement);
            $node->parentNode->replaceChild($fragment, $node);
        }
    }

    /**
     * Finds and renders paired tags, i.e. <x-foo>...</x-foo>
     */
    private function renderPairedTags(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//x-*');

        foreach ($nodes as $node) {
            $name = $node->nodeName;
            $view = $this->locateView(substr($name, 2));
            $attributes = $this->parseAttributes($node);
            $attributes['slot'] = $dom->saveHTML($node->childNodes);
            $component = $this->factory(substr($name, 2), $view);

            $replacement = $component instanceof Component
                ? $component->withView($view)->withData($attributes)->render()
                : $this->renderView($view, $attributes);

            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML($replacement);
            $node->parentNode->replaceChild($fragment, $node);
        }
    }

    /**
     * Parses a DOMNode to grab any key/value pairs, HTML attributes.
     */
    private function parseAttributes(\DOMNode $node): array
    {
        $attributes = [];
        foreach ($node->attributes as $attr) {
            $attributes[$attr->nodeName] = $attr->nodeValue;
        }
        return $attributes;
    }

    /**
     * Renders the view when no corresponding class has been found.
     */
    private function renderView(string $view, array $data): string
    {
        return (static function (string $view, $data) {
            extract($data);
            ob_start();
            eval('?>' . file_get_contents($view));
            return ob_get_clean() ?: '';
        })($view, $data);
    }

    /**
     * Attempts to locate the view and/or class that
     * will be used to render this component. By default,
     * the only thing that is needed is a view, but a
     * Component class can also be found if more power is needed.
     *
     * If a class is used, the name is expected to be
     * <viewName>Component.php
     */
    private function factory(string $name, string $view): ?Component
    {
        // Locate the class in the same folder as the view
        $class    = pascalize($name) . 'Component.php';
        $filePath = str_replace($name . '.php', $class, $view);

        if (empty($filePath)) {
            return null;
        }

        if (! file_exists($filePath)) {
            return null;
        }
        $className = service('locator')->getClassname($filePath);

        if (! class_exists($className)) {
            include_once $filePath;
        }

        return (new $className())->withView($view);
    }

    /**
     * Locate the view file used to render the component.
     * The file's name must match the name of the component,
     * minus the 'x-'.
     */
    private function locateView(string $name): string
    {
        // First search within the current theme
        $path     = Theme::path();
        $filePath = $path . 'Components/' . $name . '.php';

        if (is_file($filePath)) {
            return $filePath;
        }

        // fallback: check in components' default lookup paths from config
        $componentsLookupPaths = config('Themes')->componentsLookupPaths;

        foreach ($componentsLookupPaths as $componentPath) {
            $filePath = $componentPath . $name . '.php';

            if (is_file($filePath)) {
                return $filePath;
            }
        }

        throw new RuntimeException('View not found for component: ' . $name);
        // @todo look in all normal namespaces
    }
}