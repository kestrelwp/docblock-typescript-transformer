<?php

namespace Kestrel\DocblockTypescriptTransformer\DocBlockTags;

use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\DocBlock\DescriptionFactory;
use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\Fqsen;
use phpDocumentor\Reflection\FqsenResolver;
use phpDocumentor\Reflection\Types\Context as TypeContext;
use phpDocumentor\Reflection\Utils;
use Webmozart\Assert\Assert;

class TypeScriptRecordTag extends BaseTag implements Tag
{

	/** @var string the tag name */
	protected string $name = 'ts-record';

	/** @var string the record key type */
	private string $keyType;

	/** @var string the record value type */
	private string $valueType;

	/**
	 * Constructor.
	 *
	 * @param string $keyType
	 * @param string $valueType
	 */
	public function __construct(string $keyType, string $valueType)
	{
		$this->keyType     = $keyType;
		$this->valueType   = $valueType;
	}

	/**
	 * @inheritDoc
	 */
	public static function create(
		string              $body,
		?FqsenResolver      $fqsenResolver = null,
		?DescriptionFactory $descriptionFactory = null,
		?TypeContext        $context = null
	): self
	{
		Assert::notNull($descriptionFactory);
		Assert::notNull($fqsenResolver);

		$body  = trim($body, " \n\r\t\v\0()");
		$parts = Utils::pregSplit('/\s+/Su', $body, 2);
		$parts = array_map(function($part) {
			return trim($part, " \n\r\t\v\0,"); // remove any whitespace or commas around each part
		}, $parts);

		Assert::count($parts, 2, 'The @ts-record tag must have exactly two parts: keyType and valueType.');

		$keyType   = self::resolveType($parts[0], $fqsenResolver, $context);
		$valueType = self::resolveType($parts[1], $fqsenResolver, $context);

		return new static($keyType, $valueType);
	}

	/**
	 * Resolve the type of the record key or value.
	 *
	 * @param string $type
	 * @param FqsenResolver|null $fqsenResolver
	 * @param TypeContext|null $context
	 * @return string
	 */
	private static function resolveType(string $type, ?FqsenResolver $fqsenResolver, ?TypeContext $context): string
	{
		// Check if the type is a basic type or a class reference
		if (in_array($type, ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'void'])) {
			return $type;
		}

		// Assume it's a class reference and resolve it
		return (string) $fqsenResolver->resolve($type, $context);
	}

	/**
	 * Get the key type.
	 *
	 * @return string
	 */
	public function getKeyType(): string
	{
		return $this->keyType;
	}

	/**
	 * Get the value type.
	 *
	 * @return string
	 */
	public function getValueType(): string
	{
		return $this->valueType;
	}

	/**
	 * Get the string representation of the tag.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return sprintf('%s, %s', $this->keyType, $this->valueType);
	}
}
