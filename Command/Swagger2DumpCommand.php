<?php

namespace Nelmio\ApiDocBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class Swagger2DumpCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setDescription("Dumps swagger 2 yaml")
            ->setName('api:swagger2:dump');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $extractor = $container->get('nelmio_api_doc.extractor.api_doc_extractor');
        $formatter = $container->get('nelmio_api_doc.formatter.swagger_2_formatter');

        $apiDocs = $extractor->all();
        $result = $formatter->format($apiDocs);

        $output->write(Yaml::dump($result, 10, 2, false, true));
    }
}