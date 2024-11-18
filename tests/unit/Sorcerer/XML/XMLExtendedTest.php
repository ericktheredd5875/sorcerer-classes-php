<?php

use PHPUnit\Framework\TestCase;

use Sorcerer\XML\XMLExtended;

class XMLExtendedTest extends TestCase
{
    /*========================================
    =            Class Attributes            =
    ========================================*/

    private $files_dir = "";    
    private $xml_header = "<?xml version='1.0' encoding='UTF-8' ?>"
                        . "<spells></spells>";
    
    
    /*=====  End of Class Attributes  ======*/

    private function getXmlFileDir()
    {
        $this->files_dir = dirname(__FILE__, 4) . "/_files/Sorcerer/XML/";
        return $this;
    }
    
    public function testListingsDocXMLIsXml()
    {
        $this->getXmlFileDir();
        $xml_doc = $this->files_dir . "sorc_spells_doc.xml";
        $sorc_spells_xml = new XMLExtended($this->xml_header);

        $this->assertXmlStringEqualsXmlFile(
            $xml_doc,
            $sorc_spells_xml->asXML()
        );
    }

    public function testNodeAttributeIsAdded()
    {
        $this->getXmlFileDir();
        $xml_doc = $this->files_dir . "sorc_spells_attribute.xml";

        $sorc_spells_xml = new XMLExtended($this->xml_header);
        $sorc_spell_xml = $sorc_spells_xml->addChild("spell");
        $sorc_spell_xml->addNodeElement("name",    "Fire Bolt");
        $sorc_spell_xml->addChild("range", 120)
            ->addElementAttribute("measurement", "feet");

        $this->assertXmlStringEqualsXmlFile(
            $xml_doc,
            $sorc_spells_xml->asXML()
        );
    }

    public function testAddingNodeElement()
    {
        $this->getXmlFileDir();
        $xml_doc = $this->files_dir . "sorc_spells_node.xml";

        $sorc_spells_xml = new XMLExtended($this->xml_header);
        $sorc_spell_xml = $sorc_spells_xml->addChild("spell");
        $sorc_spell_xml->addNodeElement("name",    "Fire Bolt")
                    ->addNodeElement("range",   "120 Feet");

        $this->assertXmlStringEqualsXmlFile(
            $xml_doc,
            $sorc_spells_xml->asXML()
        );
    }

    public function testAddingCdataNode()
    {
        $this->getXmlFileDir();
        
        $xml_doc = $this->files_dir . "sorc_spells_cdata.xml";

        $cdata_block = "You hurl a mote of fire at a creature or object within range. " 
                . "Make a ranged spell attack against the target. On a hit, the target " 
                . "takes 1d10 fire damage. A flammable object hit by this spell " 
                . "ignites if it isn't being worn or carried.";
        

        $sorc_spells_xml = new XMLExtended($this->xml_header);
        $sorc_spell_xml = $sorc_spells_xml->addChild("spell");
        $sorc_spell_xml->addNodeElement("name",    "Fire Bolt")
                    ->addNodeElement("range",   "120 Feet");

        $sorc_spell_xml->addChild("description")->addCData($cdata_block);

        $this->assertXmlStringEqualsXmlFile(
            $xml_doc,
            $sorc_spells_xml->asXML()
        );
    }
}