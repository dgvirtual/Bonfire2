<?php

namespace Bonfire\View;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class ComponentRenderer
{
    public function __construct()
    {
        helper('inflector');
    }

    /**
     * Examines the given string and parses any view components,
     * returning the modified string.
     *
     * Called by the View class' render method.
     */
    public function render(?string $output): string
    {
        if (empty($output) || strpos($output, '<x-') === false) {
            return $output;
        }

        $this->badLog($output, 'RECEIVED FOR RENDERING');
        $output = mb_convert_encoding($output, 'HTML-ENTITIES', 'UTF-8');

        // Encode Alpine.js attributes to preserve them
        $output = $this->encodeAlpineAttributes($output);

        // Extract <script> tags
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $output, $scriptMatches);
        $scripts = $scriptMatches[0];
        $outputWithoutScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '<!-- script_placeholder -->', $output);

        // Load the HTML into DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($outputWithoutScripts, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Debugging: Log that renderSelfClosingTags is about to be called
        //$this->logToConsole("Calling renderSelfClosingTags");

        // Process self-closing tags
        $this->renderSelfClosingTags($dom);

        // Debugging: Log that renderPairedTags is about to be called
        //$this->logToConsole("Calling renderPairedTags");

        // Process paired tags
        $this->renderPairedTags($dom);

        // Return the modified HTML
        $result = $dom->saveHTML();

        // Reinsert <script> tags
        foreach ($scripts as $script) {
            $result = preg_replace('/<!-- script_placeholder -->/', $script, $result, 1);
        }

        // Decode HTML entities to preserve original characters
        $result = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Decode Alpine.js attributes
        $result = $this->decodeAlpineAttributes($result);

        $this->badLog($result, 'RETURNED BY RENDER');
        return $result;
    }

    /**
     * Finds and renders self-closing tags, i.e. <x-foo />
     */
    private function renderSelfClosingTags(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[starts-with(local-name(), "x-") and not(node())]');

        // Debugging: Output the number of nodes found
        // $this->logToConsole("Self-closing tags found: " . $nodes->length);

        foreach ($nodes as $node) {
            $name = $node->nodeName;
            // $this->logToConsole("Processing self-closing tag: " . $name);

            $view = $this->locateView(substr($name, 2));
            $attributes = $this->parseAttributes($node);
            $attributes['slot'] = ''; // Ensure slot is defined for self-closing tags
            $component = $this->factory(substr($name, 2), $view);

            $replacement = $component instanceof Component
                ? $component->withView($view)->withData($attributes)->render()
                : $this->renderView($view, $attributes);

            // Debugging: Output the replacement content
            // $this->logToConsole("Replacement content for self-closing tag: " . $replacement);

            // Create a new DOMDocument to parse the replacement content
            $replacementDom = new DOMDocument();
            @$replacementDom->loadHTML('<?xml encoding="UTF-8"><body>' . $replacement . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

            // Check if the body element exists
            $body = $replacementDom->getElementsByTagName('body')->item(0);
            if ($body === null) {
                // $this->logToConsole("Error: Body element not found in replacement content.");
                continue;
            }

            // Debugging: Output the body content
            // $this->logToConsole("Body content: " . $replacementDom->saveHTML($body));

            // Import the replacement content into the original DOMDocument
            $fragment = $dom->createDocumentFragment();
            foreach ($body->childNodes as $child) {
                $fragment->appendChild($dom->importNode($child, true));
            }

            // Replace the original node with the replacement content
            $node->parentNode->replaceChild($fragment, $node);
        }
    }

    /**
     * Finds and renders paired tags, i.e. <x-foo>...</x-foo>
     */
    private function renderPairedTags(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[starts-with(local-name(), "x-") and node()]');

        // Debugging: Output the number of nodes found
        // $this->logToConsole("Paired tags found: " . $nodes->length);

        foreach ($nodes as $node) {
            $name = $node->nodeName;
            // $this->logToConsole("Processing paired tag: " . $name);

            $view = $this->locateView(substr($name, 2));
            $attributes = $this->parseAttributes($node);

            // Fix for childNodes issue
            $attributes['slot'] = '';
            foreach ($node->childNodes as $child) {
                $attributes['slot'] .= $dom->saveHTML($child);
            }

            $component = $this->factory(substr($name, 2), $view);

            $replacement = $component instanceof Component
                ? $component->withView($view)->withData($attributes)->render()
                : $this->renderView($view, $attributes);

            // Debugging: Output the replacement content
            // $this->logToConsole("Replacement content for paired tag: " . $replacement);

            // Ensure well-formed XML
            $replacement = $this->ensureWellFormedXML($replacement);

            $fragment = $dom->createDocumentFragment();
            @$fragment->appendXML($replacement);
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

    /**
     * Ensures that the given XML string is well-formed.
     */
    private function ensureWellFormedXML(string $xml): string
    {
        // Load the string into a DOMDocument to ensure it's well-formed
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $xml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Logs a message to the JavaScript console.
     */
    private function logToConsole(string $message): void
    {
        echo "<script>console.log(" . json_encode($message) . ");</script>";
    }

    private function badLog(string $content, string $header): void
    {
        return;
        echo PHP_EOL . '<pre>' . PHP_EOL . PHP_EOL . '=============== ' . $header .  ':  ============</pre>' . PHP_EOL;
        echo $content;
        echo '<pre>' . PHP_EOL . PHP_EOL . '</pre>' . PHP_EOL;
    }
    /**
     * Encodes Alpine.js attributes to preserve them during DOMDocument processing.
     */
    private function encodeAlpineAttributes(string $html): string
    {
        return preg_replace_callback('/(@[a-zA-Z0-9\-:]+)="([^"]*)"/', function ($matches) {
            return 'data-alpine-' . substr($matches[1], 1) . '="' . htmlspecialchars($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
        }, $html);
    }

    /**
     * Decodes Alpine.js attributes after DOMDocument processing.
     */
    private function decodeAlpineAttributes(string $html): string
    {
        return preg_replace_callback('/data-alpine-([a-zA-Z0-9\-:]+)="([^"]*)"/', function ($matches) {
            return '@' . $matches[1] . '="' . htmlspecialchars_decode($matches[2], ENT_QUOTES) . '"';
        }, $html);
    }
}
