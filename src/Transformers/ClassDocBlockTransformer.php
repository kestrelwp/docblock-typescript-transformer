<?php

namespace Kestrel\DocblockTypescriptTransformer\Transformers;

use Kestrel\DocblockTypescriptTransformer\DocBlockTags\TypeScriptRecordTag;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyWrite;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\TypeResolver;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Nullable;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Transformers\DtoTransformer;
use Spatie\TypeScriptTransformer\TypeProcessors\TypeProcessor;
use Spatie\TypeScriptTransformer\Types\TypeScriptType;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use Spatie\TypeScriptTransformer\Types\RecordType;

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
	 * @throws ReflectionException
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

				$type = $this->propertyToType(
					$property,
					$missingSymbols,
					...$this->typeProcessors()
				);

				$transformed = $this->typeToTypeScript(
					$type,
					$missingSymbols,
					$nullablesAreOptional,
					$class->getName()
				);

				$isOptional   = $this->propertyIsOptional($property) || ($type instanceof Nullable && $nullablesAreOptional);
				$propertyName = $property->getVariableName();

				return $isOptional
					? "{$carry}{$propertyName}?: {$transformed};" . PHP_EOL
					: "{$carry}{$propertyName}: {$transformed};" . PHP_EOL;
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
		$factory  = DocBlockFactory::createInstance( [ 'ts-record' => TypeScriptRecordTag::class ] );
		$docblock = $factory->create($class->getDocComment(), $context);

		$properties = [];

		foreach ($docblock->getTags() as $tag) {

			// only include `@property`, `@property-read`, and `@property-write` tags
			if (!str_contains($tag->getName(), 'property')) {
				continue;
			}

			$properties[] = $tag;
		}

		return $properties;
	}

	/**
	 * Convert the given property to a Type.
	 *
	 * @param Property|PropertyRead|PropertyWrite $property
	 * @param MissingSymbolsCollection $missingSymbolsCollection
	 * @param TypeProcessor ...$typeProcessors
	 * @return Type|null
	 * @throws ReflectionException
	 */
	protected function propertyToType(
		Property | PropertyRead | PropertyWrite $property,
		MissingSymbolsCollection $missingSymbolsCollection,
		TypeProcessor ...$typeProcessors
	): ?Type {

		$type        = null;
		$description = $property->getDescription();

		foreach ( $description->getTags() as $tag ) {
			if ( $tag instanceof TypeScriptRecordTag ) {
				$type = new RecordType( $tag->getKeyType(), $tag->getValueType() );
			}

			if ( $tag->getName() === 'ts-literal' ) {
				$type = new TypeScriptType( $tag->getDescription()->render() );
			}
		}

		if (!$type) {
			$type = $this->typeResolver->resolve($property->getType());
		}

		// create a dummy reflection object for the processor - it's unused, but required
		$reflection = new ReflectionParameter('function_exists', 0);

		// using these processors here to ensure default class replacements from config are respected
		foreach ($typeProcessors as $processor) {
			$type = $processor->process(
				$type,
				$reflection,
				$missingSymbolsCollection
			);

			if ($type === null) {
				return null;
			}
		}

		return $type;
	}

	/**
	 * Check if the given property should be hidden from TypeScript.
	 *
	 * @param Property|PropertyRead|PropertyWrite $property
	 * @return bool
	 */
	protected function propertyIsHidden(Property | PropertyRead | PropertyWrite $property): bool
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
	protected function propertyIsOptional(Property | PropertyRead | PropertyWrite $property): bool
	{

		$description = $property->getDescription();

		foreach ($description->getTags() as $tag) {
			if ($tag->getName() === 'ts-optional') {
				return true;
			}
		}

		return false;
	}

}
