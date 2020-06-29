<?php

declare(strict_types=1);

namespace JoePritchard\JDF;

/**
 * Class JDF.
 */
class JDF extends BaseJDF
{
    /**
     * @var array
     *            These are the names of valid top-level elements that can go under the opening JMF or JDF root element
     */
    protected $root_nodes = ['ResourcePool', 'ResourceLinkPool'];

    protected $name;

    /**
     * JDF constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initialiseMessage();
        $this->setSensibleDefaults();
    }

    public function setName(string $name): self
    {
        $this->root->addAttribute('DescriptiveName', $name);

        return $this;
    }

    /**
     * Add a print to a JDF message. We do NOT check this file for existence, as you're often going to want to send files relative to the JMF server.
     */
    public function setPrintFile(string $file_name, int $quantity = 1)
    {
        // add a layout element and filespec for this document within the ResourcePool
        $runlist = $this->resourcePool()->runList();
        $runlist->addAttribute('Class', 'Parameter');
        $runlist->addAttribute('ID', 'RL1');
        $runlist->addAttribute('Status', 'Available');

        $layout_element = $runlist->addChild('LayoutElement');
        $file_path_for_jdf = $this->formatPrintFilePath($file_name);

        $file_spec = $layout_element->addChild('FileSpec');
        $file_spec->addAttribute('URL', $file_path_for_jdf);

        // now we need to reference our RunList in ResourceLinkPool
        $this->linkResource('RunList', 'Input', ['CombinedProcessIndex' => '0']);
        $this->linkResource('Component', 'Output', ['Amount' => $quantity, 'CombinedProcessIndex' => 1]);

        return $this;
    }

    protected function setSensibleDefaults()
    {
        $component = $this->resourcePool()->addChild('Component');
        $component->addAttribute('Class', 'Quantity');
        $component->addAttribute('ComponentType', 'FinalProduct');
        $component->addAttribute('ID', 'C1');
        $component->addAttribute('Status', 'Available');

        $digialPrintingParams = $this->resourcePool()->addChild('DigitalPrintingParams');
        $digialPrintingParams->addAttribute('Class', 'Parameter');
        $digialPrintingParams->addAttribute('ID', 'DP1');
        $digialPrintingParams->addAttribute('Status', 'Available');

        $layoutPreparationParams = $this->resourcePool()->addChild('LayoutPreparationParams');
        $layoutPreparationParams->addAttribute('Class', 'Parameter');
        $layoutPreparationParams->addAttribute('ID', 'LPP1');
        $layoutPreparationParams->addAttribute('Status', 'Available');

        $this->linkResource('LayoutPreparationParams', 'Input', ['CombinedProcessIndex' => '0']);
        $this->linkResource('DigialPrintingParams', 'Input', ['CombinedProcessIndex' => '1']);
    }

    /**
     * Create an entry in ResourceLinkPool which refers to the specified resource.
     *
     * @param string $resource_name The element name of the resource you want to create a link for
     * @param string $usage         The Usage attribute of the link (Input or Output)
     * @param array  $attributes    any additional attributes you want on the link, like Amount etc
     */
    private function linkResource(string $resource_name, string $usage, array $attributes = []): void
    {
        // validate the usage string
        if (! \in_array($usage, ['Input', 'Output'])) {
            throw new \InvalidArgumentException('$usage can only be Input or Output');
        }

        // validate the resource name
        if (null === $this->resourcePool()->{$resource_name}) {
            throw new \InvalidArgumentException('No ' . $resource_name . ' resource exists. Refusing to make link');
        }

        // create a link element for this resource
        $resource_link = $this->resourceLinkPool()->addChild($resource_name . 'Link');
        $resource_link->addAttribute('rRef', (string) $this->resourcePool()->{$resource_name}[0]->attributes()->ID);
        $resource_link->addAttribute('Usage', $usage);

        foreach ($attributes as $name => $value) {
            $resource_link->addAttribute((string) $name, (string) $value);
        }
    }
}
