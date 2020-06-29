<?php

declare(strict_types=1);

namespace JoePritchard\JDF;

use SimpleXMLElement;
use BadMethodCallException;
use Illuminate\Support\Str;

/**
 * Class BaseJDF.
 */
class BaseJDF
{
    /**
     * @var SimpleXMLElement
     *                       The root element
     */
    protected $root;
    protected $sender_id;

    /**
     * @var array
     *            These are the names of valid top-level elements that can go under the opening JMF or JDF root element
     */
    private $root_nodes = ['AuditPool', 'ResourcePool', 'ResourceLinkPool'];

    /**
     * BaseJDF constructor.
     */
    public function __construct()
    {
        $this->sender_id = config('jdf.sender_id', config('app.name'));
    }

    /**
     * function __call
     * If you try to call a method whose name is equal to a supported JMF message type, return the element requested.
     *
     * @param $method
     * @param $arguments
     *
     * @return SimpleXMLElement|SimpleXMLElement[]
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $arguments): SimpleXMLElement
    {
        $node_type = Str::Studly($method);

        if (! \in_array($node_type, $this->root_nodes, true)) {
            throw new BadMethodCallException('Unknown node type \'' . $node_type . '\'');
        }

        // return the node if it exists, or create a new one (so only ever one allowed)
        return $this->root->{$node_type} ?? $this->root->addChild($node_type);
    }

    /**
     * Get the JMF or JDF message as a SimpleXMLElement.
     */
    public function getMessage(): SimpleXMLElement
    {
        return $this->root;
    }

    /**
     * Get the raw jdf or jmf message as xml.
     */
    public function getRawMessage(): string
    {
        return $this->root->asXML();
    }

    /**
     * Format the path to a print file so it will work as a reference in a JDF file or JMF message.
     */
    public function formatPrintFilePath(string $file_name): string
    {
        $remote_path = $file_name;

        if (! Str::startsWith($remote_path, ['http://', 'https://', '\\\\', '//', 'cid://'])) {
            // this must be a local file, make it relative to the JMF server

            // strip off the leading file protocol string if present
            $remote_path = Str::after($remote_path, 'file://');

            // prepend the JMF server's base file path
            $remote_path = $this->server_file_path . $remote_path;

            // prepend the file protocol back on, but not if we already prepended a protocol string in the server path config option
            if (! Str::startsWith($this->server_file_path, ['//', '\\\\', 'http:', 'https:', 'file:'])) {
                $remote_path = 'file://' . $remote_path;
            }
        }

        return $remote_path;
    }

    /**
     * Build the standard JMF or JDF message object to get us started.
     */
    protected function initialiseMessage(): void
    {
        // These are used to generate the initial XML field attributes
        $xml_encoding = '<?xml version="1.0" encoding="UTF-8"?>';
        $xmlns_xsi = 'http://www.w3.org/2001/XMLSchema-instance';

        // Initialize the JMF or JDF root node
        $root = new SimpleXMLElement($xml_encoding . '<JDF/>', \LIBXML_NOEMPTYTAG);
        $root->addAttribute('Activation', 'Active');
        $root->addAttribute('DescriptiveName', $this->name);
        $root->addAttribute('ID', 'ID1');
        $root->addAttribute('JobID', 'J_000000');
        $root->addAttribute('JobPartID', 'n_000015');
        $root->addAttribute('NamedFeatures', 'FieryVirtualPrinter GL');
        $root->addAttribute('Status', 'Ready');
        $root->addAttribute('Type', 'Combined');
        $root->addAttribute('Types', 'LayoutPreparation DigitalPrinting');
        $root->addAttribute('Version', '1.3');
        $root->addAttribute('xmlns', 'http://www.CIP4.org/JDFSchema_1_1');
        $root->addAttribute('xmlns:EFI', 'http://www.efi.com/efijdf');
        $root->addAttribute('xmlns:jdftyp', 'http://www.CIP4.org/JDFSchema_1_1_Types');
        $root->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        // Register the namespace.
        $root->registerXPathNamespace('xsi', $xmlns_xsi);
        $this->root = $root;

        $this->setAuditMessage();
    }

    protected function setAuditMessage()
    {
        $created = $this->auditPool()->addChild('Created');
        $created->addAttribute('AgentName', $this->sender_id);
        $created->addAttribute('AgentVersion', '1');
        $created->addAttribute('TimeStamp', now()->toIso8601String());
    }
}
