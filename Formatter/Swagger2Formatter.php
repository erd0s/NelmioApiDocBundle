<?php
namespace Nelmio\ApiDocBundle\Formatter;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class Swagger2Formatter implements FormatterInterface
{
    public function format(array $collection)
    {
        $header = $this->getHeader();
        $paths = $this->getPaths($collection);
        $result = array_merge($header, $paths);
        return $result;
    }

    private function getHeader()
    {
        return [
            'multilinestring' => "lkajsflkj asdlkfjasl dkfj asldkfj asdlkfj ads\n saldfkasdjflkasdjflk asdf\n\nasdfkjh",
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
                $p = [];
                $p["name"] = $name;
                $p["in"] = "path";
                if (array_key_exists("dataType", $parameter)) $p["type"] = $parameter["dataType"];
                if (array_key_exists("description", $parameter)) $p["description"] = $parameter["description"];
                if (array_key_exists("required", $parameter)) $p["required"] = $parameter["required"];

                $parameters[] = $p;
            }
            if (sizeof($parameters) > 0) $route[$method]["parameters"] = $parameters;

            // TODO the body parameters

            $types = [];

            // Do the responses
            $responses = [];
            foreach ($annotation->getResponseMap() as $statusCode => $responseMap) {
                $r = [];

                // TODO schema
                if (array_key_exists("class", $responseMap)) {
                    $class = str_replace("\\", "/", $responseMap["class"]);
                    $r["schema"] = [
                        "\$ref" => "#/definitions/$class"
                    ];
                    $types[$class] = "";
                }

                // TODO description
                if (array_key_exists("description", $responseMap)) $r["description"] = $responseMap["description"];

                $responses[$statusCode] = $r;
            }
            if (sizeof($responses) > 0) $route[$method]["responses"] = $responses;


            $result["paths"]["something"] = "lkjasflk jlaskdfjalsdfkj asd\n\nasldkfjalsdkfj";
            $result["paths"][$item["resource"]] = $route;
        }

        return $result;
    }

    public function formatOne(ApiDoc $annotation)
    {
        throw new \BadMethodCallException(sprintf('%s does not support formatting a single ApiDoc only.', __CLASS__));
    }
}