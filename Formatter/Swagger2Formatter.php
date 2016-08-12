<?php
namespace Nelmio\ApiDocBundle\Formatter;

use Doctrine\Common\Collections\ArrayCollection;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class Swagger2Formatter implements FormatterInterface
{
    public function format(array $collection)
    {
        $header = $this->getHeader();
        $paths = $this->getPaths($collection);
        $definitions = $this->getDefinitions($collection);
        $result = array_merge($header, $paths, $definitions);
        return $result;
    }

    private function getHeader()
    {
        return [
            'swagger' => '2.0',
            'info' => [
                'version' => '1.0.0',
                'title' => 'Seatfrog API',
                'description' => 'Blah blah blah'
            ],
            'host' => 'dev.webservices.frogops.lol',
            'basePath' => '/api/v1',
            'schemes' => [
                "http"
            ],
            'consumes' => [
                "application/json"
            ],
            'produces' => [
                "application/json"
            ]
        ];
    }

    private function getPaths(array $collection)
    {
        $result = [
            "paths" => []
        ];
        foreach ($collection as $item) {
            /** @var ApiDoc $annotation */
            $annotation = $item["annotation"];
            $route = [];

            $method = strtolower($annotation->getMethod());
            $description = $annotation->getDocumentation();

            $route[$method] = [];

            // Tags
            $route[$method]["tags"] = $annotation->getTags();

            // Description
            $route[$method]["description"] = $description;

            // Do the url parameters
            $parameters = [];
            foreach ($annotation->getParameters() as $name => $parameter) {
                if (array_key_exists("url", $parameter)) {
                    $p = [];
                    $p["name"] = $name;
                    $p["in"] = "path";
                    if (array_key_exists("dataType", $parameter)) $p["type"] = $parameter["dataType"];
                    if (array_key_exists("description", $parameter)) $p["description"] = $parameter["description"];
                    if (array_key_exists("required", $parameter)) $p["required"] = $parameter["required"] == 'true' ? true : false;

                    $parameters[] = $p;
                }
            }
            if (sizeof($parameters) > 0) $route[$method]["parameters"] = $parameters;

            // Do the body parameters
            if ($annotation->getInput()) {
                $route[$method]["parameters"][] = [
                    'name' => 'data',
                    'in' => 'body',
                    'schema' => [
                        '$ref' => "#/definitions/".$this->getSmallClass($annotation->getInput())
                    ]
                ];
            }

            // Do the responses
            $responses = [];
            foreach ($annotation->getResponseMap() as $statusCode => $responseMap) {
                $r = [];

                if (array_key_exists("class", $responseMap) && $responseMap["class"] != "") {
                    $r["schema"] = [
                        "\$ref" => "#/definitions/".$this->getSmallClass($responseMap["class"])
                    ];
                }

                // TODO description
                if (array_key_exists("description", $responseMap)) $r["description"] = $responseMap["description"];
                else $r['description'] = '';

                $responses[$statusCode] = $r;
            }
            if (sizeof($responses) > 0) $route[$method]["responses"] = $responses;

            $result["paths"][$item["resource"]] = $route;
        }
        return $result;
    }

    private function getSmallClass($className) {
        preg_match("/[a-zA-Z0-9]+$/", $className, $c);
        if (sizeof($c) > 0) {
            return $c[0];
        }
        else {
            return $className;
        }
    }

    private function getTypes($currentTypes, $fields) {
        $walk = function($fields) use (&$currentTypes, &$walk) {
            foreach ($fields as $field) {
                if ($field["subType"] != null && !array_key_exists($field["subType"], $currentTypes)) {
                    $currentTypes[$field["subType"]] = $field["children"];
                    $walk($field["children"]);
                }
            }
        };

        $walk($fields);
        return $currentTypes;
    }

    private function getDefinitions(array $collection)
    {
        $definitions = [];
        foreach ($collection as $item) {
            $annotation = $item['annotation'];

            // Check for input type
            if ($annotation->getInput() != "" && $annotation->getInput() != null) {
                $className = $annotation->getInput();

                // We only want the parameters that weren't part of the url
                if (!array_key_exists($className, $definitions)) {
                    $params = new ArrayCollection($annotation->getParameters());
                    $params = $params->filter(function ($paramItem) {
                        return !array_key_exists("url", $paramItem);
                    });

                    $definitions[$className] = $params->toArray();
                }
            }

            // Add response maps to potential type definitions
            foreach ($annotation->getParsedResponseMap() as $response) {
                $className = $this->getSmallClass($response['type']['class']);

                if ($className != "" && !array_key_exists($className, $definitions)) {
                    $definitions[$className] = $response["model"];
                    $definitions = $this->getTypes($definitions, $response['model']);
                }
            }
        }

        // Turn the definitions into swagger format
        $swaggerDef = [];

        foreach ($definitions as $name => $definition) {
            $type = ['type' => 'object'];

            // Iterate over the properties
            foreach ($definition as $fieldName => $fieldValue) {
                $field = [];

                if ($fieldValue['subType'] != null) {
                    // This is either an object or a collection
                    $className = $this->getSmallClass($fieldValue['subType']);
                    if ($className != "") {
                        if ($fieldValue['actualType'] == "collection") {
                            $field['type'] = "array";
                            $field['items'] = ['$ref' => "#/definitions/$className"];
                        } else {
                            $field['$ref'] = "#/definitions/$className";
                        }
                    }
                }
                else {
                    // Just a normal scalar
                    if ($fieldValue['dataType'] == "float") {
                        $field["type"] = "number";
                    }
                    else if ($fieldValue['dataType'] == "DateTime") {
                        $field['type'] = "string";
                        $field['format'] = "dateTime";
                    }
                    else {
                        $field['type'] = $fieldValue['dataType'];
                    }
                }

                if (array_key_exists("description", $fieldValue)) {
                    if ($fieldValue['description'] != "") $field['description'] = $fieldValue['description'];
                }

                $type['properties'][$fieldName] = $field;
            }

            $swaggerDef['definitions'][$this->getSmallClass($name)] = $type;
        }

        return $swaggerDef;
    }

    public function formatOne(ApiDoc $annotation)
    {
        throw new \BadMethodCallException(sprintf('%s does not support formatting a single ApiDoc only.', __CLASS__));
    }
}