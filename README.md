A package to transform PHP DocBlock class property annotations to TypeScript interfaces.

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
}
export type Brand = "apple" | "samsung" | "google";
export type ProductCategory = "phone" | "tablet" | "laptop" | "widget";
```