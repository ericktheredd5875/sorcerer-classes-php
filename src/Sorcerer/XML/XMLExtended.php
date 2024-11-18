<?php
/**
 * XMLExtended
 * 
 *
 * @package     Sorcerer\XML\XMLExtended
 * @author      Eric Harris <ericktheredd5875@gmail.com>
 * @copyright   2024 - Eric Harris
 * @version     x.x.x - Comments or Notes
 * @deprecated  x.x.x - Comments or Notes
 */
/*----------  Class Namespacing  ----------*/
namespace Sorcerer\XML;

/*==================================================
=            Classes Used by this Class            =
==================================================*/

/*=====  End of Classes Used by this Class  ======*/

// Allows for grabbing LibXML Errors
libxml_use_internal_errors(true);

class XMLExtended extends \SimpleXMLElement
{
	/*========================================
    =            Class Attributes            =
    ========================================*/
	
    /*=====  End of Class Attributes  ======*/

	public static function getXmlErrors(): array
	{
		$xml_error_collection = array();
		$xml_errors           = libxml_get_errors();
		foreach($xml_errors as $xml_error)
		{
			$xml_error_collection[] = self::convertXmlError($xml_error);
		}

		libxml_clear_errors();

		return $xml_error_collection;
	}

	private static function convertXmlError($error): array
	{
		$return = array();
		switch($error->level)
		{
			case LIBXML_ERR_WARNING:
				$return["code"] = "[---Warning---] {$error->code}";
				break;
			case LIBXML_ERR_ERROR:
				$return["code"] = "[----Error----] {$error->code}";
				break;
			case LIBXML_ERR_FATAL:
				$return["code"] = "[-Fatal Error-] {$error->code}";
				break;
		}

		$return["msg"]    = trim($error->message);
		$return["file"]   = trim($error->file);
		$return["column"] = trim($error->column);
		$return["line"]   = trim($error->line);
		$return["method"] = __METHOD__;

		return $return;
	}

	public function addNullAttribute(): self
	{
		$this->addAttribute("xsi:nil", "true", "http://www.w3.org/2001/XMLSchema-instance");
		return $this;
	}

	public function addElementAttribute($name, $value, $namespace = NULL): self
	{
		$this->addAttribute($name, $value, $namespace);
		return $this;
	}

	public function addNodeElement($name, $value): self
	{
		$this->$name = $value;
		return $this;
	}

	public function addCData($cdata_text): self
	{
		$node = dom_import_simplexml($this);
		$no   = $node->ownerDocument;
		$node->appendChild($no->createCDATASection($cdata_text));

		return $this;
	}

}
/* End of file XMLExtended.php */
/* Location: /Sorcerer/XML/XMLExtended.php */