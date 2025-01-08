<?php

namespace Kestrel\DocblockTypescriptTransformer\Transformers;

use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyWrite;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\TypeResolver;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Nullable;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Transformers\DtoTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

/**
 * A Class/DTO transformer that uses class-level PHPDoc `@property` annotations to generate TypeScript interfaces.
 */
class ClassDocBlockTransformer extends DtoTransformer
{

	/** @var TypeResolver */
	protected TypeResolver $typeResolver;

	/**
	 * Constructor.
	 *
	 * @param TypeScriptTransformerConfig $config
	 */
	public function __construct(TypeScriptTransformerConfig $config)
	{
		parent::__construct($config);

		$this->typeResolver = new TypeResolver();
	}

	/**
	 * Transform the properties of the class into TypeScript.
	 *
	 * @param ReflectionClass $class
	 * @param MissingSymbolsCollection $missingSymbols
	 * @return string
	 */
	protected function transformProperties(ReflectionClass $class, MissingSymbolsCollection $missingSymbols): string
	{

		$nullablesAreOptional = $this->config->shouldConsiderNullAsOptional();

		return array_reduce(
			$this->resolveProperties($class),
			function (string $carry, $property) use ($missingSymbols, $nullablesAreOptional, $class) {

				/** @var Property|PropertyRead|PropertyWrite $property */
				if ($this->propertyIsHidden($property)) {
					return $carry;
				}

				$type          = $this->resolvePropertyType($property);
				$transformed   = $this->typeToTypeScript($type, $missingSymbols, $nullablesAreOptional, $class->getName());
				$is_optional   = $this->propertyIsOptional($property) || ($type instanceof Nullable && $nullablesAreOptional);
				$property_name = $property->getVariableName();

				return $is_optional
					? "{$carry}{$property_name}?: {$transformed};" . PHP_EOL
					: "{$carry}{$property_name}: {$transformed};" . PHP_EOL;
			},
			''
		);
	}

	/**
	 * Resolve the `@property` annotations from the class-level PHPDoc.
	 *
	 * @param ReflectionClass $class
	 * @return array
	 */
	protected function resolveProperties(ReflectionClass $class): array
	{

		$context  = (new ContextFactory())->createFromReflector($class);
		$factory  = DocBlockFactory::createInstance();
		$docblock = $factory->create($class->getDocComment(), $context);

		$properties = [];

		foreach ($docblock->getTags() as $tag) {

			// only include `@property`, `@property-read`, and `@property-write` tags
			if (strpos($tag->getName(), 'property') === false) {
				continue;
			}

			$properties[] = $tag;
		}

		return $properties;
	}

	/**
	 * Check if the given property should be hidden from TypeScript.
	 *
	 * @param Property|PropertyRead|PropertyWrite $property
	 * @return bool
	 */
	protected function propertyIsHidden($property): bool
	{

		$description = $property->getDescription();

		foreach ($description->getTags() as $tag) {
			if ($tag->getName() === 'ts-hidden') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the given property should be optional in TypeScript.
	 *
	 * @param Property|PropertyRead|PropertyWrite $property
	 * @return bool
	 */
	protected function propertyIsOptional($property): bool
	{

		$description = $property->getDescription();

		foreach ($description->getTags() as $tag) {
			if ($tag->getName() === 'ts-optional') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the type of the given property.
	 *
	 * @param Property|PropertyRead|PropertyWrite $property
	 * @return Type
	 */
	protected function resolvePropertyType($property): Type
	{
		return $this->typeResolver->resolve($property->getType());
	}

}
