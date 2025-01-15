A package to transform PHP DocBlock class property annotations to TypeScript interfaces.

Provides a transformer for the [Spatie TypeScript Transformer](https://github.com/spatie/typescript-transformer/) package.

## Usage

```php
use Kestrel\DocblockTypescriptTransformer\Transformers\ClassDocBlockTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

$transformerConfig = TypeScriptTransformerConfig::create()
    ->transformers( [
        ClassDocBlockTransformer::create(),
        // ... other transformers
    ] )

$transformer = TypeScriptTransformer::create( $transformerConfig );
```

In a PHP class, use `@property` and the `@typescript` annotations to ensure TypeScript Transformer will generate the correct TypeScript interface.
```php
/**
 * Product
 *
 * @property-read int $id product ID
 * @property string $name product name
 * @property string $description description
 * @property Brand $brand
 * @property ProductCategory[] $categories
 * @property array<string, mixed> $metadata {@ts-optional}
 * @property array<string, mixed> $internal_metadata {@ts-hidden}
 * @property array<string, Brand> $brand_map {@ts-record string, Brand}
 * @property float $price {@ts-literal number}
 *
 * @typescript
 */
class Product extends Model {};

#[TypeScript]
enum Brand : string {
	case APPLE = 'apple';
	case SAMSUNG = 'samsung';
	case GOOGLE = 'google';
};

#[TypeScript]
enum ProductCategory: string {
	case PHONE = 'phone';
	case TABLET = 'tablet';
	case LAPTOP = 'laptop';
	case WIDGET = 'widget';
};
```

Will result in this:
```ts
export type Release = {
	id: number;
	name: string;
	description: string;
	brand: Brand;
	categories: ProductCategory[];
	metadata?: { [key: string]: any };
	brand_map: Record<string, Brand>
	price: number
}
export type Brand = "apple" | "samsung" | "google";
export type ProductCategory = "phone" | "tablet" | "laptop" | "widget";
```

## Special tags

### `@ts-optional`

Marks the property as optional in TypeScript.

### `@ts-hidden`

Hides the property from the TypeScript interface.

### `@ts-record`

Generates a `Record` type in TypeScript. The first argument is the key type, the second argument is the value type.

### `@ts-literal`

Generates a literal type in TypeScript. The argument is the literal type, exactly as it should appear in TypeScript.